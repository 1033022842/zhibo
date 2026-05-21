<?php
declare(strict_types=1);

namespace app\room\service;

use app\common\constants\BizCode;
use app\common\enums\RoomState;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\room\model\RoomSwitchTask;
use think\facade\Db;
use think\facade\Log;

final class RoomSwitchScheduler
{
    private RoomStateMachine $stateMachine;

    public function __construct(
        ?RoomStateMachine $stateMachine = null
    ) {
        $this->stateMachine = $stateMachine ?? new RoomStateMachine();
    }

    public function schedulePrivilegeSwitch(
        int $roomId,
        string $triggerType,
        ?int $triggerRefId,
        int $durationSec
    ): array {
        if ($durationSec <= 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '特权时长必须大于0');
        }

        if (!$this->stateMachine->canTriggerPrivilege($roomId)) {
            throw new BusinessException(
                ResultCode::ROOM_STATE_TRANSITION_INVALID,
                '房间当前状态不允许触发特权'
            );
        }

        $taskNo = $this->genTaskNo('SW');
        $now = date('Y-m-d H:i:s');

        $snap = $this->stateMachine->getSnapshot($roomId);
        $currentMode = $snap['current_mode'] ?? 'public';

        $task = RoomSwitchTask::create([
            'task_no'       => $taskNo,
            'room_id'       => $roomId,
            'trigger_type'  => $triggerType,
            'trigger_ref_id' => $triggerRefId,
            'from_mode'     => $currentMode,
            'to_mode'       => BizCode::ROOM_MODE_PRIVILEGE,
            'duration_sec'  => $durationSec,
            'status'        => 'pending',
            'created_at'    => $now,
        ]);

        try {
            $this->stateMachine->enterPrivilege($roomId, (int) $task->id, $durationSec);
        } catch (\Throwable $e) {
            $task->status = 'failed';
            $task->save();
            throw $e;
        }

        $now = date('Y-m-d H:i:s');
        $task->status = 'accepted';
        $task->scheduled_at = $now;
        $task->save();

        $this->writeEvent($roomId, 'switch_task_created', [
            'task_id'       => $task->id,
            'task_no'       => $taskNo,
            'trigger_type'  => $triggerType,
            'to_mode'       => BizCode::ROOM_MODE_PRIVILEGE,
            'duration_sec'  => $durationSec,
        ]);

        Log::info("RoomSwitchScheduler: room {$roomId} privilege switch scheduled, task={$taskNo}, duration={$durationSec}s");

