<?php
declare(strict_types=1);

namespace app\common\enums;

enum RoomState: string
{
    case OFFLINE      = 'offline';
    case PUBLIC_READY = 'public_ready';
    case PUBLIC_LIVE  = 'public_live';
    case SWITCHING    = 'switching';
    case INTERACTION_LIVE = 'interaction_live';
    case PRIVILEGE_LIVE   = 'privilege_live';
    case DEGRADED     = 'degraded';
}
