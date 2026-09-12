<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\BaseController;

/**
 * WHEP 代理：透传前端 WebRTC SDP 请求到 MediaMTX
 */
final class Whep extends BaseController
{
    protected array $middleware = [];

    public function index(string $app, string $stream): void
    {
        $body = $this->request->getContent();
        $contentType = $this->request->header('Content-Type', 'application/sdp');

        $mediamtxUrl = sprintf(
            'http://172.81.98.55:8889/%s/%s/whep',
            urlencode($app),
            urlencode($stream)
        );

        $ch = curl_init($mediamtxUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: ' . $contentType,
                'Accept: application/sdp',
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HEADER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($response === false || $httpCode === 0) {
            http_response_code(502);
            echo 'WHEP proxy error';
            return;
        }

        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        // 透传 Content-Type
        if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $responseHeaders, $m)) {
            header('Content-Type: ' . trim($m[1]));
        }

        http_response_code($httpCode);
        echo $responseBody;
    }
}
