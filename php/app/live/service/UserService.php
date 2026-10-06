<?php
declare(strict_types=1);

namespace app\live\service;

use app\live\model\User;
use app\live\model\UserAuth;
use app\live\model\UserProfile;
use app\live\model\UserDevice;
use app\common\exception\BusinessException;
use app\common\util\StrHelper;
use app\common\web\ResultCode;
use GuzzleHttp\Client;
use think\facade\Db;
use think\facade\Log;

final class UserService
{
    public function register(string $email, string $code, string $password, string $nickname, string $ip = '', string $inviteCode = '', array $attr = []): array
    {
        $verifyService = new EmailVerifyService();
        $verifyService->verify($email, $code);

        $user = $this->createUser($email, $password, $nickname, $ip, 'email', $attr);
        $this->bindInviter((int) $user['id'], $inviteCode);

        return $user;
    }

    /**
     * AI 前端注册（无需邮箱验证码）
     *
     * @param array $attr 来源归因：domain/channel/px/platform/campaign/adset/ad + utm_source/utm_medium
     */
    public function registerFromAi(string $username, string $email, string $password, string $ip = '', string $inviteCode = '', array $attr = []): array
    {
        // 检查用户名/邮箱是否已存在
        $exists = UserAuth::where('auth_key', $email)
            ->whereOr('auth_key', $username)
            ->find();
        if ($exists) {
            throw new BusinessException(ResultCode::EMAIL_ALREADY_REGISTERED);
        }

        $user = $this->createUser($email, $password, $username, $ip, 'username', $attr);
        $this->bindInviter((int) $user['id'], $inviteCode);

        return $user;
    }

    /**
     * 绑定邀请关系（邀请码无效不影响注册）
     */
    private function bindInviter(int $userId, string $inviteCode): void
    {
        if ($inviteCode === '') {
            return;
        }
        try {
            (new InviteService())->bind($userId, $inviteCode);
        } catch (\Throwable $e) {
            Log::warning('[invite] 绑定邀请关系失败 uid=' . $userId . ' err=' . $e->getMessage());
        }
    }

    private function createUser(string $email, string $password, string $nickname, string $ip, string $authType, array $attr = []): array
    {
        $attr = array_merge([
            'domain' => '', 'channel' => '', 'px' => '',
            'platform' => '', 'campaign' => '', 'adset' => '', 'ad' => '',
            'utm_source' => '', 'utm_medium' => '',
        ], $attr);
        $exists = UserAuth::where('auth_type', 'email')
            ->where('auth_key', $email)
            ->find();
        if ($exists) {
            throw new BusinessException(ResultCode::EMAIL_ALREADY_REGISTERED);
        }

        Db::startTrans();
        try {
            $user = new User();
            $user->user_no = StrHelper::orderNo('U');
            $user->nickname = $nickname;
            $user->email    = $email;
            $user->status   = 1;
            $user->level    = 1;
            // 推广来源归因：来源域名 + 渠道 + 平台 + 像素 + 广告系列/组/广告（前台没带域名时用 Referer/Origin 兜底）
            $user->reg_domain   = self::normalizeRegDomain($attr['domain'] !== '' ? $attr['domain'] : self::requestHost());
            $user->reg_channel  = mb_substr(trim((string) $attr['channel']), 0, 64);
            $user->reg_platform = mb_substr(self::normalizePlatform($attr), 0, 32);
            $user->reg_px       = mb_substr(trim((string) $attr['px']), 0, 64);
            $user->reg_campaign = mb_substr(trim((string) $attr['campaign']), 0, 128);
            $user->reg_adset    = mb_substr(trim((string) $attr['adset']), 0, 128);
            $user->reg_ad       = mb_substr(trim((string) $attr['ad']), 0, 128);
            $user->created_at = date('Y-m-d H:i:s');
            $user->save();

            $auth = new UserAuth();
            $auth->user_id       = (int)$user->id;
            $auth->auth_type     = 'email';
            $auth->auth_key      = $email;
            $auth->password_hash = hash_password($password);
            $auth->save();

            // registerFromAi 同时创建用户名认证，支持用户名登录
            if ($authType === 'username') {
                $usernameAuth = new UserAuth();
                $usernameAuth->user_id       = (int)$user->id;
                $usernameAuth->auth_type     = 'username';
                $usernameAuth->auth_key      = $nickname;
                $usernameAuth->password_hash = hash_password($password);
                $usernameAuth->save();
            }

            $profile = new UserProfile();
            $profile->user_id       = (int)$user->id;
            $profile->last_login_ip = $ip;
            $profile->save();

            Db::commit();

            return $user->toArray();
        } catch (BusinessException $e) {
            Db::rollback();
            throw $e;
        } catch (\Exception $e) {
            Db::rollback();
            throw new BusinessException(ResultCode::SERVER_ERROR, '注册失败: ' . $e->getMessage());
        }
    }

