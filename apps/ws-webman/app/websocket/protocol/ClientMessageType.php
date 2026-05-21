<?php
declare(strict_types=1);

namespace app\websocket\protocol;

final class ClientMessageType
{
    public const AUTH = 'auth';
    public const HEARTBEAT = 'heartbeat';
    public const JOIN_ROOM = 'join_room';
    public const LEAVE_ROOM = 'leave_room';
    public const SEND_CHAT = 'send_chat';
    public const SEND_GIFT = 'send_gift';

    public static function all(): array
    {
        return [
            self::AUTH,
            self::HEARTBEAT,
            self::JOIN_ROOM,
            self::LEAVE_ROOM,
            self::SEND_CHAT,
            self::SEND_GIFT,
        ];
    }
}
