<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\facade\Log;

/**
 * AIPowerPay 信用卡收银台（法币代收）
 *
 *  - 认证：Authorization: Basic base64(appKey:secretKey) + siteDomain 请求头
 *  - 建单：POST {base_url}/payment/gateway/order/createOrder  → 返回收银台地址
 *  - 查单：GET  {base_url}/payment/gateway/order/{order_no}    → orderStatus
 *  - 回调：POST notify_url（JSON），sign = HMAC-SHA256(
 *            top-level 字段按字典序拼 key=value&… + appKey, secretKey )
 *
 * 金额：amount 按订单币种（currency）计价，单位元，保留两位小数。
 * 3DS：走 hosted 收银台，由网关页面自行完成，本站不需要处理 challengeUrl。
 */
final class AiPowerPayService
{
    private array $cfg;

    /** orderStatus → 含义（网关枚举） */
    public const STATUS = [
        'PAY_WAIT'      => '待支付',
        'PAYING'        => '支付中',
        'PAY_SUCCESS'   => '支付成功',
        'PAY_FAIL'      => '支付失败',
        'PAY_TIMEOUT'   => '订单超时',
        'REFUND'        => '已退款',
        'PAY_CHALLENGE' => '等待3DS验证',
        'REFUND_PROCESS'=> '退款中',
        'REFUND_TIMEOUT'=> '退款超时',
        'REFUND_FAIL_1' => '退款失败',
        'PAY_GIVE_UP'   => '弃单',
    ];

    public function __construct()
    {
        $this->cfg = (array) config('aipowerpay');
    }

    public function enabled(): bool
    {
        return (string) ($this->cfg['base_url'] ?? '') !== ''
            && (string) ($this->cfg['app_key'] ?? '') !== ''
            && (string) ($this->cfg['secret_key'] ?? '') !== ''
            && (string) ($this->cfg['site_domain'] ?? '') !== '';
    }

