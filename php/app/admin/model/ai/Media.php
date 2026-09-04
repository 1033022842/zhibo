<?php

declare(strict_types=1);

namespace app\admin\model\ai;

use think\Model;

final class Media extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_ai_media';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
