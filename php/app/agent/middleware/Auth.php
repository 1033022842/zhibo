<?php
declare(strict_types=1);

namespace app\agent\middleware;

use app\common\facade\Token;
use Closure;
use think\facade\Db;
use think\Request;
use think\Response;

/**
 * 代理门户登录守卫
 *
 * 与 BuildAdmin 管理员完全隔离：
 * - Cookie: agent_token（Path=/agent，HttpOnly）
 * - Token type: agent
 * - 数据来源: lp_agent（不是 ba_admin）
 */
final class Auth
{
    public const COOKIE = 'agent_token';
    public const TYPE   = 'agent';

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) ($request->cookie(self::COOKIE) ?? '');
        $agent = $token !== '' ? $this->loadAgent($token) : null;

        if (!$agent || (int) ($agent['status'] ?? 0) !== 1) {
            if ($token !== '') {
                try {
                    Token::delete($token);
                } catch (\Throwable) {
                    // 忽略
                }
            }
            $target = '/agent/auth/login';
            $uri    = (string) ($request->server('REQUEST_URI') ?? '');
            if (str_starts_with($uri, '/agent') && !str_contains($uri, '/agent/auth/login')) {
                $target .= '?redirect=' . urlencode($uri);
            }
            return redirect($target);
        }

        $request->agent      = $agent;
        $request->agentToken = $token;

        return $next($request);
    }

    private function loadAgent(string $token): ?array
    {
        try {
            $row = Token::get($token);
            if (!$row || ($row['type'] ?? '') !== self::TYPE) {
                return null;
            }
            if (!empty($row['expire_time']) && (int) $row['expire_time'] <= time()) {
                return null;
            }
            $agent = Db::connect('live_mysql')->table('lp_agent')
                ->where('id', (int) ($row['user_id'] ?? 0))->find();
            return $agent ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
