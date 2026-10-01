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
     * 订阅下单
     *
     * gateway=starpay/flowpay 返回收银台 pay_url；
     * channel_id>0 走后台链上收款渠道（站内收款页），返回收款地址。
     */
    public function order()
    {
        $planId  = (int) $this->request->post('plan_id', 0);
        $product = trim((string) $this->request->post('product', ''));
        $gateway = strtolower(trim((string) $this->request->post('gateway', 'starpay')));
        // FlowPay 收单通道（前台用户自选）
        $gcode   = trim((string) $this->request->post('gcode', ''));
        // 链上收款渠道（后台 lp_recharge_channel）
        $channelId = (int) $this->request->post('channel_id', 0);
        if ($planId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '套餐ID无效');
        }
        $order   = $this->recharge->submitVipOrder($this->getAuthUserId(), $planId, $product, $gateway, $gcode, $channelId);
        $channel = (array) ($order['channel'] ?? []);
        $spec    = \app\live\service\RechargeService::specOf($channel);
        return $this->jsonSuccess([
            'order_no'   => $order['order_no'],
            'pay_amount' => $order['pay_amount'],
            'plan_id'    => $planId,
            'product'    => $order['product'] ?? '',
            'pay_url'    => $order['pay_url'] ?? '',
            'expire_at'  => $order['expire_at'] ?? '',
            // 链上渠道：站内收款页直接展示这些（币种/网络/精度都来自后台渠道配置）
            'channel_id'   => (int) ($channel['id'] ?? 0),
            'channel_name' => (string) ($channel['name'] ?? ''),
            'channel_type' => (string) ($channel['type'] ?? ''),
            'pay_address'  => (string) ($channel['address'] ?? ''),
            'memo'         => (string) ($channel['memo'] ?? ''),
            'qr_code_url'  => self::absUrl((string) ($channel['qr_code_url'] ?? '')),
            'confirm_mode' => \app\live\service\RechargeService::effectiveConfirmMode($channel),
            'unit'         => (string) $spec['unit'],
            'chain_label'  => (string) $spec['chain'],
            'pegged'       => (bool) $spec['pegged'],
        ], '订单已创建');
    }

    /** 渠道二维码可能是相对路径，统一转成绝对地址 */
    private static function absUrl(string $url): string
    {
        if ($url === '' || str_starts_with($url, 'http')) {
            return $url;
        }
        return rtrim(request()->domain(), '/') . '/' . ltrim($url, '/');
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
