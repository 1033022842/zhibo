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

final class LoginGateway extends BaseController
{
    private const COOKIE = 'live_oauth_browser';

    public function options()
    {
        $providers = [];
        foreach (['google' => 'Google', 'discord' => 'Discord', 'twitter' => 'X (Twitter)'] as $key => $label) {
            $providers[] = ['id' => $key, 'label' => $label, 'enabled' => SocialLoginService::ready($key)];
        }
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
        return $this->jsonSuccess(['authorize_url' => $url])
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
        $payload = (new SocialLoginService())->exchange((string) $this->request->post('ticket', ''),
            (string) $this->request->cookie(self::COOKIE, ''));
        $identity = $payload['identity'];
        $context = $payload['context'];
        $result = (new UserService())->loginWithIdentity($payload['provider'], $identity['subject'],
            $identity['email'], $identity['nickname'], $this->request->ip(), $context['device_id'],
            $context['invite_code'], $context['attr']);
        return $this->complete($result, $context);
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
                'link_name' => $pick(['reg_link_name']), 'platform' => $pick(['reg_platform'], 32),
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
