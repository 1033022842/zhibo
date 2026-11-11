<?php
declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use app\common\exception\BusinessException;
use app\common\library\Email;
use app\common\web\ResultCode;
use app\live\service\EmailVerifyService;
use app\live\service\FbCapiService;
use app\live\service\SocialLoginService;
use app\live\service\UserService;
use GuzzleHttp\Client;

final class LoginGateway extends BaseController
{
    private const COOKIE = 'live_oauth_browser';

    public function options()
    {
        $providers = [];
        foreach (['google' => 'Google', 'discord' => 'Discord', 'twitter' => 'X (Twitter)'] as $key => $label) {
            $providers[] = ['id' => $key, 'label' => $label, 'enabled' => SocialLoginService::ready($key)];
        }
        // Telegram 是 widget 授权（非跳转），bot username 随配置下发供前端渲染 widget
        $providers[] = [
            'id' => 'telegram', 'label' => 'Telegram', 'enabled' => SocialLoginService::telegramReady(),
            'bot' => (string) config('social.telegram.bot_username', ''),
        ];
        return $this->jsonSuccess(['email_code' => $this->emailReady(), 'providers' => $providers])
            ->header(['Cache-Control' => 'no-store']);
    }

    public function sendEmailLoginCode()
    {
        $this->requireEmail();
        $email = $this->email();
        (new EmailVerifyService())->sendLoginCode($email, $this->request->ip());
        return $this->jsonSuccess(null, 'Code sent. Please check your email.')
            ->header(['Cache-Control' => 'no-store']);
    }

