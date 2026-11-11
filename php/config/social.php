<?php
declare(strict_types=1);

// 每个站点的凭据和完整 HTTPS 回调地址分别放在服务器 .env，不能复用客户域名。
return [
    'email_enabled' => (bool) env('social.email_enabled', true),
    'google' => [
        'client_id' => (string) env('google.client_id', ''),
        'client_secret' => (string) env('google.client_secret', ''),
        'redirect_uri' => (string) env('google.redirect_uri', ''),
        // 多域名（裸域/www/品牌别名）时用逗号分隔，按发起登录的 origin 自动匹配
        'redirect_uris' => (string) env('google.redirect_uris', ''),
    ],
    'discord' => [
        'client_id' => (string) env('discord.client_id', ''),
        'client_secret' => (string) env('discord.client_secret', ''),
        'redirect_uri' => (string) env('discord.redirect_uri', ''),
        // 多域名（裸域/www/品牌别名）时用逗号分隔，按发起登录的 origin 自动匹配
        'redirect_uris' => (string) env('discord.redirect_uris', ''),
    ],
    'twitter' => [
        'client_id' => (string) env('twitter.client_id', ''),
        'client_secret' => (string) env('twitter.client_secret', ''),
        'redirect_uri' => (string) env('twitter.redirect_uri', ''),
        // 多域名（裸域/www/品牌别名）时用逗号分隔，按发起登录的 origin 自动匹配
        'redirect_uris' => (string) env('twitter.redirect_uris', ''),
    ],
    // Telegram Login Widget：每品牌一个 bot，Allowed URLs 在 BotFather mini app 配置。
    // bot_username 用于前端渲染 widget，bot_token 只在本服务端验签，绝不发给前端。
    'telegram' => [
        'bot_username' => (string) env('telegram.bot_username', ''),
        'bot_token' => (string) env('telegram.bot_token', ''),
    ],
];
