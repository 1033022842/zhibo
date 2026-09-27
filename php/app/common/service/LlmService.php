<?php

declare(strict_types=1);

namespace app\common\service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;

final class LlmService
{
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
            throw new BusinessException(ResultCode::SERVER_ERROR, 'AI 服务请求失败：' . $e->getMessage());
        }

        $statusCode = $response->getStatusCode();
        $body       = (string) $response->getBody();
        $decoded    = json_decode($body, true);

        if ($statusCode !== 200 || !is_array($decoded)) {
            $msg = is_array($decoded) && isset($decoded['error']['message'])
                ? $decoded['error']['message']
                : ('HTTP ' . $statusCode);
            throw new BusinessException(ResultCode::SERVER_ERROR, 'AI 服务返回错误：' . $msg);
        }

        $content = $decoded['choices'][0]['message']['content'] ?? '';
        if (!is_string($content) || $content === '') {
            throw new BusinessException(ResultCode::SERVER_ERROR, 'AI 服务返回内容为空');
        }

        return $content;
    }
}
