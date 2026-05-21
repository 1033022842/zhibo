<?php
declare(strict_types=1);

namespace app\room\model;

use think\Model;

final class RoomPlayTask extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_room_play_task';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    protected $type = [
        'id' => 'integer',
        'room_id' => 'integer',
        'priority' => 'integer',
    ];
}
