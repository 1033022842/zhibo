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
 * 定制角色视频 / 换装（AI 电脑 ComfyUI MiniMax-H3 生成）
 *
 * === 生成额度规则（2026-10 调整，前后端/套餐表口径必须一致）===
 *   普通用户（未订阅）：余额上限 2 次，每天补充 1 次（最多累积到 2）
 *   会员（Premium / Premium Plus）：余额上限 5 次，每天回满 5 次（refill = limit）
 *   测试账号（UNLIMITED_USER_IDS）：不限量，前端显示 ∞
 *
 * 实际数值来自 lp_vip_plan.image_daily_limit / image_daily_refill，
 * 非会员走 VipService::buildEntitlements() 里的免费额度分支。
 * 余额模型见 GenCreditService：balance = min(cap, balance + 断签天数 × refill)。
 */
final class CustomVideo extends BaseController
{
    /** 每日生成配额兜底值：普通用户 2 次（实际以套餐配置为准） */
    private const DAILY_LIMIT = VipService::FREE_IMAGE_DAILY_LIMIT;

    /** 普通用户（非会员）的余额上限与每日补充次数：2 次上限、每天 +1 */
    private const FREE_DAILY_LIMIT  = VipService::FREE_IMAGE_DAILY_LIMIT;
    private const FREE_DAILY_REFILL = VipService::FREE_IMAGE_DAILY_REFILL;

    /**
     * 免费次数用尽后每次生成扣的代币数（后台「功能定价」可改，写进 lp_platform_config.video_gen_price）
     *
     * 业务规则（2026-10-21）：普通用户 2 次免费额度用完后，按 100 代币/次续生成；
     * 会员额度用完后同样按此价续生成。0 = 关闭代币续生成（只提示额度用尽）。
     */
    private const VIDEO_PRICE_DEFAULT = 100;

    /** 无限配额白名单（测试账号不限量） */
    private const UNLIMITED_USER_IDS = [16];

    /**
     * 任务存活上限（分钟）
     *
     * 四站共用一张显卡，ComfyUI 队列串行，高峰期排队 + 生成可能超过 30 分钟。
     * 超过这个时间仍未完成、且已被 worker 接单的任务，由 ai:task:cron 回收：
     * 先重试一次，再失败退款（见 AiTaskService::reapStuckCustomTasks）。
     */
    private const TASK_TTL_MIN = 90;

    /**
     * 视频风格卡片（worker v5.0 按 style 选工作流）
     * key   = style 传值，须与 AI 电脑 worker_v50.py 的 WF_VIDEO_MAP 键一致（空/未知值 worker 回退 fingering）
     * label = 卡片标题、desc = 卡片描述 —— 展示文案，按需填写
     */
    private const STYLES = [
        [
            'key'   => 'fingering',
            'label' => 'Self Pleasure',
            'desc'  => 'A slow, intimate moment all to herself — soft, unhurried, deeply satisfying.',
        ],
        [
            'key'   => 'mysticxxx',
            'label' => 'Deep Throat',
            'desc'  => 'Up close and intense — she gives herself completely and never breaks eye contact.',
        ],
        [
            'key'   => 'cumfacial',
            'label' => 'Cum Facial',
            'desc'  => 'The breathless finale — she lifts her chin and takes it all, just for you.',
        ],
    ];

    protected array $middleware = [
        \app\live\middleware\Auth::class => ['only' => ['options', 'submit', 'myList']],
        \app\ai\middleware\AiAuth::class => ['only' => ['pending', 'accept', 'uploadVideo', 'stats']],
    ];

    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    /**
     * 可选角色 + 动作模板（登录用户：平台人设 + 我的角色）
     */
    public function options()
    {
        $userId = $this->getAuthUserId();
        $ent = (new VipService())->entitlements($userId);
        $unlimited = in_array($userId, self::UNLIMITED_USER_IDS, true);

        $limit  = (int) ($ent['image_daily_limit'] ?? 0);
        $refill = (int) ($ent['image_daily_refill'] ?? 1);

        // 额度改为「余额 + 每天补充」：每天 +refill，最多累积到 limit
        // 首次建记录时按「上限 - 当天已用」初始化，保证上线当天行为与旧逻辑一致
        $balance = -1;
        if (!$unlimited && $userId > 0) {
            $balance = (new GenCreditService())->refillAndGet(
                $userId,
                $limit,
                $refill,
                max(0, $limit - $this->todayUsed($userId))
            );
        }

        return $this->jsonSuccess([
            'styles' => self::STYLES,
            // Outfit 模式的服装卡片 = 商店商品（owned=0 的前端提示去购买）
            'image_presets' => $this->outfitPresets($userId),
            // 免费次数用尽后的续生成单价（代币）与当前代币余额
            'video_price' => $this->videoPrice(),
            'balance'     => (new WalletService())->balance($userId),
            'quota'   => [
                'daily_limit'  => $unlimited ? '∞' : $limit,
                // used 仍按当天已用统计，仅作展示
                'used'         => $userId > 0 ? $this->todayUsed($userId) : 0,
                // remaining = 当前可用余额；-1 表示不限量
                'remaining'    => $balance,
                'refill_daily' => $refill,   // 每天补充次数（前端展示「每天 +1」）
                'level'        => (string) ($ent['level_name'] ?? 'Free'),
                'is_vip'       => (bool) ($ent['is_vip'] ?? false),
                'unlimited'    => $unlimited,
            ],
        ]);
    }

