<?php

namespace app\live\service;

use think\facade\Cache;

/**
 * IP 归属地解析（批量）
 *
 * 数据源：ip-api.com 免费批量接口（HTTP，lang=zh-CN 返回中文）。
 * 结果写入缓存 30 天，失败结果缓存 10 分钟，避免后台列表反复请求。
 * 仅用于后台展示，任何异常都返回空字符串，不阻断列表主流程。
 */
class IpRegionService
{
    private const CACHE_PREFIX = 'ip_region:';
    private const CACHE_TTL    = 2592000; // 30 天
    private const FAIL_TTL     = 600;     // 解析失败缓存 10 分钟

    /**
     * 批量解析 IP 归属地
     *
     * @param string[] $ips
     * @return array<string, string> ip => 归属地文案（解析失败为空字符串）
     */
    public static function regions(array $ips): array
    {
        $ips = array_values(array_unique(array_filter(array_map('trim', $ips))));
        $map = [];
        $missing = [];

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                continue;
            }
            // 内网 / 保留地址无需查询
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                $map[$ip] = '内网IP';
                continue;
            }
            $cached = Cache::get(self::CACHE_PREFIX . $ip);
            if (is_string($cached) && $cached !== '') {
                $map[$ip] = $cached === '-' ? '' : $cached;
                continue;
            }
            $missing[] = $ip;
        }

        if ($missing === []) {
            return $map;
        }

        $resolved = self::queryBatch($missing);
        foreach ($missing as $ip) {
            $label = $resolved[$ip] ?? '';
            if ($label !== '') {
                Cache::set(self::CACHE_PREFIX . $ip, $label, self::CACHE_TTL);
                $map[$ip] = $label;
            } else {
                Cache::set(self::CACHE_PREFIX . $ip, '-', self::FAIL_TTL);
                $map[$ip] = '';
            }
        }

        return $map;
    }

    /**
     * 调用 ip-api 批量接口
     *
     * @param string[] $ips
     * @return array<string, string>
     */
    private static function queryBatch(array $ips): array
    {
        $url     = 'http://ip-api.com/batch?lang=zh-CN&fields=status,country,regionName,city,query';
        $payload = json_encode(array_values(array_slice($ips, 0, 100)), JSON_UNESCAPED_UNICODE);
        $raw     = self::postJson($url, (string) $payload);
        if ($raw === '') {
            return [];
        }

        $rows = json_decode($raw, true);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || (string) ($row['status'] ?? '') !== 'success') {
                continue;
            }
            $ip = (string) ($row['query'] ?? '');
            if ($ip === '') {
                continue;
            }
            $parts = [];
            foreach (['country', 'regionName', 'city'] as $field) {
                $val = trim((string) ($row[$field] ?? ''));
                if ($val !== '' && !in_array($val, $parts, true)) {
                    $parts[] = $val;
                }
            }
            if ($parts !== []) {
                $out[$ip] = implode(' ', $parts);
            }
        }

        return $out;
    }

    private static function postJson(string $url, string $payload): string
    {
        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 2,
                    CURLOPT_TIMEOUT        => 3,
                    CURLOPT_USERAGENT      => 'douyin-admin/1.0',
                ]);
                $raw = curl_exec($ch);
                curl_close($ch);
                return is_string($raw) ? $raw : '';
            }

            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\n",
                    'content' => $payload,
                    'timeout' => 3,
                ],
            ]);
            $raw = @file_get_contents($url, false, $ctx);
            return is_string($raw) ? $raw : '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
