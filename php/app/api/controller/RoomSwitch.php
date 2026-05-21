<?php
declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use app\common\constants\BizCode;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\room\service\ChannelWorkerGateway;
use app\room\service\RoomSwitchScheduler;
use think\App;
use think\facade\Log;

final class RoomSwitch extends BaseController
{
    private RoomSwitchScheduler $scheduler;
    private ChannelWorkerGateway $gateway;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->scheduler = new RoomSwitchScheduler();
        $this->gateway = new ChannelWorkerGateway();
    }

    public function triggerPrivilege()
    {
        $roomId = (int) $this->request->post('room_id', 0);
        $giftId = (int) $this->request->post('gift_id', 0);
        $durationSec = (int) $this->request->post('duration_sec', 0);
        $giftName = (string) $this->request->post('gift_name', '');

        if ($roomId <= 0 || $durationSec <= 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'room_id 和 duration_sec 为必填项');
        }

        $task = $this->scheduler->schedulePrivilegeSwitch(
            $roomId,
            BizCode::TRIGGER_GIFT,
            $giftId > 0 ? $giftId : null,
            $durationSec
        );

        $this->gateway->pushSwitchToPrivilege($roomId, (int) $task['id'], $durationSec);

        $this->gateway->pushBroadcast($roomId, 'privilege_started', [
            'task_id'       => (int) $task['id'],
            'duration_sec'  => $durationSec,
            'expire_at'     => date('Y-m-d H:i:s', time() + $durationSec),
            'gift_id'       => $giftId,
            'gift_name'     => $giftName,
        ]);

        Log::info("RoomSwitch API: room {$roomId} privilege triggered, task={$task['task_no']}, duration={$durationSec}s");

        return $this->jsonSuccess([
            'task_id'      => $task['id'],
            'task_no'      => $task['task_no'],
            'duration_sec' => $durationSec,
        ]);
    }

    public function confirm()
    {
        $roomId = (int) $this->request->post('room_id', 0);
        $taskId = (int) $this->request->post('task_id', 0);
        $targetMode = (string) $this->request->post('target_mode', '');

        if ($roomId <= 0 || $taskId <= 0 || $targetMode === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'room_id、task_id、target_mode 为必填项');
        }

        $result = $this->scheduler->confirmSwitchComplete($roomId, $taskId, $targetMode);

        $this->gateway->pushBroadcast($roomId, 'stream_reload', [
            'task_id'     => $taskId,
            'target_mode' => $result['current_mode'] ?? 'public',
        ]);

        return $this->jsonSuccess([
            'room_id'     => $roomId,
            'state'       => $result['current_state'] ?? '',
            'mode'        => $result['current_mode'] ?? '',
        ]);
    }

    public function fail()
    {
        $roomId = (int) $this->request->post('room_id', 0);
        $taskId = (int) $this->request->post('task_id', 0);
        $reason = (string) $this->request->post('reason', 'unknown');

        if ($roomId <= 0 || $taskId <= 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'room_id、task_id 为必填项');
        }

        $result = $this->scheduler->confirmSwitchFailed($roomId, $taskId, $reason);

        return $this->jsonSuccess([
            'room_id' => $roomId,
            'state'   => $result['current_state'] ?? '',
        ]);
    }

    public function expireCheck()
    {
        $results = $this->scheduler->checkExpiredPrivileges();

        foreach ($results as $r) {
            if (($r['expired'] ?? false) && isset($r['task_id'])) {
                $this->gateway->pushSwitchToPublic((int) ($r['room_id'] ?? 0), (int) $r['task_id']);
                $this->gateway->pushBroadcast((int) ($r['room_id'] ?? 0), 'privilege_ended', [
                    'task_id' => $r['task_id'],
                ]);
            }
        }

        return $this->jsonSuccess(['expired_count' => count($results)]);
    }

    public function status()
    {
        $roomId = (int) $this->request->get('room_id', 0);

        if ($roomId <= 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'room_id 为必填项');
        }

        $snap = $this->scheduler->getActiveTask($roomId);

        return $this->jsonSuccess([
            'room_id'      => $roomId,
            'active_task'  => $snap,
        ]);
    }
}
