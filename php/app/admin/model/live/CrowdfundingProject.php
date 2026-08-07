<?php
declare(strict_types=1);

namespace app\admin\model\live;

use think\Model;

class CrowdfundingProject extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_crowdfunding_project';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
