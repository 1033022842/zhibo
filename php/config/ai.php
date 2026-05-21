<?php
return [
    'api_key'          => env('ai.api_key', 'live-ai-api-key-2026'),
    'task_deadline_min' => 5,
    'callback_base_url' => env('ai.callback_base_url', ''),
    'srs_secret'        => env('ai.srs_secret', 'srs-callback-secret-2026'),
    'stream_pull_timeout_ms' => (int) env('ai.stream_pull_timeout_ms', 3000),
    'stream' => [
        'rtmp_push_url'  => env('ai.rtmp_push_url', 'rtmp://127.0.0.1:1935/live/'),
        'webrtc_app'     => env('ai.webrtc_app', 'live'),
        'max_stream_sec' => (int) env('ai.max_stream_sec', 120),
    ],
];
