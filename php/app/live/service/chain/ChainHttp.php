<?php
declare(strict_types=1);

namespace app\live\service\chain;

/**
 * 链上节点 HTTP 调用（TronGrid / JSON-RPC 通用）
 */
final class ChainHttp
{
    /**
     * POST JSON，返回解析后的数组
     *
     * @throws \RuntimeException 网络异常 / 非 JSON 响应 / HTTP 4xx-5xx
     */
    public static function postJson(string $url, array $payload, array $headers = [], int $timeout = 25): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new \RuntimeException('请求体编码失败');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $res  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($res === false) {
            throw new \RuntimeException('区块链节点请求失败：' . $err);
        }
        $data = json_decode((string) $res, true);
        if (!is_array($data)) {
            throw new \RuntimeException('区块链节点返回非 JSON（HTTP ' . $code . '）：' . substr((string) $res, 0, 200));
        }
        if ($code >= 400) {
            $msg = (string) ($data['error']['message'] ?? $data['message'] ?? substr((string) $res, 0, 200));
            throw new \RuntimeException('区块链节点 HTTP ' . $code . '：' . $msg);
        }
        return $data;
    }
}
