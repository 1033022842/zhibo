<?php
declare(strict_types=1);

namespace app\admin\middleware;

use think\Request;
use think\Response;

/**
 * 后台飘页（live 下无组件直连页）访问守卫
 *
 * 这些控制器为 iframe/直连场景设置了 noNeedLogin，缺乏 BuildAdmin 会话鉴权，
 * 统一用 URL 携带 ?_key=<env ADMIN_FLOAT_KEY> 首次校验，通过后写 _fk Cookie，
 * 页面内 fetch（approve/reject 等）自动携带 Cookie 免逐个改 URL。
 *
 * 注意：目标页面用 response()->send(); exit 直出，绕过响应生命周期，
 * 因此 Cookie 用原生 header() 在动作执行前发送。
 */
final class FloatAuth
{
    public function handle(Request $request, \Closure $next): Response
    {
        $secret = trim((string) env('ADMIN_FLOAT_KEY', ''));
        if ($secret === '') {
            // 未配置密钥时拒绝访问（部署时必须在 .env 配置）
            return Response::create('Not Found', 'html', 404);
        }

        $key = (string) $request->param('_key', '');
        $cookie = (string) ($request->cookie('admin_float_key') ?? '');

        if ($key !== '' && hash_equals($secret, $key)) {
            if ($cookie !== $key) {
                header('Set-Cookie: admin_float_key=' . rawurlencode($key) . '; Path=/admin/; Max-Age=86400; HttpOnly; SameSite=Lax');
            }
            return $next($request);
        }

        if ($cookie !== '' && hash_equals($secret, $cookie)) {
            return $next($request);
        }

        // 后台面板 iframe 加载（同源 /admin 页面内嵌本页，浏览器会携带面板地址作为 Referer）
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $origin  = rtrim($request->domain(), '/');
        if ($referer !== '' && str_starts_with($referer, $origin . '/admin')) {
            return $next($request);
        }

        return Response::create('Not Found', 'html', 404);
    }
}
