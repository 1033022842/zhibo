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
        if ($triggerMode === 'privilege' && $triggerDurationSec > 0) {
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
        return is_array($gift) ? $gift : null;
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
