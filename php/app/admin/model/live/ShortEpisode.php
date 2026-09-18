<?php

declare(strict_types=1);

namespace app\admin\model\live;

use think\Model;

final class ShortEpisode extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_short_episode';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
