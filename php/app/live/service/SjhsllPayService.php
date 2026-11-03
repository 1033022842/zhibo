<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use GuzzleHttp\Client;

/**
 * 铭森支付（sjhsll）网关对接
 *
 * 文档：http://sjhsll.com/Home_Index_document.html
 *  - 下单  POST {gateway}/Pay_Index.html        表单参数，成功时纯文本返回收银台地址
 *  - 查单  POST {gateway}/Pay_Trade_query.html  返回 JSON（含 trade_state）
 *  - 签名  非空参数按参数名 ASCII 升序拼成 k=v&k=v，末尾拼 &key=商户密钥，MD5 后转大写
 *
 * 注意：下单接口只有识别到浏览器 User-Agent 才返回收银台地址，纯 curl/服务端 UA 会返回空 body，
 * 所以请求头必须带浏览器 UA（config('sjhsll.user_agent')）。
 */
final class SjhsllPayService
{
    /** 后台「网关标识」可填的通道编码 */
    public const CHANNELS = [
        '1' => '微信支付',
        '2' => '支付宝',
    ];

    private array $cfg;

    public function __construct()
    {
        $this->cfg = (array) config('sjhsll');
    }

    /** 商户参数是否配置齐全 */
    public function enabled(): bool
    {
        return (string) ($this->cfg['memberid'] ?? '') !== ''
            && (string) ($this->cfg['key'] ?? '') !== '';
    }

    /** 通道编码 → 前台展示名 */
    public static function channelName(string $code): string
    {
        return self::CHANNELS[$code] ?? ('通道' . $code);
    }

    /**
     * 统一下单，成功返回收银台地址
     *
     * @param string $orderNo  商户订单号（≤20 位）
     * @param string $amount   金额（元，两位小数）
     * @param string $bankCode 通道编码：1=微信 2=支付宝
     * @return array{pay_url:string}
     */
    public function createOrder(
        string $orderNo,
        string $amount,
        string $bankCode,
        string $notifyUrl,
        string $returnUrl,
        string $productName = 'Tokens'
    ): array {
        if (!$this->enabled()) {
            throw new BusinessException(ResultCode::SERVER_ERROR, '铭森支付商户参数未配置');
        }

        $params = [
            'pay_memberid'    => (string) $this->cfg['memberid'],
            'pay_orderid'     => $orderNo,
            'pay_applydate'   => date('Y-m-d H:i:s'),
            'pay_bankcode'    => $bankCode,
            'pay_notifyurl'   => $notifyUrl,
            'pay_callbackurl' => $returnUrl,
            'pay_amount'      => number_format((float) $amount, 2, '.', ''),
            'pay_productname' => $productName,
        ];
        $params['pay_md5sign'] = $this->sign($params);

        $body = $this->post('/Pay_Index.html', $params);
        // 失败时网关返回 {"status":"error","msg":"..."}，成功时是纯文本收银台地址
        $json = json_decode(trim($body), true);
        if (is_array($json)) {
            throw new BusinessException(
                ResultCode::PAY_CALLBACK_FAIL,
                '支付网关返回错误：' . (string) ($json['msg'] ?? '未知错误')
            );
        }

        $payUrl = $this->extractUrl($body);
        if ($payUrl === '') {
            throw new BusinessException(
                ResultCode::SERVER_ERROR,
                '支付网关未返回收银台地址：' . mb_substr(trim($body), 0, 160)
            );
        }

        return ['pay_url' => $payUrl];
    }

    /**
     * 订单查询，返回网关原始 JSON
     *
     * @return array{memberid?:string,orderid?:string,amount?:string,time_end?:string,
     *               transaction_id?:string,returncode?:string,trade_state?:string,sign?:string}
     */
    public function queryOrder(string $orderNo): array
    {
        if (!$this->enabled()) {
            throw new BusinessException(ResultCode::SERVER_ERROR, '铭森支付商户参数未配置');
        }

        $params = [
            'pay_memberid' => (string) $this->cfg['memberid'],
            'pay_orderid'  => $orderNo,
        ];
        $params['pay_md5sign'] = $this->sign($params);

        $body = $this->post('/Pay_Trade_query.html', $params);
        $json = json_decode(trim($body), true);
        if (!is_array($json)) {
            throw new BusinessException(ResultCode::SERVER_ERROR, '支付网关响应解析失败：' . mb_substr(trim($body), 0, 160));
        }
        if (isset($json['sign']) && !$this->verifyParams($json, 'sign')) {
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, '查单响应验签失败');
        }

