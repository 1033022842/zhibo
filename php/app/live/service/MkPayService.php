<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * MKPay（巴西）支付网关对接 —— 仅代收
 *
 * 商户后台：https://br.mkpayeasy.com/admin-OSdkkq/
 * 文档：https://s.apifox.cn/5caac221-771e-4b75-929d-962481063367
 *  - 鉴权   HTTP Basic（appId:signKey base64）+ x-app-id 头
 *  - 代收   POST {gateway}/open-api/pay/payment   code=200 且 status=00 为成功，取 data.payUrl
 *  - 查单   GET  {gateway}/open-api/pay/query?merchantOrderId=  实时状态（文档明确返回不验签）
 *  - 余额   GET  {gateway}/open-api/pay/queryBalance（自检/运营对账用）
 *  - 回调   网关 POST notifyUrl，除 sign 外全部参数参与验签，处理成功需返回纯文本 success
 *
 * 签名算法（回调验签用）：
 *   1. 除 sign 外的参数按 key ASCII 升序拼成 key1=v1&key2=v2&（每组后面都带 &，末尾保留）
 *   2. 追加 key=MD5(signKey)，signKey 的 md5 为 32 位小写
 *   3. 整串再 MD5，得到 sign（小写），比对回调用小写
 *
 * 金额单位：分（BRL 的百分之一），接口以字符串传递。
 */
final class MkPayService
{
    private array $cfg;

    /** 订单状态码 → 含义（网关文档「代收代付查询订单」） */
    public const STATUS = [
        '90' => '待扣款',
        '99' => '待提交',
        '00' => '支付中',
        '06' => '待确认',
        '01' => '成功',
        '02' => '失败',
        '03' => '超时',
        '04' => '退款',
        '05' => '驳回',
    ];

    public const PAY_TYPE_COLLECTION = '110'; // 代收
    public const PAY_TYPE_TRANSFER   = '120'; // 代付

    public function __construct()
    {
        $this->cfg = (array) config('mkpay');
    }

    /**
     * 商户参数是否已配置齐全（下单/验签的前置条件）
     */
    public function enabled(): bool
    {
        return (string) ($this->cfg['app_id'] ?? '') !== ''
            && (string) ($this->cfg['sign_key'] ?? '') !== '';
    }

