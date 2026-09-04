<?php

// AI 聊天 LLM 配置（OpenAI 兼容接口）
return [
    // API Key（留空则聊天接口返回降级提示）
    'api_key'  => env('llm.api_key', ''),
    // OpenAI 兼容接口 Base URL
    'base_url' => env('llm.base_url', 'https://api.openai.com/v1'),
    // 模型名
    'model'    => env('llm.model', 'gpt-4o-mini'),
    // 生成参数
    'temperature' => (float) env('llm.temperature', 0.8),
    'max_tokens'  => (int) env('llm.max_tokens', 500),
    'timeout'     => (int) env('llm.timeout', 60),
];
