<?php

namespace app\admin\model\live;

use think\Model;

class LiveUserProfile extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_user_profile';
    protected $pk = 'user_id';
    protected $autoWriteTimestamp = false;
}
