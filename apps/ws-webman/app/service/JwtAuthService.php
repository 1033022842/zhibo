<?php
declare(strict_types=1);

namespace app\service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtAuthService
{
    public function verifyAccessToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        try {
            $decoded = (array) JWT::decode($token, new Key((string) config('jwt.secret'), 'HS256'));
            if (($decoded['type'] ?? '') !== 'access') {
                return null;
            }

            if (($decoded['iss'] ?? '') !== (string) config('jwt.issuer')) {
                return null;
            }

            return [
                'user_id' => (int) ($decoded['sub'] ?? 0),
                'user_no' => (string) ($decoded['user_no'] ?? ''),
                'nickname' => (string) ($decoded['nickname'] ?? ''),
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