    /**
     * 当日已用生成次数（图片 + 视频同算一个池子，与配额上限口径一致）
     */
    private function todayUsed(int $userId): int
    {
        return (int) AiTask::whereIn('source_type', ['custom_video', 'custom_image'])
            ->where('source_ref_id', $userId)
            ->whereLike('created_at', date('Y-m-d') . '%')
            ->count();
    }

    /**
     * Outfit（换装）可选服装：商店在售商品 + 当前用户已购标记
     */
    private function outfitPresets(int $userId): array
    {
        $db = Db::connect('live_mysql');
        $items = $db->table('lp_shop_item')
            ->where('status', 1)
            ->field('id, title, description, cover_url, price')
            ->order('weigh', 'desc')
            ->order('updated_at', 'desc')
            ->order('id', 'desc')
            ->limit(100)
            ->select()->toArray();
        if (!$items) {
            return [];
        }

        $owned = [];
        if ($userId > 0) {
            $owned = array_column(
                $db->table('lp_user_item')
                    ->where('user_id', $userId)
                    ->where('item_type', 'shop')
                    ->field('item_id, quantity')
                    ->select()->toArray(),
                'quantity',
                'item_id'
            );
        }

        $list = [];
        foreach ($items as $it) {
            $id    = (int) $it['id'];
            $cover = trim((string) $it['cover_url']);
            if ($cover !== '' && !str_starts_with($cover, 'http') && !str_starts_with($cover, '//')) {
                // 反代 / 非 80 端口部署：返回根相对路径，由浏览器按当前域名+端口解析
                $cover = '/' . ltrim($cover, '/');
            }
            $list[] = [
                'key'       => 'outfit_' . $id,
                'item_id'   => $id,
                'label'     => (string) $it['title'],
                'desc'      => mb_substr(trim((string) $it['description']), 0, 48),
                'cover_url' => $cover,
                'price'     => max(0, (int) $it['price']),
                'owned'     => (int) ($owned[$id] ?? 0),
            ];
        }
        return $list;
    }

    /**
     * 提交生成任务（VIP 专属，multipart：image + style + agreed_policy）
     */
    public function submit()
    {
        $userId = $this->getAuthUserId();
        // type 兼容 image / outfit（前端历史版本用 outfit）
        $typeRaw = (string) $this->request->post('type', 'video');
        $kind = in_array($typeRaw, ['image', 'outfit'], true) ? 'image' : 'video';
        $agreed = (int) $this->request->post('agreed_policy', 0);

        if ($agreed !== 1) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Please confirm the upload policy first.');
        }

        if ($kind === 'image') {
            // 图片编辑（换装）：正向词由「商品描述 / 用户指令」拼装，随任务落库传给 worker
            // （worker 用 content.prompt 覆盖 qwenedit_query.json 节点76 的默认词）
            $userPrompt = trim((string) $this->request->post('prompt', ''));
            if (mb_strlen($userPrompt) > 500) {
                return $this->jsonFail(ResultCode::PARAM_ERROR, 'Instruction is too long (max 500 characters).');
            }

            // 统一的换装后缀：保证「换衣」诉求（全身出镜、原样保留人脸/发型/姿态/背景）
            $suffix = ' Replace her current clothing completely with this outfit, head to toe. '
                . 'Keep her face, hairstyle, body, pose and background exactly the same. '
                . 'Show her full body so the entire outfit is visible. Photorealistic, high quality detail.';

            // 选中商店里的服装：必须是已购商品，指令由商品描述拼装（不信任前端传的 prompt）
            $itemId = (int) $this->request->post('item_id/d', 0);
            $label  = 'AI Edit';
            $prompt = '';
            if ($itemId > 0) {
                $db     = Db::connect('live_mysql');
                $owns   = $db->table('lp_user_item')
                    ->where('user_id', $userId)
                    ->where('item_type', 'shop')
                    ->where('item_id', $itemId)
                    ->find();
                if (!$owns) {
                    return $this->jsonFail(ResultCode::NO_PERMISSION, 'Please buy this outfit in the Shop first.');
                }
                $item = $db->table('lp_shop_item')->where('id', $itemId)->where('status', 1)->find();
                if (!$item) {
                    return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, 'Outfit not found or removed.');
                }

                $title = (string) $item['title'];
                $desc  = trim((string) $item['description']);
                $prompt = 'Dress the woman in the following outfit — ' . $title
                    . ($desc !== '' ? ': ' . $desc : '.') . $suffix;
                if ($userPrompt !== '') {
                    $prompt .= ' ' . $userPrompt;
                }
                $label = 'Outfit · ' . $title;
            } elseif ($userPrompt !== '') {
                // 无选品（自由指令）：直接按用户指令换装
                $prompt = $userPrompt . $suffix;
            }

