<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use GuzzleHttp\Client;
use think\facade\Log;

/**
 * WisonPay 代收：四个地区各一套接口
 *
 *   US  /interface/us/payin.php  美元（浮点）  手机号 1 开头
 *   IN  /interface/payin.php     卢比（整数）  手机号 10 位不带 91
 *   TH  /interface/th/payin.php  泰铢（整数）  channelId 58=二维码 / 5=银行卡
 *   ID  /interface/id/payin.php  印尼盾（整数）paymentType QR / WA / VA
 *
 * 签名统一 RSA-SHA512：去掉 sign，其余按 key 升序拼 k=v&k=v（空值保留，不做 URL 编码）。
 */
final class WisonPayService
{
    /** 美国可选的收银台支付方式（不传则走网关默认 cashapp） */
    public const METHODS = ['cashapp', 'googlepayus', 'applepayus'];

    /** 按币种反查地区（查单 / 回调补单时用） */
    private const CURRENCY_REGION = ['USD' => 'US', 'INR' => 'IN', 'THB' => 'TH', 'IDR' => 'ID'];

    private array $cfg;

    public function __construct()
    {
        $this->cfg = (array) config('wisonpay');
    }

    /** 四个地区配置（appid 缺省时整体不可用） */
    public static function regions(): array
    {
        return (array) (config('wisonpay.regions') ?? []);
    }

    public static function regionOf(string $region): array
    {
        $region = strtoupper(trim($region));
        $regions = self::regions();
        if (!isset($regions[$region]) || (string) ($regions[$region]['appid'] ?? '') === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Unsupported payment region.');
        }
        return $regions[$region];
    }

    /** 币种 → 地区（USD/INR/THB/IDR） */
    public static function regionOfCurrency(string $currency): string
    {
        return (string) (self::CURRENCY_REGION[strtoupper($currency)] ?? '');
    }

    public function enabled(): bool
    {
        $hasAppid = false;
        foreach (self::regions() as $r) {
            if ((string) ($r['appid'] ?? '') !== '') {
                $hasAppid = true;
                break;
            }
        }
        $hasKey = !empty($this->cfg['private_key'])
            || (!empty($this->cfg['private_key_file']) && is_readable((string) $this->cfg['private_key_file']));

        return $hasAppid && $hasKey && !empty($this->cfg['platform_public_key']) && !empty($this->cfg['notify_url']);
    }

