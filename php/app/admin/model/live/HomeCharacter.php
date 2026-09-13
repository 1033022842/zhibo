<?php
declare(strict_types=1);

namespace app\admin\model\live;

use think\Model;

class HomeCharacter extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_home_character';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
