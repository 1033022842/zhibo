<?php

// 语音合成配置
// Edge TTS 以本地常驻服务方式运行（tts-service/server.py），PHP 通过 127.0.0.1 调用，
// 负责 6 种固定女声；服务不可用时 TtsService 自动回退 xAI。
return [
    // 本地 Edge TTS 服务地址；留空则关闭 Edge 通道、全部走 xAI
    'edge_url'     => env('tts.edge_url', 'http://127.0.0.1:8100'),
    // 与服务端 EDGE_TTS_TOKEN 保持一致；留空则不校验
    'edge_token'   => env('tts.edge_token', ''),
    // 单句合成超时（秒）
    'edge_timeout' => (int) env('tts.edge_timeout', 12),
];
