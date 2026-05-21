<?php
declare(strict_types=1);

namespace app\ai\model;

use think\Model;

final class AiTaskLog extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_ai_task_log';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
