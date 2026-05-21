<?php
declare(strict_types=1);

namespace app\room\model;

use think\Model;

final class RoomSwitchTask extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_room_switch_task';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    protected $type = [
        'id' => 'integer',
        'room_id' => 'integer',
        'duration_sec' => 'integer',
    ];
}
