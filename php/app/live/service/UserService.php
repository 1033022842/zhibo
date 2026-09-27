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
use think\facade\Db;
use think\facade\Log;

final class UserService
{
    public function register(string $email, string $code, string $password, string $nickname, string $ip = ''): array
    {
        $verifyService = new EmailVerifyService();
        $verifyService->verify($email, $code);

        return $this->createUser($email, $password, $nickname, $ip, 'email');
    }

    /**
     * AI 前端注册（无需邮箱验证码）
     */
    public function registerFromAi(string $username, string $email, string $password, string $ip = ''): array
    {
        // 检查用户名/邮箱是否已存在
        $exists = UserAuth::where('auth_key', $email)
            ->whereOr('auth_key', $username)
            ->find();
        if ($exists) {
            throw new BusinessException(ResultCode::EMAIL_ALREADY_REGISTERED);
        }

        return $this->createUser($email, $password, $username, $ip, 'username');
    }

    private function createUser(string $email, string $password, string $nickname, string $ip, string $authType): array
    {
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

        $profile = UserProfile::find($user->id);
        if ($profile) {
            $profile->last_login_ip = $ip;
            $profile->last_login_at = date('Y-m-d H:i:s');
            $profile->save();
        }

        if ($deviceId) {
            $device = UserDevice::where('user_id', $user->id)
                ->where('device_id', $deviceId)
                ->find();
            if (!$device) {
                $device           = new UserDevice();
                $device->user_id  = (int)$user->id;
                $device->device_id = $deviceId;
                $device->platform = 'web';
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
}
