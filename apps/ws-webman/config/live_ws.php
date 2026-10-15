<?php

return [
    // 心跳超时（秒）：浏览器会把后台标签页的定时器节流到 ≥60s，
    // 所以这里必须留足余量，否则「网页还开着」会被误判为掉线/离线。
    // 真正关页面/退出登录是靠连接断开（onClose）即时判定的，不依赖这个超时。
    'heartbeat_ttl' => 150,
    'heartbeat_check_interval' => 5,
    'auth_timeout' => 10,
    'aggregate_flush_interval' => 60,
    'thinkphp_api_base' => getenv('THINKPHP_API_BASE') ?: 'http://127.0.0.1:7090',
    'broadcast_poll_interval' => 1,
    'ai_task_stream_key' => getenv('AI_TASK_STREAM_KEY') ?: 'stream:ai:tasks',
    'ai_task_group' => getenv('AI_TASK_GROUP') ?: 'ai-workers',
];