    public function login(string $account, string $password, string $ip = '', string $deviceId = ''): array
    {
        $isEmail = str_contains($account, '@');
        $authType = $isEmail ? 'email' : 'username';

        $auth = UserAuth::where('auth_type', $authType)
            ->where('auth_key', $account)
            ->find();

        if (!$auth || !verify_password($password, $auth->password_hash)) {
            $label = $isEmail ? '邮箱' : '用户名';
            throw new BusinessException(ResultCode::AUTH_FAILED, $label . '或密码错误');
        }

        $user = User::find($auth->user_id);
        if (!$user || $user->status !== 1) {
            throw new BusinessException(ResultCode::USER_DISABLED);
        }

        return $this->issueTokens($user, $ip, $deviceId);
    }

    /**
     * Google 登录（Google Identity Services 令牌模式）
     * 前端用 GIS 拿到 google access_token，后端校验后按 Google 邮箱找/建账号
     */
    public function loginWithGoogle(string $accessToken, string $ip = '', string $deviceId = ''): array
    {
        $clientId = trim((string) env('GOOGLE.CLIENT_ID', ''));
        if ($clientId === '') {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Google 登录未配置');
        }

        $info = $this->fetchGoogleTokenInfo($accessToken);
        if (($info['aud'] ?? '') !== $clientId) {
            throw new BusinessException(ResultCode::AUTH_FAILED, 'Google 令牌不属于本站');
        }
        if (!filter_var($info['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw new BusinessException(ResultCode::AUTH_FAILED, 'Google 邮箱未验证');
        }

        $email = trim((string) ($info['email'] ?? ''));
        $sub   = trim((string) ($info['sub'] ?? ''));
        if ($email === '' || $sub === '') {
            throw new BusinessException(ResultCode::AUTH_FAILED, 'Google 未返回邮箱');
        }

        $auth = UserAuth::where('auth_type', 'google')->where('auth_key', $sub)->find();
        if ($auth) {
            $user = User::find($auth->user_id);
        } else {
            // 同邮箱的已有账号：直接绑定 Google 后登录
            $emailAuth = UserAuth::where('auth_type', 'email')->where('auth_key', $email)->find();
            if ($emailAuth) {
                $user = User::find($emailAuth->user_id);
                if ($user) {
                    $googleAuth = new UserAuth();
                    $googleAuth->user_id       = (int)$user->id;
                    $googleAuth->auth_type     = 'google';
                    $googleAuth->auth_key      = $sub;
                    $googleAuth->password_hash = '';
                    $googleAuth->save();
                }
            } else {
                $user = $this->createGoogleUser($email, $sub, $ip);
            }
        }

        if (!$user || $user->status !== 1) {
            throw new BusinessException(ResultCode::USER_DISABLED);
        }

        return $this->issueTokens($user, $ip, $deviceId);
    }

    /**
     * 校验 google access_token（tokeninfo 同时返回 aud 与 email_verified）
     */
    private function fetchGoogleTokenInfo(string $accessToken): array
    {
        try {
            $response = (new Client(['timeout' => 10, 'http_errors' => false]))->get(
                'https://oauth2.googleapis.com/tokeninfo',
                ['query' => ['access_token' => $accessToken]]
            );
        } catch (\Throwable $e) {
            Log::error('google tokeninfo failed: ' . $e->getMessage());
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Google 校验失败，请重试');
        }

        if ($response->getStatusCode() !== 200) {
            throw new BusinessException(ResultCode::AUTH_FAILED, 'Google 令牌无效');
        }

        $info = json_decode((string) $response->getBody(), true);
        if (!is_array($info)) {
            throw new BusinessException(ResultCode::AUTH_FAILED, 'Google 令牌无效');
        }

        return $info;
    }

    /**
     * Google 首次登录：建用户 + 邮箱认证（随机密码）+ Google 认证 + 资料
     */
    private function createGoogleUser(string $email, string $sub, string $ip): User
    {
        Db::startTrans();
        try {
            $user = new User();
            $user->user_no    = StrHelper::orderNo('U');
            $user->nickname   = explode('@', $email)[0];
            $user->email      = $email;
            $user->status     = 1;
            $user->level      = 1;
            // 推广来源归因：Google 首次登录建号同样记录来源域名
            $user->reg_domain  = self::normalizeRegDomain(self::requestHost());
            $user->reg_channel = '';
            $user->created_at = date('Y-m-d H:i:s');
            $user->save();

            $emailAuth = new UserAuth();
            $emailAuth->user_id       = (int)$user->id;
            $emailAuth->auth_type     = 'email';
            $emailAuth->auth_key      = $email;
            $emailAuth->password_hash = hash_password(bin2hex(random_bytes(16)));
            $emailAuth->save();

            $googleAuth = new UserAuth();
            $googleAuth->user_id       = (int)$user->id;
            $googleAuth->auth_type     = 'google';
            $googleAuth->auth_key      = $sub;
            $googleAuth->password_hash = '';
            $googleAuth->save();

            $profile = new UserProfile();
            $profile->user_id       = (int)$user->id;
            $profile->last_login_ip = $ip;
            $profile->save();

            Db::commit();

            return $user;
        } catch (\Throwable $e) {
            Db::rollback();
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Google 注册失败: ' . $e->getMessage());
        }
    }

    /**
     * 登录成功后的统一收尾：更新最近登录、记录设备、签发双 token
     */
    private function issueTokens(User $user, string $ip = '', string $deviceId = ''): array
    {
        $profile = UserProfile::find($user->id);
        if ($profile) {
            $profile->last_login_ip = $ip;
            $profile->last_login_at = date('Y-m-d H:i:s');
            $profile->save();
        }

        if ($deviceId !== '') {
            $device = UserDevice::where('user_id', $user->id)
                ->where('device_id', $deviceId)
                ->find();
            if (!$device) {
                $device            = new UserDevice();
                $device->user_id   = (int)$user->id;
                $device->device_id = $deviceId;
                $device->platform  = 'web';
                $device->save();
            }
        }

        $accessToken  = JwtService::generateAccessToken((int)$user->id, $user->user_no, $user->nickname);
        $refreshToken = JwtService::generateRefreshToken((int)$user->id);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => 7200,
            'user'          => [
                'id'       => (int)$user->id,
                'user_no'  => $user->user_no,
                'nickname' => $user->nickname,
                'avatar'   => $user->avatar,
                'level'    => $user->level,
                'email'    => $user->email,
            ],
        ];
    }

    public function refreshToken(string $refreshToken): array
    {
        $payload = JwtService::parseToken($refreshToken);

        if (!$payload || ($payload['type'] ?? '') !== 'refresh') {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_EXPIRED, '刷新Token无效');
        }

        $userId = (int)($payload['sub'] ?? 0);
        $user   = User::find($userId);
        if (!$user || $user->status !== 1) {
            throw new BusinessException(ResultCode::USER_NOT_FOUND);
        }

        JwtService::blacklist($refreshToken, 604800);

        $newAccessToken  = JwtService::generateAccessToken($userId, $user->user_no, $user->nickname);
        $newRefreshToken = JwtService::generateRefreshToken($userId);

        return [
            'access_token'  => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'expires_in'    => 7200,
        ];
    }

