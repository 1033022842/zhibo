<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\BaseController;
use app\common\web\ResultCode;
use app\live\service\VipService;

/**
 * 会员订阅
 */
final class Vip extends BaseController
{
    protected array $middleware = [
        \app\live\middleware\Auth::class => ['only' => ['buy', 'status', 'claimDaily']],
    ];

    private VipService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new VipService();
    }

    /**
     * 套餐列表（公开）
     */
    public function plans()
    {
        return $this->jsonSuccess($this->service->plans());
    }

    /**
     * 购买会员（钻石支付）
     */
    public function buy()
    {
        $planId = (int) $this->request->post('plan_id', 0);
        if ($planId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '套餐ID无效');
        }
        $result = $this->service->buy($this->getAuthUserId(), $planId);
        return $this->jsonSuccess($result, '开通成功');
    }

    /**
     * 我的会员状态
     */
    public function status()
    {
        return $this->jsonSuccess($this->service->status($this->getAuthUserId()));
    }

    /**
     * 每日领取钻石
     */
    public function claimDaily()
    {
        $result = $this->service->claimDaily($this->getAuthUserId());
        return $this->jsonSuccess($result, '领取成功');
    }
}
