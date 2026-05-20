<?php

declare(strict_types=1);

namespace app\admin\model\live;

use think\Model;

final class RoomTag extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_room_tag';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
