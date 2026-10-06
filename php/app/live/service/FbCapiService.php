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
