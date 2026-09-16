<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\BaseController;
use app\ai\service\AiTaskService;
use app\common\web\ResultCode;
use think\facade\Db;

/**
 * AI 女友端：上传图片换脸（固定视频）
 *
 * 流程：
 *   1. 用户从后台配置的模板视频中选择一条（GET /api/live/faceSwapTemplates）
 *   2. 上传人脸图片并提交（POST /api/live/faceSwapCreate）→ 创建 lp_ai_task(task_type=face_swap) 任务
 *   3. AI 电脑轮询 /api/v1/ai/tasks/pull-face-swap 领取任务，处理完回调 /api/v1/ai/tasks/complete
 *   4. 前端轮询任务状态拿换脸结果（GET /api/live/faceSwapTask?task_no=xxx）
 */
final class FaceSwap extends BaseController
{
    /** 允许的人脸图片后缀 */
    private const IMAGE_SUFFIXES = ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'gif'];

    protected array $middleware = [
        \app\live\middleware\Auth::class => ['only' => ['create', 'tasks', 'task']],
    ];

    private function table()
    {
        return Db::connect('live_mysql');
    }

    /**
     * 模板视频列表（公开）
     */
    public function templates()
    {
        $rows = $this->table()->table('lp_face_swap_template')
            ->where('status', 1)
            ->field('id, title, video_url, cover_url, duration_sec, description')
            ->order('weigh', 'desc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id'           => (int) $row['id'],
                'title'        => (string) $row['title'],
                'video_url'    => $this->absoluteUrl((string) $row['video_url']),
                'cover_url'    => $this->absoluteUrl((string) $row['cover_url']),
                'duration_sec' => (int) $row['duration_sec'],
                'description'  => (string) $row['description'],
            ];
        }

        return $this->jsonSuccess(['list' => $list]);
    }

    /**
     * 提交换脸任务（上传人脸图片 + 选择模板视频）
     */
    public function create()
    {
        $userId = $this->getAuthUserId();
        if ($userId <= 0) {
            return $this->jsonFail(ResultCode::ACCESS_TOKEN_INVALID, '请先登录后再提交');
        }

        $templateId = (int) $this->request->post('template_id/d', 0);
        if ($templateId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请选择模板视频');
        }

        $template = $this->table()->table('lp_face_swap_template')
            ->where('id', $templateId)
            ->where('status', 1)
            ->find();

        if (!$template) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, '模板视频不存在或已下架');
        }

        $file = $this->request->file('file');
        if (!$file) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请上传人脸图片');
        }

        $suffix = strtolower($file->getOriginalExtension());
        if (!in_array($suffix, self::IMAGE_SUFFIXES, true)) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '人脸图片仅支持 jpg / png / webp / bmp / gif');
        }

        try {
            $upload = new \app\common\library\Upload($file);
            $upload->setTopic('faceswap');
            $attachment = $upload->upload(null, 0, $userId);
        } catch (\Throwable $e) {
            return $this->jsonFail(ResultCode::FAIL, '图片上传失败：' . $e->getMessage());
        }

        $imageUrl = trim((string) ($attachment['url'] ?? ''));
        if ($imageUrl === '') {
            return $this->jsonFail(ResultCode::FAIL, '图片上传失败');
        }

        try {
            $task = (new AiTaskService())->createFaceSwapTask(
                $userId,
                (int) $template['id'],
                $imageUrl,
                (string) $template['video_url'],
                (string) $template['title']
            );
        } catch (\Throwable $e) {
            return $this->jsonFail(ResultCode::SERVER_ERROR, '任务创建失败：' . $e->getMessage());
        }

        return $this->jsonSuccess([
            'task_id'     => $task['task_id'],
            'task_no'     => $task['task_no'],
            'status'      => $task['status'],
            'status_text' => '排队中',
            'template_id' => (int) $template['id'],
            'image_url'   => $this->absoluteUrl($imageUrl),
        ], '任务已提交，正在换脸');
    }

    /**
     * 我的换脸记录（倒序）
     */
    public function tasks()
    {
        $userId = $this->getAuthUserId();
        $limit = max(1, (int) config('ai.face_swap.list_limit', 30));

        $rows = $this->faceSwapQuery($userId)
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $row) {
            $list[] = $this->formatTask($row);
        }

        return $this->jsonSuccess(['list' => $list]);
    }

    /**
     * 单个换脸任务状态（前端轮询用，task_no 或 id 二选一）
     */
    public function task()
    {
        $userId = $this->getAuthUserId();
        $taskNo = trim((string) $this->request->get('task_no', ''));
        $taskId = (int) $this->request->get('id/d', 0);

        if ($taskNo === '' && $taskId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task_no 或 id 不能为空');
        }

        $query = $this->faceSwapQuery($userId);
        if ($taskNo !== '') {
            $query->where('task_no', $taskNo);
        } else {
            $query->where('id', $taskId);
        }

        $row = $query->find();
        if (!$row) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, '任务不存在');
        }

        return $this->jsonSuccess($this->formatTask($row));
    }

    /**
     * @return \think\db\Query
     */
    private function faceSwapQuery(int $userId)
    {
        return $this->table()->table('lp_ai_task')
            ->where('task_type', AiTaskService::TASK_TYPE_FACE_SWAP)
            ->where('user_id', $userId)
            ->field('id, task_no, status, content, face_image_url, template_video_url, video_url, cover_url, duration_sec, created_at, finished_at');
    }

    private function formatTask(array $row): array
    {
        $status = (string) $row['status'];
        $statusText = [
            'pending'    => '排队中',
            'accepted'   => '处理中',
            'processing' => '处理中',
            'completed'  => '已完成',
            'failed'     => '换脸失败',
            'expired'    => '已超时',
        ][$status] ?? $status;

        return [
            'id'                 => (int) $row['id'],
            'task_no'            => (string) $row['task_no'],
            'status'             => $status,
            'status_text'        => $statusText,
            'finished'           => in_array($status, ['completed', 'failed', 'expired'], true),
            'title'              => (string) ($row['content'] ?? ''),
            'face_image_url'     => $this->absoluteUrl((string) ($row['face_image_url'] ?? '')),
            'template_video_url' => $this->absoluteUrl((string) ($row['template_video_url'] ?? '')),
            'video_url'          => $this->absoluteUrl((string) ($row['video_url'] ?? '')),
            'cover_url'          => $this->absoluteUrl((string) ($row['cover_url'] ?? '')),
            'duration_sec'       => (int) ($row['duration_sec'] ?? 0),
            'created_at'         => (string) ($row['created_at'] ?? ''),
            'finished_at'        => (string) ($row['finished_at'] ?? ''),
        ];
    }

    /**
     * 相对路径补全为完整 URL，方便跨端口前端直接加载
     */
    private function absoluteUrl(string $url): string
    {
        if ($url === '' || str_starts_with($url, 'http')) {
            return $url;
        }

        return rtrim($this->request->domain(), '/') . '/' . ltrim($url, '/');
    }
}
