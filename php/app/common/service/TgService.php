<?php

declare(strict_types=1);

namespace app\common\service;

use app\admin\model\live\MaintenanceConfig;

final class TgService
{
    /**
     * 发送 Telegram 消息
     */
    public function sendMessage(string $text): bool
    {
        $config = MaintenanceConfig::find(1);
        if (!$config || empty($config->bot_token) || empty($config->chat_id)) {
            return false;
        }

        $chatIds = explode(',', $config->chat_id);
        $allOk = true;

        foreach ($chatIds as $chatId) {
            $chatId = trim($chatId);
            if ($chatId === '') {
                continue;
            }
            $result = $this->callApi($config->bot_token, $chatId, $text);
            if (!$result) {
                $allOk = false;
            }
        }

        return $allOk;
    }

    /**
     * 测试发送，返回详细结果
     */
    public function testSend(string $botToken, string $chatId): array
    {
        $result = $this->callApi($botToken, $chatId, '🧪 测试消息：TG通知配置成功');
        return $result;
    }

    private function callApi(string $botToken, string $chatId, string $text): array
    {
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['ok' => false, 'error' => $error];
        }

        $data = json_decode($response, true);
        if ($httpCode === 200 && isset($data['ok']) && $data['ok']) {
            return ['ok' => true];
        }

        return ['ok' => false, 'error' => $data['description'] ?? 'Unknown error'];
    }
}
