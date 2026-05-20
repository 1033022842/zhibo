<?php
declare(strict_types=1);

namespace app\service;

use Workerman\Connection\TcpConnection;

final class WsSessionManager
{
    /** @var array<int, TcpConnection> */
    private static array $connections = [];

    /** @var array<int, array<string, mixed>> */
    private static array $sessions = [];

    /** @var array<int, array<int, true>> */
    private static array $rooms = [];

    public static function boot(TcpConnection $connection): void
    {
        self::$connections[$connection->id] = $connection;
        self::$sessions[$connection->id] = [
            'authenticated' => false,
            'user_id' => 0,
            'user_no' => '',
            'nickname' => '',
            'room_id' => 0,
            'connected_at' => time(),
            'last_heartbeat' => time(),
        ];
    }

    public static function destroy(TcpConnection $connection): void
    {
        self::leaveRoom($connection);
        unset(self::$sessions[$connection->id], self::$connections[$connection->id]);
    }

    public static function authenticate(TcpConnection $connection, array $payload): void
    {
        self::$sessions[$connection->id]['authenticated'] = true;
        self::$sessions[$connection->id]['user_id'] = (int) ($payload['user_id'] ?? 0);
        self::$sessions[$connection->id]['user_no'] = (string) ($payload['user_no'] ?? '');
        self::$sessions[$connection->id]['nickname'] = (string) ($payload['nickname'] ?? '');
        self::touch($connection);
    }

    public static function touch(TcpConnection $connection): void
    {
        if (isset(self::$sessions[$connection->id])) {
            self::$sessions[$connection->id]['last_heartbeat'] = time();
        }
    }

    public static function isAuthenticated(TcpConnection $connection): bool
    {
        return (bool) (self::$sessions[$connection->id]['authenticated'] ?? false);
    }

    public static function session(TcpConnection $connection): array
    {
        return self::$sessions[$connection->id] ?? [];
    }

    public static function joinRoom(TcpConnection $connection, int $roomId): void
    {
        self::leaveRoom($connection);
        self::$sessions[$connection->id]['room_id'] = $roomId;
        self::$rooms[$roomId][$connection->id] = true;
        self::touch($connection);
    }

    public static function leaveRoom(TcpConnection $connection): int
    {
        $roomId = (int) (self::$sessions[$connection->id]['room_id'] ?? 0);
        if ($roomId <= 0) {
            return 0;
        }

        unset(self::$rooms[$roomId][$connection->id]);
        if (empty(self::$rooms[$roomId])) {
            unset(self::$rooms[$roomId]);
        }

        self::$sessions[$connection->id]['room_id'] = 0;
        return $roomId;
    }

    public static function broadcastRoom(int $roomId, string $message): void
    {
        foreach (array_keys(self::$rooms[$roomId] ?? []) as $connectionId) {
            if (isset(self::$connections[$connectionId])) {
                self::$connections[$connectionId]->send($message);
            }
        }
    }

    public static function roomConnectionCount(int $roomId): int
    {
        return count(self::$rooms[$roomId] ?? []);
    }

    public static function closeExpired(int $heartbeatTtl, int $authTimeout): void
    {
        $now = time();
        foreach (self::$connections as $connectionId => $connection) {
            $session = self::$sessions[$connectionId] ?? null;
            if (!$session) {
                continue;
            }

            if (!$session['authenticated'] && ($now - (int) $session['connected_at']) > $authTimeout) {
                $connection->close();
                continue;
            }

            if (($now - (int) $session['last_heartbeat']) > $heartbeatTtl) {
                $connection->close();
            }
        }
    }
}
