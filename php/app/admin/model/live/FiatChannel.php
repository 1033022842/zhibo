<?php
declare(strict_types=1);

namespace app\admin\model\live;

use think\Model;

class FiatChannel extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_fiat_channel';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
