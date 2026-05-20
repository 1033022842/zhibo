<?php
declare(strict_types=1);

namespace app\common\enums;

enum RoomMode: string
{
    case PUBLIC      = 'public';
    case INTERACTION = 'interaction';
    case PRIVILEGE   = 'privilege';
}
