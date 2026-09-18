<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use GuzzleHttp\Client;

/**
 * star-pay 支付网关对接
 *
 * 文档：支付网关接入手册
 *  - 下单      POST {gateway}/api/gateway/pay
 *  - 查询订单  POST {gateway}/api/gateway/batch-query/order
 *  - 签名      MD5(merchant_no + params + sign_type + timestamp + Key)
 */
final class StarPayService
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = (array) config('starpay');
    }

    /**
     * 前端可选支付方式（product）
     */
    public function products(): array
    {
        return $this->cfg['products'] ?? [];
    }

    /**
     * 判断产品是否在允许列表中
     */
    public function isProductEnabled(string $product): bool
    {
        foreach ($this->products() as $item) {
            if ($item['code'] === $product) {
                return true;
            }
        }
        return false;
    }

    /**
     * 发起存款订单
     *
     * @param string $merchantRef 商户订单号
     * @param string $amount      金额（法币保留两位小数 / 数字货币保留六位小数）
     * @param string $product     产品（TRC20Buy / USDCDeposit ...）
     * @param array  $extra       额外参数，如 ['fiat_currency' => 'USD']
     * @return array 网关业务参数：merchant_ref/system_ref/amount/fee/status/success_time/payurl/extra
     */
    public function createDeposit(string $merchantRef, string $amount, string $product, array $extra = []): array
    {
        $params = [
            'merchant_ref' => $merchantRef,
            'product'      => $product,
            'amount'       => $amount,
        ];
        if ($extra !== []) {
            $params['extra'] = $extra;
        }
        if (!empty($this->cfg['notify_url'])) {
            $params['notify_url'] = $this->cfg['notify_url'];
        }
        if (!empty($this->cfg['return_url'])) {
            $params['return_url'] = $this->cfg['return_url'];
        }

        return $this->request('/api/gateway/pay', $params);
    }

    /**
     * 查询订单（一次最多 20 笔）
     */
    public function queryOrders(array $merchantRefs): array
    {
        $result = $this->request('/api/gateway/batch-query/order', ['merchant_refs' => array_values($merchantRefs)]);
        // 查询接口返回数组
        return isset($result[0]) || $result === [] ? $result : [$result];
    }

    /**
     * 校验异步通知签名，成功返回业务 params 数组，失败抛异常
     */
    public function verifyNotify(array $post): array
    {
        $merchantNo = (string) ($post['merchant_no'] ?? '');
        $paramsStr  = (string) ($post['params'] ?? '');
        $signType   = (string) ($post['sign_type'] ?? 'MD5');
        $timestamp  = (string) ($post['timestamp'] ?? '');
        $sign       = (string) ($post['sign'] ?? '');

        if ($merchantNo === '' || $sign === '' || $paramsStr === '' || $timestamp === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, '通知参数不完整');
        }
        if ($merchantNo !== (string) $this->cfg['merchant_no']) {
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, '商户号不匹配');
        }
        $expect = $this->sign($paramsStr, $timestamp, $signType);
        if (strcasecmp($expect, $sign) !== 0) {
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL);
        }

        $params = json_decode($paramsStr, true);
        if (!is_array($params)) {
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, '通知业务参数解析失败');
        }
        return $params;
    }

    /**
     * 发送请求
     */
    private function request(string $path, array $bizParams): array
    {
        $merchantNo = (string) $this->cfg['merchant_no'];
        $key        = (string) $this->cfg['key'];
        if ($merchantNo === '' || $key === '') {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'star-pay 商户参数未配置');
        }

        $timestamp = time();
        $signType  = (string) $this->cfg['sign_type'];
        $paramsStr = json_encode($bizParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($paramsStr === false) {
            throw new BusinessException(ResultCode::SERVER_ERROR, '业务参数序列化失败');
        }

        $fields = [
            'merchant_no' => $merchantNo,
            'timestamp'   => $timestamp,
            'sign_type'   => $signType,
            'params'      => $paramsStr,
            'sign'        => $this->sign($paramsStr, (string) $timestamp, $signType),
        ];

        try {
            $client = new Client(['timeout' => (int) $this->cfg['timeout']]);
            $resp   = $client->post($this->cfg['gateway'] . $path, [
                'form_params' => $fields,
                'headers'     => ['Content-Type' => 'application/x-www-form-urlencoded'],
            ]);
            $rawBody = (string) $resp->getBody();
            $body    = json_decode($rawBody, true);
        } catch (\Throwable $e) {
            throw new BusinessException(ResultCode::SERVER_ERROR, '支付网关请求失败：' . $e->getMessage());
        }

        if (!is_array($body)) {
            throw new BusinessException(ResultCode::SERVER_ERROR, '支付网关响应解析失败');
        }
        if ((string) ($body['code'] ?? '') !== '200') {
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, '支付网关返回错误：' . (string) ($body['message'] ?? '未知错误'));
        }

        $params = json_decode((string) ($body['params'] ?? ''), true);
        if (!is_array($params)) {
            throw new BusinessException(ResultCode::SERVER_ERROR, '支付网关业务参数解析失败');
        }
        return $params;
    }

    /**
     * 签名：MD5(merchant_no + params + sign_type + timestamp + Key)
     */
    private function sign(string $paramsStr, string $timestamp, string $signType): string
    {
        return md5(
            (string) $this->cfg['merchant_no']
            . $paramsStr
            . $signType
            . $timestamp
            . (string) $this->cfg['key']
        );
    }
}
