<?php
declare(strict_types=1);

namespace app\service;

/**
 * 会员「在线状态」实时登记。
 *
 * 由 WebSocket 长连接驱动，直接写 lp_user_profile.last_active_at
 *（后台会员列表的「在线/离线」就是按这一列判定的，所以无需 Redis、也无需后台改逻辑）：
 *   - 连接鉴权成功 / 心跳 → 写入当前时间（在线）
 *   - 该用户最后一个连接断开 → 立即置 NULL（离线）
 *
 * 注意：MySQL 时区是 UTC，PHP 时区是 Asia/Shanghai，所以这里统一用 PHP 的
 * date() 写字符串，与 ThinkPHP 端 UserService::touchActive() 完全一致。
 */
final class PresenceService
{
    /** 同一连接内写库的最小间隔（秒）：心跳每次都写库没必要 */
    private const WRITE_GAP = 20;

    /** @var array<int, int> 连接 id => 上次写库时间戳 */
    private static array $lastWriteAt = [];

    public function online(int $userId, int $connectionId = 0): void
    {
        if ($userId <= 0) {
            return;
        }

        if ($connectionId > 0) {
            $now = time();
            if (($now - (self::$lastWriteAt[$connectionId] ?? 0)) < self::WRITE_GAP) {
                return;
            }
            self::$lastWriteAt[$connectionId] = $now;
        }

        $this->execute(
            'UPDATE lp_user_profile SET last_active_at = ? WHERE user_id = ?',
            [date('Y-m-d H:i:s'), $userId]
        );
    }

    public function offline(int $userId, int $connectionId = 0): void
    {
        if ($userId <= 0) {
            return;
        }

        unset(self::$lastWriteAt[$connectionId]);

        $this->execute('UPDATE lp_user_profile SET last_active_at = NULL WHERE user_id = ?', [$userId]);
    }

    private function execute(string $sql, array $params): void
    {
        try {
            $statement = $this->pdo()->prepare($sql);
            $statement->execute($params);
        } catch (\Throwable) {
            // 在线状态属于尽力而为，失败不影响聊天/礼物主流程
        }
    }

    private function pdo(): \PDO
    {
        static $pdo = null;

        if ($pdo instanceof \PDO) {
            try {
                $pdo->query('SELECT 1');
                return $pdo;
            } catch (\PDOException) {
                $pdo = null;
            }
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
