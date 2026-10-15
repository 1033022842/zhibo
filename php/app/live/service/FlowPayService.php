<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use GuzzleHttp\Client;
use think\facade\Db;

/**
 * FlowPay（safepaycash）支付网关对接
 *
 * 文档：https://www.showdoc.com.cn/2598843287297372
 *  - 下单  POST {gateway}/payment/create_order   返回 data.payurl 收银台地址
 *  - 查单  POST {gateway}/payment/query_order
 *  - 签名  非空字段按 key 升序拼成 k=v&k=v，末尾追加 &key=商户密钥，md5 后转大写
 *
 * 商户号下可开通多条收单通道（gcode），同一收银台上金额与费率一致、只差收单路由，
 * 因此前台把通道平铺成多个支付按钮由用户自选，见 config('flowpay.channels')。
 */
final class FlowPayService
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = (array) config('flowpay');
    }

    /**
     * 可行通道列表（gcode + 前台展示名），顺序即前台按钮顺序
     *
     * @return array<int, array{gcode:string, label:string}>
     */
    public function channels(): array
    {
        $out = [];
        foreach ((array) ($this->cfg['channels'] ?? []) as $gcode => $label) {
            $gcode = trim((string) $gcode);
            if ($gcode === '') {
                continue;
            }
            $label = trim((string) $label);
            $out[] = ['gcode' => $gcode, 'label' => $label !== '' ? $label : $gcode];
        }
        return $out;
    }

    /** 后台开关存在 lp_platform_config 里的键名 */
    public const SWITCH_KEY = 'flowpay_enabled';

    /**
     * 商户参数是否已配置齐全
     */
    public function enabled(): bool
    {
        return (string) $this->cfg['mchid'] !== ''
            && (string) $this->cfg['key'] !== ''
            && $this->defaultChannel() !== '';
    }

    /**
     * 运营开关是否打开（lp_platform_config.flowpay_enabled，缺省视为开启）
     *
     * 故意与 enabled() 分开：查单补单只需要商户参数齐全，不该被运营开关拦住，
     * 否则关掉开关后，之前已经支付、回调丢失的订单就再也补不回来了。
     */
    public function switchOn(): bool
    {
        $v = Db::connect('live_mysql')->table('lp_platform_config')
            ->where('key', self::SWITCH_KEY)->value('value');
        return $v === null || (string) $v === '' ? true : ((string) $v === '1');
    }

    /**
     * 前台是否可以提供卡支付 = 商户参数齐全 + 运营开关打开
     *
     * 前台三处入口（充值页 / 订阅页 / 设置页支付方式）都用它判断。
     */
    public function available(): bool
    {
        return $this->enabled() && $this->switchOn();
    }

    /** 写入运营开关 */
    public function setSwitch(bool $on): void
    {
        $db  = Db::connect('live_mysql');
        $now = date('Y-m-d H:i:s');
        if ($db->table('lp_platform_config')->where('key', self::SWITCH_KEY)->find()) {
            $db->table('lp_platform_config')->where('key', self::SWITCH_KEY)
                ->update(['value' => $on ? '1' : '0', 'updated_at' => $now]);
            return;
        }
        $db->table('lp_platform_config')->insert([
            'key'        => self::SWITCH_KEY,
            'value'      => $on ? '1' : '0',
            'remark'     => '信用卡支付（FlowPay）总开关，1=开启 0=关闭',
            'updated_at' => $now,
        ]);
    }

    /**
     * 创建代收订单
     *
     * @param string $outTradeNo 商户订单号（10~50 位，唯一）
     * @param string $amount     下单金额（USD，保留两位小数）
     * @param string $gcode      收单通道，留空走默认通道
     * @return array 网关业务参数：order_sn/out_trade_no/amount/truly_amount/payurl
     */
    public function createOrder(string $outTradeNo, string $amount, string $gcode = ''): array
    {
        return $this->request('/payment/create_order', [
            'mchid'        => (string) $this->cfg['mchid'],
            'time'         => (string) time(),
            'rand_str'     => $this->randStr(),
            'out_trade_no' => $outTradeNo,
            'amount'       => number_format((float) $amount, 2, '.', ''),
            'gcode'        => $this->resolveChannel($gcode),
            // 文档要求：notifyurl 参与签名和请求时都需要 URLENCODE
            'notifyurl'    => urlencode((string) $this->cfg['notify_url']),
        ]);
    }

    /**
     * 查询订单（out_trade_no 与网关流水号二者皆可）
     */
    public function queryOrder(string $outTradeNo): array
    {
        return $this->request('/payment/query_order', [
            'mchid'        => (string) $this->cfg['mchid'],
            'time'         => (string) time(),
            'rand_str'     => $this->randStr(),
            'out_trade_no' => $outTradeNo,
        ]);
    }

    /**
     * 校验异步通知签名，成功返回网关原始参数，失败抛异常
     */
    public function verifyNotify(array $post): array
    {
        $sign  = strtoupper((string) ($post['sign'] ?? ''));
        $mchid = (string) ($post['mchid'] ?? '');
        unset($post['sign']);

        if ($mchid === '' || $sign === '') {
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, '通知参数不完整');
        }
        if ($mchid !== (string) $this->cfg['mchid']) {
            throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL, '商户号不匹配');
        }

        // 金额可能被网关序列化成 10.00 / 10 两种形态，逐一比对
        foreach ([false, true] as $twoDecimals) {
            if ($this->sign($post, $twoDecimals) === $sign) {
                return $post;
            }
        }
        throw new BusinessException(ResultCode::PAY_CALLBACK_FAIL);
    }

    /**
     * 发送请求
     */
    private function request(string $path, array $params): array
    {
        if (!$this->enabled()) {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'FlowPay 商户参数未配置');
        }
        $params['sign'] = $this->sign($params);

        try {
            $client = new Client([
                'timeout'     => (int) $this->cfg['timeout'],
                // 网关错误响应可能带 4xx/5xx，需自行解析 body 才能拿到 msg
                'http_errors' => false,
            ]);
            $resp = $client->post($this->cfg['gateway'] . $path, [
                'json'    => $params,
                'headers' => ['Content-Type' => 'application/json'],
            ]);
            $rawBody = (string) $resp->getBody();
            $status  = $resp->getStatusCode();
            $body    = json_decode($rawBody, true);
        } catch (\Throwable $e) {
            throw new BusinessException(ResultCode::SERVER_ERROR, '支付网关请求失败：' . $e->getMessage());
        }

        if (!is_array($body)) {
            throw new BusinessException(ResultCode::SERVER_ERROR, '支付网关响应解析失败（HTTP ' . $status . '）：' . mb_substr($rawBody, 0, 200));
        }
        if ((int) ($body['code'] ?? 0) !== 200) {
            throw new BusinessException(
                ResultCode::PAY_CALLBACK_FAIL,
                '支付网关返回错误：' . (string) ($body['msg'] ?? ('HTTP ' . $status))
            );
        }

        return (array) ($body['data'] ?? []);
    }

    /**
     * 签名：非空字段按 key 升序拼成 k=v 并 & 连接，追加 &key=密钥后 md5 转大写
     *
     * @param bool $twoDecimals 浮点值是否按两位小数还原（用于回调验签容错）
     */
    private function sign(array $params, bool $twoDecimals = false): string
    {
        unset($params['sign']);

        $data = [];
        foreach ($params as $key => $val) {
            if (is_array($val)) {
                $val = (string) json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($val)) {
                $val = $val ? '1' : '0';
            } elseif (is_float($val) && $twoDecimals) {
                $val = number_format($val, 2, '.', '');
            } else {
                $val = (string) $val;
            }
            if ($val === '') {
                continue; // 非空值字段才参与签名
            }
            $data[$key] = $val;
        }
        ksort($data, SORT_STRING);

        $parts = [];
        foreach ($data as $key => $val) {
            $parts[] = $key . '=' . $val;
        }

        return strtoupper(md5(implode('&', $parts) . '&key=' . (string) $this->cfg['key']));
    }

    /**
     * 是否为已开通的通道
     */
    public function isChannel(string $gcode): bool
    {
        foreach ($this->channels() as $c) {
            if ($c['gcode'] === $gcode) {
                return true;
            }
        }
        return false;
    }

    /**
     * 默认通道：.env 的 GCODE，未配置或不在 channels 里时取 channels 第一条
     */
    public function defaultChannel(): string
    {
        $channels = $this->channels();
        if (!$channels) {
            return '';
        }
        $configured = trim((string) $this->cfg['gcode']);
        foreach ($channels as $c) {
            if ($c['gcode'] === $configured) {
                return $configured;
            }
        }
        return $channels[0]['gcode'];
    }

    /**
     * 归一化下单通道：留空走默认通道，传入未开通的 gcode 直接拒绝
     * （避免前台传什么就用什么，把用户的钱打到陌生通道）
     */
    public function resolveChannel(string $gcode = ''): string
    {
        $gcode = trim($gcode);
        if ($gcode === '') {
            return $this->defaultChannel();
        }
        if (!$this->isChannel($gcode)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '不支持的支付通道');
        }
        return $gcode;
    }

    /**
     * 随机字符串（6~50 位）
     */
    private function randStr(): string
    {
        return bin2hex(random_bytes(12));
    }
}
