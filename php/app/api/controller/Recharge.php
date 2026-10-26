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
                'enabled'  => $flowPay->available(),
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
        $raw  = (string) $this->request->getContent();
        $post = json_decode($raw, true);
        if (!is_array($post)) {
            $post = (array) $this->request->post();
        }

        try {
            $params = $flowPay->verifyNotify($post);
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:notifyFlowPay] 验签失败：' . $e->getMessage() . ' raw=' . mb_substr($raw, 0, 2000));
            return response('FAIL', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        $orderNo = (string) ($params['orderid'] ?? '');
        // 成功标志：回调文档口径是 trade_state=success；而查单接口里 trade_state 恒为 notpay、
        // 真正标记已支付的是 status=success。两个都认，避免漏单。
        $paid = (string) ($params['trade_state'] ?? '') === 'success'
            || (string) ($params['status'] ?? '') === 'success';
        \think\facade\Log::info(sprintf(
            '[recharge:notifyFlowPay] orderid=%s trade_state=%s status=%s paid=%s',
            $orderNo, (string) ($params['trade_state'] ?? ''), (string) ($params['status'] ?? ''), $paid ? '1' : '0'
        ));
        if ($orderNo === '' || !$paid) {
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
     * MKPay 异步通知（免登录，需验签）
     *
     * 网关要求：处理成功、重复回调、无需处理的回调都返回纯文本 success，
     * 否则网关会在一定次数内重试；只有「确认失败需要重推」才返回其它内容。
     */
    public function notifyMkPay()
    {
        $mkPay = new \app\live\service\MkPayService();
        // 网关以 JSON 推送，读原始 body（全局输入过滤会破坏签名原文）
        $raw  = (string) $this->request->getContent();
        $post = json_decode($raw, true);
        if (!is_array($post)) {
            $post = (array) $this->request->post();
        }

        try {
            $params = $mkPay->verifyNotify($post);
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:notifyMkPay] 验签失败：' . $e->getMessage() . ' raw=' . mb_substr($raw, 0, 2000));
            // 验签失败不能 ack，返回非 success 让网关重推（可能是我方验签实现有误，重试期间可修复）
            return response('error', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        $orderNo = (string) ($params['merchantOrderId'] ?? '');
        $status  = (string) ($params['status'] ?? '');
        $payType = (string) ($params['payType'] ?? '');
        \think\facade\Log::info(sprintf(
            '[recharge:notifyMkPay] merchantOrderId=%s orderId=%s status=%s(%s) payType=%s',
            $orderNo,
            (string) ($params['orderId'] ?? ''),
            $status,
            \app\live\service\MkPayService::STATUS[$status] ?? '?',
            $payType
        ));

        if ($orderNo === '' || $payType !== \app\live\service\MkPayService::PAY_TYPE_COLLECTION) {
            return response('success', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        // 代收成功 → 入账（confirmByGateway 幂等：已入账直接返回、已关闭的补入账）
        if (\app\live\service\MkPayService::isSuccess($status)) {
            // 订单不存在（别站订单打错回调 / 历史遗留单 / 伪造报文）：ack 掉止住网关重试，
            // 返回 error 只会换来无限重推（文档：无需处理的回调返回 success）
            $exists = \think\facade\Db::connect('live_mysql')->table('lp_recharge_order')
                ->where('order_no', $orderNo)->value('id');
            if (!$exists) {
                \think\facade\Log::warning('[recharge:notifyMkPay] 成功回调但订单不存在，已 ack order=' . $orderNo);
                return response('success', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
            }
            try {
                $this->service->confirmByGateway($orderNo, (string) ($params['orderId'] ?? ''), '', 'MKPay');
            } catch (\Throwable $e) {
                \think\facade\Log::error('[recharge:notifyMkPay] 入账失败：' . $e->getMessage() . ' order=' . $orderNo);
                return response('error', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
            }
            return response('success', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        // 04 退款：平台会扣减商户余额，已入账订单需要人工反向处理（不自动扣用户代币，避免误伤），记备注等运营核对
        if ($status === '04') {
            try {
                \think\facade\Db::connect('live_mysql')->table('lp_recharge_order')
                    ->where('order_no', $orderNo)->where('status', 1)
                    ->update([
                        'admin_remark' => 'MKPay 回调退款(endToEndId=' . (string) ($params['endToEndId'] ?? '') . ')，需人工核对处理',
                        'updated_at'   => date('Y-m-d H:i:s'),
                    ]);
                \think\facade\Log::warning('[recharge:notifyMkPay] 收到退款回调 order=' . $orderNo);
            } catch (\Throwable $e) {
                \think\facade\Log::error('[recharge:notifyMkPay] 退款备注写入失败：' . $e->getMessage() . ' order=' . $orderNo);
            }
        }

        // 其余状态（00/06/90/99 处理中、02 失败、03 超时）：无需处理，ack 停止重推
        return response('success', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
    }

    /**
     * MKPay（越南）异步通知（免登录，需验签）
     *
     * 回调 JSON：pay_order_id / merchant_order_id / status_code / amount / timestamp / sign
     * 文档口径：只要返回 2xx 网关即认为成功；验签失败返回 400 触发重推，便于修复后补单。
     */
    public function notifyMkPayVn()
    {
        $vn  = new \app\live\service\MkPayVnService();
        // 网关以 JSON 推送，读原始 body（全局输入过滤会破坏签名原文）
        $raw  = (string) $this->request->getContent();
        $post = json_decode($raw, true);
        if (!is_array($post)) {
            $post = (array) $this->request->post();
        }

        try {
            $params = $vn->verifyNotify($post);
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:notifyMkPayVn] 验签失败：' . $e->getMessage() . ' raw=' . mb_substr($raw, 0, 2000));
            return response('INVALID SIGN', 400, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        $orderNo = (string) ($params['merchant_order_id'] ?? '');
        $payId   = (string) ($params['pay_order_id'] ?? '');
        $code    = (int) ($params['status_code'] ?? 0);
        \think\facade\Log::info(sprintf(
            '[recharge:notifyMkPayVn] merchant_order_id=%s pay_order_id=%s status_code=%d(%s)',
            $orderNo,
            $payId,
            $code,
            \app\live\service\MkPayVnService::statusText($code)
        ));

        if ($orderNo === '') {
            return response('SUCCESS', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        // status_code=2 成功 → 入账（confirmByGateway 幂等）
        if (\app\live\service\MkPayVnService::isSuccess($code)) {
            $exists = \think\facade\Db::connect('live_mysql')->table('lp_recharge_order')
                ->where('order_no', $orderNo)->value('id');
            if (!$exists) {
                \think\facade\Log::warning('[recharge:notifyMkPayVn] 成功回调但订单不存在，已 ack order=' . $orderNo);
                return response('SUCCESS', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
            }
            try {
                $this->service->confirmByGateway($orderNo, $payId, '', 'MKPay-VN');
            } catch (\Throwable $e) {
                \think\facade\Log::error('[recharge:notifyMkPayVn] 入账失败：' . $e->getMessage() . ' order=' . $orderNo);
                // 非 2xx 让网关重推，避免钱到账但没入账
                return response('ERROR', 500, ['Content-Type' => 'text/plain;charset=utf-8']);
            }
            return response('SUCCESS', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        // 0/1 处理中、3 失败、4 过期取消、6 关闭、7 异常：无需处理，ack 停止重推
        return response('SUCCESS', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
    }

    /**
     * MKPay（墨西哥）异步通知（免登录，需验签）
     *
     * 回调 JSON：pay_order_id / merchant_order_id / status_code / amount / timestamp / sign
     * 文档口径：只要返回 2xx 网关即认为成功；验签失败返回 400 触发重推，便于修复后补单。
     */
    public function notifyMkPayMx()
    {
        $mx  = new \app\live\service\MkPayMxService();
        // 网关以 JSON 推送，读原始 body（全局输入过滤会破坏签名原文）
        $raw  = (string) $this->request->getContent();
        $post = json_decode($raw, true);
        if (!is_array($post)) {
            $post = (array) $this->request->post();
        }

        try {
            $params = $mx->verifyNotify($post);
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:notifyMkPayMx] 验签失败：' . $e->getMessage() . ' raw=' . mb_substr($raw, 0, 2000));
            return response('INVALID SIGN', 400, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        $orderNo = (string) ($params['merchant_order_id'] ?? '');
        $payId   = (string) ($params['pay_order_id'] ?? '');
        $code    = (int) ($params['status_code'] ?? 0);
        \think\facade\Log::info(sprintf(
            '[recharge:notifyMkPayMx] merchant_order_id=%s pay_order_id=%s status_code=%d(%s)',
            $orderNo,
            $payId,
            $code,
            \app\live\service\MkPayMxService::statusText($code)
        ));

        if ($orderNo === '') {
            return response('SUCCESS', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        // status_code=2 成功 → 入账（confirmByGateway 幂等）
        if (\app\live\service\MkPayMxService::isSuccess($code)) {
            $exists = \think\facade\Db::connect('live_mysql')->table('lp_recharge_order')
                ->where('order_no', $orderNo)->value('id');
            if (!$exists) {
                \think\facade\Log::warning('[recharge:notifyMkPayMx] 成功回调但订单不存在，已 ack order=' . $orderNo);
                return response('SUCCESS', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
            }
            try {
                $this->service->confirmByGateway($orderNo, $payId, '', 'MKPay-MX');
            } catch (\Throwable $e) {
                \think\facade\Log::error('[recharge:notifyMkPayMx] 入账失败：' . $e->getMessage() . ' order=' . $orderNo);
                // 非 2xx 让网关重推，避免钱到账但没入账
                return response('ERROR', 500, ['Content-Type' => 'text/plain;charset=utf-8']);
            }
            return response('SUCCESS', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        // 0/1 处理中、3 失败、4 过期取消、6 关闭、7 异常：无需处理，ack 停止重推
        return response('SUCCESS', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
    }

    /**
     * MKPay（印度）异步通知（免登录，需验签）
     *
     * 回调 JSON：pay_order_id / merchant_order_id / status_code / amount / timestamp / sign
     * 文档口径：只要返回 2xx 网关即认为成功；验签失败返回 400 触发重推，便于修复后补单。
     */
    public function notifyMkPayIn()
    {
        $in  = new \app\live\service\MkPayInService();
        // 网关以 JSON 推送，读原始 body（全局输入过滤会破坏签名原文）
        $raw  = (string) $this->request->getContent();
        $post = json_decode($raw, true);
        if (!is_array($post)) {
            $post = (array) $this->request->post();
        }

        try {
            $params = $in->verifyNotify($post);
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:notifyMkPayIn] 验签失败：' . $e->getMessage() . ' raw=' . mb_substr($raw, 0, 2000));
            return response('INVALID SIGN', 400, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        $orderNo = (string) ($params['merchant_order_id'] ?? '');
        $payId   = (string) ($params['pay_order_id'] ?? '');
        $code    = (int) ($params['status_code'] ?? 0);
        \think\facade\Log::info(sprintf(
            '[recharge:notifyMkPayIn] merchant_order_id=%s pay_order_id=%s status_code=%d(%s)',
            $orderNo,
            $payId,
            $code,
            \app\live\service\MkPayInService::statusText($code)
        ));

        if ($orderNo === '') {
            return response('SUCCESS', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        // status_code=2 成功 → 入账（confirmByGateway 幂等）
        if (\app\live\service\MkPayInService::isSuccess($code)) {
            $exists = \think\facade\Db::connect('live_mysql')->table('lp_recharge_order')
                ->where('order_no', $orderNo)->value('id');
            if (!$exists) {
                \think\facade\Log::warning('[recharge:notifyMkPayIn] 成功回调但订单不存在，已 ack order=' . $orderNo);
                return response('SUCCESS', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
            }
            try {
                $this->service->confirmByGateway($orderNo, $payId, '', 'MKPay-IN');
            } catch (\Throwable $e) {
                \think\facade\Log::error('[recharge:notifyMkPayIn] 入账失败：' . $e->getMessage() . ' order=' . $orderNo);
                // 非 2xx 让网关重推，避免钱到账但没入账
                return response('ERROR', 500, ['Content-Type' => 'text/plain;charset=utf-8']);
            }
            return response('SUCCESS', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        // 0/1 处理中、3 失败、4 过期取消、6 关闭、7 异常：无需处理，ack 停止重推
        return response('SUCCESS', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
    }

    /**
     * Cregis 异步通知（免登录，需验签）
     *
     * 回调 JSON：event_name=order / event_type / data{order_id, cregis_id, status, tx_id, ...}
     * 成功必须返回 HTTP 200 + 纯文本 success；Cregis 只推一次，
     * 漏单靠 RechargeService::reconcileGateway() 定时查单补。
     */
    public function notifyCregis()
    {
        $cg  = new \app\live\service\CregisPayService();
        $raw = (string) $this->request->getContent();
        $post = json_decode($raw, true);
        if (!is_array($post)) {
            $post = (array) $this->request->post();
        }

        try {
            $params = $cg->verifyNotify($post);
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:notifyCregis] 验签失败：' . $e->getMessage() . ' raw=' . mb_substr($raw, 0, 2000));
            return response('invalid sign', 400, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        $data     = is_array($params['data'] ?? null) ? $params['data'] : [];
        $orderNo  = (string) ($data['order_id'] ?? '');
        $cregisId = (string) ($data['cregis_id'] ?? '');
        $status   = strtolower((string) ($data['status'] ?? ''));
        $txId     = (string) ($data['tx_id'] ?? '');
        $type     = (string) ($params['event_type'] ?? '');

        \think\facade\Log::info(sprintf(
            '[recharge:notifyCregis] order=%s cregis_id=%s type=%s status=%s(%s) tx=%s',
            $orderNo,
            $cregisId,
            $type,
            $status,
            \app\live\service\CregisPayService::statusText($status),
            $txId
        ));

        $ok = static fn() => response('success', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        if ($orderNo === '') {
            return $ok();
        }

        // 部分支付：不发货，备注提示运营等补款（补款后 Cregis 会推 paid_remain）
        if ($status === 'paid_partial') {
            \think\facade\Db::connect('live_mysql')->table('lp_recharge_order')
                ->where('order_no', $orderNo)->where('status', 0)
                ->update(['admin_remark' => 'Cregis 部分支付，待用户补款', 'updated_at' => date('Y-m-d H:i:s')]);
            return $ok();
        }

        if (!\app\live\service\CregisPayService::isPaid($status)) {
            // expired / canceled / paid_remain 之外的中间态：不入账，直接 ack
            return $ok();
        }

        $exists = \think\facade\Db::connect('live_mysql')->table('lp_recharge_order')
            ->where('order_no', $orderNo)->value('id');
        if (!$exists) {
            \think\facade\Log::warning('[recharge:notifyCregis] 成功回调但订单不存在，已 ack order=' . $orderNo);
            return $ok();
        }

        try {
            // confirmByGateway 幂等：重复回调不会重复加代币/开会员
            $this->service->confirmByGateway($orderNo, $cregisId, $txId, 'Cregis');
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:notifyCregis] 入账失败：' . $e->getMessage() . ' order=' . $orderNo);
            // 非 success 让 Cregis 标记失败，可到客户端「异常回调」手动重推
            return response('error', 500, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        return $ok();
    }

    /**
     * AIPowerPay 异步通知（免登录，需验签）
     *
     * 回调 JSON：type=order.payment.success / order.payment.fail / order.refund，
     * 字段含 order_no / invoice_id / failure_code / id / sign。
     * 处理成功返回 200 + 纯文本 success，失败返回 500 让网关重推。
     */
    public function notifyAiPowerPay()
    {
        $ap  = new \app\live\service\AiPowerPayService();
        $raw = (string) $this->request->getContent();
        $post = json_decode($raw, true);
        if (!is_array($post)) {
            $post = (array) $this->request->post();
        }

        try {
            $params = $ap->verifyNotify($post);
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:notifyAiPowerPay] 验签失败：' . $e->getMessage() . ' raw=' . mb_substr($raw, 0, 2000));
            return response('invalid sign', 400, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        $type    = strtolower((string) ($params['type'] ?? ''));
        $orderNo = (string) ($params['order_no'] ?? '');
        $code    = strtolower((string) ($params['failure_code'] ?? ''));
        $gwId    = (string) ($params['id'] ?? '');

        \think\facade\Log::info(sprintf(
            '[recharge:notifyAiPowerPay] type=%s order=%s failure_code=%s id=%s amount=%s %s',
            $type,
            $orderNo,
            $code,
            $gwId,
            (string) ($params['amount'] ?? ''),
            (string) ($params['currency'] ?? '')
        ));

        $ok = static fn() => response('success', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        if ($orderNo === '') {
            return $ok();
        }

        // 退款通知：只记录，不自动扣减余额（人工核对）
        if (str_contains($type, 'refund')) {
            \think\facade\Log::warning('[recharge:notifyAiPowerPay] 收到退款回调 order=' . $orderNo . ' status=' . (string) ($params['refundStatus'] ?? ''));
            return $ok();
        }

        // 支付成功：type=order.payment.success 或 failure_code=success
        $paid = str_contains($type, 'payment.success') || $code === 'success';
        if (!$paid) {
            // 失败通知：只记日志，订单保持待支付，用户可重新支付（对账会按查单结果落未支付）
            \think\facade\Log::warning('[recharge:notifyAiPowerPay] 支付失败通知 order=' . $orderNo . ' msg=' . (string) ($params['msg'] ?? ''));
            return $ok();
        }

        $exists = \think\facade\Db::connect('live_mysql')->table('lp_recharge_order')
            ->where('order_no', $orderNo)->value('id');
        if (!$exists) {
            \think\facade\Log::warning('[recharge:notifyAiPowerPay] 成功回调但订单不存在，已 ack order=' . $orderNo);
            return $ok();
        }

        try {
            // confirmByGateway 幂等：重复回调不会重复加代币/开会员
            $this->service->confirmByGateway($orderNo, $gwId, '', 'AIPowerPay');
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:notifyAiPowerPay] 入账失败：' . $e->getMessage() . ' order=' . $orderNo);
            return response('error', 500, ['Content-Type' => 'text/plain;charset=utf-8']);
        }

        return $ok();
    }

    /** WisonPay 美国钱包支付回调，仅接受 RSA 验签通过的通知。 */
    public function notifyWisonPay()
    {
        $params = json_decode((string) $this->request->getContent(), true);
        if (!is_array($params)) {
            return response('invalid request', 400, ['Content-Type' => 'text/plain;charset=utf-8']);
        }
        try {
            $payment = (new \app\live\service\WisonPayService())->verifyNotify($params);
        } catch (\Throwable $e) {
            \think\facade\Log::warning('[recharge:notifyWisonPay] invalid notification');
            return response('invalid sign', 400, ['Content-Type' => 'text/plain;charset=utf-8']);
        }
        if ((string) $payment['code'] !== '0') {
            return response('success', 200, ['Content-Type' => 'text/plain;charset=utf-8']);
        }
        try {
            $this->service->confirmByGateway(
                (string) $payment['m_order_id'], (string) $payment['order_id'], '', 'WisonPay', $payment
            );
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:notifyWisonPay] order=' . (string) $payment['m_order_id']
                . ' error=' . $e->getMessage());
            return response('error', 500, ['Content-Type' => 'text/plain;charset=utf-8']);
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
            // 到账代币口径与网关一致：套餐按套餐 tokens，自选点数按报价 tokens（服务端会校验价格）
            $packageId = $this->request->post('package_id/d', 0);
            $tokens    = $this->request->post('tokens/d', 0);
            return $this->jsonSuccess(
                $this->service->submitFiatCoinOrder($userId, $fiatChannelId, $amount, $packageId ?: null, $tokens, [
                    'email' => (string) $this->request->post('payer_email', ''),
                    'mobile' => (string) $this->request->post('payer_mobile', ''),
                    'name' => (string) $this->request->post('payer_name', ''),
                    // 泰国 / 印尼的支付方式（qr/bank/wallet）与银行卡信息
                    'method' => (string) $this->request->post('payer_method', ''),
                    'bank' => (string) $this->request->post('payer_bank', ''),
                    'card' => (string) $this->request->post('payer_card', ''),
                ]),
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
