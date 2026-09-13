<?php
declare(strict_types=1);

namespace app\live\model;

use think\Model;

class HomeCharacter extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_home_character';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    // 状态常量
    const STATUS_DISABLED = 0; // 禁用
    const STATUS_ENABLED  = 1; // 启用
}
