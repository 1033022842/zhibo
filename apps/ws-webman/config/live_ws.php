<?php

return [
    'heartbeat_ttl' => 30,
    'heartbeat_check_interval' => 5,
    'auth_timeout' => 10,
    'aggregate_flush_interval' => 60,
    'thinkphp_api_base' => getenv('THINKPHP_API_BASE') ?: 'http://127.0.0.1:7090',
    'broadcast_poll_interval' => 1,
    'ai_task_stream_key' => getenv('AI_TASK_STREAM_KEY') ?: 'stream:ai:tasks',
    'ai_task_group' => getenv('AI_TASK_GROUP') ?: 'ai-workers',
];
