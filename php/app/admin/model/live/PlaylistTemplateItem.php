<?php

declare(strict_types=1);

namespace app\admin\model\live;

use think\Model;
use think\model\relation\BelongsTo;

final class PlaylistTemplateItem extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_playlist_template_item';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'asset_id');
    }
}
