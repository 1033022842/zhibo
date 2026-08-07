<?php
declare(strict_types=1);

namespace app\live\middleware;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\live\service\JwtService;
use Closure;
use think\Request;
use think\Response;

final class Auth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('Authorization', '');
        $token = str_replace('Bearer ', '', $token);

        // 兼容 AI 女友端发来的 token header（非标准 Bearer 格式）
        if (empty($token)) {
            $token = $request->header('token', '');
        }

        if (empty($token)) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }

        if (JwtService::isBlacklisted($token)) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID, 'Token已失效');
        }

        $payload = JwtService::parseToken($token);
        if (!$payload || ($payload['type'] ?? '') !== 'access') {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_EXPIRED);
        }

        $request->userId   = (int)($payload['sub'] ?? 0);
        $request->userNo   = $payload['user_no'] ?? '';
        $request->nickname = $payload['nickname'] ?? '';

        return $next($request);
    }
}
