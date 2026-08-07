<?php
declare(strict_types=1);

namespace app\live\model;

use think\Model;

class CrowdfundingPledge extends Model
{
    protected $connection = 'live_mysql';
    protected $name = 'lp_crowdfunding_pledge';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    // 状态常量
    const STATUS_FROZEN   = 0; // 冻结中
    const STATUS_UNLOCKED = 1; // 已划转(成功)
    const STATUS_REFUNDED = 2; // 已退款(失败)

    public function project()
    {
        return $this->belongsTo(CrowdfundingProject::class, 'project_id');
    }
}
