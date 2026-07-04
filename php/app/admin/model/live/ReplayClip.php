<?php

declare(strict_types=1);

namespace app\admin\model\live;

use think\Model;
use think\model\relation\BelongsTo;

final class ReplayClip extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_replay_clip';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }
}
