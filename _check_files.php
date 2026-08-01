<?php
require __DIR__.'/php/vendor/autoload.php';
$db = new PDO('mysql:host=127.0.0.1;port=3306;dbname=live_platform;charset=utf8mb4', 'root', 'root');
$s = $db->query('SELECT id, title, file_url FROM lp_media_asset WHERE asset_type="video" AND status=1 LIMIT 8');
$root = 'd:/phpstudy_pro/WWW/douyin';
foreach ($s as $r) {
    $url = $r['file_url'];
    $exists = 'NO';
    // Try resolving like PlaylistRepository does
    $path = '';
    if (preg_match('/^[a-zA-Z]:/', $url)) {
        $path = $url;
    } elseif (preg_match('#^https?://#i', $url)) {
        $path = $root . '/php/public' . parse_url($url, PHP_URL_PATH);
    } else {
        $n = '/' . ltrim(str_replace('\\', '/', $url), '/');
        if (str_starts_with($n, '/storage/')) {
            $path = $root . '/php/public' . $n;
        } else {
            $path = $root . '/' . ltrim(str_replace('\\', '/', $url), '/');
        }
    }
    $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
    if ($path && file_exists($path)) $exists = 'YES';
    echo "#{$r['id']} {$r['title']}\n  url={$url}\n  path={$path}\n  exists={$exists}\n\n";
}
