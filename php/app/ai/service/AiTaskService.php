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
        $task->result_type = 'video_file';
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
        if (!$snap || $snap['current_state'] !== RoomState::INTERACTION_LIVE->value) {
            return ['room_id' => $roomId, 'ended' => false, 'reason' => 'not_interaction_state'];
        }

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

        Log::info("AiTaskService: room {$roomId} stream ended by {$reason}");

        return [
            'room_id'   => $roomId,
            'ended'     => true,
            'reason'    => $reason,
            'task_id'   => $taskId,
        ];
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
