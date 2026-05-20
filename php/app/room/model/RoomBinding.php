<?php
declare(strict_types=1);

namespace app\room\model;

use think\Model;

final class RoomBinding extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_room_binding';
    protected $pk   = 'id';
    protected $autoWriteTimestamp = false;

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function group()
    {
        return $this->belongsTo(RoomGroup::class, 'room_group_id');
    }
}
