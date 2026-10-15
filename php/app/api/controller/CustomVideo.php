<?php
declare(strict_types=1);

namespace app\api\controller;

use app\ai\model\AiTask;
use app\BaseController;
use app\common\enums\TaskStatus;
use app\common\exception\BusinessException;
use app\common\util\StrHelper;
use app\common\web\ResultCode;
use app\live\service\VipService;
use think\App;
use think\facade\Db;
use think\facade\Log;

/**
 * VIP 定制角色视频（AI 电脑 ComfyUI MiniMax-H3 生成）
 */
final class CustomVideo extends BaseController
{
    /** 每日生成配额（兜底；实际以套餐 image_daily_limit 为准） */
    private const DAILY_LIMIT = 2;

    /** 无限配额白名单（测试账号不限量） */
    private const UNLIMITED_USER_IDS = [16];

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
        \app\ai\middleware\AiAuth::class => ['only' => ['pending', 'accept', 'uploadVideo']],
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

        $limit = (int) ($ent['image_daily_limit'] ?? 0);
        $used  = $userId > 0 ? $this->todayUsed($userId) : 0;

        return $this->jsonSuccess([
            'styles' => self::STYLES,
            // Outfit 模式的服装卡片 = 商店商品（owned=0 的前端提示去购买）
            'image_presets' => $this->outfitPresets($userId),
            'quota'   => [
                'daily_limit' => $unlimited ? '∞' : $limit,
                'used'        => $used,
                // remaining < 0 表示不限量（与后端 submit 的 remain 口径一致）
                'remaining'   => $unlimited ? -1 : max(0, $limit - $used),
                'level'       => (string) ($ent['level_name'] ?? 'Free'),
                'is_vip'      => (bool) ($ent['is_vip'] ?? false),
                'unlimited'   => $unlimited,
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

        // 会员层级校验：定制视频/图片生成仅会员可用，额度按套餐 image_daily_limit（当前各档统一 2/天）
        $ent = (new VipService())->entitlements($userId);
        if (empty($ent['is_vip']) || empty($ent['custom_video'])) {
            return $this->jsonFail(ResultCode::NO_PERMISSION, 'Custom video is a Premium feature. Please subscribe to unlock it.');
        }
        $dailyLimit = max(1, (int) ($ent['image_daily_limit'] ?? self::DAILY_LIMIT));

        // 图片（≤10MB，jpg/png/webp；经 Upload 库自动压缩）
        $file = $this->request->file('image');
        if (!$file) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Please upload an image.');
        }
        if ((int) $file->getSize() > 10 * 1024 * 1024) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Image must be under 10MB.');
        }
        $ext = strtolower((string) $file->getOriginalExtension());
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Only jpg / png / webp images are supported.');
        }
        $upload = new \app\common\library\Upload($file);
        $upload->setTopic('custom_video');
        $attachment = $upload->upload(null, 0, $userId);
        $imageUrl = (string) $attachment['url'];

        // 每日配额（白名单用户不限量）
        $unlimited = in_array($userId, self::UNLIMITED_USER_IDS, true);
        $todayCount = 0;
        if (!$unlimited) {
            $todayCount = $this->todayUsed($userId);
            if ($todayCount >= $dailyLimit) {
                return $this->jsonFail(ResultCode::PARAM_ERROR, 'Daily limit reached (' . $dailyLimit . '/day). Try again tomorrow or upgrade to Premium Plus.');
            }
        }

        // 建任务
        $task = new AiTask();
        $task->task_no     = StrHelper::orderNo($kind === 'image' ? 'CI' : 'CV');
        $task->room_id     = 0;
        $task->task_type   = $kind === 'image' ? 'custom_image' : 'custom_video';
        $task->priority    = 5;
        $task->source_type = 'custom_video';
        $task->source_ref_id = $userId;
        $task->persona_id  = 0;
        $task->content     = json_encode([
            'style'     => $style['key'],
            'label'     => $style['label'],
            'prompt'    => (string) ($style['prompt'] ?? ''),
            'image_url' => $imageUrl,
        ], JSON_UNESCAPED_UNICODE);
        $task->callback_mode = 'none';
        $task->status      = TaskStatus::PENDING->value;
        $task->created_at  = date('Y-m-d H:i:s');
        $task->updated_at  = date('Y-m-d H:i:s');
        $task->save();

        return $this->jsonSuccess([
            'task_id' => (int) $task->id,
            'task_no' => $task->task_no,
            'status'  => $task->status,
            // remain：提交后当日剩余次数；-1 表示不限量（前端按此显示）
            'remain'  => $unlimited ? -1 : max(0, $dailyLimit - $todayCount - 1),
        ]);
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
            return [
                'task_id'    => (int) $t['id'],
                'task_no'    => $t['task_no'],
                'label'      => (string) ($c['label'] ?? ''),
                'kind'       => $t['task_type'] === 'custom_image' ? 'image' : 'video',
                'style'      => (string) ($c['style'] ?? ($c['action'] ?? '')),
                'status'     => $t['status'],
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
        $task->result_type = 'video_file';
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
