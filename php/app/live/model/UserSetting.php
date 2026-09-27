<?php
declare(strict_types=1);

namespace app\live\model;

use think\Model;

class UserSetting extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_user_setting';
    protected $pk   = 'id';
    protected $autoWriteTimestamp = false;
}
