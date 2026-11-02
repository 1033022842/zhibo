<?php
declare(strict_types=1);

namespace app\agent\controller;

use app\agent\middleware\Auth as AuthMiddleware;
use app\agent\service\AgentView;
use app\BaseController;
use app\common\facade\Token;
use think\facade\Cache;
use think\facade\Db;

/**
 * 代理登录 / 退出
 */
final class Auth extends BaseController
{
    private const TOKEN_TTL = 604800; // 7 天
    private const MAX_FAILS = 5;
    private const FAIL_TTL  = 900;    // 15 分钟

    public function index(): void
    {
        $this->redirect('/agent/auth/login');
    }

    public function login(): void
    {
        if ($this->request->isPost()) {
            $this->doLogin();
            return;
        }

        if ($this->currentAgent() !== null) {
            $this->redirect('/agent');
        }

        $this->showLogin(
            '',
            '',
            (string) $this->request->param('changed', '') === '1' ? '密码已修改，请使用新密码重新登录。' : '',
            (string) $this->request->param('redirect', '')
        );
    }

    public function logout(): void
    {
        $token = (string) ($this->request->cookie(AuthMiddleware::COOKIE) ?? '');
        $agent = $this->currentAgent();

        if ($agent !== null && $token !== '') {
            $csrf = hash_hmac(
                'sha256',
                'agent-csrf|' . (int) $agent['id'] . '|' . $token,
                (string) config('buildadmin.token.key')
            );
            if (!hash_equals($csrf, (string) ($this->request->post('_csrf') ?? ''))) {
                http_response_code(419);
                echo '请求已失效，请刷新页面后重试。';
                exit;
            }
            try {
                Token::delete($token);
            } catch (\Throwable) {
                // 忽略
            }
        }

        $this->clearCookie();
        $this->redirect('/agent/auth/login');
    }

    private function doLogin(): void
    {
        $username = trim((string) ($this->request->post('username') ?? ''));
        $password = (string) ($this->request->post('password') ?? '');
        $redirect = $this->safeRedirect((string) ($this->request->post('redirect') ?? ''));
        $ip       = (string) $this->request->ip();

        if ($username === '' || $password === '') {
            $this->showLogin('请输入代理账号和密码。', $username, '', $redirect);
        }

        $cache = null;
        try {
            $cache = Cache::store('file');
        } catch (\Throwable) {
            // 缓存不可用时跳过限流，不影响登录
        }
        $key   = 'agent_login_fail_' . md5(mb_strtolower($username) . '|' . $ip);
        $fails = $cache ? (int) ($cache->get($key) ?? 0) : 0;
        if ($fails >= self::MAX_FAILS) {
            $this->showLogin('登录失败次数过多，请 15 分钟后再试。', $username, '', $redirect);
        }

        $agent = null;
        try {
            $agent = Db::connect('live_mysql')->table('lp_agent')->where('username', $username)->find();
        } catch (\Throwable) {
            $this->showLogin('代理系统尚未初始化，请联系总部管理员。', $username, '', $redirect);
        }

        $ok = $agent && (int) ($agent['status'] ?? 0) === 1
            && verify_password($password, (string) ($agent['password_hash'] ?? ''));

        $this->writeLog($agent ? (int) $agent['id'] : 0, $username, $ip, $ok);

        if (!$ok) {
            if ($cache) {
                $cache->set($key, $fails + 1, self::FAIL_TTL);
            }
            $this->showLogin('账号或密码错误。', $username, '', $redirect);
        }

        if ($cache) {
            $cache->delete($key);
        }
        $id    = (int) $agent['id'];
        $token = bin2hex(random_bytes(32));

        try {
            Token::clear(AuthMiddleware::TYPE, $id);
            Token::set($token, AuthMiddleware::TYPE, $id, self::TOKEN_TTL);
            Db::connect('live_mysql')->table('lp_agent')->where('id', $id)->update([
                'last_login_at' => date('Y-m-d H:i:s'),
                'last_login_ip' => mb_substr($ip, 0, 64),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            $this->showLogin('登录失败，请稍后再试。', $username, '', $redirect);
        }

        $this->setCookie($token);
        $this->redirect($redirect !== '' ? $redirect : '/agent');
    }

    private function currentAgent(): ?array
    {
        $token = (string) ($this->request->cookie(AuthMiddleware::COOKIE) ?? '');
        if ($token === '') {
            return null;
        }
        try {
            $row = Token::get($token);
            if (!$row || ($row['type'] ?? '') !== AuthMiddleware::TYPE) {
                return null;
            }
            if (!empty($row['expire_time']) && (int) $row['expire_time'] <= time()) {
                return null;
            }
            $agent = Db::connect('live_mysql')->table('lp_agent')
                ->where('id', (int) ($row['user_id'] ?? 0))->find();
            return $agent && (int) $agent['status'] === 1 ? $agent : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function showLogin(string $error = '', string $username = '', string $notice = '', string $redirect = ''): void
    {
        $html = AgentView::login($error, $username, $notice, $this->safeRedirect($redirect));
        response($html)->header([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
            'X-Robots-Tag'  => 'noindex, nofollow',
        ])->send();
        exit;
    }

    private function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /** 只允许跳回代理站内，防开放重定向 */
    private function safeRedirect(string $url): string
    {
        $url = trim($url);
        if ($url === '' || !str_starts_with($url, '/agent') || str_starts_with($url, '//')) {
            return '/agent';
        }
        if (str_contains($url, '/agent/auth/login') || str_contains($url, '/agent/auth/logout')) {
            return '/agent';
        }
        return $url;
    }

    private function setCookie(string $token): void
    {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        header(
            'Set-Cookie: ' . AuthMiddleware::COOKIE . '=' . rawurlencode($token)
            . '; Path=/agent; Max-Age=' . self::TOKEN_TTL . '; HttpOnly; SameSite=Lax'
            . ($secure ? '; Secure' : ''),
            false
        );
    }

    private function clearCookie(): void
    {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        header(
            'Set-Cookie: ' . AuthMiddleware::COOKIE . '=; Path=/agent; Max-Age=0; HttpOnly; SameSite=Lax'
            . ($secure ? '; Secure' : ''),
            false
        );
    }

    private function writeLog(int $agentId, string $username, string $ip, bool $success): void
    {
        try {
            Db::connect('live_mysql')->table('lp_agent_login_log')->insert([
                'agent_id'   => $agentId,
                'username'   => mb_substr($username, 0, 64),
                'ip'         => mb_substr($ip, 0, 64),
                'ua'         => mb_substr((string) $this->request->header('user-agent', ''), 0, 255),
                'success'    => $success ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // 日志失败不影响登录
        }
    }
}
