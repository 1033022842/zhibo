<?php
declare(strict_types=1);

namespace app\websocket;

use app\service\ChatService;
use app\service\JwtAuthService;
use app\service\RoomRealtimeService;
use app\service\WsSessionManager;
use app\websocket\protocol\ClientMessageType;
use app\websocket\protocol\MessageBuilder;
use app\websocket\protocol\ServerMessageType;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;

final class LiveRoomSocket
{
    private ChatService $chatService;
    private JwtAuthService $jwtAuthService;
    private RoomRealtimeService $roomRealtimeService;

    public function __construct()
    {
        $this->chatService = new ChatService();
        $this->jwtAuthService = new JwtAuthService();
        $this->roomRealtimeService = new RoomRealtimeService();
    }

    public function onWorkerStart(): void
    {
        Timer::add((int) config('live_ws.heartbeat_check_interval', 5), function () {
            WsSessionManager::closeExpired(
                (int) config('live_ws.heartbeat_ttl', 30),
                (int) config('live_ws.auth_timeout', 10)
            );
        });

        Timer::add((int) config('live_ws.aggregate_flush_interval', 60), function () {
            try {
                $this->roomRealtimeService->flushMinuteStats();
            } catch (\Throwable) {
                // Ignore aggregation errors in local/dev environments.
            }
        });
    }

    public function onConnect(TcpConnection $connection): void
    {
        WsSessionManager::boot($connection);
    }

    public function onMessage(TcpConnection $connection, $payload): void
    {
        $message = json_decode((string) $payload, true);
        if (!is_array($message)) {
            $connection->send(MessageBuilder::error('', '消息格式错误'));
            return;
        }

        $type = (string) ($message['type'] ?? '');
        $traceId = (string) ($message['trace_id'] ?? '');
        if (!in_array($type, ClientMessageType::all(), true)) {
            $connection->send(MessageBuilder::error($traceId, '未知消息类型'));
            return;
        }

        if ($type === ClientMessageType::AUTH) {
            $this->handleAuth($connection, $message, $traceId);
            return;
        }

        if (!WsSessionManager::isAuthenticated($connection)) {
            $connection->send(MessageBuilder::error($traceId, '未鉴权', 'WS0401'));
            return;
        }

        match ($type) {
            ClientMessageType::HEARTBEAT => $this->handleHeartbeat($connection, $traceId),
            ClientMessageType::JOIN_ROOM => $this->handleJoinRoom($connection, $message, $traceId),
            ClientMessageType::LEAVE_ROOM => $this->handleLeaveRoom($connection, $traceId),
            ClientMessageType::SEND_CHAT => $this->handleSendChat($connection, $message, $traceId),
            default => $connection->send(MessageBuilder::error($traceId, '暂不支持的消息类型')),
        };
    }

    public function onClose(TcpConnection $connection): void
    {
        $leftRoomId = WsSessionManager::leaveRoom($connection);
        if ($leftRoomId > 0) {
            $snapshot = $this->roomRealtimeService->leaveRoom($leftRoomId);
            WsSessionManager::broadcastRoom($leftRoomId, MessageBuilder::encode(
                ServerMessageType::ROOM_SNAPSHOT,
                '',
                $snapshot
            ));
        }

        WsSessionManager::destroy($connection);
    }

    private function handleAuth(TcpConnection $connection, array $message, string $traceId): void
    {
        $token = (string) ($message['token'] ?? '');
        $payload = $this->jwtAuthService->verifyAccessToken($token);
        if (!$payload || empty($payload['user_id'])) {
            $connection->send(MessageBuilder::error($traceId, 'Token 无效', 'WS0401'));
            $connection->close();
            return;
        }

        WsSessionManager::authenticate($connection, $payload);
        $connection->send(MessageBuilder::encode(ServerMessageType::AUTH_OK, $traceId, [
            'user_id' => $payload['user_id'],
            'user_no' => $payload['user_no'],
            'nickname' => $payload['nickname'],
        ]));
    }

    private function handleHeartbeat(TcpConnection $connection, string $traceId): void
    {
        WsSessionManager::touch($connection);
        $connection->send(MessageBuilder::encode(ServerMessageType::HEARTBEAT_ACK, $traceId, [
            'server_ts' => time(),
        ]));
    }

    private function handleJoinRoom(TcpConnection $connection, array $message, string $traceId): void
    {
        $roomId = (int) ($message['room_id'] ?? 0);
        if ($roomId <= 0) {
            $connection->send(MessageBuilder::error($traceId, 'room_id 非法', 'WS0002'));
            return;
        }

        $currentRoomId = (int) (WsSessionManager::session($connection)['room_id'] ?? 0);
        if ($currentRoomId === $roomId) {
            WsSessionManager::touch($connection);
            $snapshot = $this->roomRealtimeService->snapshot($roomId);
            $connection->send(MessageBuilder::encode(ServerMessageType::JOINED_ROOM, $traceId, [
                'room_id' => $roomId,
            ]));
            WsSessionManager::broadcastRoom($roomId, MessageBuilder::encode(ServerMessageType::ROOM_SNAPSHOT, $traceId, $snapshot));
            return;
        }

        if ($currentRoomId > 0) {
            WsSessionManager::leaveRoom($connection);
            $leftSnapshot = $this->roomRealtimeService->leaveRoom($currentRoomId);
            WsSessionManager::broadcastRoom($currentRoomId, MessageBuilder::encode(
                ServerMessageType::ROOM_SNAPSHOT,
                $traceId,
                $leftSnapshot
            ));
        }

        WsSessionManager::joinRoom($connection, $roomId);
        $snapshot = $this->roomRealtimeService->joinRoom($roomId);

        $connection->send(MessageBuilder::encode(ServerMessageType::JOINED_ROOM, $traceId, [
            'room_id' => $roomId,
        ]));
        WsSessionManager::broadcastRoom($roomId, MessageBuilder::encode(ServerMessageType::ROOM_SNAPSHOT, $traceId, $snapshot));
    }

    private function handleLeaveRoom(TcpConnection $connection, string $traceId): void
    {
        $roomId = WsSessionManager::leaveRoom($connection);
        $connection->send(MessageBuilder::encode(ServerMessageType::LEFT_ROOM, $traceId, [
            'room_id' => $roomId,
        ]));

        if ($roomId > 0) {
            $snapshot = $this->roomRealtimeService->leaveRoom($roomId);
            WsSessionManager::broadcastRoom($roomId, MessageBuilder::encode(
                ServerMessageType::ROOM_SNAPSHOT,
                $traceId,
                $snapshot
            ));
        }
    }

    private function handleSendChat(TcpConnection $connection, array $message, string $traceId): void
    {
        try {
            $result = $this->chatService->create(WsSessionManager::session($connection), $message);
        } catch (\Throwable) {
            $connection->send(MessageBuilder::error($traceId, '弹幕发送失败', 'WS5000'));
            return;
        }

        if (!($result['ok'] ?? false)) {
            $connection->send(MessageBuilder::error($traceId, (string) $result['msg'], (string) $result['code']));
            return;
        }

        if ((int) ($result['status'] ?? 0) !== 1) {
            $connection->send(MessageBuilder::error($traceId, '消息包含敏感词，已拦截', 'WS2005', [
                'message_id' => $result['message_id'] ?? 0,
                'status' => $result['status'] ?? 0,
            ]));
            return;
        }

        $payload = (array) ($result['broadcast'] ?? []);
        WsSessionManager::broadcastRoom(
            (int) ($payload['room_id'] ?? 0),
            MessageBuilder::encode(ServerMessageType::CHAT_MESSAGE, $traceId, $payload)
        );
    }
}
