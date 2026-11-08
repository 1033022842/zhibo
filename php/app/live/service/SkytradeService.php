<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;

/**
 * Skytrade 固定商品链接收款
 *
 * 下单：按「业务类型 + 订单金额」从 config('skytrade.links') 取固定商品地址，
 *       追加 order_no 后返回给前台跳转，用户到第三方商品页完成付款。
 * 回调：第三方付款后调用 /api/v1/recharge/notifySkytrade（JSON），携带
 *       mit / order_no / amountTotal / event / paymentStatus 等字段。
 *       验签二选一：
 *         - JSON 字段 sign = md5(order_no + notify_secret)（32 位小写）
 *         - 请求头 X-SkyTrade-Signature: sha256=<HMAC-SHA256(原始 body, notify_secret)>
 *       联调期可用 .env SKYTRADE_ALLOW_UNSIGNED=1 临时放行“没有签名”的回调，
 *       生产必须为 0。
 *
 * 注意：第三方商品链接的价格是固定的，所以订单金额必须精确命中映射表；
 *       同一金额在订阅/充值下可能是不同商品，故映射按 vip / coin 分开。
 *       回调 amountTotal 单位为「分」，这里统一换算成 USD。
 */
final class SkytradeService
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = (array) config('skytrade');
    }

    /**
     * 取固定商品付款地址，并把本站订单号带过去（第三方回调时原样带回）
     *
     * @param string $bizType vip=会员订阅 / coin=代币充值
     * @param string $usd     订单 USD 金额（两位小数）
     */
    public function payUrl(string $bizType, string $usd, string $orderNo): string
    {
        $group  = $bizType === 'vip' ? 'vip' : 'coin';
        $amount = self::money($usd);
        $url    = (string) ($this->cfg['links'][$group][$amount] ?? '');

        if ($url === '') {
            return $this->customAmountUrl($amount, $orderNo);
        }

        $sep = str_contains($url, '?') ? '&' : '?';
        return $url . $sep . 'order_no=' . urlencode($orderNo);
    }

    /**
     * 自定义金额付款地址（金额由用户输入，第三方按 amount 参数收款）
     *
     * 用于「自选金额」：金额大概率不在固定商品映射表里，必须走这个入口。
     * amount 是两位小数的 USD（不是分），传错第三方会退回商品页。
     */
    public function customAmountUrl(string $usd, string $orderNo): string
    {
        $custom = (string) ($this->cfg['custom_url'] ?? '');
        if ($custom === '') {
            throw new BusinessException(
                ResultCode::PARAM_ERROR,
                '该金额暂不支持当前支付方式（$' . self::money($usd) . '），请选择其他套餐或联系客服'
            );
        }
        $sep = str_contains($custom, '?') ? '&' : '?';
        return $custom . $sep . 'amount=' . self::money($usd) . '&order_no=' . urlencode($orderNo);
    }

    /**
     * 校验并归一化第三方回调（失败抛异常）
     *
     * @param array  $params  解析后的 JSON（或表单）参数
     * @param string $rawBody 原始请求体，HMAC 验签用
     * @param array  $headers 请求头，取 X-SkyTrade-Signature
     *
     * @return array{order_no:string,amount:string,trade_no:string,event:string,payment_status:string,product:string,mit:string}
     */
    public function verifyNotify(array $params, string $rawBody = '', array $headers = []): array
    {
        $orderNo = trim((string) ($params['order_no']
            ?? $params['out_trade_no']
            ?? $params['merchant_order_id']
            ?? ''));
        if ($orderNo === '') {
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, 'Skytrade 回调缺少 order_no');
        }

        $mit      = strtolower(trim((string) ($params['mit'] ?? '')));
        $merchant = strtolower(trim((string) ($this->cfg['merchant_id'] ?? 'sugus')));
        if ($merchant !== '' && $mit !== $merchant) {
            throw new BusinessException(
                ResultCode::PAY_CALLBACK_FAIL,
                'Skytrade 回调商户标识不匹配（mit=' . $mit . '）'
            );
        }

        $secret = (string) ($this->cfg['notify_secret'] ?? '');
        if ($secret === '') {
            throw new BusinessException(
                ResultCode::SERVER_ERROR,
                'Skytrade 回调密钥未配置（.env [SKYTRADE] NOTIFY_SECRET）'
            );
        }

        // 1) 请求头 HMAC-SHA256（推荐）：sha256=<hex>，前缀可省略
        $headerSign = '';
        foreach ($headers as $key => $val) {
            if (strtolower((string) $key) === 'x-skytrade-signature') {
                $headerSign = is_array($val) ? (string) reset($val) : (string) $val;
                break;
            }
        }
        $headerSign = strtolower(trim($headerSign));
        if (str_starts_with($headerSign, 'sha256=')) {
            $headerSign = substr($headerSign, 7);
        }

        // 2) JSON 字段 sign = md5(order_no + secret)
        $fieldSign = strtolower(trim((string) ($params['sign'] ?? '')));

        $hasSign = $headerSign !== '' || $fieldSign !== '';
        $valid   = false;
        if ($headerSign !== '' && $rawBody !== '') {
            $valid = hash_equals(hash_hmac('sha256', $rawBody, $secret), $headerSign);
        }
        if (!$valid && $fieldSign !== '') {
            $valid = hash_equals(md5($orderNo . $secret), $fieldSign);
        }
        if (!$valid) {
            // 联调开关：只放行“完全没带签名”的请求；带了但错了仍然拒绝
            $allowUnsigned = (bool) ($this->cfg['allow_unsigned'] ?? false);
            if ($hasSign || !$allowUnsigned) {
                throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, 'Skytrade 回调验签失败');
            }
        }

        // amountTotal 单位是分（1099 => 10.99）；也兼容直接传 USD 的 amount
        $amountTotal = trim((string) ($params['amountTotal'] ?? ''));
        if ($amountTotal !== '' && is_numeric($amountTotal)) {
            $amount = self::money(((float) $amountTotal) / 100);
        } else {
            $amount = trim((string) ($params['amount'] ?? $params['money'] ?? $params['total'] ?? ''));
        }

        $product = '';
        if (isset($params['items'][0]['slug'])) {
            $product = trim((string) $params['items'][0]['slug']);
        } elseif (isset($params['product'])) {
            $product = trim((string) $params['product']);
        }

        return [
            'order_no'       => $orderNo,
            'amount'         => $amount,
            'trade_no'       => trim((string) ($params['reference']
                ?? $params['trade_no']
                ?? $params['transaction_id']
                ?? $params['txid']
                ?? '')),
            'event'          => strtolower(trim((string) ($params['event'] ?? ''))),
            'payment_status' => strtoupper(trim((string) ($params['paymentStatus']
                ?? $params['status']
                ?? ''))),
            'product'        => $product,
            'mit'            => $mit,
        ];
    }

    /**
     * 金额统一成两位小数字符串（10.99 / 117.00），作为映射表 key
     */
    public static function money($amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
