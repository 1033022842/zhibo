<?php
declare(strict_types=1);

namespace app\room\service;

use app\common\constants\BizCode;
use app\common\enums\RoomState;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\room\model\RoomStateSnapshot;
use think\facade\Db;
use think\facade\Log;

final class RoomStateMachine
{
    private const MAX_RETRY = 3;

    private const VALID_TRANSITIONS = [
        'offline'           => ['public_ready'],
        'public_ready'      => ['public_live', 'offline'],
        'public_live'       => ['switching', 'degraded'],
        'switching'         => ['privilege_live', 'interaction_live', 'public_live', 'degraded'],
        'privilege_live'    => ['switching', 'degraded'],
        'interaction_live'  => ['switching', 'degraded'],
        'degraded'          => ['switching', 'offline'],
    ];

    public function getSnapshot(int $roomId): ?array
    {
        return RoomStateSnapshot::find($roomId)?->toArray();
    }

    public function ensureSnapshot(int $roomId, RoomState $initialState = RoomState::OFFLINE): array
    {
        $snap = RoomStateSnapshot::find($roomId);
        if ($snap) {
            return $snap->toArray();
        }

        $now = date('Y-m-d H:i:s');
        RoomStateSnapshot::create([
            'room_id'             => $roomId,
            'current_state'       => $initialState->value,
            'current_mode'        => 'public',
            'current_task_id'     => null,
            'privilege_expire_at' => null,
            'version'             => 1,
            'updated_at'          => $now,
        ]);

        $snap = RoomStateSnapshot::find($roomId);
        return $snap->toArray();
    }

    public function transition(int $roomId, RoomState $fromState, RoomState $toState, ?int $taskId = null): array
    {
        $from = $fromState->value;
        $to = $toState->value;

        if (!isset(self::VALID_TRANSITIONS[$from]) || !in_array($to, self::VALID_TRANSITIONS[$from], true)) {
            throw new BusinessException(
                ResultCode::ROOM_STATE_TRANSITION_INVALID,
                "不允许从 {$from} 迁移到 {$to}"
            );
        }

        $lastException = null;
        for ($retry = 0; $retry < self::MAX_RETRY; $retry++) {
            try {
                return $this->tryTransition($roomId, $from, $to, $taskId);
            } catch (BusinessException $e) {
                if ($e->resultCode !== ResultCode::ROOM_STATE_CONFLICT) {
                    throw $e;
                }
                $lastException = $e;
                usleep(50000 * ($retry + 1));
            }
        }

        throw $lastException ?? new BusinessException(ResultCode::ROOM_STATE_CONFLICT);
    }

