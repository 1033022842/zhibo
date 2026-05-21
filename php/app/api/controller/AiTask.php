<?php
declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use app\ai\service\AiTaskService;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use think\App;

final class AiTask extends BaseController
{
    protected array $middleware = [
        \app\ai\middleware\AiAuth::class => ['except' => ['streamEndByRoom']],
    ];

    private AiTaskService $aiTaskService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->aiTaskService = new AiTaskService();
    }

    public function pull()
    {
        $count = min(10, max(1, (int) ($this->request->get('count') ?? 10)));
        $workerId = $this->request->aiWorkerId ?? 'unknown';
        $tasks = $this->aiTaskService->pullFromStream($workerId, $count);
        return $this->jsonSuccess($tasks);
    }

    public function accept()
    {
        $taskId = (int) $this->request->post('task_id', 0);
        $workerId = $this->request->aiWorkerId ?? '';

        if ($taskId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task_id 不能为空');
        }

        $result = $this->aiTaskService->acceptTask($taskId, $workerId);
        return $this->jsonSuccess($result, '已接单');
    }

    public function progress()
    {
        $taskId = (int) $this->request->post('task_id', 0);
        $percent = min(100, max(0, (int) $this->request->post('percent', 0)));
        $message = $this->request->post('message', '');

        if ($taskId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task_id 不能为空');
        }

        $result = $this->aiTaskService->reportProgress($taskId, $percent, $message);
        return $this->jsonSuccess($result);
    }

    public function complete()
    {
        $taskId = (int) $this->request->post('task_id', 0);
        $videoUrl = $this->request->post('video_url', '');
        $durationSec = (int) $this->request->post('duration_sec', 0);
        $coverUrl = $this->request->post('cover_url', '');

        if ($taskId <= 0 || $videoUrl === '' || $durationSec <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task_id / video_url / duration_sec 均为必填');
        }

        $result = $this->aiTaskService->completeTask($taskId, $videoUrl, $durationSec, $coverUrl);
        return $this->jsonSuccess($result, '任务完成，已排入互动播单');
    }

    public function fail()
    {
        $taskId = (int) $this->request->post('task_id', 0);
        $errorMsg = $this->request->post('error_msg', '');

        if ($taskId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task_id 不能为空');
        }

        $result = $this->aiTaskService->failTask($taskId, $errorMsg);
        return $this->jsonSuccess($result, '已标记失败');
    }

    public function streamToken()
    {
        $roomId = (int) $this->request->get('room_id', 0);

        if ($roomId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'room_id 不能为空');
        }

        $result = $this->aiTaskService->getStreamToken($roomId);
        return $this->jsonSuccess($result);
    }

    public function streamEnd()
    {
        $taskId = (int) $this->request->post('task_id', 0);
        $durationSec = (int) $this->request->post('duration_sec', 0);
        $reason = $this->request->post('reason', 'ai_notify');

        if ($taskId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task_id 不能为空');
        }

        $result = $this->aiTaskService->streamEnd($taskId, $durationSec, $reason);
        return $this->jsonSuccess($result, '推流已结束，房间已切回待机模式');
    }

    public function streamEndByRoom()
    {
        $secret = $this->request->post('secret', $this->request->get('secret', ''));
        $expectedSecret = config('ai.srs_secret', 'srs-callback-secret-2026');

        if ($secret !== '') {
            if (!hash_equals($expectedSecret, $secret)) {
                return $this->jsonFail(ResultCode::NO_PERMISSION, 'secret 无效');
            }
        }

        $stream = $this->request->post('stream', $this->request->post('Stream', ''));
        $action = $this->request->post('action', $this->request->post('Action', ''));

        if ($action && $action !== 'on_unpublish') {
            return $this->jsonSuccess(['ignored' => true, 'action' => $action]);
        }

        $roomId = $this->parseRoomIdFromStream($stream);
        if ($roomId <= 0) {
            return $this->jsonSuccess(['ignored' => true, 'reason' => 'stream_not_parsed', 'stream' => $stream]);
        }

        $result = $this->aiTaskService->handleStreamEndByRoom($roomId, 'srs_unpublish');
        return $this->jsonSuccess($result, $result['ended'] ? '推流已结束' : '无需处理');
    }

    private function parseRoomIdFromStream(string $stream): int
    {
        if (preg_match('#^room/(\d+)/(\w+)#', $stream, $m)) {
            return (int) $m[1];
        }
        if (preg_match('#/(\d+)/#', $stream, $m)) {
            return (int) $m[1];
        }

        return 0;
    }
}
