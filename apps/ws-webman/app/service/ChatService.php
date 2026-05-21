<?php
declare(strict_types=1);

namespace app\service;

use support\Redis;

final class ChatService
{
    /** @var array<string, int> */
    private static array $speakerSpeakAt = [];

    public function create(array $session, array $message): array
    {
        $roomId = (int) ($message['room_id'] ?? 0);
        $content = trim((string) ($message['content'] ?? ''));
        $joinedRoomId = (int) ($session['room_id'] ?? 0);
        $userId = (int) ($session['user_id'] ?? 0);

        if ($roomId <= 0) {
            return ['ok' => false, 'code' => 'WS0002', 'msg' => 'room_id 非法'];
        }

        if ($joinedRoomId !== $roomId) {
            return ['ok' => false, 'code' => 'WS2001', 'msg' => '请先加入房间'];
        }

        if ($content === '') {
            return ['ok' => false, 'code' => 'WS2002', 'msg' => '消息不能为空'];
        }

        if (mb_strlen($content) > (int) config('chat.message_max_length', 500)) {
            return ['ok' => false, 'code' => 'WS2003', 'msg' => '消息长度超过限制'];
        }

        if (!$this->allowSpeak($session)) {
            return ['ok' => false, 'code' => 'WS2004', 'msg' => '发送过于频繁'];
        }

        $status = $this->containsSensitiveWord($content) ? 0 : 1;
        $messageId = $this->insertMessage($roomId, $userId, $content, $status);
        $createdAt = time();

        $this->appendStream([
            'message_id' => (string) $messageId,
            'room_id' => (string) $roomId,
            'user_id' => (string) $userId,
            'nickname' => (string) ($session['nickname'] ?? ''),
            'content' => $content,
            'status' => (string) $status,
            'created_at' => (string) $createdAt,
        ]);

        $this->xaddAiTask($roomId, $userId, (string) ($session['nickname'] ?? ''), $content);

        return [
            'ok' => true,
            'status' => $status,
            'message_id' => $messageId,
            'broadcast' => [
                'room_id' => $roomId,
                'message_id' => $messageId,
                'user' => [
                    'id' => $userId,
                    'nickname' => (string) ($session['nickname'] ?? ''),
                ],
                'content' => $content,
                'created_at' => $createdAt,
            ],
        ];
    }

    private function allowSpeak(array $session): bool
    {
        $now = time();
        $limit = max(1, (int) config('chat.rate_limit_per_second', 1));
        $speakerKey = $this->speakerKey($session);
        $lastSpeakAt = self::$speakerSpeakAt[$speakerKey] ?? 0;
        if (($now - $lastSpeakAt) < $limit) {
            return false;
        }

        self::$speakerSpeakAt[$speakerKey] = $now;
        return true;
    }

    private function speakerKey(array $session): string
    {
        $userId = (int) ($session['user_id'] ?? 0);
        if ($userId > 0) {
            return 'user:' . $userId;
        }

        $userNo = trim((string) ($session['user_no'] ?? ''));
        if ($userNo !== '') {
            return 'session:' . $userNo;
        }

        return 'guest:' . md5((string) ($session['nickname'] ?? 'guest'));
    }

    private function containsSensitiveWord(string $content): bool
    {
        foreach ((array) config('chat.sensitive_words', []) as $word) {
            $word = trim((string) $word);
            if ($word !== '' && mb_stripos($content, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    private function insertMessage(int $roomId, int $userId, string $content, int $status): int
    {
        try {
            return $this->insertMessageOnce($roomId, $userId, $content, $status);
        } catch (\PDOException $exception) {
            if (!$this->isConnectionLost($exception)) {
                throw $exception;
            }

            return $this->insertMessageOnce($roomId, $userId, $content, $status, true);
        }
    }

    private function appendStream(array $payload): void
    {
        try {
            $arguments = [(string) config('chat.stream_key', 'stream:danmu:ingest'), '*'];
            foreach ($payload as $field => $value) {
                $arguments[] = (string) $field;
                $arguments[] = (string) $value;
            }

            Redis::rawCommand('XADD', ...$arguments);
        } catch (\Throwable) {
            // Redis stream is best-effort in local/dev environments.
        }
    }

    private function xaddAiTask(int $roomId, int $userId, string $nickname, string $content): void
    {
        try {
            $streamKey = (string) config('live_ws.ai_task_stream_key', 'stream:ai:tasks');
            $personaId = (int) config('live_ws.ai_default_persona_id', 0);

            $args = [$streamKey, '*'];
            $args[] = 'room_id';
            $args[] = (string) $roomId;
            $args[] = 'user_id';
            $args[] = (string) $userId;
            $args[] = 'nickname';
            $args[] = $nickname;
            $args[] = 'content';
            $args[] = $content;
            $args[] = 'persona_id';
            $args[] = (string) $personaId;
            $args[] = 'created_ts';
            $args[] = (string) time();

            Redis::rawCommand('XADD', ...$args);
        } catch (\Throwable) {
        }
    }

    private function insertMessageOnce(int $roomId, int $userId, string $content, int $status, bool $forceReconnect = false): int
    {
        $pdo = $this->pdo($forceReconnect);
        $statement = $pdo->prepare(
            'INSERT INTO lp_chat_message (room_id, user_id, message_type, content, status) VALUES (:room_id, :user_id, :message_type, :content, :status)'
        );
        $statement->execute([
            'room_id' => $roomId,
            'user_id' => $userId,
            'message_type' => 'text',
            'content' => $content,
            'status' => $status,
        ]);

        return (int) $pdo->lastInsertId();
    }

    private function isConnectionLost(\PDOException $exception): bool
    {
        $message = strtolower($exception->getMessage());
        return str_contains($message, 'server has gone away')
            || str_contains($message, 'lost connection')
            || str_contains($message, 'error while sending')
            || str_contains($message, 'is dead or not enabled')
            || str_contains($message, 'no connection to the server');
    }

    private function pdo(bool $forceReconnect = false): \PDO
    {
        static $pdo = null;
        if ($forceReconnect) {
            $pdo = null;
        }

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
