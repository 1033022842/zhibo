<?php
declare(strict_types=1);

namespace app\live\service;

use think\facade\Cache;
use think\facade\Db;

/**
 * 营销推广（像素/转化事件）与前端客服渠道配置
 *
 * - 像素存 lp_promo_pixel（可无限多条，每条带渠道 / 像素ID / 事件 / 推广域名 / 像素代码 / 测试回调编码）
 * - 事件总开关存 lp_platform_config（key/value），客服渠道存 lp_cs_channel
 * - 前端通过公开接口 GET /api/v1/site/config 读取，60 秒文件缓存，后台保存时主动清缓存
 */
final class PromoService
{
    /** 公开配置缓存键 */
    public const CACHE_KEY = 'site_promo_config_v2';
    public const CACHE_TTL = 60;

    /**
     * 转化事件总开关（关掉后所有像素都不上报该事件）
     * key 与前端的 window.SugusTrack.event(name) 一一对应
     */
    public const EVENT_FIELDS = [
        'promo_ev_register'  => ['bool', '完成注册上报转化事件'],
        'promo_ev_recharge'  => ['bool', '购物/充值成功上报转化事件'],
        'promo_ev_subscribe' => ['bool', '订阅购买成功上报转化事件'],
    ];

    /** 像素渠道（推广平台） */
    public const PIXEL_PLATFORMS = [
        'general'   => '普通推广（短信 / 群发，无需像素）',
        'facebook'  => 'Facebook / Meta',
        'instagram' => 'Instagram（无内置像素，仅归因）',
        'tiktok'    => 'TikTok',
        'x'         => 'X / Twitter（无内置像素，仅归因）',
        'youtube'   => 'YouTube（无内置像素，仅归因）',
        'reddit'    => 'Reddit（无内置像素，仅归因）',
        'threads'   => 'Threads（无内置像素，仅归因）',
        'snapchat'  => 'Snapchat',
        'kwai'      => 'Kwai',
        'bing'      => 'Bing（Microsoft）',
        'pinterest' => 'Pinterest',
        'linkedin'  => 'LinkedIn（仅归因）',
        'quora'     => 'Quora（仅归因）',
        'telegram'  => 'Telegram（仅归因）',
        'whatsapp'  => 'WhatsApp（仅归因）',
        'line'      => 'LINE（仅归因）',
        'discord'   => 'Discord（仅归因）',
        'google'    => 'Google（GA4 / Ads / GTM）',
        'other'     => '其他（无需像素）',
    ];

    /**
     * 流量来源平台（报表展示用）
     * direct = 直接访问（没有 Referer、也没有带渠道参数）
     */
    public const TRAFFIC_PLATFORMS = [
        'x'         => 'X / Twitter',
        'facebook'  => 'Facebook',
        'instagram' => 'Instagram',
        'tiktok'    => 'TikTok',
        'youtube'   => 'YouTube',
        'reddit'    => 'Reddit',
        'threads'   => 'Threads',
        'google'    => 'Google',
        'snapchat'  => 'Snapchat',
        'kwai'      => 'Kwai',
        'pinterest' => 'Pinterest',
        'bing'      => 'Bing',
        'linkedin'  => 'LinkedIn',
        'quora'     => 'Quora',
        'telegram'  => 'Telegram',
        'whatsapp'  => 'WhatsApp',
        'line'      => 'LINE',
        'discord'   => 'Discord',
        'direct'    => '直接访问',
        'other'     => '其他 / 未识别',
    ];

    /** 平台短名（后台会员列表标签用） */
    public const PLATFORM_SHORT = [
        'x'         => 'X',
        'facebook'  => 'FB',
        'instagram' => 'INS',
        'tiktok'    => 'TT',
        'youtube'   => 'YT',
        'reddit'    => 'REDDIT',
        'threads'   => 'THREADS',
        'google'    => 'GG',
        'snapchat'  => 'SNAP',
        'kwai'      => 'KWAI',
        'pinterest' => 'PIN',
        'bing'      => 'BING',
        'linkedin'  => 'IN',
        'quora'     => 'QUORA',
        'telegram'  => 'TG',
        'whatsapp'  => 'WA',
        'line'      => 'LINE',
        'discord'   => 'DC',
        'direct'    => '直访',
        'other'     => '其他',
    ];