        return $json;
    }

    /**
     * 异步通知验签，成功返回回调参数，失败抛异常
     */
    public function verifyNotify(array $post): array
    {
        $memberId = (string) ($post['memberid'] ?? '');
        if ($memberId === '' || $memberId !== (string) ($this->cfg['memberid'] ?? '')) {
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, '商户号不匹配');
        }

        // 文档字段名是 sign；兼容部分通道回传 pay_md5sign
        $signKey = isset($post['sign']) && (string) $post['sign'] !== '' ? 'sign' : 'pay_md5sign';
        if ((string) ($post[$signKey] ?? '') === '') {
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, '通知缺少签名');
        }
        if (!$this->verifyParams($post, $signKey)) {
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, '通知验签失败');
        }

        return $post;
    }

    /** 查单结果：已支付 */
    public static function isPaid(array $res): bool
    {
        return strtoupper((string) ($res['trade_state'] ?? '')) === 'SUCCESS';
    }

    /** 查单结果：确定未支付（NOTPAY 是终态；其它中间态不处理） */
    public static function isUnpaid(array $res): bool
    {
        return strtoupper((string) ($res['trade_state'] ?? '')) === 'NOTPAY';
    }

    /**
     * 签名：非空参数按参数名 ASCII 升序拼成 k=v&k=v，末尾拼 &key=商户密钥，MD5 转大写
     *
     * 文档里「参与签名=否」的商品字段必须排除，否则下单会报「签名验证失败」：
     * pay_productname / pay_productnum / pay_productdesc / pay_producturl
     */
    public function sign(array $params): string
    {
        unset(
            $params['sign'],
            $params['pay_md5sign'],
            $params['pay_productname'],
            $params['pay_productnum'],
            $params['pay_productdesc'],
            $params['pay_producturl']
        );

        $filtered = [];
        foreach ($params as $key => $value) {
            if (is_array($value) || $value === null) {
                continue;
            }
            $value = (string) $value;
            if ($value === '') {
                continue;
            }
            $filtered[(string) $key] = $value;
        }
        ksort($filtered, SORT_STRING);

        $pairs = [];
        foreach ($filtered as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }

        return strtoupper(md5(implode('&', $pairs) . '&key=' . (string) ($this->cfg['key'] ?? '')));
    }

    /** 校验一组参数里的签名 */
    private function verifyParams(array $params, string $signKey): bool
    {
        $sign = strtoupper(trim((string) ($params[$signKey] ?? '')));
        if ($sign === '') {
            return false;
        }
        return hash_equals($this->sign($params), $sign);
    }

    /** POST 表单，返回原始响应体 */
    private function post(string $path, array $params): string
    {
        // 网关只有识别到「浏览器 UA + 本站 Referer」才返回收银台地址，缺一个都会得到空 body
        $referer = (string) ($this->cfg['referer'] ?? '');
        if ($referer === '') {
            $page = (string) ($this->cfg['return_url'] ?? '');
            $host = $page !== '' ? parse_url($page) : [];
            if (!empty($host['scheme']) && !empty($host['host'])) {
                $referer = $host['scheme'] . '://' . $host['host'] . '/';
            }
        }

        $headers = [
            'User-Agent' => (string) ($this->cfg['user_agent'] ?? 'Mozilla/5.0'),
            'Accept'     => 'text/html,application/json;q=0.9,*/*;q=0.8',
        ];
        if ($referer !== '') {
            $headers['Referer'] = $referer;
        }

        try {
            $client = new Client([
                'timeout'     => (int) ($this->cfg['timeout'] ?? 20),
                'http_errors' => false,
            ]);
            $resp = $client->post(rtrim((string) $this->cfg['gateway'], '/') . $path, [
                'form_params' => $params,
                'headers'     => $headers,
            ]);
            return (string) $resp->getBody();
        } catch (\Throwable $e) {
            throw new BusinessException(ResultCode::SERVER_ERROR, '支付网关请求失败：' . $e->getMessage());
        }
    }

    /** 从响应里取出收银台地址（正常是纯文本 URL，兼容 HTML/JS 包裹） */
    private function extractUrl(string $body): string
    {
        $text = trim($body);
        if ($text === '') {
            return '';
        }
        if (preg_match('~^https?://[^\s"\'<>]+$~i', $text, $m)) {
            return $m[0];
        }
        if (preg_match('~https?://[^\s"\'<>\\\\]+~i', $text, $m)) {
            return $m[0];
        }
        return '';
    }
}
