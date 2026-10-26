<?php
declare(strict_types=1);

namespace app\common\service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use think\facade\Cache;
use think\facade\Log;

/**
 * 服务端 TTS（xAI /v1/tts）
 *
 * 用途：浏览器没有 Web Speech API 时（微信/Instagram/Facebook 内置浏览器、iOS Chrome 等）
 * 由服务端合成 MP3，前端用 <audio> 播放，保证 App 内打开也能听到通话声音。
 * 直接复用 llm.api_key / llm.base_url（同一把 xAI key）。
 */
final class TtsService
{
    /** 单句上限：通话回复一般远小于这个值，超出截断防止被刷 */
    private const MAX_CHARS = 600;
    /** xAI 预置女声：ara（warm / conversational） */
    private const VOICE = 'ara';
    private const CACHE_TTL = 3600;

    private const USER_ERROR = 'Voice is temporarily unavailable. Please try again in a moment.';

    /**
     * 合成语音，返回 MP3 二进制
     *
     * @throws BusinessException 参数非法 / 未配置 key / 上游失败
     */
    public function speak(string $text, string $lang = 'en'): string
    {
        $text = $this->clean($text);
        if ($text === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, '请输入要朗读的文字');
        }
        if (mb_strlen($text) > self::MAX_CHARS) {
            $text = mb_substr($text, 0, self::MAX_CHARS);
        }

        $apiKey = trim((string) config('llm.api_key', ''));
        if ($apiKey === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'AI 聊天尚未配置 API Key，请联系管理员');
        }
        $baseUrl  = rtrim((string) config('llm.base_url', 'https://api.openai.com/v1'), '/');
        $language = $this->langCode($lang);

        // 同一句话（开场白、重试）直接命中缓存，避免重复计费
        $cacheKey = 'tts:' . md5($language . '|' . $text);
        $cached   = Cache::store('file')->get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            $audio = base64_decode($cached, true);
            if (is_string($audio) && $audio !== '') {
                return $audio;
            }
        }

        $client = new Client([
            'timeout'         => 30,
            'connect_timeout' => 10,
            'verify'          => false,
            'http_errors'     => false,
        ]);

        try {
            $response = $client->post($baseUrl . '/tts', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'audio/mpeg',
                ],
                'json' => [
                    'text'      => $text,
                    'voice_id'  => self::VOICE,
                    'language'  => $language,
                ],
            ]);
        } catch (GuzzleException $e) {
            Log::error('[tts] request failed: ' . $e->getMessage() . ' | base=' . $baseUrl);
            throw new BusinessException(ResultCode::SERVER_ERROR, self::USER_ERROR);
        }

        $status = $response->getStatusCode();
        $audio  = (string) $response->getBody();
        if ($status !== 200 || $audio === '') {
            Log::error('[tts] http ' . $status . ' body=' . mb_substr($audio, 0, 300));
            throw new BusinessException(ResultCode::SERVER_ERROR, self::USER_ERROR);
        }

        Cache::store('file')->set($cacheKey, base64_encode($audio), self::CACHE_TTL);
        return $audio;
    }

    /** 去掉 Markdown 标记，避免把 ** / # / 链接念出来 */
    private function clean(string $text): string
    {
        $text = preg_replace('/```[\s\S]*?```/u', ' ', $text) ?? $text;
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $text) ?? $text;
        $text = str_replace(['**', '__', '`', '#', '>', '*', '~'], '', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    /**
     * 前端 uiLang() → xAI 支持的 BCP-47 语言码。
     * ms / th / km / lo 不在官方 20 种里，交给 auto 自动识别。
     */
    private function langCode(string $lang): string
    {
        $lang = strtolower(trim($lang));
        $map = [
            'en'    => 'en',
            'zh'    => 'zh-CN',
            'zh-cn' => 'zh-CN',
            'zh-tw' => 'zh-CN',
            'ja'    => 'ja',
            'ko'    => 'ko',
            'fr'    => 'fr',
            'de'    => 'de',
            'it'    => 'it',
            'es'    => 'es-ES',
            'ru'    => 'ru',
            'hi'    => 'hi',
            'id'    => 'id',
            'vi'    => 'vi',
            'ar'    => 'ar-SA',
            'tr'    => 'tr',
            'pt'    => 'pt-BR',
            'bn'    => 'bn',
        ];
        return $map[$lang] ?? 'auto';
    }
}