    /**
     * 没有内置像素、只用于归因和链接分发的平台
     * （这些平台可以只建渠道 + 复制链接，不要求填像素ID/代码）
     */
    public const NO_PIXEL_PLATFORMS = [
        'general', 'other', 'x', 'instagram', 'youtube', 'reddit', 'threads',
        'linkedin', 'quora', 'telegram', 'whatsapp', 'line', 'discord',
    ];

    /** 像素可接收的事件 */
    public const PIXEL_EVENTS = [
        'register'  => '完成注册',
        'recharge'  => '购物 / 充值',
        'subscribe' => '订阅',
    ];

    /** 内置平台在广告后台对应的事件名（前端 JS 里也有一份，保持一致） */
    public const PLATFORM_EVENTS = [
        'facebook' => ['register' => 'CompleteRegistration', 'recharge' => 'Purchase',        'subscribe' => 'Subscribe'],
        'tiktok'   => ['register' => 'CompleteRegistration', 'recharge' => 'CompletePayment', 'subscribe' => 'Subscribe'],
        'google'   => ['register' => 'sign_up',              'recharge' => 'purchase',        'subscribe' => 'subscribe'],
    ];

    /** 客服弹窗全局设置 */
    public const CS_FIELDS = [
        'cs_enabled'  => ['bool', '客服总开关'],
        'cs_float'    => ['bool', '右下角悬浮客服按钮'],
        'cs_title'    => ['str',  '客服弹窗标题'],
        'cs_subtitle' => ['str',  '客服弹窗副标题'],
        'cs_hint'     => ['str',  '悬浮按钮气泡文案'],
        'cs_logo'     => ['str',  '客服图标 URL（留空用内置图标）'],
    ];

    /**
     * 前端域名列表（像素「推广域名」下拉的数据源）
     * 一行一个，前台每有一个域名访问 /api/v1/site/config 会自动收录进来，后台也能手动维护
     */
    public const DOMAIN_KEY = 'promo_site_domains';
    public const DOMAIN_IGNORE_KEY = 'promo_site_domains_ignored';
    public const DOMAIN_MAX = 30;

    /** 渠道类型白名单 */
    public const CHANNEL_TYPES = [
        'telegram'  => 'Telegram',
        'whatsapp'  => 'WhatsApp',
        'line'      => 'LINE',
        'messenger' => 'Messenger',
        'email'     => '邮件',
        'link'      => '外链',
        'embed'     => '嵌入网页（iframe）',
    ];

    // ================= 读取 =================

    /** 读一个配置项（不存在返回默认值） */
    public function get(string $key, string $default = ''): string
    {
        $value = Db::connect('live_mysql')->table('lp_platform_config')
            ->where('key', $key)->value('value');
        return $value === null ? $default : (string) $value;
    }

