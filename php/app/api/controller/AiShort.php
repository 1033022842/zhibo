<?php
declare(strict_types=1);

namespace app\api\controller;

use app\ai\model\AiTask;
use app\BaseController;
use app\common\enums\TaskStatus;
use app\common\exception\BusinessException;
use app\common\util\StrHelper;
use app\common\web\ResultCode;
use app\live\service\GenCreditService;
use app\live\service\VipService;
use app\live\service\WalletService;
use think\App;
use think\facade\Db;
use think\facade\Log;

/**
 * 50 条短视频预设通道（与 customVideo 完全隔离）
 *
 * === 隔离设计（见 docs/50条提示词接入方案.md）===
 *   1) 任务类型用 ai_short_video / ai_short_image，与 custom_video / custom_image 分开。
 *      CustomVideo::pending()/accept()/uploadVideo() 都硬编码
 *      IN('custom_video','custom_image')，所以老 worker 在 SQL 层捞不到新任务，
 *      本通道的 worker 也捞不到老任务 —— 隔离靠数据库条件，不靠约定。
 *   2) API 命名空间 /api/aiShort/*，与 /api/customVideo/* 平行。
 *   3) 提示词正文放 lp_short_preset，lp_ai_task.content 只存 preset_code。
 *      原因：content 是 varchar(1000)，装不下长 prompt + lora_chain。
 *   4) complete/fail 由本控制器自己实现，不走 AiTaskService::completeTask——
 *      那个方法对非定制类任务会走「排入互动播单」分支，语义不符。
 */
final class AiShort extends BaseController
{
    public const TASK_VIDEO = 'ai_short_video';
    public const TASK_IMAGE = 'ai_short_image';

    /** 排队上限（分钟）：与 customVideo 同口径，四站共用一张卡 */
    private const TASK_TTL_MIN = 90;

    /** 时长上限（秒）：素材最长 60，生产限制 15 防止单条跑太久 */
    private const MAX_DURATION = 15;

    protected array $middleware = [
        \app\live\middleware\Auth::class => ['only' => ['detail', 'submit', 'myList', 'task']],
        \app\ai\middleware\AiAuth::class => ['only' => ['pending', 'accept', 'upload', 'complete', 'fail', 'stats', 'progress']],
    ];

    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    private function db()
    {
        return Db::connect('live_mysql');
    }

    /** 站点根相对地址（前端按当前域名+端口解析） */
    private function absUrl(string $url): string
    {
        if ($url === '' || str_starts_with($url, 'http') || str_starts_with($url, '//')) {
            return $url;
        }
        return '/' . ltrim($url, '/');
    }

