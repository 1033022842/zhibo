<?php
declare(strict_types=1);

namespace app\admin\model\live;

use think\Model;

class HomeBanner extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_home_banner';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
