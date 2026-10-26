<?php
declare(strict_types=1);

namespace app\ai\service;

use app\ai\model\AiTask;
use app\ai\model\AiTaskLog;
use app\common\constants\BizCode;
use app\common\enums\TaskStatus;
use app\common\enums\RoomState;
use app\common\exception\BusinessException;
use app\common\util\StrHelper;
use app\common\web\ResultCode;
use app\room\model\RoomPlayTask;
use app\room\model\RoomSwitchTask;
use app\room\service\ChannelWorkerGateway;
use app\room\service\RoomStateMachine;
use app\room\service\RoomSwitchScheduler;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

final class AiTaskService
{
    private const STREAM_KEY = 'stream:ai:tasks';
    private const GROUP_NAME = 'ai-workers';

    /** 离线换脸任务类型（用户上传人脸图 + 后台配置的固定模板视频） */
    public const TASK_TYPE_FACE_SWAP = 'face_swap';

    private RoomStateMachine $stateMachine;
    private RoomSwitchScheduler $switchScheduler;
    private ChannelWorkerGateway $gateway;

    public function __construct()
    {
        $this->stateMachine = new RoomStateMachine();
        $this->switchScheduler = new RoomSwitchScheduler($this->stateMachine);
        $this->gateway = new ChannelWorkerGateway();
    }

