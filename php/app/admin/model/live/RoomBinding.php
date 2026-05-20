<?php

declare(strict_types=1);

namespace app\admin\model\live;

use think\Model;
use think\model\relation\BelongsTo;

final class RoomBinding extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_room_binding';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    public function playlistTemplate(): BelongsTo
    {
        return $this->belongsTo(PlaylistTemplate::class, 'playlist_template_id');
    }
}
