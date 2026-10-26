<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\facade\Log;

/**
 * Cregis 支付引擎（收银台）对接 —— 仅代收
 *
 *  - 签名：md5(API_KEY + 除 sign 外按 key 字典序拼接的 "keyvalue")
 *          系统参数 nonce（6 位）+ timestamp（13 位毫秒）
 *  - 建单：POST {base_url}/api/v2/checkout → cregis_id / checkout_url / payment_info
 *  - 查单：POST {base_url}/api/v2/order/info  → 按 cregis_id 查终态
 *  - 回调：POST notify_url（JSON，含 sign / event_name / event_type / data）
 *          必须返回 HTTP 200 + 纯文本 success（Cregis 只推一次，失败要人工重推）
 *
 * 金额：order_amount 按 order_currency 计价；法币订单币种建议 USD，
 *      用户在收银台自选支付币种（USDT-TRC20 / USDT-BEP20 等）由 Cregis 换算。
 */
final class CregisPayService
{
    private array $cfg;

    /** 订单状态 → 含义 */
    public const STATUS = [
        'new'          => '待支付',
        'paid'         => '已支付',
        'expired'      => '已过期',
        'paid_over'    => '超额支付',
        'paid_partial' => '部分支付',
        'canceled'     => '已取消/已退款',
    ];

    public function __construct()
    {
        $this->cfg = (array) config('cregis');
    }

    public function enabled(): bool
    {
        return (string) ($this->cfg['base_url'] ?? '') !== ''
            && (string) ($this->cfg['pid'] ?? '') !== ''
            && (string) ($this->cfg['api_key'] ?? '') !== '';
    }

    private function assertEnabled(): void
    {
        if (!$this->enabled()) {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Cregis 商户参数未配置（.env [CREGIS] 段）');
        }
    }

    /**
     * 创建收银台订单
     *
     * @param string $orderNo    本站订单号
     * @param string $amount     订单金额（按 $currency 计价，字符串避免浮点误差）
     * @param string $currency   订单币种（USD / HKD / USDT ...）
     * @param int    $userId     付款人 ID
     * @param string $notifyUrl  异步回调地址，留空走 config('cregis.notify_url')
     * @param string $successUrl 支付成功跳回
     * @param string $cancelUrl  取消支付跳回
     * @return array{cregis_id:string,checkout_url:string,expire_time:int,payment_info:array,raw:array}
     */
    public function createCheckout(
        string $orderNo,
        string $amount,
        string $currency,
        int $userId,
        string $notifyUrl = '',
        string $successUrl = '',
        string $cancelUrl = ''
    ): array {
        $this->assertEnabled();

        $notifyUrl = $notifyUrl !== '' ? $notifyUrl : (string) ($this->cfg['notify_url'] ?? '');
        if ($notifyUrl === '') {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Cregis 回调地址未配置（.env cregis.notify_url）');
        }
        $successUrl = $successUrl !== '' ? $successUrl : (string) ($this->cfg['return_url'] ?? '');
        $cancelUrl  = $cancelUrl !== '' ? $cancelUrl : $successUrl;

        $payload = [
            'pid'            => (int) $this->cfg['pid'],
            'order_id'       => $orderNo,
            'order_amount'   => $amount,
            'order_currency' => strtoupper($currency),
            'payer_id'       => 'user_' . $userId,
            // Cregis 在容差为 0 时要求 payer_email 必填；本站没有邮箱，用占位地址
            'payer_email'    => 'user' . $userId . '@' . $this->mailDomain(),
            'success_url'    => $this->absolute($successUrl),
            'cancel_url'     => $this->absolute($cancelUrl),
            'callback_url'   => $notifyUrl,
            'valid_time'     => (int) ($this->cfg['expire_minutes'] ?? 120),
        ];
        $tokens = trim((string) ($this->cfg['tokens'] ?? ''));
        if ($tokens !== '') {
            $payload['tokens'] = $tokens;
        }
        $lang = (string) ($this->cfg['language'] ?? '');
        if (in_array($lang, ['en', 'tc', 'sc'], true)) {
            $payload['language'] = $lang;
        }

        $data = $this->request('/api/v2/checkout', $payload);

        return [
            'cregis_id'    => (string) ($data['cregis_id'] ?? ''),
            'checkout_url' => (string) ($data['checkout_url'] ?? ''),
            'expire_time'  => (int) ($data['expire_time'] ?? 0),
            'payment_info' => (array) ($data['payment_info'] ?? []),
            'raw'          => $data,
        ];
    }