    public function logout(int $userId, string $accessToken, string $refreshToken = ''): void
    {
        JwtService::blacklist($accessToken, 7200);
        if ($refreshToken) {
            JwtService::blacklist($refreshToken, 604800);
        }
    }

    public function profile(int $userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            throw new BusinessException(ResultCode::USER_NOT_FOUND);
        }

        $profile = UserProfile::find($userId);

        // 查询商家认证状态
        $certStatus = -1;
        $cert = \app\live\model\MerchantCertification::where('user_id', $userId)->find();
        if ($cert) {
            $certStatus = (int)$cert->status;
        }

        return [
            'id'            => (int)$user->id,
            'user_no'       => $user->user_no,
            'nickname'      => $user->nickname,
            'avatar'        => $user->avatar,
            'level'         => $user->level,
            'status'        => $user->status,
            'gender'        => $profile->gender ?? 0,
            'bio'           => $profile->bio ?? '',
            'cert_status'   => $certStatus,
            'last_login_at' => $profile->last_login_at ?? null,
        ];
    }

    public function updateProfile(int $userId, array $data): void
    {
        $user = User::find($userId);
        if (!$user) {
            throw new BusinessException(ResultCode::USER_NOT_FOUND);
        }

        if (!empty($data['nickname'])) {
            $user->nickname = $data['nickname'];
            $user->save();
        }

        $profile = UserProfile::find($userId);
        if ($profile) {
            if (isset($data['gender'])) {
                $profile->gender = (int)$data['gender'];
            }
            if (isset($data['bio'])) {
                $profile->bio = $data['bio'];
            }
            $profile->save();
        }
    }

    /**
     * 修改密码（新版设置页 Edit Password）：需要校验原密码
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): void
    {
        if ($currentPassword === '' || $newPassword === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, '原密码与新密码都不能为空');
        }
        $len = strlen($newPassword);
        if ($len < 6 || $len > 20) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '新密码长度为6-20个字符');
        }

        $auths = UserAuth::where('user_id', $userId)->select();
        if ($auths->isEmpty()) {
            throw new BusinessException(ResultCode::USER_NOT_FOUND);
        }

        $matched = false;
        foreach ($auths as $auth) {
            if (verify_password($currentPassword, (string)$auth->password_hash)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            throw new BusinessException(ResultCode::AUTH_FAILED, '原密码错误');
        }

        // 同一账号可能同时有邮箱与用户名两种认证，统一改成新密码
        foreach ($auths as $auth) {
            $auth->password_hash = hash_password($newPassword);
            $auth->save();
        }
    }

    /**
     * 修改登录邮箱（新版设置页 Edit Email）：需要校验密码
     */
    public function changeEmail(int $userId, string $newEmail, string $password): void
    {
        $newEmail = trim($newEmail);
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '邮箱格式错误');
        }
        if ($password === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, '密码不能为空');
        }

        $auths = UserAuth::where('user_id', $userId)->select();
        if ($auths->isEmpty()) {
            throw new BusinessException(ResultCode::USER_NOT_FOUND);
        }

        $matched = false;
        foreach ($auths as $auth) {
            if (verify_password($password, (string)$auth->password_hash)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            throw new BusinessException(ResultCode::AUTH_FAILED, '密码错误');
        }

        $user = User::find($userId);
        if (!$user) {
            throw new BusinessException(ResultCode::USER_NOT_FOUND);
        }

        $exists = UserAuth::where('auth_type', 'email')->where('auth_key', $newEmail)->find();
        if ($exists && (int)$exists->user_id !== $userId) {
            throw new BusinessException(ResultCode::EMAIL_ALREADY_REGISTERED);
        }

        Db::startTrans();
        try {
            $emailAuth = UserAuth::where('user_id', $userId)->where('auth_type', 'email')->find();
            if ($emailAuth) {
                $emailAuth->auth_key = $newEmail;
                $emailAuth->save();
            } else {
                // 之前只用用户名注册的账号：补一条邮箱认证，密码沿用当前密码
                $emailAuth = new UserAuth();
                $emailAuth->user_id       = $userId;
                $emailAuth->auth_type     = 'email';
                $emailAuth->auth_key      = $newEmail;
                $emailAuth->password_hash = hash_password($password);
                $emailAuth->save();
            }

            $user->email = $newEmail;
            $user->save();

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 注销账户（新版设置页 Danger zone）：禁用登录，保留数据以便追溯
     */
    public function deleteAccount(int $userId, string $reason = '', string $description = ''): void
    {
        $user = User::find($userId);
        if (!$user) {
            throw new BusinessException(ResultCode::USER_NOT_FOUND);
        }

        $user->status = 0;
        $user->save();

        Log::info(sprintf(
            'account deleted: user_id=%d reason=%s description=%s',
            $userId,
            $reason === '' ? '-' : $reason,
            $description === '' ? '-' : $description
        ));
    }

    /**
     * 从当前请求的 Origin / Referer 解析来源域名（注册接口没显式带 reg_domain 时兜底）
     */
    private static function requestHost(): string
    {
        try {
            $req = request();
            if (!$req) {
                return '';
            }
            $src = trim((string) $req->header('origin', ''));
            if ($src === '') {
                $src = trim((string) $req->header('referer', ''));
            }
            if ($src === '') {
                return '';
            }
            return self::normalizeRegDomain((string) parse_url($src, PHP_URL_HOST));
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * 来源平台归一化：facebook / tiktok / google / other
     * 依据渠道参数 ?ch= 与 utm_source / utm_medium 里的关键词判断
     */
    private static function normalizePlatform(array $attr): string
    {
        $raw = strtolower(trim(
            (string) $attr['platform'] . ' '
            . (string) $attr['channel'] . ' '
            . (string) $attr['utm_source'] . ' '
            . (string) $attr['utm_medium']
        ));
        if ($raw === '') {
            return '';
        }

        $map = [
            'facebook' => ['facebook', 'fb', 'meta', 'instagram', 'ig', 'messenger'],
            'tiktok'   => ['tiktok', 'tt', 'douyin'],
            'google'   => ['google', 'gg', 'adwords', 'youtube', 'yt', 'gdn'],
        ];
        foreach ($map as $platform => $keys) {
            foreach ($keys as $k) {
                if (preg_match('/(?:^|[^a-z])' . preg_quote($k, '/') . '(?:[^a-z]|$)/', $raw)) {
                    return $platform;
                }
            }
        }
        return 'other';
    }

    /**
     * 来源域名规范化：去协议/端口/路径，转小写；IP 与本机地址不算推广域名
     */
    private static function normalizeRegDomain(string $host): string
    {
        $host = strtolower(trim($host));
        $host = (string) preg_replace('#^https?://#', '', $host);
        $host = (string) preg_replace('#[/:].*$#', '', $host);
        if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return '';
        }
        return mb_substr($host, 0, 120);
    }
}
