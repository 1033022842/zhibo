<?php
declare(strict_types=1);

namespace app\live\service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use think\facade\Cache;
use app\common\redis\RedisHelper;

final class JwtService
{
    private const ALG = 'HS256';
    private const ACCESS_TTL  = 7200;
    private const REFRESH_TTL = 604800;
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
        ];
        return JWT::encode($payload, $secret, self::ALG);
    }

    public static function parseToken(string $token): ?array
    {
        try {
            $secret = config('jwt.secret');
            $decoded = JWT::decode($token, new Key($secret, self::ALG));
            return (array)$decoded;
        } catch (ExpiredException) {
            return null;
        } catch (\Exception) {
            return null;
        }
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

    public static function blacklist(string $token, int $ttl = 7200): void
    {
        $key = RedisHelper::tokenBlacklist($token);

        try {
            Cache::store('redis')->set($key, 1, $ttl);
        } catch (\Throwable) {
            Cache::store('file')->set($key, 1, $ttl);
        }
    }
}
