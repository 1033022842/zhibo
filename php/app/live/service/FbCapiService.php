<?php
declare(strict_types=1);

namespace app\live\service;

use GuzzleHttp\Client;
use think\facade\Db;
use think\facade\Log;

/**
 * Facebook 服务端回传（Conversions API）
 *
 * 浏览器端的 fbq 事件 + 服务端 CAPI 事件使用同一个 event_id 去重，避免重复计数。
 * 令牌来自「推广与像素」每条像素的「服务端回传令牌」字段（lp_promo_pixel.capi_token），
 * 只存在服务器，不会下发给前端。
 *
 * 全部逻辑都是「尽力而为」：配置缺失、网络失败都只会记日志，绝不影响注册/充值主流程。
 */
final class FbCapiService
{
    private const GRAPH   = 'https://graph.facebook.com/v21.0';
    private const TIMEOUT = 8;

    /** 与前端 tracking.js 的 PLATFORM_EVENTS 保持一致 */
    private const EVENTS = [
        'register'  => 'CompleteRegistration',
        'recharge'  => 'Purchase',
        'subscribe' => 'Subscribe',
        'login'     => 'Lead',
    ];

    /** 本次请求内缓存像素列表，避免重复查库 */
    private static ?array $pixels = null;

    /**
     * 回传一个转化事件
     *
     * @param string $key 事件键：register / recharge / subscribe / login
     * @param array  $ctx 上下文：
     *   email/phone/user_id/ip/ua/fbp/fbc/source_url  用户身份信息
     *   px        访客绑定的后台像素ID（?px=，可选）
     *   domain    来源域名（用于按域名匹配像素）
     *   event_id  与浏览器端相同的去重ID
     *   value/currency  金额（充值/订阅）
     *   test_code 测试事件编码（可选，优先用像素自己配置的）
     */
    public static function send(string $key, array $ctx = []): bool
    {
        $eventName = self::EVENTS[$key] ?? '';
        if ($eventName === '') {
            return false;
        }

        $pixel = self::resolvePixel((string) ($ctx['px'] ?? ''), (string) ($ctx['domain'] ?? ''));
        if (!$pixel) {
            Log::info('[capi] 跳过：未匹配到像素 key=' . $key
                . ' px=' . (string) ($ctx['px'] ?? '') . ' domain=' . (string) ($ctx['domain'] ?? ''));
            return false;
        }

        $pixelId = trim((string) ($pixel['pixel_id'] ?? ''));
        $token   = trim((string) ($pixel['capi_token'] ?? ''));
        if ($pixelId === '' || $token === '') {
            Log::info('[capi] 跳过：像素未配置 pixel_id 或服务端回传令牌 key=' . $key . ' pixel_id=' . $pixelId);
            return false;
        }

        $userData = self::userData($ctx);
        if ($userData === []) {
            Log::info('[capi] 跳过：缺少用户标识 key=' . $key);
            return false;
        }

        $event = [
            'event_name'    => $eventName,
            'event_time'    => (int) ($ctx['event_time'] ?? time()),
            'action_source' => 'website',
            'user_data'     => $userData,
        ];
        $eventId = trim((string) ($ctx['event_id'] ?? ''));
        if ($eventId !== '') {
            $event['event_id'] = $eventId;
        }
        $sourceUrl = trim((string) ($ctx['source_url'] ?? ''));
        if ($sourceUrl !== '') {
            $event['event_source_url'] = $sourceUrl;
        }
        $custom = [];
        if (isset($ctx['value']) && is_numeric($ctx['value']) && (float) $ctx['value'] > 0) {
            $custom['value']    = (float) $ctx['value'];
            $custom['currency'] = (string) ($ctx['currency'] ?? 'USD');
        }
        if ($custom !== []) {
            $event['custom_data'] = $custom;
        }

        $payload = ['data' => [$event]];

        // 测试事件编码：填了之后只进 Events Manager 的「测试事件」面板，可实时验证回传
        $testCode = trim((string) ($ctx['test_code'] ?? ($pixel['test_code'] ?? '')));
        if ($testCode !== '') {
            $payload['test_event_code'] = $testCode;
        }

        try {
            $client = new Client(['timeout' => self::TIMEOUT, 'http_errors' => false]);
            $resp   = $client->post(self::GRAPH . '/' . rawurlencode($pixelId) . '/events', [
                'query' => ['access_token' => $token],
                'json'  => $payload,
            ]);
            $body = (string) $resp->getBody();
            Log::info('[capi] ' . $key . ' → ' . $eventName
                . ' pixel=' . $pixelId . ' http=' . $resp->getStatusCode()
                . ' resp=' . mb_substr($body, 0, 300));
            return $resp->getStatusCode() === 200;
        } catch (\Throwable $e) {
            Log::warning('[capi] 回传失败 key=' . $key . ' err=' . $e->getMessage());
            return false;
        }
    }

