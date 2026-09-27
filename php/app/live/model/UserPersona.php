<?php
declare(strict_types=1);

namespace app\live\model;

use think\Model;

/**
 * 用户人格设定（设置页「Your personas」）
 */
class UserPersona extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_user_persona';
    protected $pk   = 'id';
    protected $autoWriteTimestamp = false;
}
