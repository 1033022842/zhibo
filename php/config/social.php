<?php
declare(strict_types=1);

// 每个站点的凭据和完整 HTTPS 回调地址分别放在服务器 .env，不能复用客户域名。
return [
    'email_enabled' => (bool) env('social.email_enabled', true),
    'google' => [
        'client_id' => (string) env('google.client_id', ''),
        'client_secret' => (string) env('google.client_secret', ''),
        'redirect_uri' => (string) env('google.redirect_uri', ''),
    ],
    'discord' => [
        'client_id' => (string) env('discord.client_id', ''),
        'client_secret' => (string) env('discord.client_secret', ''),
        'redirect_uri' => (string) env('discord.redirect_uri', ''),
    ],
    'twitter' => [
        'client_id' => (string) env('twitter.client_id', ''),
        'client_secret' => (string) env('twitter.client_secret', ''),
        'redirect_uri' => (string) env('twitter.redirect_uri', ''),
    ],
];