    /** 前端域名列表（像素「推广域名」下拉用） */
    public function siteDomains(): array
    {
        $raw = $this->get(self::DOMAIN_KEY, '');
        $out = [];
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $d) {
            $d = self::normalizeDomain($d);
            if ($d !== '') {
                $out[] = $d;
            }
        }
        return array_values(array_unique($out));
    }

    /** 被后台手工删掉、不再自动收录的域名 */
    public function ignoredDomains(): array
    {
        $raw = $this->get(self::DOMAIN_IGNORE_KEY, '');
        $out = [];
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $d) {
            $d = self::normalizeDomain($d);
            if ($d !== '') {
                $out[] = $d;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * 覆盖式保存前端域名列表：本次被删掉的域名记入「不再自动收录」，手动加回来的则取消忽略
     */
    public function saveDomains(array $list): array
    {
        $clean = [];
        foreach ($list as $d) {
            $d = self::normalizeDomain((string) $d);
            if ($d !== '' && !in_array($d, $clean, true)) {
                $clean[] = $d;
            }
        }
        $ignored = $this->ignoredDomains();
        foreach ($this->siteDomains() as $old) {
            if (!in_array($old, $clean, true) && !in_array($old, $ignored, true)) {
                $ignored[] = $old;
            }
        }
        foreach ($clean as $d) {
            $ignored = array_values(array_diff($ignored, [$d]));
        }
        $this->saveSetting(self::DOMAIN_KEY, implode("\n", $clean), '前端域名（自动收录 + 后台可维护）');
        $this->saveSetting(self::DOMAIN_IGNORE_KEY, implode("\n", $ignored), '前端域名：已删除，不再自动收录');
        return $clean;
    }

    /**
     * 收录一个前端域名（前台每次拉公开配置时把当前 Host 记进来，去重、限量）
     * 这样后台「推广域名」下拉里出现的就是真在跑我们前端的域名，不需要手填
     */
    public function rememberDomain(string $host): void
    {
        $host = self::normalizeDomain($host);
        if ($host === '' || str_contains($host, '*')) {
            return;
        }
        // IP 不作为推广域名
        if (self::isIp($host)) {
            return;
        }
        $list = $this->siteDomains();
        if (in_array($host, $list, true)) {
            return;
        }
        // 后台删过的域名不再自动回来
        if (in_array($host, $this->ignoredDomains(), true)) {
            return;
        }
        if (count($list) >= self::DOMAIN_MAX) {
            return;
        }
        $list[] = $host;
        $this->saveSetting(self::DOMAIN_KEY, implode("\n", $list), '前端域名（自动收录 + 后台可维护）');
    }

    /** 是否是 IP / 本机地址（这些不当推广域名） */
    public static function isIp(string $host): bool
    {
        return filter_var($host, FILTER_VALIDATE_IP) !== false
            || preg_match('/^(localhost|\[)/', $host) === 1;
    }

    /** 域名规范化：去协议、去端口路径、转小写 */
    public static function normalizeDomain(string $raw): string
    {
        $d = strtolower(trim($raw));
        $d = preg_replace('#^https?://#', '', $d) ?? $d;
        $d = preg_replace('#[/:].*$#', '', $d) ?? $d;
        return trim($d);
    }

    /** 批量读配置项（promo_ / cs_ 前缀） */
    public function all(): array
    {
        $out = [];
        foreach (Db::connect('live_mysql')->table('lp_platform_config')->select() as $row) {
            $key = (string) $row['key'];
            if (str_starts_with($key, 'promo_') || str_starts_with($key, 'cs_')) {
                $out[$key] = (string) $row['value'];
            }
        }
        return $out;
    }

    /**
     * 客服渠道列表（后台用：含停用）
     * $agentId = 0 表示总部全局渠道；> 0 为某个代理的独立渠道
     */
    public function channels(bool $onlyEnabled = false, int $agentId = 0): array
    {
        $query = Db::connect('live_mysql')->table('lp_cs_channel')
            ->where('agent_id', $agentId)
            ->order('sort', 'asc')->order('id', 'asc');
        if ($onlyEnabled) {
            $query->where('status', 1);
        }
        return $query->select()->toArray();
    }

    /** 像素列表（后台用：含停用） */
    public function pixels(bool $onlyEnabled = false): array
    {
        $query = Db::connect('live_mysql')->table('lp_promo_pixel')->order('sort', 'asc')->order('id', 'asc');
        if ($onlyEnabled) {
            $query->where('status', 1);
        }
        return $query->select()->toArray();
    }

    /**
     * 前端公开配置（带 60s 缓存）
     * 像素为多条列表，每条带自己的事件、域名、测试回调编码
     */
    public function publicConfig(?string $host = null): array
    {
        try {
            $cached = Cache::store('file')->get(self::CACHE_KEY);
            if (is_array($cached)) {
                return $this->applyAgentCs($cached, $host);
            }
        } catch (\Throwable $e) {
            // 缓存不可用时直接查库，不影响前台
        }

        $cfg  = $this->all();
        $bool = static fn(string $k, bool $d = false): bool => isset($cfg[$k]) ? $cfg[$k] === '1' : $d;

        $pxItems = [];
        foreach ($this->pixels(true) as $row) {
            $platform = (string) $row['platform'];
            if (!isset(self::PIXEL_PLATFORMS[$platform])) {
                $platform = 'other';
            }
            $events = array_values(array_filter(
                array_map('trim', explode(',', (string) $row['events'])),
                static fn(string $e): bool => isset(self::PIXEL_EVENTS[$e])
            ));
            $domains = array_values(array_filter(
                preg_split('/[\s,;]+/', (string) $row['domains']) ?: []
            ));
            // 代理像素：没限定域名时只在带 ?px= 的链接里生效，避免共用平台域名时对全站访客打点
            if ((int) ($row['agent_id'] ?? 0) > 0 && $domains === []) {
                $domains = ['__px_only__'];
            }
            $code = trim((string) ($row['code'] ?? ''));
            $pixelId = trim((string) $row['pixel_id']);
            // 既没有 ID 也没有自定义代码的空记录不发给前端
            if ($pixelId === '' && $code === '') {
                continue;
            }
            $pxItems[] = [
                'id'       => (int) $row['id'],
                'platform' => $platform,
                'pixel'    => $pixelId,
                'events'   => $events,
                'domains'  => $domains,
                'code'     => $code,
                'test'     => trim((string) $row['test_code']),
            ];
        }

        $items = [];
        foreach ($this->channels(true) as $row) {
            $type = (string) $row['type'];
            if (!isset(self::CHANNEL_TYPES[$type])) {
                $type = 'link';
            }
            $items[] = [
                'id'   => (int) $row['id'],
                'type' => $type,
                'name' => (string) $row['name'],
                'url'  => (string) $row['url'],
                'icon' => (string) $row['icon'],
            ];
        }

        $data = [
            'pixels' => [
                'events' => [
                    'register'  => $bool('promo_ev_register', true),
                    'recharge'  => $bool('promo_ev_recharge', true),
                    'subscribe' => $bool('promo_ev_subscribe', true),
                ],
                'items'  => $pxItems,
            ],
            'cs' => [
                'enabled'  => $bool('cs_enabled', true),
                'float'    => $bool('cs_float', true),
                'title'    => (string) ($cfg['cs_title'] ?? 'Contact us'),
                'subtitle' => (string) ($cfg['cs_subtitle'] ?? ''),
                'hint'     => (string) ($cfg['cs_hint'] ?? ''),
                'logo'     => (string) ($cfg['cs_logo'] ?? ''),
                'items'    => $items,
            ],
        ];

        try {
            Cache::store('file')->set(self::CACHE_KEY, $data, self::CACHE_TTL);
        } catch (\Throwable $e) {
            // 忽略
        }
        return $this->applyAgentCs($data, $host);
    }

    /**
     * 代理专属客服：访问域名属于某个代理时，用该代理自己的客服渠道替换总部渠道
     * （代理没配置任何渠道时仍用总部配置，避免客服入口消失）
     */
    private function applyAgentCs(array $data, ?string $host): array
    {
        if ($host === null || trim($host) === '') {
            return $data;
        }
        try {
            $agentId = \app\live\service\AgentService::agentByDomain($host);
            if ($agentId <= 0) {
                return $data;
            }
            $rows = $this->channels(true, $agentId);
            if ($rows === []) {
                return $data;
            }
            $items = [];
            foreach ($rows as $row) {
                $type = (string) $row['type'];
                if (!isset(self::CHANNEL_TYPES[$type])) {
                    $type = 'link';
                }
                $items[] = [
                    'id'   => (int) $row['id'],
                    'type' => $type,
                    'name' => (string) $row['name'],
                    'url'  => (string) $row['url'],
                    'icon' => (string) $row['icon'],
                ];
            }
            $data['cs']['items'] = $items;
        } catch (\Throwable $e) {
            // 代理客服异常时不影响前台，继续用总部配置
        }
        return $data;
    }

    // ================= 写入 =================

    /** upsert 一个配置项 */
    public function saveSetting(string $key, string $value, string $remark = ''): void
    {
        $db  = Db::connect('live_mysql');
        $now = date('Y-m-d H:i:s');
        $row = $db->table('lp_platform_config')->where('key', $key)->find();
        if ($row) {
            $data = ['value' => $value, 'updated_at' => $now];
            if ($remark !== '') {
                $data['remark'] = $remark;
            }
            $db->table('lp_platform_config')->where('key', $key)->update($data);
        } else {
            $db->table('lp_platform_config')->insert([
                'key' => $key, 'value' => $value, 'remark' => $remark, 'updated_at' => $now,
            ]);
        }
        $this->flushCache();
    }

    // ================= 流量来源（落地访问） =================

    /** 平台展示名 */
    public static function platformLabel(string $key): string
    {
        $key = strtolower(trim($key));
        if ($key === '') {
            return '其他 / 未识别';
        }
        return self::TRAFFIC_PLATFORMS[$key] ?? $key;
    }

    /** 平台短名（会员列表标签） */
    public static function platformShort(string $key): string
    {
        $key = strtolower(trim($key));
        return self::PLATFORM_SHORT[$key] ?? '';
    }

    /**
     * 从渠道参数 / 推广链接名称里识别平台
     * 支持 x / twitter、tiktok / tt、instagram / ig、reddit、threads、youtube / yt 等
     */
    public static function platformFromText(string $text): string
    {
        $text = strtolower(trim($text));
        if ($text === '') {
            return '';
        }
        // 顺序有意义：先匹配更具体的（youtube / instagram），再匹配短缩写
        $rules = [
            'youtube'   => '/youtube|youtu\.be|(?:^|[^a-z])yt\d*(?:[^a-z]|$)/i',
            'instagram' => '/instagram|insta|(?:^|[^a-z])(?:ig|ins)\d*(?:[^a-z]|$)/i',
            'threads'   => '/threads/i',
            'reddit'    => '/reddit|redd\.it/i',
            'x'         => '/twitter|x-twitter|x\.com|t\.co|(?:^|[\s_])x\d*(?:[-\s_]|$)/i',
            'tiktok'    => '/tiktok|douyin|(?:^|[^a-z])tt\d*(?:[^a-z]|$)/i',
            'facebook'  => '/facebook|(?:^|[^a-z])fb\d*(?:[^a-z]|$)|meta|messenger/i',
            'snapchat'  => '/snapchat|(?:^|[^a-z])snap\d*(?:[^a-z]|$)/i',
            'kwai'      => '/kwai|kuaishou/i',
            'pinterest' => '/pinterest|pin\.it/i',
            'bing'      => '/bing/i',
            'linkedin'  => '/linkedin|lnkd\.in/i',
            'quora'     => '/quora/i',
            'telegram'  => '/telegram|t\.me|(?:^|[^a-z])tg\d*(?:[^a-z]|$)/i',
            'whatsapp'  => '/whatsapp|wa\.me|(?:^|[^a-z])wa\d*(?:[^a-z]|$)/i',
            'line'      => '/(?:^|[^a-z])line\d*(?:[^a-z]|$)/i',
            'discord'   => '/discord/i',
            'google'    => '/google|adwords|(?:^|[^a-z])(?:gdn|gg)\d*(?:[^a-z]|$)/i',
        ];
        foreach ($rules as $platform => $regex) {
            if (preg_match($regex, $text) === 1) {
                return $platform;
            }
        }
        return '';
    }

    /**
     * 从 User-Agent 识别 App 内置浏览器
     * Referer 被社媒 App 抹掉时，UA 仍会保留 App 标识（Instagram / TikTok / FBAN 等）
     */
    public static function platformFromUserAgent(string $ua): string
    {
        $ua = strtolower(trim($ua));
        if ($ua === '') {
            return '';
        }
        $rules = [
            'threads'   => '/threads|barcelona/',
            'instagram' => '/instagram/',
            'tiktok'    => '/tiktok|musical_ly|trill|bytedance/',
            'x'         => '/twitter|x corp|\bx\/\d/',
            'youtube'   => '/youtube/',
            'reddit'    => '/reddit/',
            'facebook'  => '/fban|fbav|fbios|fb_iab|facebook/',
            'snapchat'  => '/snapchat/',
            'pinterest' => '/pinterest/',
            'linkedin'  => '/linkedin/',
            'telegram'  => '/telegram/',
            'whatsapp'  => '/whatsapp/',
            'line'      => '/\bline\//',
            'discord'   => '/discord/',
        ];
        foreach ($rules as $platform => $regex) {
            if (preg_match($regex, $ua) === 1) {
                return $platform;
            }
        }
        return '';
    }

    /** 从 Referer 主机名识别平台（站内跳转不算来源） */
    public static function platformFromReferer(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        if ($host === '') {
            return '';
        }

        $map = [
            'x'         => ['x.com', 'twitter.com', 't.co'],
            'tiktok'    => ['tiktok.com'],
            'instagram' => ['instagram.com', 'ig.me'],
            'reddit'    => ['reddit.com', 'redd.it'],
            'threads'   => ['threads.net', 'threads.com'],
            'youtube'   => ['youtube.com', 'youtu.be'],
            'facebook'  => ['facebook.com', 'fb.com', 'fb.me'],
            'snapchat'  => ['snapchat.com'],
            'kwai'      => ['kwai.com', 'kwai-video.com'],
            'pinterest' => ['pinterest.com', 'pin.it'],
            'bing'      => ['bing.com'],
            'linkedin'  => ['linkedin.com', 'lnkd.in'],
            'quora'     => ['quora.com'],
            'telegram'  => ['t.me', 'telegram.me'],
            'whatsapp'  => ['wa.me', 'whatsapp.com'],
            'line'      => ['line.me', 'line.com'],
            'discord'   => ['discord.com', 'discord.gg'],
        ];
        foreach ($map as $platform => $hosts) {
            foreach ($hosts as $suffix) {
                if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                    return $platform;
                }
            }
        }
        // Google 各国家域名（google.com / google.co.uk / google.com.hk …）
        if (preg_match('/^google\.[a-z.]+$/', $host) === 1) {
            return 'google';
        }
        return '';
    }

    /**
     * 写入一条落地访问（同一会话 sid 只记一次）
     * 任何异常都吞掉，绝不影响前台页面
     */
    public function recordVisit(array $in, string $ip = '', string $country = ''): bool
    {
        $sid = self::clip(trim((string) ($in['sid'] ?? '')), 64);
        if ($sid === '') {
            return false;
        }
        $ua = strtolower(substr(trim((string) ($in['ua'] ?? '')), 0, 255));
        if ($ua !== '' && preg_match('/googlebot|bingbot|yandex|baiduspider|ahrefs|semrush|mj12|dotbot|petalbot|bytespider|crawler|spider|slurp|headless|lighthouse|pingdom|uptime|curl|wget|python-requests|go-http-client|facebookexternalhit|telegrambot|(?:^|[^a-z])bot(?:[\/\s;)]|$)/i', $ua) === 1) {
            return false;
        }

        $domain = self::normalizeDomain((string) ($in['domain'] ?? ''));
        if ($domain === '') {
            return false;
        }

        $referer = trim((string) ($in['referer'] ?? ''));
        $refHost = '';
        if ($referer !== '') {
            $h       = parse_url($referer, PHP_URL_HOST);
            $refHost = self::normalizeDomain(is_string($h) && $h !== '' ? $h : $referer);
        }
        // 站内跳转不算外部来源
        if ($refHost === $domain) {
            $refHost = '';
        }

        $linkId   = max(0, (int) ($in['l'] ?? 0));
        $px       = self::clip(trim((string) ($in['px'] ?? '')), 64);
        $channel  = self::clip(trim((string) ($in['ch'] ?? '')), 64);
        $linkName = self::clip(trim((string) ($in['name'] ?? '')), 128);

        $db = Db::connect('live_mysql');
        try {
            if ($linkId > 0) {
                $link = $db->table('lp_promo_link')->where('id', $linkId)->find();
                if ($link) {
                    $linkName = $linkName !== '' ? $linkName : self::clip(trim((string) ($link['name'] ?? '')), 128);
                    if ($channel === '') {
                        $channel = self::clip(trim((string) ($link['channel'] ?? '')), 64);
                    }
                    if ($px === '' && (int) ($link['pixel_id'] ?? 0) > 0) {
                        $px = (string) (int) $link['pixel_id'];
                    }
                }
            }
        } catch (\Throwable $e) {
            // 链接反解失败不阻断访问记录
        }

        // 像素平台：推广链接只带 ?px= 时也能确定平台
        $pixelPlatform = '';
        if ($px !== '') {
            try {
                $pixel = ctype_digit($px)
                    ? $db->table('lp_promo_pixel')->where('id', (int) $px)->find()
                    : null;
                if (!$pixel) {
                    $pixel = $db->table('lp_promo_pixel')->where('pixel_id', $px)->find();
                }
                $pKey = strtolower(trim((string) ($pixel['platform'] ?? '')));
                if ($pKey !== '' && isset(self::TRAFFIC_PLATFORMS[$pKey]) && !in_array($pKey, ['direct', 'other'], true)) {
                    $pixelPlatform = $pKey;
                }
            } catch (\Throwable $e) {
                // 忽略
            }
        }

        $platform = self::platformFromText($channel . ' ' . $linkName);
        if ($platform === '') {
            $platform = $pixelPlatform;
        }
        if ($platform === '') {
            $platform = self::platformFromReferer($refHost);
        }
        if ($platform === '') {
            // Referer 被 App 抹掉时，UA 里的 App 标识仍可兜底
            $platform = self::platformFromUserAgent((string) ($in['ua'] ?? ''));
        }
        if ($platform === '') {
            $platform = ($refHost === '' && $channel === '' && $linkName === '') ? 'direct' : 'other';
        }

        $data = [
            'vid'        => self::clip(trim((string) ($in['vid'] ?? '')), 64),
            'sid'        => $sid,
            'domain'     => self::clip($domain, 120),
            'platform'   => self::clip($platform, 32),
            'referer'    => self::clip($refHost, 120),
            'channel'    => $channel,
            'link_id'    => $linkId,
            'link_name'  => $linkName,
            'px'         => $px,
            'campaign'   => self::clip(trim((string) ($in['campaign'] ?? '')), 128),
            'adset'      => self::clip(trim((string) ($in['adset'] ?? '')), 128),
            'ad'         => self::clip(trim((string) ($in['ad'] ?? '')), 128),
            'landing'    => self::clip(trim((string) ($in['landing'] ?? '')), 255),
            'ua'         => $ua,
            'ip'         => self::clip(trim($ip), 45),
            'country'    => strtoupper(self::clip(trim($country), 8)),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        try {
            $affected = $db->execute(
                'INSERT IGNORE INTO `lp_promo_visit`
                    (`vid`,`sid`,`domain`,`platform`,`referer`,`channel`,`link_id`,`link_name`,`px`,
                     `campaign`,`adset`,`ad`,`landing`,`ua`,`ip`,`country`,`created_at`)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                array_values($data)
            );
            return (int) $affected > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** 按字符截断（避免超长参数写库报错） */
    private static function clip(string $value, int $length): string
    {
        return mb_substr($value, 0, $length);
    }

    /** 渠道点击 +1（只统计启用中的渠道，返回是否记上） */
    public function hit(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        $affected = Db::connect('live_mysql')->table('lp_cs_channel')
            ->where('id', $id)->where('status', 1)
            ->update(['clicks' => Db::raw('clicks + 1')]);
        return $affected > 0;
    }

    public function flushCache(): void
    {
        try {
            Cache::store('file')->delete(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // 忽略
        }
    }
}
