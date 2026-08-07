<?php
declare(strict_types=1);

namespace app\live\model;

use think\Model;

class RechargeChannel extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_recharge_channel';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