    /**
     * 预设列表（公开）
     *
     * 只返回展示字段，绝不返回 prompt / lora_chain —— 否则等于把 50 条提示词公开。
     */
    public function presets()
    {
        $rows = $this->db()->table('lp_short_preset')
            ->where('status', 1)
            ->field('code, title, category, kind, duration_sec, cover_url, preview_url')
            ->order('weigh', 'desc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return $this->jsonSuccess([
            'total' => count($rows),
            'list'  => array_map(function (array $r) {
                return [
                    'code'         => (string) $r['code'],
                    'title'        => (string) $r['title'],
                    'category'     => (string) $r['category'],
                    'kind'         => (string) $r['kind'],
                    'duration_sec' => (int) $r['duration_sec'],
                    'cover_url'    => $this->absUrl((string) $r['cover_url']),
                    'preview_url'  => $this->absUrl((string) $r['preview_url']),
                ];
            }, $rows),
        ]);
    }

    /**
     * 单条详情（需登录）：含 prompt + lora_chain，供调用方自行编排
     */
    public function detail()
    {
        $code = trim((string) $this->request->get('code', ''));
        if ($code === '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'code 必填');
        }
        $r = $this->db()->table('lp_short_preset')
            ->where('code', $code)->where('status', 1)->find();
        if (!$r) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, 'Preset not found');
        }
        return $this->jsonSuccess([
            'code'         => (string) $r['code'],
            'title'        => (string) $r['title'],
            'category'     => (string) $r['category'],
            'kind'         => (string) $r['kind'],
            'prompt'       => (string) $r['prompt'],
            'lora_chain'   => json_decode((string) $r['lora_chain'], true) ?: [],
            'duration_sec' => (int) $r['duration_sec'],
            'cover_url'    => $this->absUrl((string) $r['cover_url']),
            'preview_url'  => $this->absUrl((string) $r['preview_url']),
        ]);
    }

    /**
     * 提交生成（需登录，multipart：image + preset_code + agreed_policy）
     */
    public function submit()
    {
        $userId = $this->getAuthUserId();

        $agreed = (string) $this->request->post('agreed_policy', '');
        if ($agreed !== '1') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Please confirm the image policy.');
        }

        $code = trim((string) $this->request->post('preset_code', ''));
        if ($code === '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'preset_code 必填');
        }
        $preset = $this->db()->table('lp_short_preset')
            ->where('code', $code)->where('status', 1)->find();
        if (!$preset) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, 'Preset not found or removed.');
        }

        $kind = ((string) $preset['kind']) === 'image' ? 'image' : 'video';

        // 时长：预设默认值，允许调用方覆盖（上限 MAX_DURATION）
        $duration = (int) $this->request->post('duration_sec', 0);
        if ($duration <= 0) {
            $duration = (int) $preset['duration_sec'];
        }
        $duration = max(1, min(self::MAX_DURATION, $duration));

        $confirmedPaid = (string) $this->request->post('confirm_paid', '') === '1';

        // 额度：口径与 customVideo 完全一致（普通 2 上限 +1/天；会员 5 回满；用尽后代币续）
        $ent = (new VipService())->entitlements($userId);
        $dailyLimit   = max(1, (int) ($ent['image_daily_limit'] ?? 2));
        $refillPerDay = max(0, (int) ($ent['image_daily_refill'] ?? 1));

        $credits = new GenCreditService();
        $wallet = new WalletService();
        $paidTokens = 0.0;

        $credits->refillAndGet($userId, $dailyLimit, $refillPerDay, 0);
        if (!$credits->consume($userId)) {
            $price = $this->genPrice($kind);
            if ($price <= 0) {
                return $this->jsonFail(
                    ResultCode::PARAM_ERROR,
                    'Daily limit reached. Your balance refills +' . $refillPerDay . '/day (max ' . $dailyLimit . ').'
                );
            }
            if ($wallet->balance($userId) < $price) {
                return $this->jsonFail(
                    ResultCode::BALANCE_NOT_ENOUGH,
                    'Your free generations are used up. Each extra ' . $kind . ' costs '
                    . $this->fmtPrice($price) . ' tokens — please top up to continue.'
                );
            }
            if (!$confirmedPaid) {
                return $this->jsonFail(
                    ResultCode::PAYMENT_CONFIRM_REQUIRED,
                    'This ' . $kind . ' costs ' . $this->fmtPrice($price) . ' tokens.'
                );
            }
            try {
                $wallet->debit($userId, $price, 'video_gen', 0, 'AI short ' . $kind . ' generation');
            } catch (BusinessException $e) {
                return $this->jsonFail(ResultCode::BALANCE_NOT_ENOUGH, 'Not enough tokens.');
            }
            $paidTokens = $price;
        }

        $refund = function () use (&$paidTokens, $credits, $wallet, $userId, $dailyLimit) {
            if ($paidTokens > 0) {
                try {
                    $wallet->credit($userId, $paidTokens, 'video_gen_refund', 0, 'AI generation refund');
                } catch (\Throwable $e) {
                    Log::error('[aiShort] token refund failed: ' . $e->getMessage());
                }
                return;
            }
            $credits->refund($userId, $dailyLimit);
        };

        $file = $this->request->file('image');
        if (!$file) {
            $refund();
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Please upload an image.');
        }
        if ((int) $file->getSize() > 10 * 1024 * 1024) {
            $refund();
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Image must be under 10MB.');
        }
        $ext = strtolower((string) $file->getOriginalExtension());
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $refund();
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Only jpg / png / webp images are supported.');
        }

        try {
            $upload = new \app\common\library\Upload($file);
            $upload->setTopic('custom_video');
            $attachment = $upload->upload(null, 0, $userId);
            $imageUrl = (string) $attachment['url'];

            $task = new AiTask();
            $task->task_no     = StrHelper::orderNo('AS');
            $task->room_id     = 0;
            $task->task_type   = $kind === 'image' ? self::TASK_IMAGE : self::TASK_VIDEO;
            // 低优先级：GPU 一张卡，让老通道 customVideo 先取（方案 A+B 里的 B）
            $task->priority    = 1;
            $task->source_type = 'ai_short';
            $task->source_ref_id = $userId;
            $task->persona_id  = 0;
            // content 只存引用：prompt / lora_chain 正文在 lp_short_preset
            $task->content     = json_encode([
                'preset_code'  => (string) $preset['code'],
                'label'        => (string) $preset['title'],
                'duration_sec' => $duration,
                'image_url'    => $imageUrl,
                'paid_tokens'  => $paidTokens,
                'retry'        => 0,
            ], JSON_UNESCAPED_UNICODE);
            $task->callback_mode = 'none';
            $task->status      = TaskStatus::PENDING->value;
            $task->deadline_at = date('Y-m-d H:i:s', time() + self::TASK_TTL_MIN * 60);
            $task->created_at  = date('Y-m-d H:i:s');
            $task->updated_at  = date('Y-m-d H:i:s');
            $task->save();
        } catch (\Throwable $e) {
            $refund();
            Log::error('[aiShort] submit failed: ' . $e->getMessage());
            return $this->jsonFail(
                ResultCode::RECORD_NOT_FOUND,
                'Submit failed, your generation credit has been refunded. Please retry.'
            );
        }

        $remain = (new GenCreditService())->refillAndGet($userId, $dailyLimit, $refillPerDay, 0);

        return $this->jsonSuccess([
            'task_id'        => (int) $task->id,
            'task_no'        => $task->task_no,
            'status'         => $task->status,
            'preset_code'    => (string) $preset['code'],
            'kind'           => $kind,
            'duration_sec'   => $duration,
            'remain'         => $remain,
            'paid_tokens'    => $paidTokens,
            'balance'        => (new WalletService())->balance($userId),
        ]);
    }

    /**
     * 我的作品（需登录）
     */
    public function myList()
    {
        $userId = $this->getAuthUserId();
        $tasks = AiTask::whereIn('task_type', [self::TASK_VIDEO, self::TASK_IMAGE])
            ->where('source_ref_id', $userId)
            ->order('id', 'desc')
            ->limit(50)
            ->select()->toArray();

        return $this->jsonSuccess(array_map(function (array $t) {
            $c = json_decode((string) $t['content'], true) ?: [];
            $status = (string) $t['status'];
            // 成品已上传但 complete 没到：按已完成展示，避免前端永远「生成中」
            if ((string) $t['video_url'] !== '' && in_array($status, ['accepted', 'processing'], true)) {
                $status = 'completed';
            }
            return [
                'task_id'     => (int) $t['id'],
                'task_no'     => (string) $t['task_no'],
                'preset_code' => (string) ($c['preset_code'] ?? ''),
                'title'       => (string) ($c['label'] ?? ''),
                'kind'        => $t['task_type'] === self::TASK_IMAGE ? 'image' : 'video',
                'status'      => $status,
                'video_url'   => $t['video_url'] ? $this->absUrl((string) $t['video_url']) : '',
                'cover_url'   => $t['cover_url'] ? $this->absUrl((string) $t['cover_url']) : '',
                'duration'    => (int) $t['duration_sec'],
                'created_at'  => $t['created_at'],
                'finished_at' => $t['finished_at'],
            ];
        }, $tasks));
    }

    /**
     * 单个任务状态（需登录，轮询用）
     */
    public function task()
    {
        $userId = $this->getAuthUserId();
        $taskNo = trim((string) $this->request->get('task_no', ''));
        $taskId = (int) $this->request->get('task_id', 0);
        if ($taskNo === '' && $taskId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task_no 或 task_id 必填');
        }
        $q = AiTask::whereIn('task_type', [self::TASK_VIDEO, self::TASK_IMAGE])
            ->where('source_ref_id', $userId);
        $t = $taskNo !== '' ? $q->where('task_no', $taskNo)->find() : $q->where('id', $taskId)->find();
        if (!$t) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, 'Task not found');
        }
        $c = json_decode((string) $t->content, true) ?: [];
        return $this->jsonSuccess([
            'task_id'     => (int) $t->id,
            'task_no'     => (string) $t->task_no,
            'preset_code' => (string) ($c['preset_code'] ?? ''),
            'title'       => (string) ($c['label'] ?? ''),
            'status'      => (string) $t->status,
            'video_url'   => $t->video_url ? $this->absUrl((string) $t->video_url) : '',
            'cover_url'   => $t->cover_url ? $this->absUrl((string) $t->cover_url) : '',
            'duration'    => (int) $t->duration_sec,
            'error'       => (string) ($c['error'] ?? ''),
            'created_at'  => $t->created_at,
            'finished_at' => $t->finished_at,
        ]);
    }

    // ───────────────────────── worker 侧 ─────────────────────────

    /**
     * worker 捞单（X-Api-Key）
     *
     * 只查 ai_short_video / ai_short_image，并按 priority 升序（低优先级数字小），
     * 与老通道 priority=5 拉开差距，高峰期让老通道先取。
     * 返回时 join lp_short_preset 下发 prompt / lora_chain / duration。
     */
    public function pending()
    {
        $count = min(3, max(1, (int) ($this->request->get('count') ?? 1)));
        $tasks = AiTask::whereIn('task_type', [self::TASK_VIDEO, self::TASK_IMAGE])
            ->where('status', TaskStatus::PENDING->value)
            ->order('priority', 'asc')
            ->order('id', 'asc')
            ->limit($count)
            ->select()->toArray();

        if (!$tasks) {
            return $this->jsonSuccess([]);
        }

        // 批量取预设，避免 N+1
        $codes = [];
        foreach ($tasks as $t) {
            $c = json_decode((string) $t['content'], true) ?: [];
            if (!empty($c['preset_code'])) {
                $codes[] = (string) $c['preset_code'];
            }
        }
        $presets = [];
        if ($codes) {
            foreach ($this->db()->table('lp_short_preset')
                         ->whereIn('code', array_values(array_unique($codes)))
                         ->select()->toArray() as $p) {
                $presets[(string) $p['code']] = $p;
            }
        }

        return $this->jsonSuccess(array_map(function (array $t) use ($presets) {
            $c = json_decode((string) $t['content'], true) ?: [];
            $code = (string) ($c['preset_code'] ?? '');
            $p = $presets[$code] ?? null;
            return [
                'task_id'          => (int) $t['id'],
                'task_no'          => (string) $t['task_no'],
                'kind'             => $t['task_type'] === self::TASK_IMAGE ? 'image' : 'video',
                'preset_code'      => $code,
                'title'            => (string) ($p['title'] ?? ($c['label'] ?? '')),
                // 正文来自 preset 表；preset 被删时退回空串，worker 会拒绝这种任务
                'prompt'           => (string) ($p['prompt'] ?? ''),
                'lora_chain'       => json_decode((string) ($p['lora_chain'] ?? '[]'), true) ?: [],
                'duration_sec'     => (int) ($c['duration_sec'] ?? ($p['duration_sec'] ?? 8)),
                'image_url'        => $this->absUrl((string) ($c['image_url'] ?? '')),
                'created_at'       => $t['created_at'],
            ];
        }, $tasks));
    }

    /**
     * worker 接单（pending → accepted）
     */
    public function accept()
    {
        $taskId = (int) $this->request->post('task_id', 0);
        $workerId = (string) ($this->request->aiWorkerId ?? 'unknown');
        $task = AiTask::find($taskId);
        if (!$task || !in_array($task->task_type, [self::TASK_VIDEO, self::TASK_IMAGE], true)) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task 不存在');
        }
        if ($task->status !== TaskStatus::PENDING->value) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '任务已被接单或已完成');
        }
        $task->status = TaskStatus::ACCEPTED->value;
        $task->worker_id = $workerId;
        $task->accepted_at = date('Y-m-d H:i:s');
        $task->updated_at = date('Y-m-d H:i:s');
        $task->save();
        return $this->jsonSuccess(['task_id' => $taskId, 'status' => $task->status]);
    }

    /**
     * worker 上传成品（/upload 与老通道 /uploadVideo 语义一致，只是任务类型不同）
     */
    public function upload()
    {
        $taskId = (int) $this->request->post('task_id', 0);
        $file = $this->request->file('video');
        $cover = $this->request->file('cover');

        if ($taskId <= 0 || !$file) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task_id / video 必填');
        }
        $task = AiTask::find($taskId);
        if (!$task || !in_array($task->task_type, [self::TASK_VIDEO, self::TASK_IMAGE], true)) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task 不存在');
        }

        $upload = new \app\common\library\Upload($file);
        $upload->setTopic('custom_video');
        $videoUrl = (string) $upload->upload(null, 0, 0)['url'];

        $coverUrl = '';
        if ($cover) {
            $upload2 = new \app\common\library\Upload($cover);
            $upload2->setTopic('custom_video');
            $coverUrl = (string) $upload2->upload(null, 0, 0)['url'];
        }

        $task->result_type = $task->task_type === self::TASK_IMAGE ? 'image_file' : 'video_file';
        $task->video_url = $videoUrl;
        $task->cover_url = $coverUrl;
        $task->updated_at = date('Y-m-d H:i:s');
        $task->save();

        return $this->jsonSuccess(['video_url' => $videoUrl, 'cover_url' => $coverUrl]);
    }

    /**
     * worker 上报进度（accepted → processing，只更新状态与心跳时间）
     */
    public function progress()
    {
        $taskId = (int) $this->request->post('task_id', 0);
        if ($taskId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task_id 必填');
        }
        $task = AiTask::find($taskId);
        if (!$task || !in_array($task->task_type, [self::TASK_VIDEO, self::TASK_IMAGE], true)) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task 不存在');
        }
        // 只把 accepted 推进到 processing，绝不回退已完成/失败状态
        if ($task->status === TaskStatus::ACCEPTED->value) {
            $task->status = TaskStatus::PROCESSING->value;
            $task->updated_at = date('Y-m-d H:i:s');
            $task->save();
        }
        return $this->jsonSuccess(['task_id' => $taskId, 'status' => $task->status]);
    }

    /**
     * worker 报完成（accepted/processing → completed）
     *
     * 不复用 AiTaskService::completeTask：那个方法对非定制类任务会走
     * 「createInteractionPlayTask + 切房间 + 推流」分支，本通道是离线成品，语义不符。
     */
    public function complete()
    {
        $taskId = (int) $this->request->post('task_id', 0);
        $duration = (int) $this->request->post('duration_sec', 0);
        $task = AiTask::find($taskId);
        if (!$task || !in_array($task->task_type, [self::TASK_VIDEO, self::TASK_IMAGE], true)) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task 不存在');
        }
        if (!in_array($task->status, [TaskStatus::ACCEPTED->value, TaskStatus::PROCESSING->value], true)) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '任务状态不允许完成');
        }
        $now = date('Y-m-d H:i:s');
        $task->status = TaskStatus::COMPLETED->value;
        $task->duration_sec = max(0, $duration);
        $task->finished_at = $now;
        $task->updated_at = $now;
        $task->save();

        return $this->jsonSuccess([
            'task_id'     => $taskId,
            'task_no'     => (string) $task->task_no,
            'status'      => $task->status,
            'finished_at' => $now,
        ]);
    }

    /**
     * worker 报失败（含退款）
     *
     * 与 AiTaskService::failTask 的差别只有一点：那里只对 custom_* 退款，
     * 本通道需要同样的退款语义，所以在自己这边实现（不改共享文件）。
     */
    public function fail()
    {
        $taskId = (int) $this->request->post('task_id', 0);
        $errorMsg = (string) $this->request->post('error_msg', '');
        $task = AiTask::find($taskId);
        if (!$task || !in_array($task->task_type, [self::TASK_VIDEO, self::TASK_IMAGE], true)) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task 不存在');
        }
        if (!in_array($task->status, [TaskStatus::ACCEPTED->value, TaskStatus::PROCESSING->value], true)) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '任务状态不允许置失败');
        }

        $now = date('Y-m-d H:i:s');
        $task->status = TaskStatus::FAILED->value;
        $task->failed_at = $now;
        $task->updated_at = $now;
        $task->save();

        $this->refund($task, $errorMsg);

        return $this->jsonSuccess([
            'task_id'   => $taskId,
            'task_no'   => (string) $task->task_no,
            'status'    => $task->status,
            'failed_at' => $now,
        ]);
    }

    /** 退款：付费退回代币，免费额度退回 1 次 */
    private function refund(AiTask $task, string $reason): void
    {
        $userId = (int) $task->source_ref_id;
        if ($userId <= 0) {
            return;
        }
        $c = json_decode((string) $task->content, true) ?: [];
        $paid = (float) ($c['paid_tokens'] ?? 0);
        // 把错误信息留在 content 里，前端 task 接口可读
        $c['error'] = mb_substr($reason, 0, 200);
        $task->content = json_encode($c, JSON_UNESCAPED_UNICODE);
        $task->save();

        try {
            if ($paid > 0.0) {
                (new WalletService())->credit($userId, $paid, 'video_gen_refund', 0, 'AI short generation refund');
                return;
            }
            $ent = (new VipService())->entitlements($userId);
            (new GenCreditService())->refund($userId, max(1, (int) ($ent['image_daily_limit'] ?? 1)));
        } catch (\Throwable $e) {
            Log::error("[aiShort] refund failed user={$userId}: {$e->getMessage()}");
        }
    }

    /**
     * 队列概览（X-Api-Key）：排查用
     */
    public function stats()
    {
        $row = $this->db()->table('lp_ai_task')
            ->whereIn('task_type', [self::TASK_VIDEO, self::TASK_IMAGE])
            ->fieldRaw(
                "SUM(status = 'pending') AS pending,
                 SUM(status IN ('accepted','processing')) AS running,
                 SUM(status IN ('accepted','processing')
                     AND accepted_at IS NOT NULL
                     AND accepted_at < '" . date('Y-m-d H:i:s', time() - self::TASK_TTL_MIN * 60) . "') AS stuck"
            )
            ->find() ?: [];

        return $this->jsonSuccess([
            'pending'     => (int) ($row['pending'] ?? 0),
            'running'     => (int) ($row['running'] ?? 0),
            'stuck'       => (int) ($row['stuck'] ?? 0),
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /** 代币单价（后台「功能定价」可改；0=关闭） */
    private function genPrice(string $kind): float
    {
        $key = $kind === 'image' ? 'image_gen_price' : 'video_gen_price';
        return max(0.0, (float) WalletService::config($key, $kind === 'image' ? '10' : '50'));
    }

    private function fmtPrice(float $price): string
    {
        return rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');
    }
}