    private function assertEnabled(): void
    {
        if (!$this->enabled()) {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'AIPowerPay 商户参数未配置（.env [AIPOWERPAY] 段）');
        }
    }

    /**
     * 创建收银台订单
     *
     * @param string $orderNo    本站订单号（≤60）
     * @param string $amount     金额，两位小数
     * @param string $currency   ISO 4217，如 USD
     * @param int    $userId     用户 ID
     * @param string $email      用户邮箱（没有则用占位地址）
     * @param string $notifyUrl  异步回调地址
     * @param string $returnUrl  同步跳回地址
     * @param array  $device     client_ip / client_agent / client_language
     * @return array{pay_url:string,gateway_id:string,raw:array}
     */
    public function createOrder(
        string $orderNo,
        string $amount,
        string $currency,
        int $userId,
        string $email,
        string $notifyUrl,
        string $returnUrl,
        array $device = []
    ): array {
        $this->assertEnabled();

        $notifyUrl = $notifyUrl !== '' ? $notifyUrl : (string) ($this->cfg['notify_url'] ?? '');
        if ($notifyUrl === '') {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'AIPowerPay 回调地址未配置（.env aipowerpay.notify_url）');
        }
        $returnUrl = $returnUrl !== '' ? $returnUrl : (string) ($this->cfg['return_url'] ?? '');
        if ($returnUrl !== '' && !preg_match('#^https?://#i', $returnUrl)) {
            $returnUrl = rtrim((string) request()->domain(), '/') . '/' . ltrim($returnUrl, '/');
        }

        $bill  = (array) ($this->cfg['bill'] ?? []);
        $items = [[
            'sku'   => 'tokens_' . $orderNo,
            'name'  => 'Tokens / Membership',
            'price' => $amount,
            'qty'   => 1,
        ]];

        $payload = [
            'order_no'        => $orderNo,
            'currency'        => $currency !== '' ? strtoupper($currency) : (string) ($this->cfg['currency'] ?? 'USD'),
            'body'            => (string) json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'return_uri'      => $returnUrl,
            'notify_url'      => $notifyUrl,
            'first_name'      => (string) ($bill['first_name'] ?? 'Customer'),
            'last_name'       => (string) ($bill['last_name'] ?? 'User'),
            'email'           => $email !== '' ? $email : ('user' . $userId . '@' . $this->mailDomain()),
            'telephone'       => (string) ($bill['telephone'] ?? '+12025550123'),
            'address'         => (string) ($bill['address'] ?? '350 5th Ave'),
            'city'            => (string) ($bill['city'] ?? 'New York'),
            'country'         => (string) ($bill['country'] ?? 'US'),
            'zip_code'        => (string) ($bill['zip_code'] ?? '10118'),
            'zone'            => (string) ($bill['zone'] ?? 'NY'),
            'amount'          => $amount,
            'invoice_id'      => $orderNo,
            'client_ip'       => (string) ($device['client_ip'] ?? ''),
            'client_agent'    => (string) ($device['client_agent'] ?? ''),
            'client_language' => (string) ($device['client_language'] ?? 'en-US,en;q=0.9'),
            'bn'              => (string) ($this->cfg['bn'] ?? 'wordpress'),
        ];

        $data = $this->request('POST', '/payment/gateway/order/createOrder', $payload);

        // 不同环境返回结构略有差异：兼容 bsiteUrl / redirectUrl / payUrl / url
        $payUrl = '';
        foreach (['cashierUrl', 'payUrl', 'pay_url', 'redirectUrl', 'redirect_url', 'bsiteUrl', 'url'] as $k) {
            $v = trim((string) ($data[$k] ?? ''));
            if ($v !== '') {
                $payUrl = $v;
                break;
            }
        }
        if ($payUrl === '') {
            Log::error('[aipowerpay] 建单成功但未找到收银台地址 order=' . $orderNo . ' resp=' . mb_substr(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 0, 800));
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Payment is temporarily unavailable, please try again later.');
        }

        return [
            'pay_url'    => $payUrl,
            'gateway_id' => (string) ($data['mainOrderNo'] ?? $data['orderNo'] ?? $data['id'] ?? ''),
            'raw'        => $data,
        ];
    }

    /**
     * 按商户订单号查单
     *
     * @return array{status:string,order_no:string,id:string,amount:string,currency:string,raw:array}
     */
    public function queryOrder(string $orderNo): array
    {
        $this->assertEnabled();
        $data = $this->request('GET', '/payment/gateway/order/' . rawurlencode($orderNo));

        return [
            'status'   => strtoupper((string) ($data['orderStatus'] ?? '')),
            'order_no' => (string) ($data['order_no'] ?? $orderNo),
            'id'       => (string) ($data['id'] ?? ''),
            'amount'   => (string) ($data['amount'] ?? ''),
            'currency' => (string) ($data['currency'] ?? ''),
            'raw'      => $data,
        ];
    }

    /**
     * 回调验签
     *
     * 新版：sign = hmac_sha256(字典序 "k=v&..." + appKey, secretKey)
     * 旧版兼容：token = sha256Upper(failure_code + invoice_id + order_no + secretKey)
     */
    public function verifyNotify(array $body): array
    {
        $sign = strtolower(trim((string) ($body['sign'] ?? '')));
        if ($sign !== '') {
            $data = $body;
            unset($data['sign'], $data['token']);
            ksort($data, SORT_STRING);
            $pairs = [];
            foreach ($data as $k => $v) {
                if ($v === null) {
                    continue;
                }
                if (is_array($v)) {
                    $v = (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $pairs[] = $k . '=' . $v;
            }
            $expected = hash_hmac(
                'sha256',
                implode('&', $pairs) . (string) ($this->cfg['app_key'] ?? ''),
                (string) ($this->cfg['secret_key'] ?? '')
            );
            if (!hash_equals($expected, $sign)) {
                throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, 'AIPowerPay 回调验签失败');
            }
            return $body;
        }

        // 旧版 token 校验（Query 参数老回调）
        $token = strtoupper(trim((string) ($body['token'] ?? '')));
        if ($token !== '') {
            $src = (string) ($body['failure_code'] ?? '')
                . (string) ($body['invoice_id'] ?? '')
                . (string) ($body['order_no'] ?? '')
                . (string) ($this->cfg['secret_key'] ?? '');
            if (hash_equals(strtoupper(hash('sha256', $src)), $token)) {
                return $body;
            }
        }

        throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, 'AIPowerPay 回调缺少有效签名');
    }

    /** 支付成功状态 */
    public static function isPaid(string $status): bool
    {
        return strtoupper($status) === 'PAY_SUCCESS';
    }

    /** 终态失败 */
    public static function isFailed(string $status): bool
    {
        return in_array(strtoupper($status), ['PAY_FAIL', 'PAY_TIMEOUT', 'PAY_GIVE_UP'], true);
    }

    public static function statusText(string $status): string
    {
        return self::STATUS[strtoupper($status)] ?? '未知状态';
    }

    /**
     * 发送请求：Basic 认证 + siteDomain；固定浏览器 UA（网关前面有 Cloudflare，默认 UA 会被拦）
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $base = (string) ($this->cfg['base_url'] ?? '');
        $url  = $base . $path;

        $headers = [
            'Authorization' => 'Basic ' . base64_encode(
                (string) ($this->cfg['app_key'] ?? '') . ':' . (string) ($this->cfg['secret_key'] ?? '')
            ),
            'siteDomain'    => (string) ($this->cfg['site_domain'] ?? ''),
            'Accept'        => 'application/json, text/plain, */*',
            'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
        ];

        try {
            $client = new Client(['timeout' => (int) ($this->cfg['timeout'] ?? 20), 'http_errors' => false]);
            $opts = ['headers' => $headers];
            if ($method === 'POST') {
                $opts['json'] = $payload;
            } else {
                $opts['query'] = $payload;
            }
            $resp    = $client->request($method, $url, $opts);
            $status  = $resp->getStatusCode();
            $rawBody = (string) $resp->getBody();
            $decoded = json_decode($rawBody, true);
        } catch (GuzzleException $e) {
            Log::error('[aipowerpay] request failed path=' . $path . ' err=' . $e->getMessage() . ' base=' . $base);
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Payment is temporarily unavailable, please try again later.');
        }

        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            Log::error('[aipowerpay] http ' . $status . ' path=' . $path . ' body=' . mb_substr($rawBody, 0, 800));
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Payment is temporarily unavailable, please try again later.');
        }

        $code = $decoded['code'] ?? null;
        if ((int) $code !== 0) {
            Log::error('[aipowerpay] code=' . (string) $code . ' msg=' . (string) ($decoded['msg'] ?? '') . ' path=' . $path);
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Payment is temporarily unavailable, please try again later.');
        }

        $data = $decoded['data'] ?? [];
        return is_array($data) ? $data : [];
    }

    /** 占位邮箱域名：取站点域名的 host */
    private function mailDomain(): string
    {
        $host = (string) parse_url((string) ($this->cfg['site_domain'] ?? ''), PHP_URL_HOST);
        return $host !== '' ? $host : 'example.com';
    }
}
