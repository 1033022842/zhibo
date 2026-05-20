<?php
declare(strict_types=1);

namespace ChannelWorker;

final class Database
{
    private ?\PDO $pdo = null;

    public function pdo(): \PDO
    {
        if ($this->pdo instanceof \PDO) {
            return $this->pdo;
        }

        $this->pdo = new \PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                getenv('DB_HOST') ?: '127.0.0.1',
                (int) (getenv('DB_PORT') ?: 3306),
                getenv('DB_NAME') ?: 'live_platform',
                getenv('DB_CHARSET') ?: 'utf8mb4'
            ),
            getenv('DB_USER') ?: 'root',
            getenv('DB_PASSWORD') ?: 'root',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );

        return $this->pdo;
    }
}