    private function assertEnabled(): void
    {
        if (!$this->enabled()) {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'MKPay 商户参数未配置（.env [MKPAY] 段）');
        }
    }

    /**
     * 创建代收订单（收银台模式，返回 payUrl 供用户在网关页自选 Pix/GGPay/ApplePay 等方式）
     *
     * @param string $merchantOrderId 商户订单号（本站订单号，收+付全程唯一；两站共用 appId 靠前缀区分）
     * @param int    $amountCents     金额（分）
     * @param string $notifyUrl       异步回调地址，留空走 config('mkpay.notify_url')
     * @return array [payUrl, orderId, amount, fee, ...]
     */
    public function createCollection(string $merchantOrderId, int $amountCents, string $notifyUrl = ''): array
    {
        $this->assertEnabled();
        if ($amountCents <= 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'MKPay 下单金额必须大于 0');
        }
        $notifyUrl = $notifyUrl !== '' ? $notifyUrl : (string) ($this->cfg['notify_url'] ?? '');
        if ($notifyUrl === '') {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'MKPay 回调地址未配置（.env mkpay.notify_url）');
        }

        $body = [
            'amount'          => (string) $amountCents,
            'notifyUrl'       => $notifyUrl,
            'merchantOrderId' => $merchantOrderId,
        ];

        // 文档：HTTP 502 可间隔 2 秒重试 3 次
        $attempt = 0;
        $lastErr = '';
        while (true) {
            $attempt++;
            try {
                return $this->request('POST', '/open-api/pay/payment', $body);
            } catch (BusinessException $e) {
                $msg = $e->getMessage();
                // 502 属网关侧过载，重试；425 重复下单不重试，直接抛给上层换订单号
                if (str_contains($msg, 'HTTP 502') && $attempt < 3) {
                    $lastErr = $msg;
                    sleep(2);
                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * 查询订单（代收/代付通用，实时状态）
     *
     * 文档明确：该接口返回数据不要验签（可能增减字段）。
     * code=200 返回全部字段；404 视为「查无此单」抛业务异常；500 为网关失败抛异常。
     */
    public function queryOrder(string $merchantOrderId): array
    {
        $this->assertEnabled();
        return $this->request('GET', '/open-api/pay/query', ['merchantOrderId' => $merchantOrderId]);
    }

    /**
     * 查询商户余额（配置自检 / 运营对账用）
     */
    public function queryBalance(): array
    {
        $this->assertEnabled();
        return $this->request('GET', '/open-api/pay/queryBalance');
    }

    /**
     * 校验异步回调签名，成功返回原始参数（含 sign），失败抛异常
     *
     * 回调里没有 appId/mchid 之类身份字段，身份与完整性全靠签名；
     * 业务归属由调用方拿 merchantOrderId 查本站订单确认。
     */
    public function verifyNotify(array $params): array
    {
        $sign = strtolower(trim((string) ($params['sign'] ?? '')));
        if ($sign === '') {
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, 'MKPay 回调缺少 sign');
        }
        if ($this->sign($params) !== $sign) {
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, 'MKPay 回调验签失败');
        }
        return $params;
    }

    /**
     * 签名：除 sign 外参数按 key ASCII 升序拼 key=v&（保留末尾 &），
     * 追加 key=MD5(signKey)，整体 MD5 小写
     */
    public function sign(array $params): string
    {
        unset($params['sign']);

        $data = [];
        foreach ($params as $key => $val) {
            if (is_array($val)) {
                $val = (string) json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($val)) {
                $val = $val ? 'true' : 'false';
            } else {
                $val = (string) $val;
            }
            if ($val === '') {
                continue; // 空值不参与签名
            }
            $data[$key] = $val;
        }
        ksort($data, SORT_STRING);

        $str = '';
        foreach ($data as $key => $val) {
            $str .= $key . '=' . $val . '&'; // 每组后面都带 &，末尾保留
        }
        $str .= 'key=' . md5((string) ($this->cfg['sign_key'] ?? ''));

        return md5($str);
    }

    /**
     * 发送请求：Basic 鉴权 + x-app-id 头
     *
     * 代收成功判定：HTTP 200 且 code=200 且 status=00（文档口径）；
     * 业务失败（code!=200）抛异常并带上网关 msg 与 traceId，方便对账排查。
     */
    private function request(string $method, string $path, array $params = []): array
    {
        $appId    = (string) ($this->cfg['app_id'] ?? '');
        $signKey  = (string) ($this->cfg['sign_key'] ?? '');
        $headers  = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($appId . ':' . $signKey),
            'x-app-id'      => $appId,
        ];

        try {
            $client = new Client([
                'timeout'     => (int) ($this->cfg['timeout'] ?? 15),
                // 网关错误响应可能带 4xx/5xx，需自行解析 body 才能拿到 code/msg
                'http_errors' => false,
            ]);
            $options = ['headers' => $headers];
            if ($method === 'GET') {
                $options['query'] = $params;
            } else {
                $options['json'] = $params;
            }
            $resp    = $client->request($method, $this->cfg['gateway'] . $path, $options);
            $status  = $resp->getStatusCode();
            $rawBody = (string) $resp->getBody();
            $body    = json_decode($rawBody, true);
        } catch (GuzzleException $e) {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'MKPay 网关请求失败：' . $e->getMessage());
        }

        // 文档：HTTP 502 可重试 —— 这里把 HTTP 状态带进异常消息，由调用方决定重试
        if (!is_array($body)) {
            throw new BusinessException(
                ResultCode::SERVER_ERROR,
                'MKPay 响应解析失败（HTTP ' . $status . '）：' . mb_substr($rawBody, 0, 200)
            );
        }

        $code = (int) ($body['code'] ?? 0);
        if ($code === 404) {
            throw new BusinessException(ResultCode::RECORD_NOT_FOUND, 'MKPay 查无此单');
        }
        if ($code !== 200) {
            throw new BusinessException(
                ResultCode::PAY_CALLBACK_FAIL,
                'MKPay 返回错误：' . (string) ($body['msg'] ?? ('HTTP ' . $status . ' code=' . $code))
                    . (isset($body['traceId']) ? ' traceId=' . (string) $body['traceId'] : '')
            );
        }

        // 代收下单时 status!=00 说明网关没有给出收银台（如风控拦截），与 code!=200 同样处理
        if ($method === 'POST' && isset($body['status']) && (string) $body['status'] !== '00') {
            throw new BusinessException(
                ResultCode::PAY_CALLBACK_FAIL,
                'MKPay 下单未通过：status=' . (string) $body['status']
                    . '（' . (string) (self::STATUS[(string) $body['status']] ?? '未知状态') . '）'
            );
        }

        return $body;
    }

    /**
     * 本币金额（两位小数）→ 分（整数）
     */
    public static function toCents($amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * 状态码是否「支付成功」
     */
    public static function isSuccess(string $status): bool
    {
        return $status === '01';
    }

    /**
     * 状态码是否「终态失败」（未付出款，可关单）
     */
    public static function isFailed(string $status): bool
    {
        return $status === '02' || $status === '03';
    }
}
