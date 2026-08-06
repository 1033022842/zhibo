<?php
declare(strict_types=1);

namespace ChannelWorker;

final class Database
{
    private ?\PDO $pdo = null;

    private function createPdo(): \PDO
    {
        $pdo = new \PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                getenv('DB_HOST') ?: '127.0.0.1',
                (int) (getenv('DB_PORT') ?: 3306),
                getenv('DB_NAME') ?: 'zhibo',
                getenv('DB_CHARSET') ?: 'utf8mb4'
            ),
            getenv('DB_USER') ?: 'zhibo',
            getenv('DB_PASSWORD') ?: '12345678',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_TIMEOUT => 5,
            ]
        );
        return $pdo;
    }

    public function pdo(): \PDO
    {
        if ($this->pdo instanceof \PDO) {
            // 检测连接是否仍然有效，无效则重连
            try {
                $this->pdo->query('SELECT 1');
            } catch (\PDOException $e) {
                if (str_contains($e->getMessage(), 'gone away') || str_contains($e->getMessage(), '2006')) {
                    fwrite(STDERR, "[Database] MySQL connection lost, reconnecting...\n");
                    $this->pdo = $this->createPdo();
                } else {
                    throw $e;
                }
            }
            return $this->pdo;
        }

        $this->pdo = $this->createPdo();
        return $this->pdo;
    }

    public function reconnect(): void
    {
        $this->pdo = null;
        $this->pdo();
    }
}
