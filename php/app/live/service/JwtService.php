<?php
declare(strict_types=1);

namespace app\live\service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use think\facade\Cache;
use think\facade\Db;
use app\common\redis\RedisHelper;

final class JwtService
{
    private const ALG = 'HS256';
    // 客户要求登录态长期有效：访问令牌 1 年，刷新令牌 10 年
    public const ACCESS_TTL  = 31536000;
    public const REFRESH_TTL = 315360000;
    private const ISSUER = 'live-platform';

    public static function generateAccessToken(int $userId, string $userNo, string $nickname): string
    {
        $secret = config('jwt.secret');
        $payload = [
            'sub'      => $userId,
            'user_no'  => $userNo,
            'nickname' => $nickname,
            'type'     => 'access',
            'iss'      => self::ISSUER,
            'iat'      => time(),
            'exp'      => time() + self::ACCESS_TTL,
            'jti'      => bin2hex(random_bytes(16)),
            'version'  => self::userTokenVersion($userId),
        ];
        return JWT::encode($payload, $secret, self::ALG);
    }

    public static function generateRefreshToken(int $userId): string
    {
        $secret = config('jwt.secret');
        $payload = [
            'sub'  => $userId,
            'type' => 'refresh',
            'iss'  => self::ISSUER,
            'iat'  => time(),
            'exp'  => time() + self::REFRESH_TTL,
            'jti'  => bin2hex(random_bytes(16)),
            'version' => self::userTokenVersion($userId),
        ];
        return JWT::encode($payload, $secret, self::ALG);
    }

    public static function parseToken(string $token): ?array
    {
        try {
            $secret = config('jwt.secret');
            $decoded = JWT::decode($token, new Key($secret, self::ALG));
            if (self::isBlacklisted($token)
                || (string) ($decoded->version ?? '') !== self::userTokenVersion((int) ($decoded->sub ?? 0))) {
                return null;
            }
            return (array)$decoded;
        } catch (ExpiredException) {
            return null;
        } catch (\Exception) {
            return null;
        }
    }

    private static function userTokenVersion(int $userId): string
    {
        return (string) Db::connect('live_mysql')->table('lp_platform_config')
            ->where('key', 'user_token_version_' . $userId)->value('value');
    }

    public static function isBlacklisted(string $token): bool
    {
        $key = RedisHelper::tokenBlacklist($token);

        try {
            return Cache::store('redis')->has($key);
        } catch (\Throwable) {
            return Cache::store('file')->has($key);
        }
    }

    public static function blacklist(string $token, int $ttl = self::ACCESS_TTL): void
    {
        $key = RedisHelper::tokenBlacklist($token);

        try {
            Cache::store('redis')->set($key, 1, $ttl);
        } catch (\Throwable) {
            Cache::store('file')->set($key, 1, $ttl);
        }
    }
}
