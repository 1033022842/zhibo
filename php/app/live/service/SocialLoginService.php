<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use GuzzleHttp\Client;
use think\facade\Cache;

final class SocialLoginService
{
    /** Telegram 签名载荷有效期（官方建议 86400s），nonce 与时效共用该值 */
    private const TELEGRAM_AUTH_TTL = 86400;

    private const PROVIDERS = [
        'google' => [
            'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token' => 'https://oauth2.googleapis.com/token',
            'user' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'scope' => 'openid email profile',
        ],
        'discord' => [
            'authorize' => 'https://discord.com/oauth2/authorize',
            'token' => 'https://discord.com/api/oauth2/token',
            'user' => 'https://discord.com/api/users/@me',
            'scope' => 'identify email',
        ],
        'twitter' => [
            'authorize' => 'https://x.com/i/oauth2/authorize',
            'token' => 'https://api.x.com/2/oauth2/token',
            'user' => 'https://api.x.com/2/users/me',
            'scope' => 'users.read tweet.read',
        ],
    ];

    public static function ready(string $provider): bool
    {
        if (!isset(self::PROVIDERS[$provider])) {
            return false;
        }
        $cfg = (array) config('social.' . $provider, []);
        $uri = (string) ($cfg['redirect_uri'] ?? '');
        return trim((string) ($cfg['client_id'] ?? '')) !== ''
            && trim((string) ($cfg['client_secret'] ?? '')) !== ''
            && self::validCallback($uri, $provider);
    }

    /**
     * Telegram Login Widget 是否已配置（不走 OAuth2 跳转，无需回调地址）。
     */
    public static function telegramReady(): bool
    {
        $cfg = (array) config('social.telegram', []);
        return trim((string) ($cfg['bot_username'] ?? '')) !== ''
            && trim((string) ($cfg['bot_token'] ?? '')) !== '';
    }

    /**
     * 校验 Telegram Login Widget 回传的签名载荷，返回登录身份。
     * 官方字段按 key 排序拼 data_check_string，用 SHA256(bot_token) 作 HMAC-SHA256 密钥验签；
     * auth_date 限 24h 内，Redis 一次性 nonce 防 24h 内重放。
     */
    public function verifyTelegram(array $fields): array
    {
        $cfg = (array) config('social.telegram', []);
        $token = trim((string) ($cfg['bot_token'] ?? ''));
        if ($token === '') {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'This sign-in provider is not configured yet.');
        }

        $hash = (string) ($fields['hash'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/D', $hash)) {
            throw new BusinessException(ResultCode::AUTH_FAILED, 'Invalid Telegram authorization.');
        }
        $id = (string) ($fields['id'] ?? '');
        if (!preg_match('/^[1-9][0-9]{0,19}$/D', $id)) {
            throw new BusinessException(ResultCode::AUTH_FAILED, 'Invalid Telegram authorization.');
        }
        $authDate = (int) ($fields['auth_date'] ?? 0);
        if ($authDate <= 0 || abs(time() - $authDate) > self::TELEGRAM_AUTH_TTL) {
            throw new BusinessException(ResultCode::AUTH_FAILED, 'Telegram authorization expired. Please try again.');
        }

        // 只信任 widget 的官方字段，按 key 字典序拼签名串（与 Telegram 文档一致）
        $allowed = ['auth_date', 'first_name', 'id', 'last_name', 'photo_url', 'username'];
        $pairs = [];
        foreach ($allowed as $key) {
            if (!isset($fields[$key]) || !is_scalar($fields[$key])) {
                continue;
            }
            $value = trim((string) $fields[$key]);
            if ($key === 'id') {
                $value = $id;
            }
            if ($value !== '' && strlen($value) <= 512) {
                $pairs[] = $key . '=' . $value;
            }
        }
        $checkString = implode("\n", $pairs);
        $expected = hash_hmac('sha256', $checkString, hash('sha256', $token, true));
        if (!hash_equals($expected, $hash)) {
            throw new BusinessException(ResultCode::AUTH_FAILED, 'Invalid Telegram authorization.');
        }

        // 同一签名载荷只允许消费一次：拦住窗口期内被截获的重放
        $redis = Cache::store('redis')->handler();
        $nonceKey = 'tg_auth_hash:' . hash('sha256', $hash . ':' . $id . ':' . $authDate);
        if (!$redis->set($nonceKey, 1, ['nx', 'ex' => self::TELEGRAM_AUTH_TTL])) {
            throw new BusinessException(ResultCode::AUTH_FAILED, 'Telegram authorization expired. Please try again.');
        }

        $first = mb_substr(trim((string) ($fields['first_name'] ?? '')), 0, 64);
        $last = mb_substr(trim((string) ($fields['last_name'] ?? '')), 0, 64);
        return ['subject' => $id, 'email' => '', 'nickname' => trim($first . ' ' . $last) ?: 'Member'];
    }

