<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * MKPay（印度）支付网关对接 —— 仅代收
 *
 * 与越南版 MkPayVnService / 墨西哥版 MkPayMxService 是同一个网关的多套商户配置，互不复用。
 *  - 鉴权：X-Mch-ID / X-Timestamp / X-Signature
 *  - 签名：HMAC-SHA256(timestamp + 实际发送的 JSON 字符串, mch_secret)，十六进制小写
 *  - 代收：POST /api/v1/pay            → redirect_url / qr_code_url
 *  - 查单：POST /api/v1/query-by-moid  → 按商户订单号
 *  - 余额：POST /api/v1/balance
 *  - 回调：JSON，去 sign 后按 key 排序 + 紧凑 JSON 验签，成功返回 2xx + SUCCESS
 *
 * 金额单位：INR 分（factor=100），接口传整数分（100.00 INR → 10000）。
 */
final class MkPayInService
{
    private array $cfg;

    /** status_code → 含义（文档第 8 章；判断一律以 status_code 为准） */
    public const STATUS = [
        0  => '待支付',
        1  => '处理中',
        2  => '成功',
        3  => '失败',
        4  => '已过期/已取消',
        6  => '已关闭',
        7  => '异常待处理',
    ];

    public function __construct()
    {
        $this->cfg = (array) config('mkpay_in');
    }

    public function enabled(): bool
    {
        return (string) ($this->cfg['mch_id'] ?? '') !== ''
            && (string) ($this->cfg['mch_secret'] ?? '') !== '';
    }

    private function assertEnabled(): void
    {
        if (!$this->enabled()) {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'MKPay-IN 商户参数未配置（.env [MKPAY_IN] 段）');
        }
    }

    /**
     * 创建代收订单
     *
     * @param string $merchantOrderId 本站订单号（两站各自前缀，保证商户订单号唯一）
     * @param int    $amountPaise     金额（整数分，100.00 INR = 10000）
     * @param string $notifyUrl       异步回调地址，留空走 config('mkpay_in.notify_url')
     * @param string $returnUrl       支付完成后浏览器跳回地址
     * @return array{pay_order_id:string,pay_url:string,qr_code_url:string,status_code:int,status:string,raw:array}
     */
    public function createCollection(string $merchantOrderId, int $amountPaise, string $notifyUrl = '', string $returnUrl = ''): array
    {
        $this->assertEnabled();
        if ($amountPaise <= 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'MKPay-IN 下单金额必须大于 0');
        }
        $notifyUrl = $notifyUrl !== '' ? $notifyUrl : (string) ($this->cfg['notify_url'] ?? '');
        if ($notifyUrl === '') {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'MKPay-IN 回调地址未配置（.env mkpay_in.notify_url）');
        }

        $body = [
            'mch_id'            => (string) $this->cfg['mch_id'],
            'amount'            => $amountPaise,
            'merchant_order_id' => $merchantOrderId,
            'notify_url'        => $notifyUrl,
        ];
        // 商户只绑了一条产品时网关自动路由；多产品场景才需要显式指定
        $productCode = trim((string) ($this->cfg['product_code'] ?? ''));
        if ($productCode !== '') {
            $body['product_code'] = $productCode;
        }
        if ($returnUrl !== '') {
            $body['return_url'] = $returnUrl;
        }

        $data = $this->request('/api/v1/pay', $body);

        return [
            'pay_order_id' => (string) ($data['pay_order_id'] ?? ''),
            'pay_url'      => (string) ($data['redirect_url'] ?? '') !== ''
                ? (string) $data['redirect_url']
                : (string) ($data['qr_code_url'] ?? ''),
            'qr_code_url'  => (string) ($data['qr_code_url'] ?? ''),
            'status_code'  => (int) ($data['status_code'] ?? 0),
            'status'       => (string) ($data['status'] ?? ''),
            'raw'          => $data,
        ];
    }

    /**
     * 按商户订单号查单
     */
    public function queryOrder(string $merchantOrderId): array
    {
        $this->assertEnabled();
        return $this->request('/api/v1/query-by-moid', [
            'mch_id'            => (string) $this->cfg['mch_id'],
            'merchant_order_id' => $merchantOrderId,
        ]);
    }

    /**
     * 查询商户余额（自检 / 对账用）
     */
    public function queryBalance(): array
    {
        $this->assertEnabled();
        return $this->request('/api/v1/balance', ['mch_id' => (string) $this->cfg['mch_id']]);
    }

    /**
     * 校验异步回调签名（失败抛异常）
     *
     * 文档要求：取出 sign，对剩余字段按键名排序 + 紧凑 JSON（无空格）后，
     * 以 HMAC-SHA256(timestamp + body, mch_secret) 计算并比较。
     */
    public function verifyNotify(array $params): array
    {
        $sign = strtolower(trim((string) ($params['sign'] ?? '')));
        if ($sign === '') {
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, 'MKPay-IN 回调缺少 sign');
        }
        $payload = $params;
        unset($payload['sign']);
        ksort($payload, SORT_STRING);
        // Python 参考实现不转义斜杠；PHP 默认会把 / 转成 \/，这里保持不转义
        $json = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $expected = hash_hmac(
            'sha256',
            (string) ($payload['timestamp'] ?? '') . $json,
            (string) ($this->cfg['mch_secret'] ?? '')
        );
        if (!hash_equals($expected, $sign)) {
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, 'MKPay-IN 回调验签失败');
        }
        return $params;
    }

    /**
     * 发送 POST JSON：请求体字符串先生成，再对「实际发送的字符串」签名
     */
    private function request(string $path, array $body): array
    {
        $json = (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . $json, (string) ($this->cfg['mch_secret'] ?? ''));

        $headers = [
            'Content-Type' => 'application/json',
            'X-Mch-ID'     => (string) ($this->cfg['mch_id'] ?? ''),
            'X-Timestamp'  => $timestamp,
            'X-Signature'  => $signature,
        ];

        try {
            $client = new Client([
                'timeout'     => (int) ($this->cfg['timeout'] ?? 15),
                'http_errors' => false,
            ]);
            $resp    = $client->request('POST', $this->cfg['gateway'] . $path, [
                'headers' => $headers,
                'body'    => $json,
            ]);
            $status  = $resp->getStatusCode();
            $rawBody = (string) $resp->getBody();
            $data    = json_decode($rawBody, true);
        } catch (GuzzleException $e) {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'MKPay-IN 网关请求失败：' . $e->getMessage());
        }

        if ($status === 404) {
            throw new BusinessException(ResultCode::RECORD_NOT_FOUND, 'MKPay-IN 查无此单');
        }
        if ($status < 200 || $status >= 300 || !is_array($data)) {
            $msg = is_array($data)
                ? (string) ($data['error'] ?? $data['msg'] ?? ('HTTP ' . $status))
                : ('HTTP ' . $status . ' ' . mb_substr($rawBody, 0, 200));
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, 'MKPay-IN 返回错误：' . $msg);
        }

        return $data;
    }

    public static function statusText(int $code): string
    {
        return self::STATUS[$code] ?? '未知状态';
    }

    /** status_code=2 为支付成功 */
    public static function isSuccess(int $code): bool
    {
        return $code === 2;
    }

    /** 3 失败 / 4 过期取消 / 6 关闭：终态失败，可落「未支付」 */
    public static function isFailed(int $code): bool
    {
        return in_array($code, [3, 4, 6], true);
    }
}
