<?php
declare(strict_types=1);

namespace app\service;

final class GiftService
{
    public function create(array $session, array $payload): array
    {
        $roomId = (int) ($payload['room_id'] ?? 0);
        $giftId = (int) ($payload['gift_id'] ?? 0);
        $quantity = max(1, min(99, (int) ($payload['quantity'] ?? 1)));

        if ($roomId <= 0 || $giftId <= 0) {
            return ['ok' => false, 'code' => 'WS3001', 'msg' => '礼物参数错误'];
        }

        $sessionRoomId = (int) ($session['room_id'] ?? 0);
        if ($sessionRoomId > 0 && $sessionRoomId !== $roomId) {
            return ['ok' => false, 'code' => 'WS3002', 'msg' => '请先进入目标房间'];
        }

        $gift = $this->findGift($giftId);
        if ($gift === null) {
            return ['ok' => false, 'code' => 'WS3003', 'msg' => '礼物不存在或已下架'];
        }

        $giftName = (string) $gift['name'];
        $totalPrice = round(((float) $gift['price_diamond']) * $quantity, 2);
        $orderNo = 'G' . date('YmdHis') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $orderId = $this->insertOrder(
            $orderNo,
            (int) ($session['user_id'] ?? 0),
            $roomId,
            $giftId,
            $quantity,
            $totalPrice
        );

        $triggerMode = (string) ($gift['trigger_mode'] ?? 'none');
        $triggerDurationSec = (int) ($gift['trigger_duration_sec'] ?? 0);

        $switchTask = null;
        if ($triggerMode === 'keyword') {
            $this->pushKeywordCommand($roomId, $giftId);
        } elseif ($triggerMode === 'privilege' && $triggerDurationSec > 0) {
            $switchTask = $this->requestPrivilegeSwitch($roomId, $giftId, $triggerDurationSec, $giftName);
        }

        return [
            'ok' => true,
            'order_id' => $orderId,
            'order_no' => $orderNo,
            'broadcast' => [
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'room_id' => $roomId,
                'gift' => [
                    'gift_id' => (int) $gift['id'],
                    'name' => (string) $gift['name'],
                    'price' => (float) $gift['price_diamond'],
                    'trigger_mode' => $triggerMode,
                    'trigger_duration_sec' => $triggerDurationSec,
                    'effect_code' => (string) ($gift['effect_code'] ?? ''),
                    'effect_video_url' => (string) ($gift['effect_video_url'] ?? ''),
                ],
                'quantity' => $quantity,
                'total_price' => $totalPrice,
                'user' => [
                    'user_id' => (int) ($session['user_id'] ?? 0),
                    'user_no' => (string) ($session['user_no'] ?? ''),
                    'nickname' => (string) ($session['nickname'] ?? '观众'),
                ],
                'switch_task' => $switchTask,
            ],
        ];
    }