    /**
     * 付款资料校验：各地区的手机号格式不同
     *
     * US 形如 14241276442；IN 10 位不带 91；TH 10 位；ID 以 0 或 62 开头
     */
    public static function validatePayer(array $payer, string $region = 'US'): array
    {
        $region = strtoupper($region);
        $email = trim((string) ($payer['email'] ?? ''));
        $name = trim((string) ($payer['name'] ?? ''));
        $mobile = self::normalizeMobile((string) ($payer['mobile'] ?? ''), $region);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Please enter your real email address.');
        }
        if ($name === '' || mb_strlen($name) > 100 || preg_match('/[\x00-\x1f]/', $name)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Please enter your full name.');
        }
        if (!preg_match('/^[0-9]{8,15}$/D', $mobile)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Please enter a valid phone number for the selected region.');
        }
        if ($region === 'IN' && strlen($mobile) !== 10) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'India phone number must be 10 digits without +91.');
        }

        $out = ['email' => $email, 'mobile' => $mobile, 'name' => $name];

        // 泰国 / 印尼还要带上支付方式：qr（默认）/ bank / wallet
        if (in_array($region, ['TH', 'ID'], true)) {
            $method = strtolower(trim((string) ($payer['method'] ?? '')));
            $out['method'] = $method === '' ? 'qr' : $method;
            $out['bank'] = strtoupper(trim((string) ($payer['bank'] ?? '')));
            $out['card'] = preg_replace('/\s+/', '', (string) ($payer['card'] ?? ''));
        }

        return $out;
    }

    /**
     * 手机号归一：去符号，再按地区补/去国家码
     */
    public static function normalizeMobile(string $mobile, string $region): string
    {
        $m = preg_replace('/[^0-9]/', '', $mobile) ?? '';
        switch (strtoupper($region)) {
            case 'IN':
                // 网关只认 10 位，不带 +91
                if (strlen($m) === 12 && str_starts_with($m, '91')) {
                    $m = substr($m, 2);
                }
                return $m;
            case 'ID':
                // 网关认 0 开头或 62 开头
                if (str_starts_with($m, '62')) {
                    return $m;
                }
                if (str_starts_with($m, '0')) {
                    return $m;
                }
                return str_starts_with($m, '8') ? '0' . $m : $m;
            case 'TH':
                // 本地 10 位（0 开头）；+66 换成本地 0 开头
                if (str_starts_with($m, '66')) {
                    return '0' . substr($m, 2);
                }
                return $m;
            default:
                // US：保留 1 开头的 11 位
                return $m;
        }
    }

    /** 不使用浮点差值核对支付金额；仅允许正数和最多两位小数。 */
    public static function cents($amount): int
    {
        $value = (string) $amount;
        if (!preg_match('/^([0-9]{1,9})(?:\.([0-9]{1,2}))?$/D', $value, $m)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Invalid payment amount.');
        }
        $cents = (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
        if ($cents <= 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Invalid payment amount.');
        }
        return $cents;
    }

    /** 金额 → 该地区的最小单位整数（US 分，其余整币），用于回调金额核对 */
    private static function minor($amount, int $decimals): int
    {
        $value = (float) $amount;
        return $decimals > 0 ? (int) round($value * 100) : (int) round($value);
    }

    /**
     * 创建代收订单
     *
     * @param array $opts method(TH/ID: qr|bank|wallet) / bank / card
     */
    public function createOrder(string $orderNo, string $amount, int $userId, string $region, array $payer, array $opts = []): array
    {
        $cfg = self::regionOf($region);
        $region = strtoupper($region);
        $payer = self::validatePayer($payer, $region);
        self::cents($amount);
        if ($userId <= 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Invalid user.');
        }

        $decimals = (int) ($cfg['decimals'] ?? 2);
        $params = [
            'appid' => (string) $cfg['appid'],
            'm_order_id' => $orderNo,
            'amount' => $decimals > 0
                ? round((float) $amount, 2)
                : (int) round((float) $amount),
            'user_id' => (string) $userId,
            'desc' => 'Tokens / Membership',
            'email' => $payer['email'],
            'mobile' => $payer['mobile'],
            'accountOwner' => $payer['name'],
            'notify_url' => (string) ($this->cfg['notify_url'] ?? ''),
            'extra' => '',
        ];
        if ((string) ($cfg['country'] ?? '') !== '') {
            $params['country'] = (string) $cfg['country'];
        }

        if ($region === 'US') {
            // paymentType 可选；不传用网关默认 cashapp
            $method = strtolower(trim((string) ($opts['method'] ?? '')));
            if ($method !== '' && in_array($method, self::METHODS, true)) {
                $params['paymentType'] = $method;
            }
        } elseif ($region === 'TH') {
            $bank = ($payer['method'] ?? '') === 'bank';
            $params['channelId'] = $bank ? 5 : 58;
            if ($bank) {
                $cardNo = (string) ($payer['card'] ?? '');
                $bankCode = (string) ($payer['bank'] ?? '');
                if (!preg_match('/^[0-9]{8,20}$/D', $cardNo) || $bankCode === '') {
                    throw new BusinessException(ResultCode::PARAM_ERROR, 'Please enter the card number and bank.');
                }
                $params['accountId'] = $cardNo;
                $params['bankCode'] = $bankCode;
            }
        } elseif ($region === 'ID') {
            $type = strtoupper((string) ($payer['method'] ?? 'qr'));
            $type = $type === 'BANK' ? 'VA' : ($type === 'WALLET' ? 'WA' : ($type === 'QR' ? 'QR' : 'QR'));
            $params['paymentType'] = $type;
            if ($type === 'VA') {
                $bankCode = (string) ($payer['bank'] ?? '');
                if ($bankCode === '') {
                    throw new BusinessException(ResultCode::PARAM_ERROR, 'Please select your bank.');
                }
                $params['bank'] = $bankCode;
            }
        }

        $data = $this->request((string) $cfg['path'], $params, (string) $cfg['appid']);
        $url = (string) ($data['payurl'] ?? '');
        if ((string) ($data['m_order_id'] ?? '') !== $orderNo
            || empty($data['order_id']) || !filter_var($url, FILTER_VALIDATE_URL)
            || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            // 用户看到的仍是友好文案；真实报文挂在 previous 上，由后台写进订单备注方便排查
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Unable to open payment checkout.', 200,
                new \RuntimeException('bad checkout: ' . mb_substr(json_encode($data, JSON_UNESCAPED_SLASHES) ?: '', 0, 160)));
        }
        return ['pay_url' => $url, 'gateway_id' => (string) $data['order_id']];
    }

    /**
     * 查单：路径按地区区分
     */
    public function queryOrder(string $gatewayId, string $orderNo, string $region = 'US'): array
    {
        if ($gatewayId === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Missing gateway order number.');
        }
        $cfg = self::regionOf($region);
        $data = $this->request((string) $cfg['query'], [
            'order_id' => $gatewayId,
            'appid' => (string) $cfg['appid'],
            'timestamp' => time(),
        ], (string) $cfg['appid']);
        $this->validatePayment($data, (string) $cfg['appid']);
        if ((string) $data['order_id'] !== $gatewayId || (string) $data['m_order_id'] !== $orderNo) {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Payment order mismatch.');
        }
        return $data;
    }

    /**
     * 异步回调：appid 决定地区
     */
    public function verifyNotify(array $params): array
    {
        $appid = (string) ($params['appid'] ?? '');
        $region = $this->regionOfAppid($appid);
        if ($region === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Unknown merchant.');
        }
        $this->verifySignature($params);
        $this->validatePayment($params, $appid);
        return $params;
    }

    /** appid → 地区代码 */
    public function regionOfAppid(string $appid): string
    {
        foreach (self::regions() as $region => $cfg) {
            if ((string) ($cfg['appid'] ?? '') === $appid) {
                return (string) $region;
            }
        }
        return '';
    }

    private function validatePayment(array $params, string $appid): void
    {
        if ((string) ($params['appid'] ?? '') !== $appid
            || empty($params['order_id']) || empty($params['m_order_id'])
            || !in_array((string) ($params['code'] ?? ''), ['0', '1', '2'], true)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Invalid payment notification.');
        }
        self::cents($params['amount'] ?? '');
    }

    /**
     * 回调入账前核对：订单币种决定地区，金额按该地区精度比较
     */
    public static function assertMatchesOrder(array $order, array $payment): void
    {
        $region = self::regionOfCurrency((string) ($order['pay_currency'] ?? ''));
        $cfg = self::regions()[$region] ?? null;
        if ((string) ($order['pay_channel'] ?? '') !== 'wisonpay' || $region === '' || $cfg === null
            || (string) ($payment['appid'] ?? '') !== (string) $cfg['appid']
            || (string) ($order['order_no'] ?? '') !== (string) ($payment['m_order_id'] ?? '')
            || (string) ($payment['code'] ?? '') !== '0'
            || ((string) ($order['system_ref'] ?? '') !== ''
                && (string) $order['system_ref'] !== (string) ($payment['order_id'] ?? ''))
            || self::minor($order['pay_amount'] ?? 0, (int) $cfg['decimals'])
                !== self::minor($payment['amount'] ?? 0, (int) $cfg['decimals'])) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Payment does not match the order.');
        }
    }

    /** 文档要求空值也参与签名，不做 URL 编码或删空值。 */
    public static function canonical(array $params): string
    {
        unset($params['sign']);
        ksort($params, SORT_STRING);
        $parts = [];
        foreach ($params as $key => $value) {
            if (!is_scalar($value) && $value !== null) {
                throw new BusinessException(ResultCode::PARAM_ERROR, 'Invalid payment signature fields.');
            }
            $parts[] = $key . '=' . (string) $value;
        }
        return implode('&', $parts);
    }

    private static function pem(string $key, string $type): string
    {
        $key = trim(str_replace('\\n', "\n", $key));
        if (str_contains($key, '-----BEGIN ')) {
            return $key;
        }
        return "-----BEGIN {$type}-----\n" . chunk_split(preg_replace('/\s+/', '', $key), 64, "\n")
            . "-----END {$type}-----\n";
    }

    private function verifySignature(array $params): void
    {
        $signature = base64_decode((string) ($params['sign'] ?? ''), true);
        $key = openssl_pkey_get_public(self::pem((string) ($this->cfg['platform_public_key'] ?? ''), 'PUBLIC KEY'));
        if (!$key || $signature === false || $signature === ''
            || openssl_verify(self::canonical($params), $signature, $key, OPENSSL_ALGO_SHA512) !== 1) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Invalid payment signature.');
        }
    }

    private function request(string $path, array $params, string $appid): array
    {
        if (!$this->enabled()) {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'This payment method is not available yet.');
        }
        try {
            $private = (string) ($this->cfg['private_key'] ?? '');
            if ($private === '') {
                $private = (string) file_get_contents($this->cfg['private_key_file']);
            }
            $key = openssl_pkey_get_private(self::pem($private, 'PRIVATE KEY'));
            if (!$key || !openssl_sign(self::canonical($params), $signature, $key, OPENSSL_ALGO_SHA512)) {
                throw new \RuntimeException('Merchant signing key is invalid');
            }
            $params['sign'] = base64_encode($signature);
            $base = (string) ($this->cfg['base_url'] ?? '');
            if (strtolower((string) parse_url($base, PHP_URL_SCHEME)) !== 'https') {
                throw new \RuntimeException('Gateway must use HTTPS');
            }
            $response = (new Client())->post($base . $path, [
                'json' => $params, 'timeout' => (int) ($this->cfg['timeout'] ?? 20),
                'connect_timeout' => 10, 'http_errors' => false, 'allow_redirects' => false,
            ]);
            $body = json_decode((string) $response->getBody(), true);
            if ($response->getStatusCode() !== 200 || !is_array($body)
                || (string) ($body['ret'] ?? '') !== '0' || !is_array($body['data'] ?? null)) {
                throw new \RuntimeException('Gateway rejected request, HTTP=' . $response->getStatusCode()
                    . ' ret=' . (string) ($body['ret'] ?? 'unknown')
                    . ' msg=' . mb_substr((string) ($body['msg'] ?? ''), 0, 80));
            }
            $this->verifySignature($body['data']);
            if ((string) ($body['data']['appid'] ?? '') !== $appid) {
                throw new \RuntimeException('Merchant response mismatch');
            }
            return $body['data'];
        } catch (\Throwable $e) {
            Log::error('[wisonpay] ' . $path . ' ' . $e->getMessage());
            // 友好文案给用户，原始原因通过 previous 交给订单备注
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Payment is temporarily unavailable. Please try again later.', 200, $e);
        }
    }
}
