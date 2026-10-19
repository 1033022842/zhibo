<?php
/**
 * 素材独立存储 + 签名防盗链
 *
 * 架构：
 *   项目机 ──(写)──> 存储服务器 MinIO（经 nginx /s3/ 网关，带 X-Media-Key）
 *   浏览器 ──(读)──> 存储服务器 nginx /sec/（secure_link 校验）或 /pub/（公开缓存）
 *
 * 数据库保持只存 /storage/xxx，对外输出时由 Media::url() 换成签名地址。
 * 全部开关默认关闭，关闭时行为与改造前完全一致。
 */
return [
    // 对外输出是否走签名地址（关掉 = 原样返回 /storage/xxx）
    'enabled'         => (bool) env('media.enabled', false),

    // 存储服务器对外访问地址（不带结尾斜杠），如 https://media.example.com
    'endpoint'        => (string) env('media.endpoint', ''),

    // 本项目在共享桶里的前缀，4 个项目各用一个（sugus / hey / xxx / yyy）
    'project'         => (string) env('media.project', 'sugus'),

    // 签名密钥：必须与存储服务器 nginx 的 secure_link_md5 里的密钥一致
    'sign_secret'     => (string) env('media.sign_secret', ''),

    // 签名有效期（秒）与过期时间对齐粒度（秒）
    // 对齐到时间桶是为了同一时间段内产出相同 URL，利于浏览器/CDN 缓存
    'sign_ttl'        => (int) env('media.sign_ttl', 21600),
    'sign_bucket_sec' => (int) env('media.sign_bucket_sec', 3600),

    // 目录列举（ListObjects）本地缓存秒数：0=不缓存。存储服务器在海外，单次往返 ~180ms
    'list_cache_ttl'  => (int) env('media.list_cache_ttl', 60),

    // 公开素材的顶层目录（这些走 /pub/，不签名，可被浏览器/CDN 长缓存）
    // 只放装饰性素材；付费内容（shorts/posts/private/chat_*）一律走 /sec/ 签名
    'public_prefixes' => [
        'covers', 'home_banner', 'home_character', 'gift_effect', 'loops', 'default',
    ],

    // ───── 写路径（上传落对象存储） ─────
    'storage_enabled' => (bool) env('media.storage_enabled', false),

    // S3 网关地址（存储服务器 nginx /s3/ 反代到本机 MinIO）
    's3_endpoint'     => (string) env('media.s3_endpoint', ''),
    // bucket=标准 path-style；key=网关模式（地址里已含 /s3，nginx 补 bucket 前缀）
    's3_path_mode'    => (string) env('media.s3_path_mode', 'key'),
    'bucket'          => (string) env('media.bucket', 'sugus-media'),
    'region'          => (string) env('media.region', 'us-east-1'),
    'access_key'      => (string) env('media.access_key', ''),
    'secret_key'      => (string) env('media.secret_key', ''),

    // 网关校验头（nginx 侧校验，避免 MinIO 端口直接暴露）
    's3_headers'      => [
        'X-Media-Key' => (string) env('media.gateway_key', ''),
    ],

    // TLS 校验：true（默认）| false（自签证书时才关）| CA bundle 路径
    's3_verify'       => env('media.s3_verify', true),
];
