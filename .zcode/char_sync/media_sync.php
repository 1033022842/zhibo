<?php
/**
 * 角色素材跨品牌复制（通用版）：按 storage 路径清单把 sugus 本地素材
 * 推到目标品牌的项目前缀（同一 bucket，本地读 → 对象存储写）。
 *
 * 用法：php media_sync.php <targetProject> <pathListFile> [--dry]
 * 清单每行一个站内路径，如 /storage/chat_gifts/home-88/persona_800.jpg
 */

declare(strict_types=1);

use app\common\library\media\S3Client;

// 在源站（sugus）上运行：站点根 = 本脚本所在仓库的上一级
$PHP_ROOT = getenv('SITE_PHP_ROOT') ?: '/www/wwwroot/douyin/php';
$ENV_FILE = $PHP_ROOT . '/.env';
$STORE    = $PHP_ROOT . '/public/storage';

require $PHP_ROOT . '/app/common/library/media/S3Client.php';

$PUBLIC_PREFIXES = ['covers', 'home_banner', 'home_character', 'gift_effect', 'loops', 'default'];
$IMAGE_EXT       = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico', 'avif'];

$MIME = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
    'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'avif' => 'image/avif',
    'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'mp3' => 'audio/mpeg',
];

function mediaEnv(string $file): array
{
    $out = [];
    $sec = '';
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === ';' || $line[0] === '#') {
            continue;
        }
        if (preg_match('/^\[(.+)\]$/', $line, $m)) {
            $sec = strtoupper(trim($m[1]));
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $k = strtoupper(trim(substr($line, 0, $pos)));
        $v = trim(substr($line, $pos + 1));
        if ($v !== '' && ($v[0] === '"' || $v[0] === "'")) {
            $v = trim($v, "\"'");
        }
        $out[$sec][$k] = $v;
    }
    return $out;
}

function keyFor(string $path, string $project, array $publicPrefixes, array $imageExt): string
{
    $rel = preg_replace('#^/?(?:public/)?storage/#i', '', $path) ?? $path;
    $rel = ltrim($rel, '/');
    $first = strtolower((string) (explode('/', $rel)[0] ?? ''));
    $ext   = strtolower((string) pathinfo($rel, PATHINFO_EXTENSION));
    $pub   = in_array($first, $publicPrefixes, true) && in_array($ext, $imageExt, true);
    return ($pub ? 'pub' : 'sec') . '/' . trim($project, '/') . '/' . $rel;
}

$env    = mediaEnv($ENV_FILE)['MEDIA'] ?? [];
$cfg    = [
    'endpoint'   => $env['S3_ENDPOINT'] ?? '',
    'bucket'     => $env['BUCKET'] ?? '',
    'region'     => $env['REGION'] ?? 'us-east-1',
    'access_key' => $env['ACCESS_KEY'] ?? '',
    'secret_key' => $env['SECRET_KEY'] ?? '',
    'path_mode'  => $env['S3_PATH_MODE'] ?? 'key',
];
if (!empty($env['GATEWAY_KEY'])) {
    $cfg['headers'] = ['X-Media-Key' => $env['GATEWAY_KEY']];
}

$target = $argv[1] ?? '';
$list   = $argv[2] ?? '';
$dry    = in_array('--dry', $argv, true);

if ($target === '' || !is_file($list)) {
    fwrite(STDERR, "usage: php media_sync.php <targetProject> <pathListFile> [--dry]\n");
    exit(2);
}

$s3   = $dry ? null : new S3Client($cfg);
$stat = ['pushed' => 0, 'nofile' => 0, 'failed' => 0];
$fail = [];

foreach (file($list, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $path) {
    $path = trim($path);
    if ($path === '') {
        continue;
    }
    $rel   = ltrim(preg_replace('#^/?(?:public/)?storage/#i', '', $path) ?? $path, '/');
    $local = $STORE . '/' . $rel;
    if (!is_file($local)) {
        $stat['nofile']++;
        continue;
    }
    $key = keyFor($path, $target, $PUBLIC_PREFIXES, $IMAGE_EXT);
    $ext = strtolower((string) pathinfo($rel, PATHINFO_EXTENSION));
    if ($dry) {
        echo $key, "\n";
        $stat['pushed']++;
        continue;
    }
    try {
        $ok = $s3->putFile($local, $key, $MIME[$ext] ?? 'application/octet-stream');
        if ($ok) {
            $stat['pushed']++;
        } else {
            $stat['failed']++;
            $fail[] = $key;
        }
    } catch (\Throwable $e) {
        $stat['failed']++;
        $fail[] = $key . ' :: ' . $e->getMessage();
    }
}

echo json_encode(['target' => $target, 'stat' => $stat, 'fail' => array_slice($fail, 0, 10)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
