<?php

declare(strict_types=1);

namespace app\admin\model\live;

use think\Model;

final class MediaAsset extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_media_asset';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
