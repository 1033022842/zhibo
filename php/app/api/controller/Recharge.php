<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\BaseController;
use app\common\web\ResultCode;
use app\live\service\RechargeService;

final class Recharge extends BaseController
{
    protected array $middleware = [
        \app\live\middleware\Auth::class => ['only' => ['channels', 'submit', 'orders', 'status', 'upload', 'createOrder']],
    ];

    private RechargeService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new RechargeService();
    }

    /**
     * 可用充值渠道
     */
    public function channels()
    {
        $list = $this->service->channels();
        // 将相对路径的二维码 URL 转为绝对 URL
        $domain = rtrim($this->request->domain(), '/');
        foreach ($list as &$ch) {
            if (!empty($ch['qr_code_url']) && !str_starts_with($ch['qr_code_url'], 'http')) {
                $ch['qr_code_url'] = $domain . '/' . ltrim($ch['qr_code_url'], '/');
            }
        }
        return $this->jsonSuccess($list);
    }

    /**
     * 可用法币支付通道（后台 lp_fiat_channel，含各通道的汇率与币种）
     */
    public function fiatChannels()
    {
        return $this->jsonSuccess(\app\live\service\FiatChannelService::channels());
    }

    /**
     * AI 女友端：充值套餐 + 可选支付方式（免登录）
     */
    public function packages()
    {
        $starPay = new \app\live\service\StarPayService();
        $flowPay = new \app\live\service\FlowPayService();
        return $this->jsonSuccess([
            'list'     => $this->service->packages(),
            'products' => $starPay->products(),
            // 卡支付：是否可用 + 可选通道（前台平铺成多个按钮由用户自选）
            'flowpay'  => [
                'enabled'  => $flowPay->enabled(),
                'channels' => $flowPay->channels(),
            ],
            // 法币通道（单国本币收款）：下单时按这条通道的汇率把 USD 折算成本币
            'fiat'     => \app\live\service\FiatChannelService::channels(),
        ]);
    }

    /**
     * 自选点数报价（免登录，前端展示用）
     */
    public function quote()
    {
        $tokens = $this->request->param('tokens/d', 0);
        if ($tokens <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '点数必须大于0');
        }
        return $this->jsonSuccess($this->service->quoteByTokens($tokens));
    }

    /**
     * AI 女友端：网关下单（固定套餐或自选点数），返回收银台地址
     */
    public function createOrder()
    {
        $userId    = $this->getAuthUserId();
        $packageId = $this->request->post('package_id/d', 0);
        $tokens    = $this->request->post('tokens/d', 0);
        $product   = trim((string) $this->request->post('product', ''));
        $gateway   = strtolower(trim((string) $this->request->post('gateway', 'starpay')));
        // FlowPay 收单通道（前台用户自选，仅 gateway=flowpay 时有意义）
        $gcode     = trim((string) $this->request->post('gcode', ''));

        if ($packageId > 0) {
            $order = $this->service->submitPackageOrder($userId, $packageId, $product, $gateway, $gcode);
        } elseif ($tokens > 0) {
            $order = $this->service->submitCustomOrder($userId, $tokens, $product, $gateway, $gcode);
        } else {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请选择充值套餐或点数');
        }

        return $this->jsonSuccess([
            'order_no'       => $order['order_no'],
            'pay_amount'     => $order['pay_amount'],
            'diamond_amount' => $order['diamond_amount'],
            'product'        => $order['product'] ?? '',
            'pay_url'        => $order['pay_url'] ?? '',
            'expire_at'      => $order['expire_at'] ?? '',
        ], '订单已创建');
    }

    /**
     * star-pay 异步通知（免登录，需验签）
     */
    public function notify()
    {
        $starPay = new \app\live\service\StarPayService();
        try {
            $params = $starPay->verifyNotify($this->request->post());
        } catch (\Throwable $e) {
            return response('FAIL', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        $orderNo = (string) ($params['merchant_ref'] ?? '');
        // 入金：0 Unpaid / 1 Paid
        if ($orderNo === '' || (int) ($params['status'] ?? 0) !== 1) {
            return response('SUCCESS', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        try {
            $this->service->confirmByGateway($orderNo, (string) ($params['system_ref'] ?? ''), (string) ($params['product_ref'] ?? ''));
        } catch (\Throwable $e) {
            return response('FAIL', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        return response('SUCCESS', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
    }

    /**
     * FlowPay 异步通知（免登录，需验签）
     */
    public function notifyFlowPay()
    {
        $flowPay = new \app\live\service\FlowPayService();
        // 网关以 application/json 推送，需读原始 body（全局输入过滤会破坏签名原文）
        $post = json_decode((string) $this->request->getContent(), true);
        if (!is_array($post)) {
            $post = (array) $this->request->post();
        }

        try {
            $params = $flowPay->verifyNotify($post);
        } catch (\Throwable $e) {
            return response('FAIL', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        $orderNo = (string) ($params['orderid'] ?? '');
        // trade_state：success 成功 / notpay 失败；务必判断状态
        if ($orderNo === '' || (string) ($params['trade_state'] ?? '') !== 'success') {
            return response('success', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        try {
            $this->service->confirmByGateway($orderNo, (string) ($params['transaction_id'] ?? ''), '', 'FlowPay');
        } catch (\Throwable $e) {
            return response('FAIL', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        return response('success', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
    }

    /**
     * 提交充值订单
     */
    public function submit()
    {
        $userId = $this->getAuthUserId();
        $amount = $this->request->post('amount/f', 0);
        if ($amount <= 0) return $this->jsonFail(ResultCode::PARAM_ERROR, '请输入充值金额');

        // 法币通道：按后台汇率折算成本币收款（pay_amount 即本币实付金额）
        $fiatChannelId = $this->request->post('fiat_channel_id/d', 0);
        if ($fiatChannelId > 0) {
            return $this->jsonSuccess(
                $this->service->submitFiatCoinOrder($userId, $fiatChannelId, $amount),
                '订单已提交，请按本币金额支付'
            );
        }

        $channelId = $this->request->post('channel_id/d', 0);
        $proofImage = $this->request->post('proof_image', '');
        if ($channelId <= 0) return $this->jsonFail(ResultCode::PARAM_ERROR, '请选择充值渠道');

        $order = $this->service->submitOrder($userId, $channelId, $amount, $proofImage);
        $spec  = \app\live\service\RechargeService::specOf((array) ($order['channel'] ?? []));
        return $this->jsonSuccess(array_merge($order, [
            // 站内收款页用：币种/网络/精度都来自后台渠道配置，前端不猜
            'confirm_mode' => \app\live\service\RechargeService::effectiveConfirmMode((array) ($order['channel'] ?? [])),
            'unit'         => (string) $spec['unit'],
            'chain_label'  => (string) $spec['chain'],
            'pegged'       => (bool) $spec['pegged'],
            'pay_address'  => (string) (($order['channel']['address'] ?? '')),
            'memo'         => (string) (($order['channel']['memo'] ?? '')),
        ]), '订单已提交，请等待审核');
    }

    /**
     * 充值记录
     */
    /**
     * 订单状态轮询（付款等待页 5s 一次）
     */
    public function status()
    {
        $userId = $this->getAuthUserId();
        $orderNo = (string) $this->request->param('order_no', '');
        if ($orderNo === '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '参数错误');
        }
        $order = \think\facade\Db::connect('live_mysql')->table('lp_recharge_order')
            ->where('order_no', $orderNo)
            ->where('user_id', $userId)
            ->find();
        if (!$order) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '订单不存在');
        }
        return $this->jsonSuccess([
            'order_no'       => $order['order_no'],
            'status'         => (int) $order['status'],
            // 支付状态：0 未确认 / 1 已支付 / 2 未支付（网关查单或回调写入）
            // 前台回跳后靠它区分「还没付成功」与「网关已明确回未支付」
            'pay_state'      => (int) ($order['pay_state'] ?? 0),
            'pay_amount'     => $order['pay_amount'],
            'diamond_amount' => $order['diamond_amount'],
            'pay_url'        => (string) ($order['pay_url'] ?? ''),
            'expire_at'      => $order['expire_at'] ?? '',
        ]);
    }

    public function orders()
    {
        $userId = $this->getAuthUserId();
        $page = $this->request->param('page/d', 1);
        $result = $this->service->orderList($userId, $page);
        return $this->jsonSuccess($result);
    }

    /**
     * 上传凭证图片
     */
    public function upload()
    {
        $file = $this->request->file('file');
        if (!$file) return $this->jsonFail(ResultCode::PARAM_ERROR, '请选择文件');
        $upload = new \app\common\library\Upload($file);
        $upload->setTopic('recharge');
        $userId = $this->getAuthUserId();
        $attachment = $upload->upload(null, 0, $userId);
        $url = $attachment['url'];
        if (!str_starts_with($url, 'http')) {
            $url = rtrim($this->request->domain(), '/') . '/' . ltrim($url, '/');
        }
        return $this->jsonSuccess(['url' => $url]);
    }
}
