<?php

namespace app\admin\model\live;

use think\Model;

class LiveUserAuth extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_user_auth';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