    /**
     * 按 Cregis 订单号查单
     *
     * @return array{status:string,order_id:string,pay_amount:string,pay_currency:string,tx_id:string,raw:array}
     */
    public function queryOrder(string $cregisId): array
    {
        $this->assertEnabled();
        if ($cregisId === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Cregis 查单缺少 cregis_id');
        }

        $data = $this->request('/api/v2/order/info', [
            'pid'       => (int) $this->cfg['pid'],
            'cregis_id' => $cregisId,
        ]);

        return [
            'status'       => (string) ($data['status'] ?? ''),
            'order_id'     => (string) ($data['order_id'] ?? ''),
            'pay_amount'   => (string) ($data['pay_amount'] ?? ''),
            'pay_currency' => (string) ($data['pay_currency'] ?? ''),
            'tx_id'        => (string) ($data['tx_id'] ?? ''),
            'raw'          => $data,
        ];
    }

    /**
     * 校验回调签名（失败抛异常）
     *
     * 回调 payload 里的 data 是嵌套对象，不参与签名拼接（Cregis 只对顶层字段签名）。
     */
    public function verifyNotify(array $payload): array
    {
        $sign = strtolower(trim((string) ($payload['sign'] ?? '')));
        if ($sign === '') {
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, 'Cregis 回调缺少 sign');
        }
        if (isset($payload['pid']) && (string) $payload['pid'] !== (string) ($this->cfg['pid'] ?? '')) {
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, 'Cregis 回调 pid 不匹配');
        }
        if (!hash_equals($this->sign($payload), $sign)) {
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, 'Cregis 回调验签失败');
        }
        return $payload;
    }

    /**
     * 签名：md5(API_KEY + 字典序拼接 keyvalue)，跳过 sign / 空值 / 嵌套对象
     */
    public function sign(array $payload): string
    {
        unset($payload['sign']);
        ksort($payload, SORT_STRING);
        $canonical = '';
        foreach ($payload as $k => $v) {
            if ($v === '' || $v === null || is_array($v)) {
                continue;
            }
            if (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            }
            $canonical .= $k . $v;
        }
        return strtolower(md5((string) ($this->cfg['api_key'] ?? '') . $canonical));
    }

    /**
     * 发送签名后的 POST JSON
     *
     * 网关错误只写日志，对外统一提示，避免把 IP 白名单 / 密钥等细节暴露给用户。
     */
    private function request(string $path, array $body): array
    {
        $base = (string) ($this->cfg['base_url'] ?? '');
        if ($base === '') {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Cregis Base URL 未配置');
        }

        $body['nonce']     = (string) random_int(100000, 999999);
        $body['timestamp'] = (int) round(microtime(true) * 1000);
        $body['sign']      = $this->sign($body);

        $json = (string) json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $client = new Client([
                'timeout'     => (int) ($this->cfg['timeout'] ?? 20),
                'http_errors' => false,
            ]);
            $resp    = $client->request('POST', $base . $path, [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => $json,
            ]);
            $status  = $resp->getStatusCode();
            $rawBody = (string) $resp->getBody();
            $decoded = json_decode($rawBody, true);
        } catch (GuzzleException $e) {
            Log::error('[cregis] request failed path=' . $path . ' err=' . $e->getMessage() . ' base=' . $base);
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Payment is temporarily unavailable, please try again later.');
        }

        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            Log::error('[cregis] http ' . $status . ' path=' . $path . ' body=' . mb_substr($rawBody, 0, 1000));
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Payment is temporarily unavailable, please try again later.');
        }

        $code = (string) ($decoded['code'] ?? '');
        if ($code !== '00000') {
            Log::error('[cregis] code=' . $code . ' msg=' . (string) ($decoded['msg'] ?? '') . ' path=' . $path);
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Payment is temporarily unavailable, please try again later.');
        }

        $data = $decoded['data'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }
        return $data;
    }

    /** success / cancel 地址必须是绝对 URL（Cregis 强校验 http/https） */
    private function absolute(string $url): string
    {
        if ($url !== '' && preg_match('#^https?://#i', $url)) {
            return $url;
        }
        $host = rtrim((string) request()->domain(), '/');
        return $url === '' ? $host . '/' : $host . '/' . ltrim($url, '/');
    }

    /** payer_email 占位域名：取回调地址的域名 */
    private function mailDomain(): string
    {
        $host = (string) parse_url((string) ($this->cfg['notify_url'] ?? ''), PHP_URL_HOST);
        return $host !== '' ? $host : 'example.com';
    }

    /** 支付成功状态：足额或超额 */
    public static function isPaid(string $status): bool
    {
        return in_array(strtolower($status), ['paid', 'paid_over'], true);
    }

    /** 终态未支付：过期 / 取消 */
    public static function isClosed(string $status): bool
    {
        return in_array(strtolower($status), ['expired', 'canceled'], true);
    }

    public static function statusText(string $status): string
    {
        return self::STATUS[strtolower($status)] ?? '未知状态';
    }
}