    private static function validCallback(string $uri, string $provider): bool
    {
        $parts = parse_url($uri);
        return is_array($parts) && ($parts['scheme'] ?? '') === 'https'
            && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass'])
            && !isset($parts['query']) && !isset($parts['fragment'])
            && ($parts['path'] ?? '') === '/api/live/socialCallback/' . $provider;
    }

    public static function safeReturn(string $path): string
    {
        if (strlen($path) > 1500 || preg_match('/[\x00-\x20\\\\]/', $path)
            || !str_starts_with($path, '/ai/') || str_contains(strtolower($path), 'login.html')) {
            return '/ai/Home.html';
        }
        $parts = parse_url($path);
        return is_array($parts) && !isset($parts['host']) && !isset($parts['scheme'])
            ? $path : '/ai/Home.html';
    }

    public function start(string $provider, string $browser, string $origin, array $context, string $ip): string
    {
        if (!self::ready($provider)) {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'This sign-in provider is not configured yet.');
        }
        $cfg = (array) config('social.' . $provider);
        // 回调必须与发起登录的站点同源，否则 cookie 无法用于核验。
        $expectedOrigin = 'https://' . parse_url($cfg['redirect_uri'], PHP_URL_HOST);
        $port = parse_url($cfg['redirect_uri'], PHP_URL_PORT);
        if ($port) {
            $expectedOrigin .= ':' . $port;
        }
        if (strtolower($origin) !== strtolower($expectedOrigin)) {
            throw new BusinessException(ResultCode::AUTH_FAILED, 'Please sign in on the configured site domain.');
        }
        $redis = Cache::store('redis')->handler();
        $rateKey = 'oauth_start_ip:' . hash('sha256', $ip);
        $allowed = $redis->eval(
            "local n=redis.call('INCR',KEYS[1])
             if n==1 then redis.call('EXPIRE',KEYS[1],300) end
             return n<=30 and 1 or 0", [$rateKey], 1
        );
        if ((int) $allowed !== 1) {
            throw new BusinessException(ResultCode::AUTH_FAILED, 'Too many sign-in attempts. Please try again later.');
        }
        $state = bin2hex(random_bytes(32));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $payload = [
            'browser' => hash('sha256', $browser), 'provider' => $provider,
            'verifier' => $verifier, 'context' => $context,
        ];
        $redis->setex('oauth_state:' . hash('sha256', $state), 300, json_encode($payload, JSON_THROW_ON_ERROR));
        $query = [
            'client_id' => $cfg['client_id'], 'redirect_uri' => $cfg['redirect_uri'],
            'response_type' => 'code', 'scope' => self::PROVIDERS[$provider]['scope'],
            'state' => $state, 'code_challenge_method' => 'S256',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        ];
        if ($provider === 'google') {
            $query['prompt'] = 'select_account';
        }
        return self::PROVIDERS[$provider]['authorize'] . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * 返回一次性登录票据，不把本站 access/refresh token 放进地址栏。
     */
    public function callback(string $provider, string $state, string $code, string $browser): string
    {
        if (!self::ready($provider) || !preg_match('/^[a-f0-9]{64}$/D', $state)
            || $browser === '' || $code === '' || strlen($code) > 2048) {
            throw new BusinessException(ResultCode::AUTH_FAILED, 'Invalid or expired authorization.');
        }
        $payload = $this->consume('oauth_state:', $state, $browser, $provider);
        $identity = $this->fetchIdentity($provider, $code, $payload['verifier']);
        $ticket = bin2hex(random_bytes(32));
        Cache::store('redis')->handler()->setex(
            'oauth_ticket:' . hash('sha256', $ticket), 60,
            json_encode(['browser' => hash('sha256', $browser), 'provider' => $provider,
                'identity' => $identity, 'context' => $payload['context']], JSON_THROW_ON_ERROR)
        );
        return $ticket;
    }