    /**
     * 后台「测试回传」：用该像素的 像素ID + 回传令牌 发一条测试事件，返回 Facebook 的原始响应
     * 返回：ok 是否成功 / http 状态码 / resp 原始响应（便于后台直接展示给运营排查）
     */
    public static function probe(array $pixel): array
    {
        $pixelId = trim((string) ($pixel['pixel_id'] ?? ''));
        $token   = trim((string) ($pixel['capi_token'] ?? ''));
        if ($pixelId === '' || $token === '') {
            return ['ok' => false, 'http' => 0, 'resp' => '该像素未配置「像素ID」或「服务端回传令牌」'];
        }

        $payload = ['data' => [[
            'event_name'    => 'CompleteRegistration',
            'event_time'    => time(),
            'action_source' => 'website',
            'event_id'      => 'admin-test-' . time(),
            'user_data'     => ['em' => [hash('sha256', 'capi-test@sugus.ai')]],
            'custom_data'   => ['value' => 0.01, 'currency' => 'USD'],
        ]]];
        $testCode = trim((string) ($pixel['test_code'] ?? ''));
        if ($testCode !== '') {
            $payload['test_event_code'] = $testCode;
        }

        try {
            $client = new Client(['timeout' => self::TIMEOUT, 'http_errors' => false]);
            $resp   = $client->post(self::GRAPH . '/' . rawurlencode($pixelId) . '/events', [
                'query' => ['access_token' => $token],
                'json'  => $payload,
            ]);
            $body = (string) $resp->getBody();
            $ok   = $resp->getStatusCode() === 200 && str_contains($body, '"events_received"');
            Log::info('[capi] probe pixel=' . $pixelId . ' http=' . $resp->getStatusCode() . ' resp=' . mb_substr($body, 0, 300));
            $out = ['ok' => $ok, 'http' => $resp->getStatusCode(), 'resp' => mb_substr($body, 0, 600)];
            // 失败时补一份自诊断：这个令牌属于谁、能不能读到这个像素
            if (!$ok) {
                $out['diag'] = self::diagnose($pixelId, $token);
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('[capi] probe 异常 pixel=' . $pixelId . ' err=' . $e->getMessage());
            return ['ok' => false, 'http' => 0, 'resp' => $e->getMessage()];
        }
    }

    /**
     * 回传失败时的自诊断：确认「令牌身份」以及「该令牌能否读到这个像素」
     * 400 / code 100 / subcode 33 基本都是令牌与像素不属于同一资产
     */
    private static function diagnose(string $pixelId, string $token): string
    {
        $client = new Client(['timeout' => self::TIMEOUT, 'http_errors' => false]);
        $out    = [];

        // 1) 令牌属于哪个用户 / 系统用户
        try {
            $r = $client->get(self::GRAPH . '/me', [
                'query' => ['fields' => 'id,name', 'access_token' => $token],
            ]);
            $out[] = '① 令牌身份：HTTP ' . $r->getStatusCode() . ' → ' . mb_substr((string) $r->getBody(), 0, 200);
        } catch (\Throwable $e) {
            $out[] = '① 令牌身份查询异常：' . $e->getMessage();
        }

        // 2) 这个令牌能不能读到该像素（读不到 = 令牌不是在这个像素下生成的）
        try {
            $r = $client->get(self::GRAPH . '/' . rawurlencode($pixelId), [
                'query' => ['fields' => 'id,name', 'access_token' => $token],
            ]);
            $out[] = '② 读取像素：HTTP ' . $r->getStatusCode() . ' → ' . mb_substr((string) $r->getBody(), 0, 300);
        } catch (\Throwable $e) {
            $out[] = '② 读取像素异常：' . $e->getMessage();
        }

        $out[] = '③ 若②读不到该像素，说明令牌不是在这个像素的「设置 → 生成访问令牌」里生成的，需重新生成。';

        return implode("\n", $out);
    }

    /**
     * 选像素：优先访客绑定的 ?px=，其次按来源域名匹配生效域名
     */
    private static function resolvePixel(string $px, string $domain): ?array
    {
        $px     = trim($px);
        $domain = strtolower(trim($domain));

        foreach (self::pixels() as $row) {
            if ($px !== '') {
                if ((string) $row['id'] === $px || (string) $row['pixel_id'] === $px) {
                    return $row;
                }
                continue;
            }
            if ($domain === '') {
                continue;
            }
            foreach (preg_split('/[\s,;]+/', (string) $row['domains']) ?: [] as $d) {
                $d = strtolower(trim($d));
                if ($d === '') {
                    continue;
                }
                if ($d === $domain) {
                    return $row;
                }
                if ($d[0] === '*' && substr($domain, -(strlen($d) - 1)) === substr($d, 1)) {
                    return $row;
                }
            }
        }
        // 老用户 / 无归因（reg_px 与 reg_domain 都为空）：站点只有一个启用像素时直接用它，
        // 否则购物与订阅会完全丢回传（注册走浏览器端，所以只有注册能看到）。
        $rows = self::pixels();
        $ids  = array_values(array_unique(array_map(
            static fn(array $r): string => (string) ($r['pixel_id'] ?? ''),
            $rows
        )));
        if (count($ids) === 1 && $rows !== []) {
            return $rows[0];
        }
        return null;
    }

    /** 启用中的 Facebook 像素 */
    private static function pixels(): array
    {
        if (self::$pixels === null) {
            try {
                self::$pixels = Db::connect('live_mysql')->table('lp_promo_pixel')
                    ->where('platform', 'facebook')
                    ->where('status', 1)
                    ->order('sort', 'asc')->order('id', 'asc')
                    ->select()->toArray();
            } catch (\Throwable $e) {
                self::$pixels = [];
            }
        }
        return self::$pixels;
    }

    /**
     * 按 FB 要求做规范化 + SHA256（邮箱小写去空格、手机号只留数字）
     */
    private static function userData(array $ctx): array
    {
        $ud = [];

        $em = strtolower(trim((string) ($ctx['email'] ?? '')));
        if ($em !== '') {
            $ud['em'] = [hash('sha256', $em)];
        }
        $ph = preg_replace('/\D+/', '', (string) ($ctx['phone'] ?? ''));
        if (is_string($ph) && $ph !== '') {
            $ud['ph'] = [hash('sha256', $ph)];
        }
        $uid = trim((string) ($ctx['user_id'] ?? ''));
        if ($uid !== '') {
            $ud['external_id'] = [hash('sha256', $uid)];
        }
        $ip = trim((string) ($ctx['ip'] ?? ''));
        if ($ip !== '') {
            $ud['client_ip_address'] = $ip;
        }
        $ua = trim((string) ($ctx['ua'] ?? ''));
        if ($ua !== '') {
            $ud['client_user_agent'] = $ua;
        }
        $fbp = trim((string) ($ctx['fbp'] ?? ''));
        if ($fbp !== '') {
            $ud['fbp'] = $fbp;
        }
        $fbc = trim((string) ($ctx['fbc'] ?? ''));
        if ($fbc !== '') {
            $ud['fbc'] = $fbc;
        }

        return $ud;
    }
}
