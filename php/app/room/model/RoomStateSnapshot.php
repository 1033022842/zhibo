<?php
declare(strict_types=1);

namespace app\room\model;

use think\Model;

final class RoomStateSnapshot extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_room_state_snapshot';
    protected $pk = 'room_id';
    protected $autoWriteTimestamp = false;

    protected $type = [
        'room_id' => 'integer',
        'version' => 'integer',
    ];
}
