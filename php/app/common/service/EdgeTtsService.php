<?php
declare(strict_types=1);

namespace app\common\service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use think\facade\Log;

/**
 * Edge TTS 本地常驻服务客户端（tts-service/server.py）
 *
 * 用途：让创建角色时选择的 6 种女声在聊天通话里真正生效。
 * PHP 只负责转发文本与音色，合成与音色映射都在本地 Python 服务里完成；
 * 服务不可用时由 TtsService 自动回退 xAI，前端无感。
 */
final class EdgeTtsService
{
    private const USER_ERROR = 'Voice is temporarily unavailable. Please try again in a moment.';

    /**
     * 合成语音，返回 MP3 二进制
     *
     * @param string $text   已清洗、已截断的文本
     * @param string $preset 6 种音色之一：sweet/mature/magnetic/loli/cold/gentle
     *
     * @throws BusinessException 服务未配置 / 上游失败
     */
    public function speak(string $text, string $preset): string
    {
        $baseUrl = rtrim(trim((string) config('tts.edge_url', '')), '/');
        if ($baseUrl === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, self::USER_ERROR);
        }

        $headers = ['Accept' => 'audio/mpeg'];
        $token = trim((string) config('tts.edge_token', ''));
        if ($token !== '') {
            $headers['X-TTS-Token'] = $token;
        }

        $client = new Client([
            'timeout'         => max(3, (int) config('tts.edge_timeout', 12)),
            'connect_timeout' => 3,
            'verify'          => false,
            'http_errors'     => false,
        ]);

        try {
            $response = $client->post($baseUrl . '/tts', [
                'headers' => $headers,
                'json'    => ['text' => $text, 'voice' => $preset],
            ]);
        } catch (GuzzleException $e) {
            Log::warning('[tts] edge request failed: ' . $e->getMessage() . ' | url=' . $baseUrl);
            throw new BusinessException(ResultCode::SERVER_ERROR, self::USER_ERROR);
        }

        $status = $response->getStatusCode();
        $audio  = (string) $response->getBody();
        if ($status !== 200 || $audio === '') {
            Log::warning('[tts] edge http ' . $status . ' body=' . mb_substr($audio, 0, 200));
            throw new BusinessException(ResultCode::SERVER_ERROR, self::USER_ERROR);
        }

        return $audio;
    }
}
