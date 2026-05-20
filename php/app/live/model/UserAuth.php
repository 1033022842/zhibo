<?php
declare(strict_types=1);

namespace app\live\model;

use think\Model;

class UserAuth extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_user_auth';
    protected $pk   = 'id';
    protected $autoWriteTimestamp = false;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