    private function requestPrivilegeSwitch(int $roomId, int $giftId, int $durationSec, string $giftName): ?array
    {
        try {
            $apiBase = (string) config('live_ws.thinkphp_api_base', 'http://127.0.0.1:7090');
            $url = rtrim($apiBase, '/') . '/api/v1/rooms/switch/privilege';

            $body = json_encode([
                'room_id'      => $roomId,
                'gift_id'      => $giftId,
                'duration_sec' => $durationSec,
                'gift_name'    => $giftName,
            ], JSON_UNESCAPED_UNICODE);

            $context = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
                    'content' => $body,
                    'timeout' => 5,
                ],
            ]);

            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);
            if (!is_array($data) || ($data['code'] ?? '') !== '00000') {
                return null;
            }

            return $data['data'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function findGift(int $giftId): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT id, name, price_diamond, trigger_mode, trigger_duration_sec, effect_code
             FROM lp_gift
             WHERE id = :id AND status = 1
             LIMIT 1'
        );
        $statement->execute(['id' => $giftId]);
        $gift = $statement->fetch();
        if (!is_array($gift)) {
            return null;
        }

        // 查找礼物特效视频
        $effectCode = trim((string) ($gift['effect_code'] ?? ''));
        if ($effectCode !== '') {
            $videoUrl = $this->findEffectVideoUrl($effectCode);
            if ($videoUrl !== null) {
                $gift['effect_video_url'] = $videoUrl;
            }
        }

        return $gift;
    }

    private function findEffectVideoUrl(string $effectCode): ?string
    {
        try {
            $stmt = $this->pdo()->prepare(
                'SELECT file_url FROM lp_media_asset
                 WHERE asset_code = :code AND asset_type = \'video\' AND status = 1
                 LIMIT 1'
            );
            $stmt->execute(['code' => $effectCode]);
            $row = $stmt->fetch();
            if (!$row || empty($row['file_url'])) {
                return null;
            }

            $fileUrl = (string) $row['file_url'];
            // 如果已是完整URL直接返回
            if (preg_match('/^https?:\/\//i', $fileUrl)) {
                return $fileUrl;
            }

            // 返回相对路径，前端通过 Vite proxy 或同域访问
            $normalizedPath = '/' . ltrim(str_replace('\\', '/', $fileUrl), '/');
            return $normalizedPath;
        } catch (\Throwable) {
            return null;
        }
    }

    private function insertOrder(string $orderNo, int $userId, int $roomId, int $giftId, int $quantity, float $totalPrice): int
    {
        try {
            return $this->insertOrderOnce($orderNo, $userId, $roomId, $giftId, $quantity, $totalPrice);
        } catch (\PDOException $exception) {
            if (!$this->isConnectionLost($exception)) {
                throw $exception;
            }

            return $this->insertOrderOnce($orderNo, $userId, $roomId, $giftId, $quantity, $totalPrice, true);
        }
    }

    private function insertOrderOnce(string $orderNo, int $userId, int $roomId, int $giftId, int $quantity, float $totalPrice, bool $forceReconnect = false): int
    {
        $statement = $this->pdo($forceReconnect)->prepare(
            'INSERT INTO lp_gift_order (order_no, user_id, room_id, gift_id, quantity, total_price, status)
             VALUES (:order_no, :user_id, :room_id, :gift_id, :quantity, :total_price, :status)'
        );
        $statement->execute([
            'order_no' => $orderNo,
            'user_id' => $userId,
            'room_id' => $roomId,
            'gift_id' => $giftId,
            'quantity' => $quantity,
            'total_price' => $totalPrice,
            'status' => 1,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    private function pushKeywordCommand(int $roomId, int $giftId): void
    {
        try {
            // 查询礼物对应的关键词
            $stmt = $this->pdo()->prepare(
                'SELECT keyword FROM lp_gift_keyword WHERE gift_id = :gid ORDER BY priority LIMIT 1'
            );
            $stmt->execute(['gid' => $giftId]);
            $row = $stmt->fetch();
            if (!$row) {
                $this->logKeyword('roomId=' . $roomId . ' giftId=' . $giftId . ' NO_KEYWORD_FOUND');
                return;
            }

            $keyword = (string) $row['keyword'];
            if ($keyword === '') {
                $this->logKeyword('roomId=' . $roomId . ' giftId=' . $giftId . ' EMPTY_KEYWORD');
                return;
            }

            // 写入 Redis List（兼容 Redis 3.x，不支持 Stream）
            $redis = $this->redis();
            $payload = json_encode([
                'room_id' => $roomId,
                'command_type' => 'keyword',
                'params' => ['keyword' => $keyword],
                'created_at' => date('Y-m-d H:i:s'),
            ], JSON_UNESCAPED_UNICODE);

            $len = $redis->rpush('list:keyword:room:' . $roomId, $payload);
            $this->logKeyword('OK roomId=' . $roomId . ' keyword=' . $keyword . ' rPush=' . $len);
        } catch (\Throwable $e) {
            // Redis 连接断开则重试一次
            $this->logKeyword('RETRY roomId=' . $roomId . ' giftId=' . $giftId . ' ' . $e->getMessage());
            try {
                $redis2 = $this->redis(true);
                $payload = json_encode([
                    'room_id' => $roomId,
                    'command_type' => 'keyword',
                    'params' => ['keyword' => $keyword],
                    'created_at' => date('Y-m-d H:i:s'),
                ], JSON_UNESCAPED_UNICODE);
                $len = $redis2->rpush('list:keyword:room:' . $roomId, $payload);
                $this->logKeyword('OK roomId=' . $roomId . ' keyword=' . $keyword . ' rPush=' . $len . ' (retried)');
            } catch (\Throwable $e2) {
                $this->logKeyword('ERROR roomId=' . $roomId . ' giftId=' . $giftId . ' retry also failed: ' . $e2->getMessage());
            }
        }
    }

    private function logKeyword(string $msg): void
    {
        $line = date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL;
        @file_put_contents(runtime_path('logs') . '/gift_push.log', $line, FILE_APPEND);
    }

    private function redis(bool $forceReconnect = false): \Predis\Client
    {
        static $redis = null;
        if ($forceReconnect) {
            $redis = null;
        }
        if ($redis instanceof \Predis\Client) {
            return $redis;
        }

        $redis = new \Predis\Client([
            'scheme'   => 'tcp',
            'host'     => (string) config('redis.default.host', '127.0.0.1'),
            'port'     => (int) config('redis.default.port', 6379),
            'password' => config('redis.default.password') ?: null,
            'database' => (int) config('redis.default.database', 0),
        ]);

        return $redis;
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

        // 验证连接是否存活，如果断开则重建
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
