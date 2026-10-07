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
        'tiktok'    => 'TikTok',
        'google'    => 'Google（GA4 / Ads / GTM）',
        'snapchat'  => 'Snapchat',
        'kwai'      => 'Kwai',
        'bing'      => 'Bing（Microsoft）',
        'pinterest' => 'Pinterest',
        'other'     => '其他（用自定义像素代码）',
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

    /** 客服渠道列表（后台用：含停用） */
    public function channels(bool $onlyEnabled = false): array
    {
        $query = Db::connect('live_mysql')->table('lp_cs_channel')->order('sort', 'asc')->order('id', 'asc');
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
    public function publicConfig(): array
    {
        try {
            $cached = Cache::store('file')->get(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
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
