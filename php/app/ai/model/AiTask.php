<?php
declare(strict_types=1);

namespace app\ai\model;

use think\Model;

final class AiTask extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_ai_task';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
