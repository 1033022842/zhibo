<?php
declare(strict_types=1);

namespace app\service;

use support\Redis;

final class RoomRealtimeService
{
    public function joinRoom(int $roomId): array
    {
        try {
            Redis::sAdd($this->touchedRoomsKey(), (string) $roomId);
            Redis::incr($this->onlineKey($roomId));
        } catch (\Throwable) {
            // Ignore Redis errors in local/dev environments.
        }

        return $this->snapshot($roomId);
    }

    public function leaveRoom(int $roomId): array
    {
        try {
            Redis::sAdd($this->touchedRoomsKey(), (string) $roomId);
            $onlineCount = (int) Redis::decr($this->onlineKey($roomId));
            if ($onlineCount < 0) {
                Redis::set($this->onlineKey($roomId), 0);
            }
        } catch (\Throwable) {
            // Ignore Redis errors in local/dev environments.
        }

        return $this->snapshot($roomId);
    }

    public function snapshot(int $roomId): array
    {
        $onlineCount = 0;
        $likeCount = 0;

        try {
            $onlineCount = max(0, (int) Redis::get($this->onlineKey($roomId)));
            $likeCount = max(0, (int) Redis::get($this->likeKey($roomId)));
        } catch (\Throwable) {
            // Ignore Redis errors in local/dev environments.
        }

        return [
            'room_id' => $roomId,
            'state' => 'public_live',
            'online_count' => $onlineCount,
            'like_count' => $likeCount,
            'current_mode' => 'public',
            'privilege_expire_at' => 0,
        ];
    }

    public function flushMinuteStats(): void
    {
        $roomIds = [];
        try {
            $roomIds = array_map('intval', Redis::sMembers($this->touchedRoomsKey()));
        } catch (\Throwable) {
            $roomIds = [];
        }

        if (empty($roomIds)) {
            return;
        }

        $minuteAt = date('Y-m-d H:i:00');
        $pdo = $this->pdo();
        $statement = $pdo->prepare(
            'INSERT INTO lp_room_online_minute (room_id, minute_at, online_count, like_count, gift_amount)
             VALUES (:room_id, :minute_at, :online_count, :like_count, :gift_amount)
             ON DUPLICATE KEY UPDATE online_count = VALUES(online_count), like_count = VALUES(like_count), gift_amount = VALUES(gift_amount)'
        );

        foreach ($roomIds as $roomId) {
            $snapshot = $this->snapshot((int) $roomId);
            $statement->execute([
                'room_id' => (int) $roomId,
                'minute_at' => $minuteAt,
                'online_count' => (int) $snapshot['online_count'],
                'like_count' => (int) $snapshot['like_count'],
                'gift_amount' => 0,
            ]);

            if ((int) $snapshot['online_count'] <= 0) {
                try {
                    Redis::sRem($this->touchedRoomsKey(), (string) $roomId);
                } catch (\Throwable) {
                    // Ignore Redis errors in local/dev environments.
                }
            }
        }
    }

    private function onlineKey(int $roomId): string
    {
        return 'room:online:' . $roomId;
    }

    private function likeKey(int $roomId): string
    {
        return 'room:like:' . $roomId;
    }

    private function touchedRoomsKey(): string
    {
        return 'room:online:touched';
    }

    private function pdo(): \PDO
    {
        static $pdo = null;
        if ($pdo instanceof \PDO) {
            return $pdo;
        }

        $host = (string) config('database.host', '127.0.0.1');
        $port = (int) config('database.port', 3306);
        $database = (string) config('database.database', 'live_platform');
        $charset = (string) config('database.charset', 'utf8mb4');
        $username = (string) config('database.username', 'root');
        $password = (string) config('database.password', 'root');

        $pdo = new \PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset={$charset}",
            $username,
            $password,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );

        return $pdo;
    }
}
