<?php
/**
 * 站点首页入口（公开 / 与 /ai/ 都从这里决定）
 *
 * 由后台「站点设置 → 站点首页」(/admin/live.SiteHome/index) 控制：
 *   - 未设置：保持当前线上行为，302 到 Home.html
 *   - 已设置：302 到所选页面，例如 Image.html / Shorts.html / index.html 落地页
 *
 * 状态文件 .state/home.json 由后台写入（该目录需 chown www-data）。
 * 只影响 / 和 /ai/，直接访问 /X.html 不受影响。
 */
declare(strict_types=1);

$default = 'Home.html';
$stateFile = __DIR__ . '/.state/home.json';

$page = '';
if (is_file($stateFile)) {
    $raw = @file_get_contents($stateFile);
    $json = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($json) && isset($json['page'])) {
        $page = trim((string) $json['page']);
    }
}
if ($page === '') {
    $page = $default;
}

// 只允许站点目录下的 .html，防目录穿越
$page = basename(str_replace('\\', '/', $page));
if (!preg_match('/^[A-Za-z0-9._-]+\.html$/', $page) || !is_file(__DIR__ . '/' . $page)) {
    $page = is_file(__DIR__ . '/' . $default) ? $default : 'index.html';
}

// 当前请求前缀：直接访问 /index.php 时为 ''，经 /ai/index.php 时为 '/ai'
$script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
$prefix = rtrim(str_replace('/index.php', '', $script), '/');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Location: ' . $prefix . '/' . $page, true, 302);
exit;
