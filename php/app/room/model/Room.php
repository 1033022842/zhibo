<?php
declare(strict_types=1);

namespace app\room\model;

use think\Model;

final class Room extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_room';
    protected $pk   = 'id';
    protected $autoWriteTimestamp = false;

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    public function binding()
    {
        return $this->hasOne(RoomBinding::class, 'room_id');
    }

    public function tags()
    {
        return $this->hasMany(RoomTag::class, 'room_id');
    }
}
