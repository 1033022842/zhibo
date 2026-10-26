<?php
declare(strict_types=1);

namespace app\common\service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use think\facade\Log;

/**
 * 服务端 STT（xAI /v1/stt）
 *
 * 用途：浏览器没有 SpeechRecognition 时（微信/Instagram/Facebook 内置浏览器、iOS Chrome 等），
 * 前端用 WebAudio 录 WAV 上传，这里转发给 xAI 转文字，保证 App 内打开也能用语音通话。
 * 直接复用 llm.api_key / llm.base_url（同一把 xAI key）。
 */
final class SttService
{
    /** 前端最长录 12s（16k/16bit 约 384KB），留足余量 */
    private const MAX_BYTES = 8 * 1024 * 1024;
    private const USER_ERROR = 'Voice input is temporarily unavailable. Please type your message instead.';

    /**
     * 转写音频，返回文本（可能为空字符串）
     *
     * @throws BusinessException 参数非法 / 未配置 key / 上游失败
     */
    public function transcribe(string $audio, string $filename = 'voice.wav', string $lang = 'en'): string
    {
        if ($audio === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, '没有收到录音数据');
        }
        if (strlen($audio) > self::MAX_BYTES) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '录音过长，请缩短后重试');
        }

        $apiKey = trim((string) config('llm.api_key', ''));
        if ($apiKey === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'AI 聊天尚未配置 API Key，请联系管理员');
        }
        $baseUrl = rtrim((string) config('llm.base_url', 'https://api.openai.com/v1'), '/');

        // 注意：xAI 要求 file 必须是 multipart 的最后一个字段
        $multipart = [];
        $language  = $this->langCode($lang);
        if ($language !== '') {
            $multipart[] = ['name' => 'language', 'contents' => $language];
        }
        $multipart[] = ['name' => 'file', 'contents' => $audio, 'filename' => $filename];

        $client = new Client([
            'timeout'         => 60,
            'connect_timeout' => 10,
            'verify'          => false,
            'http_errors'     => false,
        ]);

        try {
            $response = $client->post($baseUrl . '/stt', [
                'headers'   => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept'        => 'application/json',
                ],
                'multipart' => $multipart,
            ]);
        } catch (GuzzleException $e) {
            Log::error('[stt] request failed: ' . $e->getMessage() . ' | base=' . $baseUrl);
            throw new BusinessException(ResultCode::SERVER_ERROR, self::USER_ERROR);
        }

        $status = $response->getStatusCode();
        $body   = (string) $response->getBody();
        $data   = json_decode($body, true);
        if ($status !== 200 || !is_array($data)) {
            Log::error('[stt] http ' . $status . ' body=' . mb_substr($body, 0, 300));
            throw new BusinessException(ResultCode::SERVER_ERROR, self::USER_ERROR);
        }

        return trim((string) ($data['text'] ?? ''));
    }

    /**
     * 前端语言码 → xAI STT 语言码；不认识的就不传，交给模型自动识别
     */
    private function langCode(string $lang): string
    {
        $lang = strtolower(trim($lang));
        if ($lang === 'zh-cn' || $lang === 'zh-tw') {
            return 'zh';
        }
        $supported = [
            'en', 'zh', 'ja', 'ko', 'fr', 'de', 'it', 'es', 'ru', 'hi', 'id', 'vi', 'ms',
            'th', 'ar', 'tr', 'pt', 'bn', 'nl', 'pl', 'sv', 'cs', 'da', 'fi', 'fil', 'fa',
            'ro', 'mk',
        ];
        return in_array($lang, $supported, true) ? $lang : '';
    }
}
