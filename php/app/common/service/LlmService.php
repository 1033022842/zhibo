<?php

declare(strict_types=1);

namespace app\common\service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use think\facade\Log;

final class LlmService
{
    /** 对外统一兜底文案：技术细节只写日志，不返回给用户 */
    private const USER_ERROR = 'AI service is temporarily unavailable. Please try again in a moment.';

    /**
     * 调用 OpenAI 兼容 Chat Completions 接口
     *
     * @param array<int, array{role:string, content:string}> $messages
     * @param array{max_tokens?:int, temperature?:float, model?:string} $options 覆盖默认生成参数
     */
    public function chat(array $messages, array $options = []): string
    {
        $apiKey = trim((string) config('llm.api_key', ''));
        if ($apiKey === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'AI 聊天尚未配置 API Key，请联系管理员');
        }

        $baseUrl = rtrim((string) config('llm.base_url', 'https://api.openai.com/v1'), '/');
        $model   = (string) ($options['model'] ?? config('llm.model', 'gpt-4o-mini'));

        $client = new Client([
            'timeout'         => (int) config('llm.timeout', 60),
            'connect_timeout' => 10,
            'verify'          => false,
            'http_errors'     => false,
        ]);

        try {
            $response = $client->post($baseUrl . '/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => $model,
                    'messages'    => $messages,
                    'temperature' => (float) ($options['temperature'] ?? config('llm.temperature', 0.8)),
                    'max_tokens'  => (int) ($options['max_tokens'] ?? config('llm.max_tokens', 500)),
                ],
            ]);
        } catch (GuzzleException $e) {
            // 超时 / 连接失败 / 网关异常：技术细节只进日志，页面只给统一提示
            Log::error('[llm] request failed: ' . $e->getMessage()
                . ' | base=' . $baseUrl . ' model=' . $model);
            throw new BusinessException(ResultCode::SERVER_ERROR, self::USER_ERROR);
        }

        $statusCode = $response->getStatusCode();
        $body       = (string) $response->getBody();
        $decoded    = json_decode($body, true);

        if ($statusCode !== 200 || !is_array($decoded)) {
            $detail = is_array($decoded) && isset($decoded['error']['message'])
                ? (string) $decoded['error']['message']
                : mb_substr($body, 0, 500);
            Log::error('[llm] http ' . $statusCode . ' model=' . $model . ' body=' . $detail);
            throw new BusinessException(ResultCode::SERVER_ERROR, self::USER_ERROR);
        }

        $content = $decoded['choices'][0]['message']['content'] ?? '';
        if (!is_string($content) || $content === '') {
            Log::warning('[llm] empty content model=' . $model . ' body=' . mb_substr($body, 0, 500));
            throw new BusinessException(ResultCode::SERVER_ERROR, self::USER_ERROR);
        }

        return $content;
    }
}
