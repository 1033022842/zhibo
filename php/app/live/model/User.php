<?php
declare(strict_types=1);

namespace app\live\model;

use think\Model;

class User extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_user';
    protected $pk   = 'id';
    protected $autoWriteTimestamp = false;

    public function auths()
    {
        return $this->hasMany(UserAuth::class, 'user_id');
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class, 'user_id');
    }
}
