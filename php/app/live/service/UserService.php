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

        return [
            'id'            => (int)$user->id,
            'user_no'       => $user->user_no,
            'nickname'      => $user->nickname,
            'avatar'        => $user->avatar,
            'level'         => $user->level,
            'status'        => $user->status,
            'gender'        => $profile->gender ?? 0,
            'bio'           => $profile->bio ?? '',
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
}
