<?php

namespace app\common\library;

use Throwable;
use think\facade\Log;

class Sms
{
    public bool $configured = false;

    protected array $config = [];

    public function __construct()
    {
        $sysConfig = get_sys_config('', 'sms');
        $this->configured = true;
        foreach (['sms_api_url', 'sms_api_key', 'sms_sign_id', 'sms_template_id'] as $key) {
            if (empty($sysConfig[$key])) {
                $this->configured = false;
                break;
            }
        }
        $this->config = $sysConfig;
    }

    public function send(string $mobile, string $code, string $templateParam = ''): bool
    {
        if (!$this->configured) {
            Log::error('SMS service not configured');
            return false;
        }

        $apiKey = $this->decryptValue($this->config['sms_api_key']);
        $url    = $this->config['sms_api_url'];
        $signId = $this->config['sms_sign_id'];
        $tplId  = $this->config['sms_template_id'];

        $params = [
            'api_key'      => $apiKey,
            'mobile'       => $mobile,
            'sign_id'      => $signId,
            'template_id'  => $tplId,
            'code'         => $code,
            'template_param' => $templateParam ?: json_encode(['code' => $code]),
        ];

        try {
            $response = $this->httpPost($url, $params);
            $result   = json_decode($response, true);
            if (!$result) {
                Log::error('SMS response parse failed: ' . $response);
                return false;
            }
            if (($result['code'] ?? '') !== '00000') {
                Log::error('SMS send failed: ' . ($result['message'] ?? 'unknown'));
                return false;
            }
            return true;
        } catch (Throwable $e) {
            Log::error('SMS send exception: ' . $e->getMessage());
            return false;
        }
    }

    public function testSend(string $mobile, string $apiUrl, string $apiKey, string $signId, string $templateId): array
    {
        $params = [
            'api_key'      => $apiKey,
            'mobile'       => $mobile,
            'sign_id'      => $signId,
            'template_id'  => $templateId,
            'code'         => '123456',
            'template_param' => json_encode(['code' => '123456']),
        ];

        $response = $this->httpPost($apiUrl, $params);
        $result   = json_decode($response, true);

        if (!$result) {
            return ['success' => false, 'message' => '接口响应解析失败: ' . mb_substr($response, 0, 500)];
        }

        if (($result['code'] ?? '') === '00000') {
            return ['success' => true, 'message' => '短信发送成功'];
        }

        return ['success' => false, 'message' => $result['message'] ?? '未知错误', 'raw' => $result];
    }

    public function encryptValue(string $plain): string
    {
        $key = $this->getEncryptKey();
        $iv  = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    public function decryptValue(string $cipher): string
    {
        $key   = $this->getEncryptKey();
        $data  = base64_decode($cipher);
        $iv    = substr($data, 0, 16);
        $crypt = substr($data, 16);
        return openssl_decrypt($crypt, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv) ?: '';
    }

    private function getEncryptKey(): string
    {
        $key = config('app.app_key') ?: 'base_key_32_bytes_for_aes256!';
        return substr(hash('sha256', $key, true), 0, 32);
    }

    private function httpPost(string $url, array $data): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('HTTP请求失败: ' . $error);
        }

        return $response ?: '';
    }
}