    public function createFromChat(
        int $roomId,
        int $userId,
        string $nickname,
        string $content,
        int $personaId = 0
    ): array {
        $taskNo = StrHelper::taskNo('AI');
        $deadlineMin = (int) config('ai.task_deadline_min', 5);
        $deadlineAt = date('Y-m-d H:i:s', time() + $deadlineMin * 60);

        $task = AiTask::create([
            'task_no'       => $taskNo,
            'room_id'       => $roomId,
            'task_type'     => 'interaction_async',
            'priority'      => 0,
            'source_type'   => 'chat',
            'source_ref_id' => $userId,
            'persona_id'    => $personaId > 0 ? $personaId : null,
            'content'       => mb_substr($content, 0, 1000),
            'callback_mode' => 'file',
            'status'        => TaskStatus::PENDING->value,
            'deadline_at'   => $deadlineAt,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        $this->writeLog((int) $task->id, 'created', [
            'source_type' => 'chat',
            'user_id'     => $userId,
            'nickname'    => $nickname,
            'content'     => $content,
        ]);

        Log::info("AiTaskService: task {$taskNo} created, room={$roomId}, content=" . mb_substr($content, 0, 50));

        $now = date('Y-m-d H:i:s');
        return [
            'task_id'  => (int) $task->id,
            'task_no'  => $taskNo,
            'room_id'  => $roomId,
            'status'   => TaskStatus::PENDING->value,
            'content'  => $content,
            'created_at' => $now,
        ];
    }

    /**
     * 管理后台/定时任务专用：直接从 MySQL 查待处理任务（不涉及 Redis Stream）
     *
     * 实时 AI 客户端请使用 pullFromStream()
     */
    public function pullTasks(string $workerId): array
    {
        $tasks = AiTask::where('status', TaskStatus::PENDING->value)
            ->where(function ($query) {
                $query->whereNull('deadline_at')
                    ->whereOr('deadline_at', '>', date('Y-m-d H:i:s'));
            })
            ->order('priority', 'desc')
            ->order('id', 'asc')
            ->limit(10)
            ->select()
            ->toArray();

        return array_map(function ($task) {
            return [
                'task_id'    => (int) $task['id'],
                'task_no'    => $task['task_no'],
                'room_id'    => (int) $task['room_id'],
                'task_type'  => $task['task_type'],
                'content'    => $task['content'],
                'persona_id' => $task['persona_id'] ? (int) $task['persona_id'] : null,
                'deadline_at' => $task['deadline_at'],
                'created_at' => $task['created_at'],
            ];
        }, $tasks);
    }

    public function pullFromStream(string $workerId, int $count = 10): array
    {
        $redis = Cache::store('redis')->handler();

        try {
            $redis->xGroup('CREATE', self::STREAM_KEY, self::GROUP_NAME, '0', true);
        } catch (\Throwable) {
        }

        $maxWaitMs = (int) config('ai.stream_pull_timeout_ms', 3000);
        $messages = $redis->xReadGroup(
            self::GROUP_NAME,
            $workerId,
            [self::STREAM_KEY => '>'],
            $count,
            max(0, $maxWaitMs)
        );

        if (empty($messages) || !isset($messages[self::STREAM_KEY])) {
            return [];
        }

        $taskIds = [];
        $result = [];

        foreach ($messages[self::STREAM_KEY] as $streamId => $fields) {
            $roomId = (int) ($fields['room_id'] ?? 0);
            $userId = (int) ($fields['user_id'] ?? 0);
            $nickname = (string) ($fields['nickname'] ?? '');
            $content = (string) ($fields['content'] ?? '');
            $personaId = (int) ($fields['persona_id'] ?? 0);
            $createdTs = (int) ($fields['created_ts'] ?? 0);

            $created = $this->createFromChat($roomId, $userId, $nickname, $content, $personaId);
            $taskIds[] = $streamId;
            $result[] = $created;
        }

        if (!empty($taskIds)) {
            $redis->xAck(self::STREAM_KEY, self::GROUP_NAME, $taskIds);
        }

        Log::info("AiTaskService: pulled " . count($result) . " tasks from stream, consumer={$workerId}");

        return $result;
    }

    /**
     * 创建离线换脸任务（AI 女友端用户上传图片 + 后台配置的固定模板视频）
     * 复用 lp_ai_task 队列：task_type=face_swap，room_id=0（无房间），callback_mode=file
     */
    public function createFaceSwapTask(
        int $userId,
        int $templateId,
        string $imageUrl,
        string $templateVideoUrl,
        string $templateTitle = ''
    ): array {
        $taskNo = StrHelper::taskNo('FS');
        $deadlineMin = (int) config('ai.face_swap.deadline_min', 60);
        $now = date('Y-m-d H:i:s');

        $task = AiTask::create([
            'task_no'            => $taskNo,
            'room_id'            => 0,
            'task_type'          => self::TASK_TYPE_FACE_SWAP,
            'priority'           => 0,
            'source_type'        => 'user',
            'source_ref_id'      => $userId,
            'user_id'            => $userId,
            'persona_id'         => null,
            'content'            => mb_substr($templateTitle, 0, 1000),
            'face_image_url'     => $imageUrl,
            'template_video_url' => $templateVideoUrl,
            'callback_mode'      => 'file',
            'status'             => TaskStatus::PENDING->value,
            'deadline_at'        => date('Y-m-d H:i:s', time() + $deadlineMin * 60),
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        $this->writeLog((int) $task->id, 'created', [
            'source_type' => self::TASK_TYPE_FACE_SWAP,
            'user_id'     => $userId,
            'template_id' => $templateId,
            'image_url'   => $imageUrl,
            'video_url'   => $templateVideoUrl,
        ]);

        Log::info("AiTaskService: face_swap task {$taskNo} created, user={$userId}, template={$templateId}");

        return [
            'task_id'    => (int) $task->id,
            'task_no'    => $taskNo,
            'status'     => TaskStatus::PENDING->value,
            'created_at' => $now,
        ];
    }

    /**
     * AI 电脑拉取待处理的换脸任务（离线任务不走 Redis Stream，直接查 MySQL）
     */
    public function pullFaceSwapTasks(string $workerId, int $count = 5): array
    {
        $count = min(10, max(1, $count));

        $tasks = AiTask::where('task_type', self::TASK_TYPE_FACE_SWAP)
            ->where('status', TaskStatus::PENDING->value)
            ->where(function ($query) {
                $query->whereNull('deadline_at')
                    ->whereOr('deadline_at', '>', date('Y-m-d H:i:s'));
            })
            ->order('priority', 'desc')
            ->order('id', 'asc')
            ->limit($count)
            ->select()
            ->toArray();

        Log::info("AiTaskService: pulled " . count($tasks) . " face_swap tasks, consumer={$workerId}");

        return array_map(function ($task) {
            return [
                'task_id'            => (int) $task['id'],
                'task_no'            => $task['task_no'],
                'task_type'          => $task['task_type'],
                'user_id'            => (int) $task['user_id'],
                'face_image_url'     => $task['face_image_url'],
                'template_video_url' => $task['template_video_url'],
                'title'              => $task['content'],
                'deadline_at'        => $task['deadline_at'],
                'created_at'         => $task['created_at'],
            ];
        }, $tasks);
    }

    public function acceptTask(int $taskId, string $workerId): array
    {
        $task = AiTask::find($taskId);
        if (!$task) {
            throw new BusinessException(ResultCode::TASK_NOT_FOUND, "任务 {$taskId} 不存在");
        }

        if ($task->status !== TaskStatus::PENDING->value) {
            throw new BusinessException(ResultCode::TASK_ALREADY_DONE, "任务状态为 {$task->status}，不可接单");
        }

        if ($task->deadline_at && strtotime($task->deadline_at) <= time()) {
            $task->status = TaskStatus::EXPIRED->value;
            $task->save();
            throw new BusinessException(ResultCode::TASK_EXPIRED, '任务已过期');
        }

        $now = date('Y-m-d H:i:s');
        $task->status = TaskStatus::ACCEPTED->value;
        $task->worker_id = $workerId;
        $task->accepted_at = $now;
        $task->updated_at = $now;
        $task->save();

        $this->writeLog($taskId, 'accepted', ['worker_id' => $workerId]);

        Log::info("AiTaskService: task {$task->task_no} accepted by {$workerId}");

        return [
            'task_id'    => (int) $task->id,
            'task_no'    => $task->task_no,
            'room_id'    => (int) $task->room_id,
            'status'     => TaskStatus::ACCEPTED->value,
            'accepted_at' => $now,
        ];
    }

    public function reportProgress(int $taskId, int $percent, string $message = ''): array
    {
        $task = AiTask::find($taskId);
        if (!$task) {
            throw new BusinessException(ResultCode::TASK_NOT_FOUND);
        }

        if (!in_array($task->status, [TaskStatus::ACCEPTED->value, TaskStatus::PROCESSING->value], true)) {
            throw new BusinessException(ResultCode::TASK_ALREADY_DONE);
        }

        if ($task->status === TaskStatus::ACCEPTED->value) {
            $task->status = TaskStatus::PROCESSING->value;
        }

        $task->updated_at = date('Y-m-d H:i:s');
        $task->save();

        $this->writeLog($taskId, 'progress', [
            'percent' => $percent,
            'message' => $message,
        ]);

        return [
            'task_id'  => $taskId,
            'task_no'  => $task->task_no,
            'status'   => $task->status,
            'percent'  => $percent,
        ];
    }

    public function completeTask(int $taskId, string $videoUrl, int $durationSec, string $coverUrl = ''): array
    {
        $task = AiTask::find($taskId);
        if (!$task) {
            throw new BusinessException(ResultCode::TASK_NOT_FOUND);
        }

        if (!in_array($task->status, [TaskStatus::ACCEPTED->value, TaskStatus::PROCESSING->value], true)) {
            throw new BusinessException(ResultCode::TASK_ALREADY_DONE);
        }

        $now = date('Y-m-d H:i:s');
        $task->status = TaskStatus::COMPLETED->value;
        $task->result_type = $task->task_type === 'custom_image' ? 'image_file' : 'video_file';
        $task->video_url = $videoUrl;
        $task->cover_url = $coverUrl;
        $task->duration_sec = $durationSec;
        $task->finished_at = $now;
        $task->updated_at = $now;
        $task->save();

        $this->writeLog($taskId, 'completed', [
            'video_url'    => $videoUrl,
            'duration_sec' => $durationSec,
            'cover_url'    => $coverUrl,
        ]);

        Log::info("AiTaskService: task {$task->task_no} completed, duration={$durationSec}s");

        // VIP 定制内容（视频/图片）：与直播间无关（room_id=0），跳过播单/切换调度/房间推送
        if (in_array($task->task_type, ['custom_video', 'custom_image'], true)) {
            return [
                'task_id'     => $taskId,
                'task_no'     => $task->task_no,
                'status'      => TaskStatus::COMPLETED->value,
                'finished_at' => $now,
            ];
        }

        // 离线换脸任务：只落结果文件，不进互动播单、不切房间
        if ($task->task_type === self::TASK_TYPE_FACE_SWAP) {
            return [
                'task_id'     => $taskId,
                'task_no'     => $task->task_no,
                'status'      => TaskStatus::COMPLETED->value,
                'result_type' => 'video_file',
                'video_url'   => $videoUrl,
                'finished_at' => $now,
            ];
        }

        $roomId = (int) $task->room_id;

        $playTaskId = $this->createInteractionPlayTask($roomId, $taskId, $task->task_no, $durationSec, $videoUrl, $coverUrl);

        $switchTask = null;
        try {
            $switchTask = $this->switchScheduler->scheduleInteractionSwitch($roomId, $playTaskId);
            $this->gateway->pushSwitchToInteraction($roomId, (int) $switchTask['id'], $playTaskId);
            $this->gateway->pushBroadcast($roomId, 'interaction_ready', [
                'task_no'      => $task->task_no,
                'duration_sec' => $durationSec,
                'cover_url'    => $coverUrl,
            ]);
        } catch (\Throwable $e) {
            Log::warning("AiTaskService: interaction switch failed for task {$task->task_no}: {$e->getMessage()}");
        }

        return [
            'task_id'       => $taskId,
            'task_no'       => $task->task_no,
            'status'        => TaskStatus::COMPLETED->value,
            'play_task_id'  => $playTaskId,
            'switch_task'   => $switchTask ? ['id' => $switchTask['id'], 'task_no' => $switchTask['task_no']] : null,
            'finished_at'   => $now,
        ];
    }

    public function failTask(int $taskId, string $errorMsg = ''): array
    {
        $task = AiTask::find($taskId);
        if (!$task) {
            throw new BusinessException(ResultCode::TASK_NOT_FOUND);
        }

        if (!in_array($task->status, [TaskStatus::ACCEPTED->value, TaskStatus::PROCESSING->value], true)) {
            throw new BusinessException(ResultCode::TASK_ALREADY_DONE);
        }

        $now = date('Y-m-d H:i:s');
        $task->status = TaskStatus::FAILED->value;
        $task->failed_at = $now;
        $task->updated_at = $now;
        $task->save();

        // 定制生成失败要把额度/代币退给用户：worker 报失败时前面已经扣过费，
        // 不退就等于「生成失败还收钱」。其它任务类型（互动/换脸）不收用户额度，跳过。
        if (in_array($task->task_type, ['custom_video', 'custom_image'], true)) {
            $content = json_decode((string) $task->content, true) ?: [];
            $this->refundCustomTask(
                (int) $task->source_ref_id,
                (float) ($content['paid_tokens'] ?? 0),
                (int) ($content['retry'] ?? 0)
            );
        }

        $this->writeLog($taskId, 'failed', ['error' => $errorMsg]);

        Log::warning("AiTaskService: task {$task->task_no} failed: {$errorMsg}");

        return [
            'task_id'   => $taskId,
            'task_no'   => $task->task_no,
            'status'    => TaskStatus::FAILED->value,
            'failed_at' => $now,
        ];
    }

    public function getStreamToken(int $roomId): array
    {
        $pushUrl = config('ai.stream.rtmp_push_url', 'rtmp://127.0.0.1:1935/live/');
        $streamAlias = 'room/' . $roomId . '/interaction';
        $expireAt = time() + 3600;
        $token = hash_hmac('sha256', $streamAlias . '|' . $expireAt, config('jwt.secret'));
        $maxStreamSec = (int) config('ai.stream.max_stream_sec', 120);

        return [
            'room_id'        => $roomId,
            'stream_alias'   => $streamAlias,
            'push_url'       => $pushUrl . $streamAlias,
            'play_hls'       => '/hls/' . $streamAlias . '.m3u8',
            'token'          => $token,
            'expire_at'      => $expireAt,
            'max_stream_sec' => $maxStreamSec,
        ];
    }

    public function streamEnd(int $taskId, int $durationSec, string $reason = 'ai_notify'): array
    {
        $task = AiTask::find($taskId);
        if (!$task) {
            throw new BusinessException(ResultCode::TASK_NOT_FOUND);
        }

        if (!in_array($task->status, [TaskStatus::ACCEPTED->value, TaskStatus::PROCESSING->value, TaskStatus::COMPLETED->value], true)) {
            throw new BusinessException(ResultCode::TASK_ALREADY_DONE);
        }

        $now = date('Y-m-d H:i:s');
        $roomId = (int) $task->room_id;

        if ($task->status !== TaskStatus::COMPLETED->value) {
            $task->status = TaskStatus::COMPLETED->value;
            $task->result_type = 'stream_live';
            $task->duration_sec = $durationSec;
            $task->finished_at = $now;
            $task->updated_at = $now;
            $task->save();
        }

        $this->writeLog($taskId, 'stream_ended', [
            'reason'       => $reason,
            'duration_sec' => $durationSec,
        ]);

        Log::info("AiTaskService: task {$task->task_no} stream ended, duration={$durationSec}s, reason={$reason}");

        $switchTaskId = null;
        $snap = $this->stateMachine->getSnapshot($roomId);
        if ($snap) {
            $switchTaskId = $snap['current_task_id'] ?? null;
        }

        $this->endInteractionInRoom($roomId, $switchTaskId);

        return [
            'task_id'       => $taskId,
            'task_no'       => $task->task_no,
            'room_id'       => $roomId,
            'status'        => TaskStatus::COMPLETED->value,
            'duration_sec'  => $durationSec,
            'finished_at'   => $now,
        ];
    }

    public function handleStreamEndByRoom(int $roomId, string $reason = 'srs_unpublish'): array
    {
        $snap = $this->stateMachine->getSnapshot($roomId);
        if (!$snap) {
            return ['room_id' => $roomId, 'ended' => false, 'reason' => 'no_snapshot'];
        }

        $currentState = $snap['current_state'];

        // 互动直播中的断流 → 走原有互动结束逻辑
        if ($currentState === RoomState::INTERACTION_LIVE->value) {
            $taskId = $snap['current_task_id'] ?? null;
            $this->endInteractionInRoom($roomId, $taskId);
            if ($taskId) {
                $switchTask = RoomSwitchTask::find($taskId);
                if ($switchTask) {
                    $switchTask->status = 'completed';
                    $switchTask->ended_at = date('Y-m-d H:i:s');
                    $switchTask->save();
                }
            }
            Log::info("AiTaskService: room {$roomId} interaction stream ended by {$reason}");
            return ['room_id' => $roomId, 'ended' => true, 'reason' => $reason, 'task_id' => $taskId];
        }

        // 普通直播中断流 → 房间置回 offline（有推流才算开播，断流自动关播）
        // 注意：public_live → offline 不在状态机迁移表里，SRS 断流是物理事实，直接 upsert
        if ($currentState === RoomState::PUBLIC_LIVE->value) {
            $this->forceOffline($roomId);
            Log::info("AiTaskService: room {$roomId} public_live stream ended by {$reason}, set offline");
            return ['room_id' => $roomId, 'ended' => true, 'reason' => $reason];
        }

        return ['room_id' => $roomId, 'ended' => false, 'reason' => 'not_live_state', 'state' => $currentState];
    }

    /**
     * SRS on_publish 回调处理：AI 电脑推流成功 → 自动把房间设为 public_live
     * 利用 RoomStateMachine::markPublicLive()（自动处理 offline→public_ready→public_live 两步迁移）
     * 不踩 interaction_live/privilege_live/switching（这些状态会抛异常，捕获后跳过）
     */
    public function handleStreamStartByRoom(int $roomId): array
    {
        try {
            // markPublicLive 会处理 offline→public_ready，但可能停在 public_ready
            $snap = $this->stateMachine->markPublicLive($roomId);
            // 如果停在 public_ready，继续推进到 public_live
            if (($snap['current_state'] ?? '') === RoomState::PUBLIC_READY->value) {
                $snap = $this->stateMachine->transition($roomId, RoomState::PUBLIC_READY, RoomState::PUBLIC_LIVE);
            }
            $finalState = $snap['current_state'] ?? RoomState::PUBLIC_LIVE->value;
            Log::info("AiTaskService: room {$roomId} stream started, state={$finalState}");
            return ['room_id' => $roomId, 'started' => true, 'reason' => 'mark_public_live', 'state' => $finalState];
        } catch (\Throwable $e) {
            // 互动/特权/切换中状态 → markPublicLive 会抛异常，正常跳过
            Log::info("AiTaskService: room {$roomId} stream started but markPublicLive skipped: {$e->getMessage()}");
            return ['room_id' => $roomId, 'started' => false, 'reason' => 'skipped', 'error' => $e->getMessage()];
        }
    }

    /**
     * 直接把房间状态置为 offline（绕过状态机迁移校验，用于 SRS 物理断流）
     */
    private function forceOffline(int $roomId): void
    {
        $conn = Db::connect('live_mysql');
        $conn->table('lp_room_state_snapshot')
            ->where('room_id', $roomId)
            ->update([
                'current_state' => RoomState::OFFLINE->value,
                'current_mode'  => BizCode::ROOM_MODE_PUBLIC,
                'version'       => Db::raw('version + 1'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
    }

    public function expireOverdueTasks(): array
    {
        $overdueTasks = AiTask::where('status', TaskStatus::PENDING->value)
            ->where('deadline_at', '<=', date('Y-m-d H:i:s'))
            ->select();

        $result = [];
        foreach ($overdueTasks as $task) {
            $task->status = TaskStatus::EXPIRED->value;
            $task->updated_at = date('Y-m-d H:i:s');
            $task->save();

            $this->writeLog((int) $task->id, 'expired', [
                'deadline_at' => $task->deadline_at,
            ]);

            $result[] = [
                'task_id' => (int) $task->id,
                'task_no' => $task->task_no,
                'room_id' => (int) $task->room_id,
            ];
        }

        if (count($result) > 0) {
            Log::info("AiTaskService: expired " . count($result) . " overdue tasks");
        }

        return $result;
    }

    /**
     * 回收「worker 接单后卡死」的定制生成任务
     *
     * 背景：custom_video / custom_image 走的是最长 90 分钟的排队 + 生成，
     * worker 若在 accept 之后挂掉（OOM、重启、跨境断线），任务会永久停在
     * accepted/processing，前端一直「生成中」，而额度/代币早已扣掉。
     *
     * 策略（幂等、保守）：
     *   1. 超时且 content.retry < 1  → 退回 pending 重试一次（重置 accepted_at）
     *   2. 超时且已重试过          → 置 failed，并按 content.paid_tokens 退款 / 退免费额度
     *   3. 已有 video_url（成品其实已落库）→ 直接判 completed，绝不重复扣费
     *
     * 只处理 task_type in (custom_video, custom_image)，不碰其它 AI 任务类型。
     *
     * @param int $ttlMin 接单后允许的最长处理时间（分钟）
     * @return array 处理明细
     */
    public function reapStuckCustomTasks(int $ttlMin = 90): array
    {
        $db = Db::connect('live_mysql');
        $cutoff = date('Y-m-d H:i:s', time() - max(10, $ttlMin) * 60);

        $rows = $db->table('lp_ai_task')
            ->whereIn('task_type', ['custom_video', 'custom_image'])
            ->whereIn('status', [TaskStatus::ACCEPTED->value, TaskStatus::PROCESSING->value])
            ->where(function ($q) use ($cutoff) {
                $q->where('accepted_at', '<=', $cutoff)
                  ->whereOr(function ($q2) use ($cutoff) {
                      // accepted_at 为空但已过 deadline 的，也一并回收
                      $q2->whereNull('accepted_at')->where('deadline_at', '<=', $cutoff);
                  });
            })
            ->select()
            ->toArray();

        $result = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $content = json_decode((string) ($row['content'] ?? ''), true) ?: [];
            $paid = (float) ($content['paid_tokens'] ?? 0);
            $retry = (int) ($content['retry'] ?? 0);
            $now = date('Y-m-d H:i:s');

            // 成品已落库：worker 只是没回调成功，别当失败
            if ((string) ($row['video_url'] ?? '') !== '') {
                $db->table('lp_ai_task')->where('id', $id)->update([
                    'status'      => TaskStatus::COMPLETED->value,
                    'finished_at' => $now,
                    'updated_at'  => $now,
                ]);
                $this->writeLog($id, 'reaped_completed', ['reason' => 'video_url already stored']);
                $result[] = ['task_id' => $id, 'action' => 'completed'];
                continue;
            }

            if ($retry < 1) {
                $content['retry'] = $retry + 1;
                $db->table('lp_ai_task')->where('id', $id)->update([
                    'status'      => TaskStatus::PENDING->value,
                    'content'     => json_encode($content, JSON_UNESCAPED_UNICODE),
                    'worker_id'   => null,
                    'accepted_at' => null,
                    'deadline_at' => date('Y-m-d H:i:s', time() + max(10, $ttlMin) * 60),
                    'updated_at'  => $now,
                ]);
                $this->writeLog($id, 'reaped_retry', ['worker_id' => $row['worker_id'] ?? '']);
                $result[] = ['task_id' => $id, 'action' => 'retry'];
                continue;
            }

            $db->table('lp_ai_task')->where('id', $id)->update([
                'status'    => TaskStatus::FAILED->value,
                'failed_at' => $now,
                'updated_at' => $now,
            ]);
            $refunded = $this->refundCustomTask((int) ($row['source_ref_id'] ?? 0), $paid, $retry);
            $this->writeLog($id, 'reaped_failed', [
                'worker_id'  => $row['worker_id'] ?? '',
                'paid_tokens' => $paid,
                'refunded'   => $refunded,
            ]);
            $result[] = ['task_id' => $id, 'action' => 'failed', 'refunded' => $refunded];
        }

        if ($result) {
            Log::info('AiTaskService: reaped ' . count($result) . ' stuck custom tasks');
        }

        return $result;
    }

    /**
     * 卡死任务退款：付了代币退代币，走免费额度退一次额度
     */
    private function refundCustomTask(int $userId, float $paidTokens, int $retry): bool
    {
        if ($userId <= 0) {
            return false;
        }
        try {
            if ($paidTokens > 0.0) {
                (new \app\live\service\WalletService())->credit(
                    $userId, $paidTokens, 'video_gen_refund', 0, 'AI generation timeout refund'
                );
                return true;
            }
            // 免费额度：兜底上限按免费档（普通用户 2 / 会员 5 由 lp_vip_plan 决定，
            // 这里退 1 次，上限取 entitlements 实际值）
            $ent = (new \app\live\service\VipService())->entitlements($userId);
            (new \app\live\service\GenCreditService())->refund(
                $userId, max(1, (int) ($ent['image_daily_limit'] ?? 1))
            );
            return true;
        } catch (\Throwable $e) {
            Log::error("[reap] refund failed user={$userId}: {$e->getMessage()}");
            return false;
        }
    }

    public function expireOverdueStreams(): array
    {
        $maxStreamSec = (int) config('ai.stream.max_stream_sec', 120);

        $conn = Db::connect('live_mysql');
        $expiredSwitches = $conn->table('lp_room_switch_task')
            ->where('status', 'accepted')
            ->where('to_mode', BizCode::ROOM_MODE_INTERACTION)
            ->whereNotNull('scheduled_at')
            ->select()
            ->toArray();

        $result = [];
        foreach ($expiredSwitches as $st) {
            $expireAt = date('Y-m-d H:i:s', strtotime($st['scheduled_at']) + $maxStreamSec);

            if ($expireAt <= date('Y-m-d H:i:s')) {
                try {
                    $endResult = $this->handleStreamEndByRoom((int) $st['room_id'], 'stream_timeout');
                    $result[] = array_merge(['switch_task_id' => $st['id'], 'room_id' => $st['room_id']], $endResult);
                } catch (\Throwable $e) {
                    $result[] = [
                        'switch_task_id' => $st['id'],
                        'room_id'        => $st['room_id'],
                        'ended'          => false,
                        'error'          => $e->getMessage(),
                    ];
                }
            }
        }

        if (count($result) > 0) {
            Log::info("AiTaskService: expired " . count($result) . " overdue streams");
        }

        return $result;
    }

    public function getTask(int $taskId): ?array
    {
        $task = AiTask::find($taskId);
        if (!$task) {
            return null;
        }
        return $task->toArray();
    }

    private function endInteractionInRoom(int $roomId, ?int $taskId): void
    {
        try {
            $this->switchScheduler->expireInteraction($roomId);
        } catch (\Throwable $e) {
            Log::warning("AiTaskService: expireInteraction failed: {$e->getMessage()}");
        }

        try {
            if ($taskId) {
                $this->gateway->pushSwitchToPublic($roomId, $taskId);
            }
        } catch (\Throwable $e) {
            Log::warning("AiTaskService: pushSwitchToPublic failed: {$e->getMessage()}");
        }

        try {
            $this->gateway->pushBroadcast($roomId, 'interaction_ended', [
                'task_id' => $taskId,
            ]);
        } catch (\Throwable $e) {
            Log::warning("AiTaskService: broadcast interaction_ended failed: {$e->getMessage()}");
        }
    }

    private function createInteractionPlayTask(
        int $roomId,
        int $aiTaskId,
        string $taskNo,
        int $durationSec,
        string $videoUrl,
        string $coverUrl
    ): int {
        $playTask = RoomPlayTask::create([
            'room_id'      => $roomId,
            'task_type'    => 'interaction',
            'ref_task_id'  => $aiTaskId,
            'mode'         => 'interaction',
            'priority'     => 50,
            'status'       => 'pending',
            'scheduled_at' => date('Y-m-d H:i:s'),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return (int) $playTask->id;
    }

    private function writeLog(int $taskId, string $eventType, array $payload): void
    {
        try {
            AiTaskLog::create([
                'task_id'      => $taskId,
                'event_type'   => $eventType,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Log::error("AiTaskService log write failed: {$e->getMessage()}");
        }
    }
}
