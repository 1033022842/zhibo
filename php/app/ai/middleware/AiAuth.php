<?php
declare(strict_types=1);

namespace app\ai\middleware;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use Closure;
use think\Request;
use think\Response;

final class AiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Api-Key', '');

        if (empty($apiKey)) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID, '缺少 X-Api-Key');
        }

        $expectedKey = config('ai.api_key', 'live-ai-api-key-2026');

        if (!hash_equals($expectedKey, $apiKey)) {
            throw new BusinessException(ResultCode::NO_PERMISSION, 'API Key 无效');
        }

        $request->aiWorkerId = $request->header('X-Worker-Id', '');

        return $next($request);
    }
}
