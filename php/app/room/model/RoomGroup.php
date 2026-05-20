<?php
declare(strict_types=1);

namespace app\room\model;

use think\Model;

final class RoomGroup extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_room_group';
    protected $pk   = 'id';
    protected $autoWriteTimestamp = false;
}
