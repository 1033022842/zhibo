<?php
declare(strict_types=1);

namespace app\room\service;

use think\facade\Cache;
use think\facade\Log;

final class ChannelWorkerGateway
{
    private const STREAM_KEY = 'stream:room:switch';

    public function pushSwitch(int $roomId, string $commandType, array $params): string
    {
        $messageId = $this->publishStream([
            'room_id'      => $roomId,
            'command_type' => $commandType,
            'params'       => $params,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        Log::info("ChannelWorkerGateway: room {$roomId} command={$commandType}, msgId={$messageId}");

        return $messageId;
    }

    public function pushBroadcast(int $roomId, string $eventType, array $data): void
    {
        $payload = json_encode([
            'event_type' => $eventType,
            'room_id'    => $roomId,
            'data'       => $data,
            'ts'         => time(),
        ], JSON_UNESCAPED_UNICODE);

        try {
            $redis = Cache::store('redis')->handler();
            $redis->rPush('list:room:broadcast', $payload);
        } catch (\Throwable $e) {
            Log::error("ChannelWorkerGateway broadcast failed: {$e->getMessage()}");
        }
    }

    public function pushSwitchToPrivilege(int $roomId, int $taskId, int $durationSec): string
    {
        return $this->pushSwitch($roomId, 'switch_to_privilege', [
            'task_id'      => $taskId,
            'duration_sec' => $durationSec,
        ]);
    }

    public function pushSwitchToPublic(int $roomId, int $taskId): string
    {
        return $this->pushSwitch($roomId, 'switch_to_public', [
            'task_id' => $taskId,
        ]);
    }

    public function pushSwitchToInteraction(int $roomId, int $taskId, int $playTaskId): string
    {
        return $this->pushSwitch($roomId, 'switch_to_interaction', [
            'task_id'      => $taskId,
            'play_task_id' => $playTaskId,
        ]);
    }

    public function pushConfirm(int $roomId, string $commandType, int $taskId, bool $success, string $reason = ''): string
    {
        return $this->pushSwitch($roomId, $success ? 'confirm_complete' : 'confirm_failed', [
            'task_id'       => $taskId,
            'command_type'  => $commandType,
            'reason'        => $reason,
        ]);
    }

    private function publishStream(array $data): string
    {
        $redis = Cache::store('redis')->handler();
        $messageId = $redis->xAdd(self::STREAM_KEY, '*', $data);
        $redis->xTrim(self::STREAM_KEY, 1000, true);
        return $messageId;
    }

    public function readPendingConfirmations(string $group, string $consumer, int $count = 10): array
    {
        $redis = Cache::store('redis')->handler();

        try {
            $redis->xGroup('CREATE', self::STREAM_KEY, $group, '0', true);
        } catch (\Throwable) {
        }

        $messages = $redis->xReadGroup($group, $consumer, [self::STREAM_KEY => '>'], $count, 0);

        if (empty($messages) || !isset($messages[self::STREAM_KEY])) {
            return [];
        }

        $result = [];
        foreach ($messages[self::STREAM_KEY] as $id => $fields) {
            $result[] = ['id' => $id, 'fields' => $fields];
            $redis->xAck(self::STREAM_KEY, $group, [$id]);
        }

        return $result;
    }
}
