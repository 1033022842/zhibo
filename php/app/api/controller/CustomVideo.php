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
 * VIP 定制角色视频（AI 电脑生成，演示管线：LivePortrait）
 */
final class CustomVideo extends BaseController
{
    /** 每日生成配额（AI 电脑单卡串行，防爆队列） */
    private const DAILY_LIMIT = 3;

    /** 动作模板 → AI 电脑驱动片段文件名（worker 侧 D:\custom_video\driving\ 下同名 mp4） */
    private const ACTIONS = [
        ['key' => 'wave',    'label' => 'Wave & smile',     'driving' => 'wave.mp4'],
        ['key' => 'kiss',    'label' => 'Blow a kiss',      'driving' => 'kiss.mp4'],
        ['key' => 'dance',   'label' => 'Gentle dance',     'driving' => 'dance.mp4'],
        ['key' => 'wink',    'label' => 'Wink & tease',     'driving' => 'wink.mp4'],
        ['key' => 'hello',   'label' => 'Say hello',        'driving' => 'hello.mp4'],
    ];

    protected array $middleware = [
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
        $db = Db::connect('live_mysql');

        $platform = $db->table('lp_persona')
            ->field('id, name, cover_url')
            ->where('user_id', 0)
            ->whereIn('status', [1, 2])
            ->where('cover_url', '<>', '')
            ->order('id')
            ->select()->toArray();

        $mine = [];
        if ($userId > 0) {
            $mine = $db->table('lp_persona')
                ->field('id, name, cover_url')
                ->where('user_id', $userId)
                ->where('cover_url', '<>', '')
                ->order('id', 'desc')
                ->limit(50)
                ->select()->toArray();
        }

        return $this->jsonSuccess([
            'platform' => array_map(fn($p) => [
                'id'    => (int) $p['id'],
                'name'  => $p['name'],
                'cover' => $this->absUrl((string) $p['cover_url']),
                'mine'  => false,
            ], $platform),
            'mine' => array_map(fn($p) => [
                'id'    => (int) $p['id'],
                'name'  => $p['name'],
                'cover' => $this->absUrl((string) $p['cover_url']),
                'mine'  => true,
            ], $mine),
            'actions' => self::ACTIONS,
            'quota'   => ['daily_limit' => self::DAILY_LIMIT],
        ]);
    }

    /**
     * 提交生成任务（VIP 专属）
     */
    public function submit()
    {
        $userId = $this->getAuthUserId();
        $personaId = (int) $this->request->post('persona_id', 0);
        $actionKey = (string) $this->request->post('action', '');

        if ($personaId <= 0 || $actionKey === '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请选择角色和动作');
        }
        $action = null;
        foreach (self::ACTIONS as $a) {
            if ($a['key'] === $actionKey) { $action = $a; break; }
        }
        if (!$action) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '不支持的动作');
        }

        // VIP 校验
        $vip = (new VipService())->status($userId);
        if (empty($vip['is_vip'])) {
            return $this->jsonFail(ResultCode::NO_PERMISSION, 'Custom video is a VIP feature. Please subscribe to VIP first.');
        }

        // 角色归属校验：平台人设(user_id=0) 或本人角色
        $db = Db::connect('live_mysql');
        $persona = $db->table('lp_persona')
            ->field('id, name, user_id, cover_url')
            ->where('id', $personaId)
            ->whereIn('user_id', [0, $userId])
            ->find();
        if (!$persona || (string) $persona['cover_url'] === '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '角色不存在或缺少封面');
        }

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
        $task->persona_id  = $personaId;
        $task->content     = json_encode([
            'action'      => $action['key'],
            'driving'     => $action['driving'],
            'persona_name' => $persona['name'],
            'persona_cover' => $persona['cover_url'],
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
                'persona_id' => (int) $t['persona_id'],
                'persona_name' => (string) ($c['persona_name'] ?? ''),
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
                'persona_id' => (int) $t['persona_id'],
                'persona_name' => (string) ($c['persona_name'] ?? ''),
                'persona_cover' => $this->absUrl((string) ($c['persona_cover'] ?? '')),
                'action'     => (string) ($c['action'] ?? ''),
                'driving'    => (string) ($c['driving'] ?? ''),
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

    /** 相对路径补当前域名（worker 回传的是 /storage/... 相对地址） */
    private function absUrl(string $url): string
    {
        if ($url === '' || str_starts_with($url, 'http')) {
            return $url;
        }
        return rtrim((string) $this->request->domain(), '/') . '/' . ltrim($url, '/');
    }
}
