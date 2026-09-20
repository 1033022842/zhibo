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
    /** 每日生成配额（AI 电脑单卡串行，防爆队列） */
    private const DAILY_LIMIT = 3;

    /** 无限配额白名单（测试账号不限量） */
    private const UNLIMITED_USER_IDS = [16];

    /** 生成预设 → H3 时间线导演台结构化提示词（subject_definitions/summary/分镜/音效/配乐） */
    private const PRESETS = [
        [
            'key'   => 'dance',
            'label' => 'Dance clip',
            'desc'  => 'She dances to the beat in a studio',
            'prompt' => "
            Editorial photograph for a Japanese fragrance campaign, cinematic still. A 22-year-old Japanese beauty stands completely nude in three-quarter view in a softly lit night garden, thighs held together, hips turned slightly away from the camera, body language carrying the whole mood, languid decadent air. She wears nothing. A luxurious pale blue and gold wide-sleeve silk robe lies folded on the stones behind her heels, not wrapping her ankles, not on her body. Her perky G-cup breasts with pink nipples are fully exposed and unobstructed. Her lower body is fully nude, smooth pale skin with a faint natural sheen; her thighs stay together and the private region between them is not presented to the camera, hidden in soft shadow along the inner line of her legs, not described further. Only a jeweled hip chain catching the light, covering nothing. Her right hand holds a closed black folding fan beside her right cheek, not covering her body. Her left arm hangs straight at her left side, empty, five fingers relaxed, away from her hips. Exactly two hands, exactly two arms, no extra limbs. She stands with both bare feet planted flat on the stone path, exactly two feet, ten toes visible, weight even. Long wavy hair half-tied in a loose bun with a few stray strands, metallic pendant hair ornaments, luxurious jeweled headpiece, tassel earrings, small sparkling floral sequins near the outer corners of her eyes, soft peach blush, glossy lips, almond eyes, sweet expressive gaze, otherworldly ethereal aura with a subtle yoki mystique. Background of blurred stone lanterns, cherry blossoms, drifting petals. 85mm portrait lens, soft warm Rembrandt side light carving her silhouette, rim light outlining the hair, dreamy bokeh, fine film grain, Kodak Portra 400 warmth. Photorealistic skin texture, delicate pores, golden ratio figure, lomo effect, surreal dreamy quality, halation.
            ",
        ],
        [
            'key'   => 'wave',
            'label' => 'Say hi',
            'desc'  => 'She waves and blows you a kiss',
            'prompt' => "fingering her vagina with her two middle fingers, her fingers are sliding in and out of her vagina,
water is squirting out of her vagina each time her fingers slides out",
        ],
        [
            'key'   => 'vlog',
            'label' => 'Daily vlog',
            'desc'  => 'A cozy morning moment at home',
            'prompt' => "subject_definitions:\n<Subject 1> is the young woman in <Picture 1>, with her facial features, hairstyle and skin tone exactly matching <Picture 1>, a natural figure.\n\nsummary:\n[reference generation] A 10-second cozy lifestyle vlog clip. <Subject 1> from <Picture 1> enjoys a slow morning at home by a sunlit window — stretching, sipping coffee, sharing a soft smile with the camera. The camera is locked off.\n\nretention_analysis:\n<Subject 1> (appears in [Shot 1]): fully_preserved - her face, hairstyle, skin tone and figure identical to <Picture 1> throughout.\n\ndetailed_description:\nA realistic live-action lifestyle clip in a cozy home interior with warm morning sunlight streaming through a window, sheer curtains, plants and soft textures in the background.\n[Shot 1] Locked-off static camera, medium framing, no camera movement.\nFrom 00:00.000 to 00:00.500 pure black. At 00:00.500 the frame fades in on <Subject 1> standing by the window in the sunlight, stretching her arms overhead with a contented sigh.\nFrom 00:00.500 to 00:03.000 she lowers her arms, brushes a strand of hair behind her ear and picks up a warm mug from the windowsill, cradling it in both hands.\nFrom 00:03.000 to 00:06.000 she takes a slow sip, eyes closing briefly in enjoyment, then glances toward the camera and smiles softly as if sharing the peaceful moment with you.\nFrom 00:06.000 to 00:09.500 she leans her shoulder against the window frame, mug in hand, looking out at the light then back to the camera with a relaxed happy expression, chatting silently, her appearance exactly as in <Picture 1>.\nAt 00:09.500 the frame fades to black, fully black by 00:10.000.\n\noverall_soundscape:\nA soft morning ambience: distant birdsong, the faint hum of the city, a gentle clink as the mug is set on the sill, a contented exhale after the sip.\n\nnon_diegetic_music:\nA warm acoustic lo-fi track around 85 BPM with a soft guitar loop, mellow keys and a light shaker, unhurried and cozy, ending on a gentle resolved chord through the fade.",
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
        $unlimited = in_array($this->getAuthUserId(), self::UNLIMITED_USER_IDS, true);
        return $this->jsonSuccess([
            'presets' => self::PRESETS,
            'quota'   => [
                'daily_limit' => $unlimited ? '∞' : self::DAILY_LIMIT,
                'unlimited'   => $unlimited,
            ],
        ]);
    }

    /**
     * 提交生成任务（VIP 专属，multipart：image + preset + agreed_policy）
     */
    public function submit()
    {
        $userId = $this->getAuthUserId();
        $presetKey = (string) $this->request->post('preset', '');
        // type 兼容 image / outfit（前端历史版本用 outfit）
        $typeRaw = (string) $this->request->post('type', 'video');
        $kind = in_array($typeRaw, ['image', 'outfit'], true) ? 'image' : 'video';
        $agreed = (int) $this->request->post('agreed_policy', 0);

        if ($agreed !== 1) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Please confirm the upload policy first.');
        }

        if ($kind === 'image') {
            // 图片编辑：正向词固定在 AI 电脑的工作流文件里（qwenedit_api.json 节点76），
            // 支持可选覆盖（后台/前端传 prompt 时优先）
            $prompt = trim((string) $this->request->post('prompt', ''));
            if (mb_strlen($prompt) > 500) {
                return $this->jsonFail(ResultCode::PARAM_ERROR, 'Instruction is too long (max 500 characters).');
            }
            $preset = ['key' => 'image_edit', 'label' => 'AI Edit', 'prompt' => $prompt];
        } else {
            $preset = null;
            foreach (self::PRESETS as $p) {
                if ($p['key'] === $presetKey) { $preset = $p; break; }
            }
            if (!$preset) {
                return $this->jsonFail(ResultCode::PARAM_ERROR, 'Please choose a style preset.');
            }
        }

        // VIP 校验
        $vip = (new VipService())->status($userId);
        if (empty($vip['is_vip'])) {
            return $this->jsonFail(ResultCode::NO_PERMISSION, 'Custom video is a VIP feature. Please subscribe to VIP first.');
        }

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
            $todayCount = AiTask::whereIn('source_type', ['custom_video', 'custom_image'])
                ->where('source_ref_id', $userId)
                ->whereLike('created_at', date('Y-m-d') . '%')
                ->count();
            if ($todayCount >= self::DAILY_LIMIT) {
                return $this->jsonFail(ResultCode::PARAM_ERROR, 'Daily limit reached (' . self::DAILY_LIMIT . '/day). Try again tomorrow.');
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
            'action'    => $preset['key'],
            'label'     => $preset['label'],
            'prompt'    => $preset['prompt'],
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
            'remain'  => $unlimited ? '∞' : max(0, self::DAILY_LIMIT - $todayCount - 1),
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
                'action'     => (string) ($c['action'] ?? ''),
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
                'action'     => (string) ($c['action'] ?? ''),
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
