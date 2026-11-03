<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use GuzzleHttp\Client;
use think\facade\Log;

/**
 * HissPay 代收（托管收银台）
 *
 * 下单 /transaction/payin/v1/apply，查单 /transaction/payin/v1/query。
 * 付款资料（姓名/邮箱/手机号/付款人 IP）由本站收集后传给网关，卡信息只在托管页填写。
 *
 * 签名：请求体 + 请求头 appId/timestamp/nonce，按 key 升序拼 k=v&k=v，
 *       末尾直接追加 appSecret，SHA-256 小写十六进制。
 */
final class HissPayService
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = (array) config('hisspay');
    }

    public function enabled(): bool
    {
        return (string) ($this->cfg['gateway'] ?? '') !== ''
            && (string) ($this->cfg['app_id'] ?? '') !== ''
            && (string) ($this->cfg['app_secret'] ?? '') !== '';
    }

    /**
     * 付款人资料校验：姓名 / 邮箱 / 手机号都是网关必填
     */
    public static function validatePayer(array $payer): array
    {
        $email = trim((string) ($payer['email'] ?? ''));
        $name  = trim((string) ($payer['name'] ?? ''));
        // 只保留数字，避免 +、空格、横线被网关拒绝
        $phone = preg_replace('/[^0-9]/', '', (string) ($payer['mobile'] ?? '')) ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Please enter your real email address.');
        }
        if ($name === '' || mb_strlen($name) > 100 || preg_match('/[\x00-\x1f]/', $name)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Please enter your full name.');
        }
        if (!preg_match('/^[0-9]{5,15}$/D', $phone)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Please enter a valid phone number.');
        }

        return ['email' => $email, 'name' => $name, 'phone' => $phone];
    }

    /**
     * 创建代收订单
     *
     * @param array $payer 已通过 validatePayer 的资料
     */
    public function createOrder(
        string $orderNo,
        string $amount,
        string $currency,
        int $userId,
        array $payer,
        string $returnUrl,
        string $product,
        string $clientIp
    ): array {
        $product = trim($product);
        if ($product === '') {
            $product = (string) ($this->cfg['product'] ?? '');
        }
        if ($product === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Payment product is not configured.');
        }
        if ($userId <= 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Invalid user.');
        }

        // 该产品只接受固定金额档位（见 .env [HISSPAY] AMOUNTS），不在档位内直接拒绝
        $amt     = number_format((float) $amount, 2, '.', '');
        $allowed = (array) ($this->cfg['amounts'] ?? []);
        if ($allowed !== [] && !in_array($amt, $allowed, true)) {
            throw new BusinessException(
                ResultCode::PARAM_ERROR,
                'This payment method is not available for the selected amount. Please choose another payment method.'
            );
        }

        $body = [
            'merchantOrderNo' => $orderNo,
            'amount'          => $amt,
            'currency'        => strtoupper($currency),
            'product'         => $product,
            'name'            => (string) $payer['name'],
            'phone'           => (string) $payer['phone'],
            'email'           => (string) $payer['email'],
            'userId'          => (string) $userId,
            'clientIp'        => $clientIp,
        ];
        $payType = trim((string) ($this->cfg['pay_type'] ?? ''));
        if ($payType !== '') {
            $body['payType'] = $payType;
        }
        if ((string) ($this->cfg['notify_url'] ?? '') !== '') {
            $body['notifyUrl'] = (string) $this->cfg['notify_url'];
        }
        if ($returnUrl !== '') {
            $body['returnUrl'] = $returnUrl;
        }

        $data = $this->request('/transaction/payin/v1/apply', $body);
        $url  = (string) ($data['payUrl'] ?? '');
        if ((string) ($data['merchantOrderNo'] ?? '') !== $orderNo
            || (string) ($data['orderNo'] ?? '') === ''
            || !filter_var($url, FILTER_VALIDATE_URL)
            || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Unable to open payment checkout.', 200,
                new \RuntimeException('bad checkout: ' . mb_substr(json_encode($data, JSON_UNESCAPED_SLASHES) ?: '', 0, 200)));
        }

        return ['pay_url' => $url, 'gateway_id' => (string) $data['orderNo']];
    }

    /**
     * 查单：按商户订单号查询，返回网关 data
     */
    public function queryOrder(string $orderNo): array
    {
        $data = $this->request('/transaction/payin/v1/query', ['merchantOrderNo' => $orderNo]);
        if ((string) ($data['merchantOrderNo'] ?? '') !== $orderNo) {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Payment order mismatch.');
        }
        return $data;
    }

    public static function isPaid(array $data): bool
    {
        return (string) ($data['payStatus'] ?? '') === '1';
    }

    /** 0 只是「未报告成功」，只有 2 / paymentStatus=FAILED 才是明确失败 */
    public static function isFailed(array $data): bool
    {
        return (string) ($data['payStatus'] ?? '') === '2'
            || strtoupper((string) ($data['paymentStatus'] ?? '')) === 'FAILED';
    }

    /**
     * 异步通知验签
     *
     * @param array $body     通知体（所有非 null 字段参与签名）
     * @param array $headers  已转小写键名：appid / timestamp / nonce / sign
     */
    public function verifyNotify(array $body, array $headers): array
    {
        $appId     = trim((string) ($headers['appid'] ?? ''));
        $timestamp = trim((string) ($headers['timestamp'] ?? ''));
        $nonce     = trim((string) ($headers['nonce'] ?? ''));
        $sign      = strtolower(trim((string) ($headers['sign'] ?? '')));

        if ($appId === '' || $appId !== (string) ($this->cfg['app_id'] ?? '')) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Unknown merchant.');
        }
        if (!preg_match('/^[0-9]{13}$/D', $timestamp)
            || abs((int) (microtime(true) * 1000) - (int) $timestamp) > 300000) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Expired notification.');
        }
        if (strlen($nonce) < 10 || !preg_match('/^[0-9a-f]{64}$/D', $sign)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Invalid payment signature.');
        }

        $params = $body;
        $params['appId']     = $appId;
        $params['timestamp'] = $timestamp;
        $params['nonce']     = $nonce;
        $expected = strtolower(hash('sha256', self::canonical($params) . (string) ($this->cfg['app_secret'] ?? '')));
        if (!hash_equals($expected, $sign)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Invalid payment signature.');
        }

        $status = (string) ($body['status'] ?? '');
        if (empty($body['merchantOrderNo']) || empty($body['orderNo'])
            || empty($body['currency']) || !in_array($status, ['1', '2'], true)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Invalid payment notification.');
        }
        if (!is_numeric($body['amount'] ?? '') || (float) $body['amount'] <= 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Invalid payment notification.');
        }

        $body['_appId'] = $appId;
        return $body;
    }

    /**
     * 回调入账前核对：应用、商户订单号、平台订单号、币种、金额
     */
    public static function assertMatchesOrder(array $order, array $payment): void
    {
        $cfg = (array) config('hisspay');
        if ((string) ($order['pay_channel'] ?? '') !== 'hisspay'
            || (string) ($payment['status'] ?? '') !== '1'
            || (string) ($payment['_appId'] ?? '') !== (string) ($cfg['app_id'] ?? '')
            || (string) ($order['order_no'] ?? '') !== (string) ($payment['merchantOrderNo'] ?? '')
            || strtoupper((string) ($order['pay_currency'] ?? '')) !== strtoupper((string) ($payment['currency'] ?? ''))
            || ((string) ($order['system_ref'] ?? '') !== ''
                && (string) $order['system_ref'] !== (string) ($payment['orderNo'] ?? ''))
            || abs((float) ($order['pay_amount'] ?? 0) - (float) ($payment['amount'] ?? 0)) > 0.011) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Payment does not match the order.');
        }
    }

    /** 待签名串：去掉 sign / null，按 key 升序拼 k=v，不做 URL 编码 */
    public static function canonical(array $params): string
    {
        unset($params['sign']);
        ksort($params, SORT_STRING);
        $parts = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (!is_scalar($value)) {
                throw new BusinessException(ResultCode::PARAM_ERROR, 'Invalid payment signature fields.');
            }
            $parts[] = $key . '=' . (string) $value;
        }
        return implode('&', $parts);
    }

    private function request(string $path, array $body): array
    {
        if (!$this->enabled()) {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'This payment method is not available yet.');
        }
        foreach ($body as $value) {
            if ($value === null) {
                throw new BusinessException(ResultCode::PARAM_ERROR, 'Invalid payment fields.');
            }
        }

        $appId     = (string) $this->cfg['app_id'];
        $timestamp = (string) (int) floor(microtime(true) * 1000);
        $nonce     = bin2hex(random_bytes(16));
        $sign      = strtolower(hash('sha256', self::canonical(array_merge($body, [
            'appId'     => $appId,
            'timestamp' => $timestamp,
            'nonce'     => $nonce,
        ])) . (string) $this->cfg['app_secret']));

        try {
            $response = (new Client())->post((string) $this->cfg['gateway'] . $path, [
                'json' => $body,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'appId'        => $appId,
                    'timestamp'    => $timestamp,
                    'nonce'        => $nonce,
                    'sign'         => $sign,
                ],
                'timeout'         => (int) ($this->cfg['timeout'] ?? 20),
                'connect_timeout' => 10,
                'http_errors'     => false,
                'allow_redirects' => false,
            ]);
            $raw  = (string) $response->getBody();
            $json = json_decode($raw, true);
            if ($response->getStatusCode() !== 200 || !is_array($json)
                || (int) ($json['code'] ?? -1) !== 0 || !is_array($json['data'] ?? null)) {
                throw new \RuntimeException(
                    'Gateway rejected request, HTTP=' . $response->getStatusCode()
                    . ' code=' . (string) ($json['code'] ?? 'unknown')
                    . ' msg=' . mb_substr((string) ($json['msg'] ?? ''), 0, 120)
                );
            }
            if ((string) ($json['data']['appId'] ?? $appId) !== $appId) {
                throw new \RuntimeException('Merchant response mismatch');
            }
            return $json['data'];
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('[hisspay] ' . $path . ' ' . $e->getMessage());
            throw new BusinessException(
                ResultCode::SERVER_ERROR,
                'Payment is temporarily unavailable. Please try again later.',
                200,
                $e
            );
        }
    }
}
