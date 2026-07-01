<?php

namespace app\admin\model\live;

use think\Model;

class LiveUser extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_user';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    public function auth()
    {
        return $this->hasOne(LiveUserAuth::class, 'user_id');
    }

    public function profile()
    {
        return $this->hasOne(LiveUserProfile::class, 'user_id');
    }
}
