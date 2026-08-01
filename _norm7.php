<?php
require __DIR__.'/php/vendor/autoload.php';
$db = new PDO('mysql:host=127.0.0.1;port=3306;dbname=live_platform;charset=utf8mb4', 'root', 'root');
$root = 'd:/phpstudy_pro/WWW/douyin';

$s = $db->query("SELECT ma.id, ma.title, ma.file_url FROM lp_room_binding rb JOIN lp_playlist_template pt ON pt.id=rb.playlist_template_id JOIN lp_playlist_template_item pti ON pti.template_id=pt.id JOIN lp_media_asset ma ON ma.id=pti.asset_id WHERE rb.room_id=7 AND ma.asset_type='video' ORDER BY pti.seq");
$videos = $s->fetchAll();
echo "Room 7 has ".count($videos)." videos\n\n";

$normalizer = new \app\common\service\VideoNormalizer();
foreach ($videos as $v) {
    $id = $v['id'];
    $title = $v['title'];
    $url = $v['file_url'];
    $path = resolvePath($url, $root);
    
    echo "#{$id} {$title}\n  file_url={$url}\n  resolved={$path}\n";
    
    if (!$path || !file_exists($path)) {
        echo "  SKIP: file not found\n\n";
        continue;
    }
    
    if ($normalizer->isNormalized($path)) {
        echo "  SKIP: already normalized\n\n";
        continue;
    }
    
    try {
        echo "  NORMALIZING...\n";
        $normPath = $normalizer->normalize($path);
        $normUrl = toStorageUrl($normPath, $root);
        if ($normUrl) {
            $db->exec("UPDATE lp_media_asset SET file_url=".$db->quote($normUrl)." WHERE id={$id}");
            echo "  DONE => {$normUrl}\n\n";
        }
    } catch (Exception $e) {
        echo "  FAIL: {$e->getMessage()}\n\n";
    }
}
echo "Complete.\n";

function resolvePath($url, $root) {
    $url = trim($url);
    if (!$url) return '';
    if (preg_match('/^[a-zA-Z]:/', $url)) return str_replace('/', DIRECTORY_SEPARATOR, $url);
    if (preg_match('#^https?://#i', $url)) {
        $p = parse_url($url, PHP_URL_PATH);
        if ($p) return $root.'/php/public'.str_replace('/', DIRECTORY_SEPARATOR, '/'.ltrim($p,'/'));
    }
    $n = '/'.ltrim(str_replace('\\','/',$url),'/');
    if (str_starts_with($n, '/storage/')) return $root.'/php/public'.str_replace('/', DIRECTORY_SEPARATOR, $n);
    return $root.'/'.str_replace('/', DIRECTORY_SEPARATOR, ltrim(str_replace('\\','/',$url),'/'));
}
function toStorageUrl($path, $root) {
    $path = str_replace('\\', '/', $path);
    $ps = str_replace('\\', '/', $root.'/php/public/storage');
    if (str_starts_with($path, $ps.'/')) return '/storage/'.ltrim(substr($path, strlen($ps)+1), '/');
    $pr = str_replace('\\', '/', $root.'/php/public');
    if (str_starts_with($path, $pr.'/')) return ltrim(substr($path, strlen($pr)), '/');
    return '';
}
