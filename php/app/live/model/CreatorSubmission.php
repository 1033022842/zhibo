<?php
declare(strict_types=1);

namespace app\live\model;

use think\Model;

class CreatorSubmission extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_creator_submission';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    // 状态常量
    const STATUS_PENDING  = 0; // 待审核
    const STATUS_ONLINE   = 1; // 已上线
    const STATUS_REJECTED = 2; // 已驳回
}