    public function exchange(string $ticket, string $browser): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $ticket) || $browser === '') {
            throw new BusinessException(ResultCode::AUTH_FAILED, 'Invalid or expired sign-in ticket.');
        }
        return $this->consume('oauth_ticket:', $ticket, $browser);
    }

    private function consume(string $prefix, string $value, string $browser, string $provider = ''): array
    {
        // 错误浏览器不能消费票据；合法浏览器只能成功消费一次。
        $raw = Cache::store('redis')->handler()->eval(
            "local raw=redis.call('GET',KEYS[1])
             if not raw then return false end
             local data=cjson.decode(raw)
             if data.browser~=ARGV[1] then return false end
             if ARGV[2]~='' and data.provider~=ARGV[2] then return false end
             redis.call('DEL',KEYS[1]); return raw",
            [$prefix . hash('sha256', $value), hash('sha256', $browser), $provider], 1
        );
        if (!is_string($raw) || $raw === '') {
            throw new BusinessException(ResultCode::AUTH_FAILED, 'Invalid or expired authorization. Please try again.');
        }
        return json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    }

    private function fetchIdentity(string $provider, string $code, string $verifier): array
    {
        $cfg = (array) config('social.' . $provider);
        $client = new Client(['timeout' => 15, 'connect_timeout' => 8, 'http_errors' => false,
            'allow_redirects' => false]);
        $options = ['form_params' => [
            'grant_type' => 'authorization_code', 'code' => $code,
            'redirect_uri' => $cfg['redirect_uri'], 'code_verifier' => $verifier,
            'client_id' => $cfg['client_id'],
        ]];
        if ($provider === 'twitter') {
            $options['auth'] = [$cfg['client_id'], $cfg['client_secret']];
        } else {
            $options['form_params']['client_secret'] = $cfg['client_secret'];
        }
        try {
            $response = $client->post(self::PROVIDERS[$provider]['token'], $options);
            $tokens = json_decode((string) $response->getBody(), true);
            if ($response->getStatusCode() !== 200 || empty($tokens['access_token'])) {
                throw new \RuntimeException('Invalid token response');
            }
            $response = $client->get(self::PROVIDERS[$provider]['user'], [
                'headers' => ['Authorization' => 'Bearer ' . $tokens['access_token']],
            ]);
            $profile = json_decode((string) $response->getBody(), true);
            if ($response->getStatusCode() !== 200 || !is_array($profile)) {
                throw new \RuntimeException('Invalid profile response');
            }
        } catch (\Throwable) {
            // 不记录包含 code、access token 或 client secret 的请求异常。
            throw new BusinessException(ResultCode::AUTH_FAILED, 'Authorization failed. Please try again.');
        }
        if ($provider === 'twitter') {
            $profile = $profile['data'] ?? [];
            $subject = (string) ($profile['id'] ?? '');
            $email = '';
            $name = (string) ($profile['name'] ?? $profile['username'] ?? 'Member');
        } elseif ($provider === 'discord') {
            $subject = (string) ($profile['id'] ?? '');
            $email = ($profile['verified'] ?? false) === true ? (string) ($profile['email'] ?? '') : '';
            $name = (string) ($profile['global_name'] ?? $profile['username'] ?? 'Member');
        } else {
            $subject = (string) ($profile['sub'] ?? '');
            $email = ($profile['email_verified'] ?? false) === true ? (string) ($profile['email'] ?? '') : '';
            $name = (string) ($profile['name'] ?? 'Member');
        }
        $email = strtolower(trim($email));
        if ($subject === '' || strlen($subject) > 128
            || ($email !== '' && (strlen($email) > 128 || !filter_var($email, FILTER_VALIDATE_EMAIL)))) {
            throw new BusinessException(ResultCode::AUTH_FAILED, 'The provider did not return a valid identity.');
        }
        return ['subject' => $subject, 'email' => $email, 'nickname' => $name];
    }
}