        return $task->toArray();
    }

    public function scheduleInteractionSwitch(int $roomId, int $playTaskId): array
    {
        if (!$this->stateMachine->canEnterInteraction($roomId)) {
            throw new BusinessException(
                ResultCode::ROOM_PRIVILEGE_BLOCKING,
                '房间当前状态不允许互动插播'
            );
        }

        $taskNo = $this->genTaskNo('SI');

        $snap = $this->stateMachine->getSnapshot($roomId);
        $currentMode = $snap['current_mode'] ?? 'public';

        $task = RoomSwitchTask::create([
            'task_no'       => $taskNo,
            'room_id'       => $roomId,
            'trigger_type'  => BizCode::TRIGGER_SYSTEM,
            'trigger_ref_id' => $playTaskId,
            'from_mode'     => $currentMode,
            'to_mode'       => BizCode::ROOM_MODE_INTERACTION,
            'duration_sec'  => 0,
            'status'        => 'pending',
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        try {
            $this->stateMachine->enterInteraction($roomId, (int) $task->id);
        } catch (\Throwable $e) {
            $task->status = 'failed';
            $task->save();
            throw $e;
        }

        $now = date('Y-m-d H:i:s');
        $task->status = 'accepted';
        $task->scheduled_at = $now;
        $task->save();

        $this->writeEvent($roomId, 'interaction_switch_scheduled', [
            'task_id'      => $task->id,
            'task_no'      => $taskNo,
            'play_task_id' => $playTaskId,
        ]);

        Log::info("RoomSwitchScheduler: room {$roomId} interaction switch scheduled, task={$taskNo}");

        return $task->toArray();
    }

    public function confirmSwitchComplete(int $roomId, int $taskId, string $targetMode): array
    {
        $task = RoomSwitchTask::find($taskId);
        if (!$task) {
            throw new BusinessException(ResultCode::TASK_NOT_FOUND);
        }

        $now = date('Y-m-d H:i:s');
        $task->status = 'completed';
        $task->started_at = $now;
        $task->save();

        $result = $this->stateMachine->confirmSwitchComplete($roomId, $taskId, $targetMode);

        $this->writeEvent($roomId, 'switch_completed', [
            'task_id'     => $taskId,
            'target_mode' => $targetMode,
        ]);

        return $result;
    }

    public function confirmSwitchFailed(int $roomId, int $taskId, string $reason): array
    {
        $task = RoomSwitchTask::find($taskId);
        if ($task) {
            $task->status = 'failed';
            $task->save();
        }

        $result = $this->stateMachine->confirmSwitchFailed($roomId, $taskId, 'public');

        $this->writeEvent($roomId, 'switch_failed', [
            'task_id' => $taskId,
            'reason'  => $reason,
        ]);

        Log::warning("RoomSwitchScheduler: room {$roomId} switch failed, task={$taskId}, reason={$reason}");

        return $result;
    }

    public function expirePrivilege(int $roomId): array
    {
        $snap = $this->stateMachine->getSnapshot($roomId);
        if (!$snap) {
            return ['expired' => false, 'reason' => 'no_snapshot'];
        }

        if ($snap['current_state'] !== RoomState::PRIVILEGE_LIVE->value) {
            return ['expired' => false, 'reason' => 'not_privilege'];
        }

        $expireAt = $snap['privilege_expire_at'] ?? null;
        if (!$expireAt || strtotime($expireAt) > time()) {
            return ['expired' => false, 'reason' => 'not_expired_yet'];
        }

        $taskId = $snap['current_task_id'] ?? null;

        $this->stateMachine->leavePrivilege($roomId);

        $now = date('Y-m-d H:i:s');
        $conn = Db::connect('live_mysql');
        $conn->table('lp_room_state_snapshot')
            ->where('room_id', $roomId)
            ->update([
                'privilege_expire_at' => null,
                'updated_at'          => $now,
            ]);

        if ($taskId) {
            $conn->table('lp_room_switch_task')
                ->where('id', $taskId)
                ->update([
                    'status'   => 'expired',
                    'ended_at' => $now,
                ]);
        }

        $this->writeEvent($roomId, 'privilege_expired', [
            'task_id' => $taskId,
        ]);

        Log::info("RoomSwitchScheduler: room {$roomId} privilege expired, switching back to public");

        return ['expired' => true, 'task_id' => $taskId];
    }

    public function expireInteraction(int $roomId): array
    {
        $snap = $this->stateMachine->getSnapshot($roomId);
        if (!$snap) {
            return ['expired' => false, 'reason' => 'no_snapshot'];
        }

        if ($snap['current_state'] !== RoomState::INTERACTION_LIVE->value) {
            return ['expired' => false, 'reason' => 'not_interaction'];
        }

        $taskId = $snap['current_task_id'] ?? null;

        $this->stateMachine->leaveInteraction($roomId);

        if ($taskId) {
            $conn = Db::connect('live_mysql');
            $conn->table('lp_room_switch_task')
                ->where('id', $taskId)
                ->update(['status' => 'expired', 'ended_at' => date('Y-m-d H:i:s')]);
        }

        $this->writeEvent($roomId, 'interaction_ended', [
            'task_id' => $taskId,
        ]);

        Log::info("RoomSwitchScheduler: room {$roomId} interaction ended, switching back to public");

        return ['expired' => true, 'task_id' => $taskId];
    }

    public function checkExpiredPrivileges(): array
    {
        $now = date('Y-m-d H:i:s');
        $expiredTasks = Db::connect('live_mysql')
            ->table('lp_room_switch_task')
            ->where('status', 'accepted')
            ->where('to_mode', BizCode::ROOM_MODE_PRIVILEGE)
            ->whereNotNull('scheduled_at')
            ->select()
            ->toArray();

        $result = [];
        foreach ($expiredTasks as $task) {
            $expireAt = date('Y-m-d H:i:s', strtotime($task['scheduled_at']) + (int) $task['duration_sec']);

            if ($expireAt <= $now) {
                try {
                    $expResult = $this->expirePrivilege((int) $task['room_id']);
                    $result[] = array_merge(['task_id' => $task['id']], $expResult);
                } catch (\Throwable $e) {
                    $result[] = [
                        'task_id' => $task['id'],
                        'expired' => false,
                        'error'   => $e->getMessage(),
                    ];
                }
            }
        }

        return $result;
    }

    public function getActiveTask(int $roomId): ?array
    {
        $task = RoomSwitchTask::where('room_id', $roomId)
            ->whereIn('status', ['accepted'])
            ->order('id', 'desc')
            ->find();

        return $task ? $task->toArray() : null;
    }

    public function getPendingTasks(int $roomId): array
    {
        return RoomSwitchTask::where('room_id', $roomId)
            ->whereIn('status', ['pending', 'accepted'])
            ->order('id', 'asc')
            ->select()
            ->toArray();
    }

    private function genTaskNo(string $prefix): string
    {
        return $prefix . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function writeEvent(int $roomId, string $eventType, array $payload): void
    {
        try {
            Db::connect('live_mysql')
                ->table('lp_room_event_log')
                ->insert([
                    'room_id'      => $roomId,
                    'event_type'   => $eventType,
                    'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
        } catch (\Throwable $e) {
            Log::error("RoomSwitchScheduler event write failed: {$e->getMessage()}");
        }
    }
}
