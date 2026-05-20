<?php

declare(strict_types=1);

namespace app\admin\model\live;

use think\Model;
use think\model\relation\HasMany;

final class PlaylistTemplate extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_playlist_template';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    public function items(): HasMany
    {
        return $this->hasMany(PlaylistTemplateItem::class, 'template_id');
    }
}
