<?php
return [
    'api_key'          => env('ai.api_key', 'live-ai-api-key-2026'),
    'task_deadline_min' => 5,
    'callback_base_url' => env('ai.callback_base_url', ''),
    // 静态资源（上传的视频/语音/图片）访问基础地址，末尾不带 /；留空则回退到请求域名
    'resource_base_url' => env('ai.resource_base_url', ''),
    'srs_secret'        => env('ai.srs_secret', 'srs-callback-secret-2026'),
    'stream_pull_timeout_ms' => (int) env('ai.stream_pull_timeout_ms', 3000),
    'stream' => [
        'rtmp_push_url'  => env('ai.rtmp_push_url', 'rtmp://127.0.0.1:1935/live/'),
        'webrtc_app'     => env('ai.webrtc_app', 'live'),
        'max_stream_sec' => (int) env('ai.max_stream_sec', 120),
    ],
    // 离线换脸任务（用户上传图片 + 后台配置的固定模板视频）
    'face_swap' => [
        'deadline_min' => (int) env('ai.face_swap_deadline_min', 60),
        'list_limit'   => (int) env('ai.face_swap_list_limit', 30),
    ],
];
