<?php

declare(strict_types=1);

namespace app\admin\model\live;

use think\Model;
use think\model\relation\BelongsTo;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

final class Room extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_room';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
    protected $append = ['tag_names', 'asset_ids', 'playlist_name'];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    public function binding(): HasOne
    {
        return $this->hasOne(RoomBinding::class, 'room_id');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(RoomTag::class, 'room_id');
    }

    public function getTagNamesAttr(): string
    {
        $tags = $this->tags()->column('tag_name');
        return implode(',', array_filter(array_map('strval', $tags)));
    }

    public function getAssetIdsAttr(): array
    {
        $binding = $this->binding()->find();
        if (!$binding) {
            return [];
        }

        $playlist = $binding->playlistTemplate()->find();
        if (!$playlist) {
            return [];
        }

        $items = $playlist->items()->order('seq asc,id asc')->select()->toArray();
        return array_map(static fn(array $item): int => (int) $item['asset_id'], $items);
    }

    public function getPlaylistNameAttr(): string
    {
        $binding = $this->binding()->find();
        if (!$binding) {
            return '';
        }

        $playlist = $binding->playlistTemplate()->find();
        return $playlist ? (string) $playlist->name : '';
    }
}