    public function emailCodeLogin()
    {
        $this->requireEmail();
        $email = $this->email();
        $code = (string) $this->request->post('code', '');
        if (!preg_match('/^[0-9]{6}$/D', $code)) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Please enter the 6-digit sign-in code.');
        }
        (new EmailVerifyService())->verifyLoginCode($email, $code);
        $context = $this->context();
        $result = (new UserService())->loginWithIdentity('email', $email, $email, explode('@', $email)[0],
            $this->request->ip(), $context['device_id'], $context['invite_code'], $context['attr']);
        return $this->complete($result, $context);
    }

    public function socialStart(string $provider)
    {
        $browser = (string) $this->request->cookie(self::COOKIE, '');
        if (!preg_match('/^[a-f0-9]{64}$/D', $browser)) {
            $browser = bin2hex(random_bytes(32));
        }
        $url = (new SocialLoginService())->start($provider, $browser, $this->request->domain(),
            $this->context(), $this->request->ip());
        $cookie = self::COOKIE . '=' . $browser . '; Path=/api/live/; Max-Age=600; HttpOnly; SameSite=Lax';
        if ($this->request->isSsl()) {
            $cookie .= '; Secure';
        }
        // browser 令牌同时随 JSON 下发、前端存 sessionStorage：Bunny 等 CDN 不回传 Set-Cookie，
        // 只靠 cookie 会让回调/兑换全部失败。
        return $this->jsonSuccess(['authorize_url' => $url, 'browser' => $browser])
            ->header(['Set-Cookie' => $cookie, 'Cache-Control' => 'no-store']);
    }

    public function socialCallback(string $provider)
    {
        try {
            if ((string) $this->request->get('error', '') !== '') {
                throw new BusinessException(ResultCode::AUTH_FAILED, 'Authorization was cancelled.');
            }
            $ticket = (new SocialLoginService())->callback($provider,
                (string) $this->request->get('state', ''), (string) $this->request->get('code', ''),
                (string) $this->request->cookie(self::COOKIE, ''));
            $url = '/ai/Login.html#oauth_ticket=' . $ticket;
        } catch (\Throwable) {
            $url = '/ai/Login.html#oauth_error=authorization_failed';
        }
        return redirect($url)->header(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function socialExchange()
    {
        // browser 令牌优先取前端 sessionStorage 回传值（CDN 会吞 Set-Cookie），cookie 作兜底
        $browser = (string) $this->request->post('browser', '');
        if (!preg_match('/^[a-f0-9]{64}$/D', $browser)) {
            $browser = (string) $this->request->cookie(self::COOKIE, '');
        }
        $payload = (new SocialLoginService())->exchange((string) $this->request->post('ticket', ''), $browser);
        $identity = $payload['identity'];
        $context = $payload['context'];
        $result = (new UserService())->loginWithIdentity($payload['provider'], $identity['subject'],
            $identity['email'], $identity['nickname'], $this->request->ip(), $context['device_id'],
            $context['invite_code'], $context['attr']);
        return $this->complete($result, $context);
    }

    /**
     * Telegram Login Widget 授权回传：前端拿到带签名的用户载荷后直连本接口，
     * 服务端验签（不经过 ticket 中转），通过后与社交登录同链路落库发 token。
     * 当前 UI 走下方深链方案（不受 bot 域名白名单限制），本接口保留备用。
     */
    public function telegramLogin()
    {
        $this->throttle('tg_login_ip:', $this->request->ip(), 30);
        $identity = (new SocialLoginService())->verifyTelegram((array) $this->request->post());
        $context = $this->context();
        $result = (new UserService())->loginWithIdentity('telegram', $identity['subject'],
            $identity['email'], $identity['nickname'], $this->request->ip(), $context['device_id'],
            $context['invite_code'], $context['attr']);
        return $this->complete($result, $context);
    }

    /**
     * Telegram 深链登录第一步：发一次性 code，前端据此打开 t.me/<bot>?start=<code>。
     * code 与浏览器 cookie 绑定（同 socialStart 的票据机制），5 分钟有效。
     */
    public function telegramStart()
    {
        if (!SocialLoginService::telegramReady()) {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'This sign-in provider is not configured yet.');
        }
        $this->throttle('tg_login_ip:', $this->request->ip(), 30);
        $browser = (string) $this->request->cookie(self::COOKIE, '');
        if (!preg_match('/^[a-f0-9]{64}$/D', $browser)) {
            $browser = bin2hex(random_bytes(32));
        }
        $code = bin2hex(random_bytes(16));
        \think\facade\Cache::store('redis')->handler()->setex(
            'tg_login_code:' . hash('sha256', $code), 300,
            json_encode(['browser' => hash('sha256', $browser), 'identity' => null], JSON_THROW_ON_ERROR)
        );
        $bot = (string) config('social.telegram.bot_username', '');
        $cookie = self::COOKIE . '=' . $browser . '; Path=/api/live/; Max-Age=600; HttpOnly; SameSite=Lax';
        if ($this->request->isSsl()) {
            $cookie .= '; Secure';
        }
        // browser 绑定令牌随 JSON 下发、轮询时回传：部分 CDN/浏览器会丢 Set-Cookie，
        // 不能依赖 cookie 做浏览器绑定（cookie 仍设置，作为老链路兜底）。
        return $this->jsonSuccess(['code' => $code, 'deep_link' => 'https://t.me/' . $bot . '?start=' . $code,
            'browser' => $browser])
            ->header(['Set-Cookie' => $cookie, 'Cache-Control' => 'no-store']);
    }

    /**
     * Telegram 深链登录第二步：前端持 code 轮询，bot webhook 收到 /start 后 here 写入身份，
     * 本接口原子取码并校验浏览器绑定，成功即与社交登录同链路落库发 token。
     */
    public function telegramPoll()
    {
        $this->throttle('tg_poll_ip:', $this->request->ip(), 1200);
        $code = (string) $this->request->post('code', '');
        if (!preg_match('/^[a-f0-9]{32}$/D', $code)) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Invalid sign-in code.');
        }
        $redis = \think\facade\Cache::store('redis')->handler();
        // 原子取码并删除：code 只能被消费一次
        $raw = $redis->eval(
            "local v=redis.call('GET',KEYS[1]) if not v then return false end redis.call('DEL',KEYS[1]) return v",
            ['tg_login_code:' . hash('sha256', $code)], 1
        );
        if (!is_string($raw) || $raw === '') {
            return $this->jsonSuccess(['pending' => true]);
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['identity'])) {
            // 用户还没按 START，把等待态放回去继续轮询
            $redis->setex('tg_login_code:' . hash('sha256', $code), 300, $raw);
            return $this->jsonSuccess(['pending' => true]);
        }
        // 浏览器绑定令牌优先取轮询 body 回传值（不依赖 cookie），cookie 作兜底
        $browser = (string) $this->request->post('browser', '');
        if (!preg_match('/^[a-f0-9]{64}$/D', $browser)) {
            $browser = (string) $this->request->cookie(self::COOKIE, '');
        }
        if (!hash_equals((string) ($data['browser'] ?? ''), hash('sha256', $browser))) {
            return $this->jsonSuccess(['pending' => true]);
        }
        $identity = $data['identity'];
        $context = $this->context();
        $result = (new UserService())->loginWithIdentity('telegram', (string) $identity['subject'],
            '', (string) $identity['nickname'], $this->request->ip(), $context['device_id'],
            $context['invite_code'], $context['attr']);
        return $this->complete($result, $context);
    }

    /**
     * Telegram bot webhook（setWebhook 指向本接口，secret_token = sha256(bot_token) 前 24 位）。
     * 只处理私聊 /start <code>：把 Telegram 身份写入待登录 code，回复确认文案。
     */
    public function telegramWebhook()
    {
        $token = trim((string) config('social.telegram.bot_token', ''));
        $expected = $token === '' ? '' : substr(hash('sha256', $token), 0, 24);
        if ($expected === '' || !hash_equals($expected, (string) $this->request->header('X-Telegram-Bot-Api-Secret-Token', ''))) {
            return response('forbidden', 403);
        }
        $update = json_decode((string) $this->request->getContent(), true);
        $message = is_array($update) ? ($update['message'] ?? null) : null;
        $from = is_array($message) ? ($message['from'] ?? null) : null;
        $text = is_array($message) ? (string) ($message['text'] ?? '') : '';
        $chatId = is_array($message) && isset($message['chat']['id']) ? (string) $message['chat']['id'] : '';
        if (!is_array($from) || !preg_match('/^\/start(?: ([A-Za-z0-9_-]{1,64}))?$/', $text, $m)) {
            return response('OK');
        }
        if (($m[1] ?? '') === '') {
            $this->telegramReply($chatId, 'Welcome! Open our website and tap the Telegram button to sign in.');
            return response('OK');
        }
        $code = $m[1];
        if (!preg_match('/^[a-f0-9]{32}$/D', $code)) {
            return response('OK');
        }
        $redis = \think\facade\Cache::store('redis')->handler();
        $key = 'tg_login_code:' . hash('sha256', $code);
        $raw = $redis->get($key);
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($data) || !isset($data['browser'])) {
            return response('OK');
        }
        $first = mb_substr(trim((string) ($from['first_name'] ?? '')), 0, 64);
        $last = mb_substr(trim((string) ($from['last_name'] ?? '')), 0, 64);
        $data['identity'] = [
            'subject' => (string) ($from['id'] ?? ''),
            'nickname' => trim($first . ' ' . $last) ?: 'Member',
        ];
        if ($data['identity']['subject'] === '' || strlen($data['identity']['subject']) > 128) {
            return response('OK');
        }
        $redis->setex($key, 300, json_encode($data, JSON_THROW_ON_ERROR));
        $this->telegramReply($chatId, '✅ Signed in — you can close this chat and return to the site.');
        return response('OK');
    }

    /** 给发起登录的用户回一条确认（失败静默，不影响登录流程） */
    private function telegramReply(string $chatId, string $text): void
    {
        if ($chatId === '' || !ctype_digit($chatId)) {
            return;
        }
        try {
            (new Client(['timeout' => 4, 'connect_timeout' => 3, 'http_errors' => false]))->post(
                'https://api.telegram.org/bot' . trim((string) config('social.telegram.bot_token', '')) . '/sendMessage',
                ['json' => ['chat_id' => (int) $chatId, 'text' => $text]]
            );
        } catch (\Throwable) {
            // 确认消息发不出去不影响登录。
        }
    }

    /** per-IP 限流：window 秒内最多 max 次（5 分钟窗口） */
    private function throttle(string $prefix, string $ip, int $max): void
    {
        $redis = \think\facade\Cache::store('redis')->handler();
        $rateKey = $prefix . hash('sha256', $ip);
        $allowed = $redis->eval(
            "local n=redis.call('INCR',KEYS[1])
             if n==1 then redis.call('EXPIRE',KEYS[1],300) end
             return n<=" . $max . " and 1 or 0", [$rateKey], 1
        );
        if ((int) $allowed !== 1) {
            throw new BusinessException(ResultCode::AUTH_FAILED, 'Too many sign-in attempts. Please try again later.');
        }
    }

    private function complete(array $result, array $context)
    {
        $result['redirect'] = SocialLoginService::safeReturn($context['redirect']);
        $eventId = $context['event_id'];
        if (!preg_match('/^[a-zA-Z0-9_-]{16,128}$/D', $eventId)) {
            $eventId = bin2hex(random_bytes(16));
        }
        $result['event_id'] = $eventId;
        if ($result['is_new_user']) {
            try {
                FbCapiService::send('register', [
                    'email' => $result['user']['email'], 'user_id' => (string) $result['user']['id'],
                    'ip' => $this->request->ip(), 'ua' => (string) $this->request->header('user-agent', ''),
                    'fbp' => $context['fbp'], 'fbc' => $context['fbc'],
                    'px' => $context['attr']['px'], 'domain' => $context['attr']['domain'],
                    'event_id' => $eventId, 'source_url' => $context['source_url'],
                ]);
            } catch (\Throwable) {
                // 转化回传失败不能影响用户登录。
            }
        }
        return $this->jsonSuccess($result)->header(['Cache-Control' => 'no-store']);
    }

    private function emailReady(): bool
    {
        if (!config('social.email_enabled', true)) {
            return false;
        }
        try {
            return (new Email())->configured;
        } catch (\Throwable) {
            return false;
        }
    }

    private function requireEmail(): void
    {
        if (!$this->emailReady()) {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Email sign-in is not configured yet. Please use your password.');
        }
    }

    private function email(): string
    {
        $email = strtolower(trim((string) $this->request->post('email', '')));
        if (strlen($email) > 128 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Please enter a valid email address.');
        }
        return $email;
    }

    private function context(): array
    {
        $params = $this->request->post();
        $pick = static function (array $keys, int $limit = 128) use ($params): string {
            foreach ($keys as $key) {
                if (isset($params[$key]) && is_scalar($params[$key])) {
                    $value = trim((string) $params[$key]);
                    if ($value !== '') {
                        return mb_substr($value, 0, $limit);
                    }
                }
            }
            return '';
        };
        return [
            'attr' => [
                'domain' => $pick(['reg_domain']) ?: $this->request->host(),
                'channel' => $pick(['reg_channel'], 64), 'px' => $pick(['reg_px'], 64),
                'link_name' => $pick(['reg_link_name']), 'link_id' => $pick(['reg_link'], 16),
                'platform' => $pick(['reg_platform'], 32),
                'utm_source' => $pick(['utm_source']), 'utm_medium' => $pick(['utm_medium']),
                'campaign' => $pick(['campaign_name', 'campaign_id', 'utm_campaign']),
                'adset' => $pick(['adset_name', 'adset_id', 'utm_term']),
                'ad' => $pick(['ad_name', 'ad_id', 'utm_content']),
            ],
            'invite_code' => $pick(['invite_code'], 64), 'device_id' => $pick(['device_id']),
            'event_id' => $pick(['event_id']), 'source_url' => $pick(['source_url'], 1500),
            'redirect' => SocialLoginService::safeReturn($pick(['redirect'], 1500)),
            'fbp' => (string) $this->request->cookie('_fbp', ''),
            'fbc' => (string) $this->request->cookie('_fbc', ''),
        ];
    }
}