            if (mb_strlen($prompt) > 500) {
                $prompt = mb_substr($prompt, 0, 500);
            }

            $style = ['key' => 'image_edit', 'label' => $label, 'prompt' => $prompt];
        } else {
            // 视频：style 选工作流（worker v5.0）；兼容旧前端传 preset
            $styleKey = strtolower(trim((string) ($this->request->post('style', '') ?: $this->request->post('preset', ''))));
            $style = null;
            foreach (self::STYLES as $s) {
                if ($s['key'] === $styleKey) { $style = $s; break; }
            }
            if (!$style) {
                return $this->jsonFail(ResultCode::PARAM_ERROR, 'Please choose a style.');
            }
        }

        // 额度校验：普通用户与会员都可生成，只是额度不同
        //   普通用户：上限 2、每天 +1（见 VipService 免费分支 / self::FREE_*）
        //   会员：上限 5、每天回满（lp_vip_plan.image_daily_limit = image_daily_refill = 5）
        $ent = (new VipService())->entitlements($userId);
        $dailyLimit   = max(1, (int) ($ent['image_daily_limit'] ?? self::DAILY_LIMIT));
        $refillPerDay = max(0, (int) ($ent['image_daily_refill'] ?? self::FREE_DAILY_REFILL));
        $unlimited    = in_array($userId, self::UNLIMITED_USER_IDS, true);

        // 先扣额度再上传：原来先存图再校验，超额时会白落一份文件
        // 免费额度用尽后，按后台配置的单价用代币续生成（仍仅限会员）
        $credits = new GenCreditService();
        $wallet = new WalletService();
        $paidTokens = 0.0;
        if (!$unlimited) {
            $credits->refillAndGet($userId, $dailyLimit, $refillPerDay, 0);
            if (!$credits->consume($userId)) {
                // 免费额度用尽：优先用代币续生成（后台可配单价，0=关闭）
                $price = $this->videoPrice();
                if ($price <= 0) {
                    $tip = $refillPerDay >= $dailyLimit
                        ? 'Your free quota resets to ' . $dailyLimit . '/day. Top up tokens or upgrade for more.'
                        : 'Daily limit reached. Your balance refills +' . $refillPerDay . '/day (max ' . $dailyLimit . ').';
                    return $this->jsonFail(ResultCode::PARAM_ERROR, $tip);
                }
                if ($wallet->balance($userId) < $price) {
                    return $this->jsonFail(ResultCode::BALANCE_NOT_ENOUGH, 'Your free generations are used up. Each extra generation costs ' . $this->fmtPrice($price) . ' tokens — please top up to continue.');
                }
                try {
                    $wallet->debit($userId, $price, 'video_gen', 0, 'AI video generation');
                } catch (BusinessException $e) {
                    return $this->jsonFail(ResultCode::BALANCE_NOT_ENOUGH, 'Not enough tokens. Please top up to continue generating.');
                }
                $paidTokens = $price;
            }
        }

        // 失败补偿：免费额度退次数，代币支付退回代币
        $refund = function () use (&$paidTokens, $credits, $wallet, $userId, $dailyLimit, $unlimited) {
            if ($unlimited) {
                return;
            }
            if ($paidTokens > 0) {
                try {
                    $wallet->credit($userId, $paidTokens, 'video_gen_refund', 0, 'AI video generation refund');
                } catch (\Throwable $e) {
                    Log::error('[customVideo] token refund failed: ' . $e->getMessage());
                }
                return;
            }
            $credits->refund($userId, $dailyLimit);
        };

        // 图片（≤10MB，jpg/png/webp；经 Upload 库自动压缩）
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

            // 建任务
            $task = new AiTask();
            $task->task_no     = StrHelper::orderNo($kind === 'image' ? 'CI' : 'CV');
            $task->room_id     = 0;
            $task->task_type   = $kind === 'image' ? 'custom_image' : 'custom_video';
            $task->priority    = 5;
            $task->source_type = 'custom_video';
            $task->source_ref_id = $userId;
            $task->persona_id  = 0;
            // paid_tokens / retry 一并落库：worker 接单后挂掉时，定时任务要靠这两个值
            // 决定「重试一次」还是「置失败并把额度/代币退给用户」
            $task->content     = json_encode([
                'style'       => $style['key'],
                'label'       => $style['label'],
                'prompt'      => (string) ($style['prompt'] ?? ''),
                'image_url'   => $imageUrl,
                'paid_tokens' => $paidTokens,
                'retry'       => 0,
            ], JSON_UNESCAPED_UNICODE);
            $task->callback_mode = 'none';
            $task->status      = TaskStatus::PENDING->value;
            // 排队上限：四站共用一张卡，ComfyUI 串行执行，给足 90 分钟
            // （AiTaskCron 会回收「accepted 但超过这个时间没完成」的任务）
            $task->deadline_at = date('Y-m-d H:i:s', time() + self::TASK_TTL_MIN * 60);
            $task->created_at  = date('Y-m-d H:i:s');
            $task->updated_at  = date('Y-m-d H:i:s');
            $task->save();
        } catch (\Throwable $e) {
            // 上传或入库失败：把额度/代币退回去，不让用户白扣
            $refund();
            Log::error('[customVideo] submit failed: ' . $e->getMessage());
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, 'Submit failed, your generation credit has been refunded. Please retry.');
        }

        $remain = $unlimited ? -1 : (new GenCreditService())->refillAndGet($userId, $dailyLimit, $refillPerDay, 0);

        return $this->jsonSuccess([
            'task_id' => (int) $task->id,
            'task_no' => $task->task_no,
            'status'  => $task->status,
            // remain：提交后剩余余额；-1 表示不限量（前端按此显示）
            'remain'  => $remain,
            // paid_tokens：本次是否走了代币续生成（>0 为扣掉的代币数）
            'paid_tokens' => $paidTokens,
            'balance' => $unlimited ? -1 : (new WalletService())->balance($userId),
        ]);
    }

    /**
     * 免费次数用尽后每次生成的代币单价（后台「功能定价」可改；0 = 关闭代币续生成）
     */
    private function videoPrice(): float
    {
        return max(0.0, (float) WalletService::config('video_gen_price', (string) self::VIDEO_PRICE_DEFAULT));
    }

    /** 价格展示：30.00 → 30 */
    private function fmtPrice(float $price): string
    {
        return rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');
    }

    /**
     * 我的任务列表
     */
    public function myList()
    {
        $userId = $this->getAuthUserId();
        $tasks = AiTask::whereIn('source_type', ['custom_video', 'custom_image'])
            ->where('source_ref_id', $userId)
            ->order('id', 'desc')
            ->limit(50)
            ->select()->toArray();

        return $this->jsonSuccess(array_map(function (array $t) {
            $c = json_decode((string) $t['content'], true) ?: [];
            $status = (string) $t['status'];
            // 成品已上传但 complete 回调没到（网络抖动/worker 中断）：对用户按已完成展示，
            // 否则页面会永远停在「生成中」，明明文件已经在对象存储里却看不了
            if ((string) $t['video_url'] !== '' && in_array($status, ['accepted', 'processing'], true)) {
                $status = 'completed';
            }
            return [
                'task_id'    => (int) $t['id'],
                'task_no'    => $t['task_no'],
                'label'      => (string) ($c['label'] ?? ''),
                'kind'       => $t['task_type'] === 'custom_image' ? 'image' : 'video',
                'style'      => (string) ($c['style'] ?? ($c['action'] ?? '')),
                'status'     => $status,
                'video_url'  => $t['video_url'] ? $this->absUrl((string) $t['video_url']) : '',
                'cover_url'  => $t['cover_url'] ? $this->absUrl((string) $t['cover_url']) : '',
                'duration'   => (int) $t['duration_sec'],
                'created_at' => $t['created_at'],
                'finished_at' => $t['finished_at'],
            ];
        }, $tasks));
    }

    /**
     * worker：捞待处理任务（X-Api-Key 鉴权）
     */
    public function pending()
    {
        $count = min(3, max(1, (int) ($this->request->get('count') ?? 1)));
        $tasks = AiTask::whereIn('task_type', ['custom_video', 'custom_image'])
            ->where('status', TaskStatus::PENDING->value)
            ->order('priority', 'desc')
            ->order('id', 'asc')
            ->limit($count)
            ->select()->toArray();

        return $this->jsonSuccess(array_map(function (array $t) {
            $c = json_decode((string) $t['content'], true) ?: [];
            return [
                'task_id'    => (int) $t['id'],
                'task_no'    => $t['task_no'],
                'kind'       => $t['task_type'] === 'custom_image' ? 'image' : 'video',
                'style'      => (string) ($c['style'] ?? ($c['action'] ?? '')),
                'label'      => (string) ($c['label'] ?? ''),
                'prompt'     => (string) ($c['prompt'] ?? ''),
                'image_url'  => $this->absUrl((string) ($c['image_url'] ?? '')),
                'created_at' => $t['created_at'],
            ];
        }, $tasks));
    }

    /**
     * worker：认领任务（pending → accepted，记录 worker_id）
     */
    public function accept()
    {
        $taskId = (int) $this->request->post('task_id', 0);
        $workerId = (string) ($this->request->aiWorkerId ?? 'unknown');
        $task = AiTask::find($taskId);
        if (!$task || !in_array($task->task_type, ['custom_video', 'custom_image'], true)) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task 不存在');
        }
        if ($task->status !== TaskStatus::PENDING->value) {
            return $this->jsonFail(ResultCode::TASK_ALREADY_DONE, 'task 已被处理');
        }
        $now = date('Y-m-d H:i:s');
        $task->status = TaskStatus::ACCEPTED->value;
        $task->worker_id = $workerId;
        $task->accepted_at = $now;
        $task->updated_at = $now;
        $task->save();
        return $this->jsonSuccess(['task_id' => $taskId, 'status' => $task->status]);
    }

    /**
     * worker：队列概况（供 GPU 节点的 gpu-health 做积压监控）
     *
     * MySQL 默认不对外开 3306，运维脚本拿不到库，所以由各站自己算好返回。
     * 只暴露计数，不含任何用户数据。
     */
    public function stats()
    {
        $base = AiTask::whereIn('task_type', ['custom_video', 'custom_image']);
        $pending  = (clone $base)->where('status', TaskStatus::PENDING->value)->count();
        $running  = (clone $base)->whereIn('status', [TaskStatus::ACCEPTED->value, TaskStatus::PROCESSING->value])->count();
        $stuck    = (clone $base)
            ->whereIn('status', [TaskStatus::ACCEPTED->value, TaskStatus::PROCESSING->value])
            ->whereNotNull('accepted_at')
            ->where('accepted_at', '<', date('Y-m-d H:i:s', time() - 90 * 60))
            ->count();

        return $this->jsonSuccess([
            'pending'  => $pending,
            'running'  => $running,
            'stuck'    => $stuck,
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * worker：上传成品视频（multipart file=video, task_id）
     */
    public function uploadVideo()
    {
        $taskId = (int) $this->request->post('task_id', 0);
        $file = $this->request->file('video');
        $cover = $this->request->file('cover');

        if ($taskId <= 0 || !$file) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task_id / video 必填');
        }
        $task = AiTask::find($taskId);
        if (!$task || !in_array($task->task_type, ['custom_video', 'custom_image'], true)) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task 不存在');
        }

        $upload = new \app\common\library\Upload($file);
        $upload->setTopic('custom_video');
        $attachment = $upload->upload(null, 0, 0);
        $videoUrl = (string) $attachment['url'];

        $coverUrl = '';
        if ($cover) {
            $upload2 = new \app\common\library\Upload($cover);
            $upload2->setTopic('custom_video');
            $coverUrl = (string) $upload2->upload(null, 0, 0)['url'];
        }

        // 视频文件不计入图片自动压缩（Upload 内部已按类型判断）
        $task->result_type = $task->task_type === 'custom_image' ? 'image_file' : 'video_file';
        // 成品地址必须落库：万一 worker 随后的 /aiTask/complete 失败（任务状态已不是
        // accepted/processing），前端仍能从 video_url 拿到结果，不会一直停在「生成中」
        $task->video_url = $videoUrl;
        $task->cover_url = $coverUrl;
        $task->updated_at = date('Y-m-d H:i:s');
        $task->save();

        return $this->jsonSuccess(['video_url' => $videoUrl, 'cover_url' => $coverUrl]);
    }

    /** 统一返回相对路径（/storage/...）：同源访问正确，且不受 CDN 回源 Host 影响 */
    private function absUrl(string $url): string
    {
        if ($url === '' || str_starts_with($url, 'http')) {
            return $url;
        }
        return '/' . ltrim($url, '/');
    }
}
