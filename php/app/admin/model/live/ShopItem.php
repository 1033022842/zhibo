<?php

declare(strict_types=1);

namespace app\admin\model\live;

use think\Model;

final class ShopItem extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_shop_item';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
