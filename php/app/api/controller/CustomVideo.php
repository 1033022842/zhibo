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

    /** 生成预设 → H3 提示词（演示期 3 个，后续扩模板库） */
    private const PRESETS = [
        [
            'key'    => 'dance',
            'label'  => 'Dance clip',
            'desc'   => 'She dances gracefully to the music',
            'prompt' => 'The person in the reference image comes to life and dances gracefully with rhythmic body movement, natural micro-expressions, warm cinematic lighting, music video quality.',
        ],
        [
            'key'    => 'wave',
            'label'  => 'Say hi',
            'desc'   => 'She waves and blows you a kiss',
            'prompt' => 'The person in the reference image smiles warmly at the camera, waves her hand and blows a kiss, gentle head tilt, natural micro-expressions, soft cinematic lighting.',
        ],
        [
            'key'    => 'vlog',
            'label'  => 'Daily vlog',
            'desc'   => 'A cozy daily-life moment',
            'prompt' => 'The person in the reference image comes alive in a cozy daily vlog moment, looking around naturally, softly smiling, adjusting her hair, warm ambient home lighting, realistic camera.',
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
        return $this->jsonSuccess([
            'presets' => self::PRESETS,
            'quota'   => ['daily_limit' => self::DAILY_LIMIT],
        ]);
    }

    /**
     * 提交生成任务（VIP 专属，multipart：image + preset + agreed_policy）
     */
    public function submit()
    {
        $userId = $this->getAuthUserId();
        $presetKey = (string) $this->request->post('preset', '');
        $agreed = (int) $this->request->post('agreed_policy', 0);

        if ($agreed !== 1) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Please confirm the upload policy first.');
        }
        $preset = null;
        foreach (self::PRESETS as $p) {
            if ($p['key'] === $presetKey) { $preset = $p; break; }
        }
        if (!$preset) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Please choose a style preset.');
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

        // 每日配额
        $todayCount = AiTask::where('source_type', 'custom_video')
            ->where('source_ref_id', $userId)
            ->whereLike('created_at', date('Y-m-d') . '%')
            ->count();
        if ($todayCount >= self::DAILY_LIMIT) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Daily limit reached (' . self::DAILY_LIMIT . '/day). Try again tomorrow.');
        }

        // 建任务
        $task = new AiTask();
        $task->task_no     = StrHelper::orderNo('CV');
        $task->room_id     = 0;
        $task->task_type   = 'custom_video';
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
            'remain'  => max(0, self::DAILY_LIMIT - $todayCount - 1),
        ]);
    }

    /**
     * 我的任务列表
     */
    public function myList()
    {
        $userId = $this->getAuthUserId();
        $tasks = AiTask::where('source_type', 'custom_video')
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
        $tasks = AiTask::where('task_type', 'custom_video')
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
        if (!$task || $task->task_type !== 'custom_video') {
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
        if (!$task || $task->task_type !== 'custom_video') {
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
