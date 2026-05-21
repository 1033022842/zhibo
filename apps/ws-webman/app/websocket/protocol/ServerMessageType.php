<?php
declare(strict_types=1);

namespace app\websocket\protocol;

final class ServerMessageType
{
    public const ERROR = 'error';
    public const AUTH_OK = 'auth_ok';
    public const HEARTBEAT_ACK = 'heartbeat_ack';
    public const JOINED_ROOM = 'joined_room';
    public const LEFT_ROOM = 'left_room';
    public const ROOM_SNAPSHOT = 'room_snapshot';
    public const CHAT_MESSAGE = 'chat_message';
    public const GIFT_MESSAGE = 'gift_message';
    public const PRIVILEGE_STARTED = 'privilege_started';
    public const PRIVILEGE_ENDED = 'privilege_ended';
    public const STREAM_RELOAD = 'stream_reload';
    public const INTERACTION_READY = 'interaction_ready';
    public const INTERACTION_ENDED = 'interaction_ended';
}
