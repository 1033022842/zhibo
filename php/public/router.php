<?php
// ThinkPHP 开发环境伪静态路由

// /hls/ 静态文件映射到项目根的 hls/ 目录
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/hls/(.+)#', $uri, $m)) {
    $hlsFile = dirname(__DIR__, 2) . '/hls/' . $m[1];
    if (is_file($hlsFile)) {
        // 根据 extension 设置 Content-Type
        $ext = pathinfo($hlsFile, PATHINFO_EXTENSION);
        $ct = match($ext) {
            'm3u8' => 'application/vnd.apple.mpegurl',
            'ts'   => 'video/mp2t',
            default => mime_content_type($hlsFile),
        };
        header('Content-Type: ' . $ct);
        header('Cache-Control: no-cache');
        readfile($hlsFile);
        return;
    }
    // HLS 文件不存在，返回 404 而不是走 ThinkPHP 路由
    http_response_code(404);
    return;
}

if (is_file(__DIR__ . $uri)) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PATH_INFO'] = $uri;
require __DIR__ . '/index.php';
