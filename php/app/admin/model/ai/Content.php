<?php

declare(strict_types=1);

namespace app\admin\model\ai;

use think\Model;

final class Content extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_ai_content';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    protected $json = ['personality'];
    protected $jsonAssoc = true;
}
