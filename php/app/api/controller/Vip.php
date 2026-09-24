<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\BaseController;
use app\common\web\ResultCode;
use app\live\service\RechargeService;
use app\live\service\VipService;

/**
 * 会员订阅
 */
final class Vip extends BaseController
{
    protected array $middleware = [
        \app\live\middleware\Auth::class => ['only' => ['buy', 'order', 'status', 'claimDaily']],
    ];

    private VipService $service;
    private RechargeService $recharge;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service  = new VipService();
        $this->recharge = new RechargeService();
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
     * 网关订阅下单（返回收银台地址）
     */
    public function order()
    {
        $planId  = (int) $this->request->post('plan_id', 0);
        $product = trim((string) $this->request->post('product', ''));
        $gateway = strtolower(trim((string) $this->request->post('gateway', 'starpay')));
        if ($planId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '套餐ID无效');
        }
        $order = $this->recharge->submitVipOrder($this->getAuthUserId(), $planId, $product, $gateway);
        return $this->jsonSuccess([
            'order_no'   => $order['order_no'],
            'pay_amount' => $order['pay_amount'],
            'plan_id'    => $planId,
            'product'    => $order['product'] ?? '',
            'pay_url'    => $order['pay_url'] ?? '',
            'expire_at'  => $order['expire_at'] ?? '',
        ], '订单已创建');
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
