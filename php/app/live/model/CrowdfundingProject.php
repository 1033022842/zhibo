<?php
declare(strict_types=1);

namespace app\live\model;

use think\Model;

class CrowdfundingProject extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_crowdfunding_project';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    // 状态常量
    const STATUS_ACTIVE  = 0; // 进行中
    const STATUS_SUCCESS = 1; // 已成功
    const STATUS_FAILED  = 2; // 已失败(已退款)

    public function pledges()
    {
        return $this->hasMany(CrowdfundingPledge::class, 'project_id');
    }
}
