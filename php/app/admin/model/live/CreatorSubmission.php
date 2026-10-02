<?php
declare(strict_types=1);

namespace app\admin\model\live;

use think\Model;

final class CreatorSubmission extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_creator_submission';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