    private function tryTransition(int $roomId, string $from, string $to, ?int $taskId): array
    {
        $conn = Db::connect('live_mysql');

        $snap = $conn->table('lp_room_state_snapshot')
            ->where('room_id', $roomId)
            ->find();

        if (!$snap) {
            throw new BusinessException(ResultCode::ROOM_NOT_FOUND, "房间 {$roomId} 状态快照不存在");
        }

        if ($snap['current_state'] !== $from) {
            throw new BusinessException(
                ResultCode::ROOM_STATE_TRANSITION_INVALID,
                "期望状态 {$from}，实际状态 {$snap['current_state']}"
            );
        }

        $updateData = [
            'current_state' => $to,
            'current_mode'  => $this->inferMode($snap['current_mode'], $to),
            'version'       => ($snap['version'] ?? 0) + 1,
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        if ($taskId !== null) {
            $updateData['current_task_id'] = $taskId;
        }

        $affected = $conn->table('lp_room_state_snapshot')
            ->where('room_id', $roomId)
            ->where('version', $snap['version'])
            ->update($updateData);

        if ($affected === 0) {
            throw new BusinessException(
                ResultCode::ROOM_STATE_CONFLICT,
                "房间 {$roomId} 版本冲突，version={$snap['version']}"
            );
        }

        $newSnap = $snap;
        $newSnap['current_state'] = $to;
        $newSnap['current_mode'] = $updateData['current_mode'];
        $newSnap['version'] = $updateData['version'];
        if (array_key_exists('current_task_id', $updateData)) {
            $newSnap['current_task_id'] = $updateData['current_task_id'];
        }

        $this->writeEvent($roomId, 'state_transition', [
            'from_state' => $from,
            'to_state'   => $to,
            'task_id'    => $taskId,
            'version'    => $updateData['version'],
        ]);

        Log::info("RoomStateMachine: room {$roomId} {$from} → {$to} (v{$newSnap['version']})");

        return $newSnap;
    }

    public function enterPrivilege(int $roomId, int $taskId, int $durationSec): array
    {
        $from = $this->getCurrentState($roomId);
        $fromValue = $from->value;

        if ($from === RoomState::PRIVILEGE_LIVE) {
            $snap = $this->getSnapshot($roomId);
            $newExpireAt = date('Y-m-d H:i:s', time() + $durationSec);
            $conn = Db::connect('live_mysql');
            $conn->table('lp_room_state_snapshot')
                ->where('room_id', $roomId)
                ->update([
                    'privilege_expire_at' => $newExpireAt,
                    'current_task_id'     => $taskId,
                    'version'             => (int) $snap['version'] + 1,
                    'updated_at'          => date('Y-m-d H:i:s'),
                ]);
            $snap['privilege_expire_at'] = $newExpireAt;
            $snap['current_task_id'] = $taskId;
            return $snap;
        }

        if ($from !== RoomState::PUBLIC_LIVE && $from !== RoomState::INTERACTION_LIVE) {
            throw new BusinessException(
                ResultCode::ROOM_STATE_TRANSITION_INVALID,
                "当前状态 {$fromValue} 不允许进入特权"
            );
        }

        if ($from === RoomState::INTERACTION_LIVE) {
            $this->abortInteractionTask($roomId, $snap['current_task_id'] ?? null);
        }

        $now = date('Y-m-d H:i:s');
        $expireAt = date('Y-m-d H:i:s', time() + $durationSec);

        $this->transition($roomId, $from, RoomState::SWITCHING, $taskId);

        $conn = Db::connect('live_mysql');
        $conn->table('lp_room_state_snapshot')
            ->where('room_id', $roomId)
            ->update([
                'privilege_expire_at' => $expireAt,
                'updated_at'          => $now,
            ]);

        $snap = $this->getSnapshot($roomId);
        $snap['privilege_expire_at'] = $expireAt;

        return $snap;
    }

    public function leavePrivilege(int $roomId): array
    {
        $snap = $this->getSnapshot($roomId);
        if (!$snap) {
            throw new BusinessException(ResultCode::ROOM_NOT_FOUND);
        }

        $currentState = $snap['current_state'];

        if ($currentState === RoomState::SWITCHING->value) {
            return $snap;
        }

        if ($currentState !== RoomState::PRIVILEGE_LIVE->value) {
            throw new BusinessException(
                ResultCode::ROOM_STATE_TRANSITION_INVALID,
                "当前状态 {$currentState} 不是特权状态"
            );
        }

        return $this->transition($roomId, RoomState::PRIVILEGE_LIVE, RoomState::SWITCHING);
    }

    public function enterInteraction(int $roomId, int $taskId): array
    {
        $snap = $this->getSnapshot($roomId);
        if (!$snap) {
            throw new BusinessException(ResultCode::ROOM_NOT_FOUND);
        }

        $currentState = $snap['current_state'];

        if ($currentState === RoomState::PRIVILEGE_LIVE->value) {
            throw new BusinessException(
                ResultCode::ROOM_PRIVILEGE_BLOCKING,
                "特权进行中，互动插播被阻止"
            );
        }

        if ($currentState !== RoomState::PUBLIC_LIVE->value) {
            throw new BusinessException(
                ResultCode::ROOM_STATE_TRANSITION_INVALID,
                "当前状态 {$currentState} 不允许进入互动"
            );
        }

        return $this->transition($roomId, RoomState::PUBLIC_LIVE, RoomState::SWITCHING, $taskId);
    }

    public function leaveInteraction(int $roomId): array
    {
        $snap = $this->getSnapshot($roomId);
        if (!$snap) {
            throw new BusinessException(ResultCode::ROOM_NOT_FOUND);
        }

        $currentState = $snap['current_state'];

        if ($currentState === RoomState::SWITCHING->value) {
            return $snap;
        }

        if ($currentState !== RoomState::INTERACTION_LIVE->value) {
            throw new BusinessException(
                ResultCode::ROOM_STATE_TRANSITION_INVALID,
                "当前状态 {$currentState} 不是互动状态"
            );
        }

        return $this->transition($roomId, RoomState::INTERACTION_LIVE, RoomState::SWITCHING);
    }

    public function confirmSwitchComplete(int $roomId, int $taskId, string $targetMode): array
    {
        $snap = $this->getSnapshot($roomId);
        if (!$snap) {
            throw new BusinessException(ResultCode::ROOM_NOT_FOUND);
        }

        $currentState = $snap['current_state'];

        if ($currentState !== RoomState::SWITCHING->value) {
            throw new BusinessException(
                ResultCode::ROOM_STATE_TRANSITION_INVALID,
                "当前状态 {$currentState} 不是切换中"
            );
        }

        $targetState = $this->modeToState($targetMode);

        return $this->transition($roomId, RoomState::SWITCHING, $targetState, $taskId);
    }

    public function confirmSwitchFailed(int $roomId, int $taskId, string $fallbackMode): array
    {
        $snap = $this->getSnapshot($roomId);
        if (!$snap) {
            throw new BusinessException(ResultCode::ROOM_NOT_FOUND);
        }

        $currentState = $snap['current_state'];

        if ($currentState !== RoomState::SWITCHING->value) {
            return $snap;
        }

        $fallbackState = $this->modeToState($fallbackMode);

        return $this->transition($roomId, RoomState::SWITCHING, RoomState::DEGRADED);
    }

    public function markPublicLive(int $roomId): array
    {
        $snap = $this->getSnapshot($roomId);

        if ($snap && $snap['current_state'] === RoomState::PUBLIC_LIVE->value) {
            return $snap;
        }

        $current = $snap ? RoomState::from($snap['current_state']) : RoomState::OFFLINE;
        $this->ensureSnapshot($roomId, RoomState::OFFLINE);

        if ($current === RoomState::OFFLINE) {
            return $this->transition($roomId, RoomState::OFFLINE, RoomState::PUBLIC_READY);
        }

        if ($current === RoomState::PUBLIC_READY) {
            return $this->transition($roomId, RoomState::PUBLIC_READY, RoomState::PUBLIC_LIVE);
        }

        throw new BusinessException(
            ResultCode::ROOM_STATE_TRANSITION_INVALID,
            "当前状态 {$current->value} 无法标记开播"
        );
    }

    public function markOffline(int $roomId): array
    {
        $snap = $this->getSnapshot($roomId);
        if (!$snap) {
            $this->ensureSnapshot($roomId, RoomState::OFFLINE);
            return $this->getSnapshot($roomId);
        }

        $currentState = $snap['current_state'];
        $allowedFrom = [RoomState::OFFLINE->value, RoomState::PUBLIC_READY->value, RoomState::DEGRADED->value];

        if (in_array($currentState, $allowedFrom, true)) {
            if ($currentState === RoomState::OFFLINE->value) {
                return $snap;
            }
            $from = RoomState::from($currentState);
            return $this->transition($roomId, $from, RoomState::OFFLINE);
        }

        throw new BusinessException(
            ResultCode::ROOM_STATE_TRANSITION_INVALID,
            "当前状态 {$currentState} 不允许下线"
        );
    }

    public function markDegraded(int $roomId): array
    {
        $snap = $this->getSnapshot($roomId);
        if (!$snap) {
            throw new BusinessException(ResultCode::ROOM_NOT_FOUND);
        }

        $currentState = $snap['current_state'];

        if ($currentState === RoomState::DEGRADED->value) {
            return $snap;
        }

        $degradableFrom = [
            RoomState::PUBLIC_LIVE->value,
            RoomState::PRIVILEGE_LIVE->value,
            RoomState::INTERACTION_LIVE->value,
            RoomState::SWITCHING->value,
        ];

        if (!in_array($currentState, $degradableFrom, true)) {
            throw new BusinessException(
                ResultCode::ROOM_STATE_TRANSITION_INVALID,
                "当前状态 {$currentState} 不允许降级"
            );
        }

        $from = RoomState::from($currentState);
        return $this->transition($roomId, $from, RoomState::DEGRADED);
    }

    public function recoverFromDegraded(int $roomId): array
    {
        $snap = $this->getSnapshot($roomId);
        if (!$snap) {
            throw new BusinessException(ResultCode::ROOM_NOT_FOUND);
        }

        if ($snap['current_state'] !== RoomState::DEGRADED->value) {
            throw new BusinessException(
                ResultCode::ROOM_STATE_TRANSITION_INVALID,
                "当前状态 {$snap['current_state']} 不是降级状态"
            );
        }

        return $this->transition($roomId, RoomState::DEGRADED, RoomState::SWITCHING);
    }

    public function canEnterInteraction(int $roomId): bool
    {
        $snap = $this->getSnapshot($roomId);
        if (!$snap) {
            return false;
        }

        return $snap['current_state'] === RoomState::PUBLIC_LIVE->value;
    }

    public function canTriggerPrivilege(int $roomId): bool
    {
        $snap = $this->getSnapshot($roomId);
        if (!$snap) {
            return false;
        }

        return in_array($snap['current_state'], [
            RoomState::PUBLIC_LIVE->value,
            RoomState::INTERACTION_LIVE->value,
            RoomState::PRIVILEGE_LIVE->value,
        ], true);
    }

    public function getCurrentState(int $roomId): RoomState
    {
        $snap = $this->getSnapshot($roomId);
        if (!$snap) {
            return RoomState::OFFLINE;
        }

        return RoomState::from($snap['current_state']);
    }

    private function inferMode(string $currentMode, string $targetState): string
    {
        return match ($targetState) {
            RoomState::PRIVILEGE_LIVE->value   => BizCode::ROOM_MODE_PRIVILEGE,
            RoomState::INTERACTION_LIVE->value => BizCode::ROOM_MODE_INTERACTION,
            RoomState::PUBLIC_LIVE->value,
            RoomState::PUBLIC_READY->value,
            RoomState::SWITCHING->value        => $currentMode,
            RoomState::OFFLINE->value,
            RoomState::DEGRADED->value         => BizCode::ROOM_MODE_PUBLIC,
            default                            => BizCode::ROOM_MODE_PUBLIC,
        };
    }

    private function modeToState(string $mode): RoomState
    {
        return match ($mode) {
            BizCode::ROOM_MODE_PRIVILEGE   => RoomState::PRIVILEGE_LIVE,
            BizCode::ROOM_MODE_INTERACTION => RoomState::INTERACTION_LIVE,
            default                        => RoomState::PUBLIC_LIVE,
        };
    }

    private function abortInteractionTask(int $roomId, ?int $taskId): void
    {
        if (!$taskId) {
            return;
        }

        $conn = Db::connect('live_mysql');
        $conn->table('lp_room_play_task')
            ->where('id', $taskId)
            ->update(['status' => 'skipped']);

        $this->writeEvent($roomId, 'interaction_aborted', [
            'task_id' => $taskId,
            'reason'  => 'privilege_preempted',
        ]);
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
            Log::error("RoomStateMachine event write failed: {$e->getMessage()}");
        }
    }
}
