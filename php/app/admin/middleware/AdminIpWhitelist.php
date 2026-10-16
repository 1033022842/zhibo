<?php
declare(strict_types=1);

namespace app\admin\middleware;

use think\Request;
use think\Response;
use think\facade\Db;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * 后台 IP 白名单（应用层）
 *
 * 规则（防锁死优先）：
 *  1. 总开关关闭（默认）→ 直接放行；
 *  2. 本机回环（127.0.0.1 / ::1）→ 永远放行；
 *  3. 携带 ?_ipkey=<ADMIN_FLOAT_KEY> → 放行（应急旁路，即使被拦也能进）；
 *  4. 命中白名单表 → 放行；
 *  5. 其它 → 403（AJAX/接口返回 JSON，页面返回 HTML）。
 *
 * 白名单值支持单 IP 与 CIDR 网段（IpUtils::checkIp）。
 */
final class AdminIpWhitelist
{
    public function handle(Request $request, \Closure $next): Response
    {
        // 任何异常（DB 抖动、表未建、vendor 缺失）都放行，确保不因白名单把后台锁死
        try {
            // 1. 开关（默认关，不改变现有行为）
            $enabled = trim((string) Db::connect('live_mysql')->table('lp_platform_config')
                ->where('key', 'admin_ip_whitelist_enabled')->value('value'));
            if ($enabled !== '1') {
                return $next($request);
            }
        } catch (\Throwable $e) {
            return $next($request);
        }

        $ip = $request->ip();

        // 2. 回环永远放行（服务器本机运维）
        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            return $next($request);
        }

        // 3. 应急旁路：?_ipkey=<ADMIN_FLOAT_KEY>
        $secret = trim((string) env('ADMIN_FLOAT_KEY', ''));
        $ipkey  = (string) $request->param('_ipkey', '');
        if ($secret !== '' && $ipkey !== '' && hash_equals($secret, $ipkey)) {
            return $next($request);
        }

        // 4. 白名单命中（支持 CIDR）
        try {
            $rules = array_values(array_filter(array_map('trim',
                (array) Db::connect('live_mysql')->table('lp_admin_ip_whitelist')->column('ip')
            ), static fn($v) => $v !== ''));
            if ($rules && IpUtils::checkIp($ip, $rules)) {
                return $next($request);
            }
        } catch (\Throwable $e) {
            return $next($request);
        }

        // 5. 拒绝
        $accept = (string) $request->header('accept', '');
        if ($request->isAjax() || str_contains($accept, 'application/json')) {
            return Response::create(['code' => 403, 'msg' => 'IP not allowed', 'data' => null], 'json', 403);
        }
        return Response::create(
            '<html><body style="background:#0d0a0f;color:#a4a4a4;font-family:sans-serif;display:flex;'
            . 'align-items:center;justify-content:center;height:100vh"><h2>403 · This IP is not allowed.</h2></body></html>',
            'html',
            403
        );
    }
}
