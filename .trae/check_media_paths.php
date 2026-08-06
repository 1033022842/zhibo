<?php
// Quick check: what file paths will the playlist produce for room 1
require __DIR__ . '/../php/vendor/autoload.php';

$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=live_platform;charset=utf8mb4',
    'root', 'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Get playlist template items
$stmt = $pdo->query(
    'SELECT ma.id, ma.title, ma.file_url, ma.duration_ms
     FROM lp_playlist_template_item pti
     JOIN lp_media_asset ma ON ma.id = pti.asset_id
     WHERE pti.template_id = (SELECT playlist_template_id FROM lp_room_binding WHERE room_id=1)
     ORDER BY pti.seq ASC'
);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$projectRoot = dirname(__DIR__);

foreach ($rows as $r) {
    $fileUrl = $r['file_url'];
    echo "DB file_url: {$fileUrl}\n";
    
    // Simulate PlaylistRepository::resolvePath
    // HTTP URL mapping
    if (preg_match('/^https?:\/\//i', $fileUrl)) {
        $path = parse_url($fileUrl, PHP_URL_PATH);
        if ($path) {
            $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
            if (str_starts_with($path, '/storage/')) {
                $resolved = dirname(__DIR__) . '/php/public' . $path;
                echo "  => resolved: $resolved\n";
                echo "  => exists: " . (file_exists($resolved) ? 'YES' : 'NO') . "\n";
            }
        }
    } elseif (preg_match('/^[a-zA-Z]:[\\\\\/]/', $fileUrl)) {
        echo "  => absolute path, exists: " . (file_exists($fileUrl) ? 'YES' : 'NO') . "\n";
    } else {
        // relative path with media_base_dir
        $resolved = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fileUrl);
        echo "  => resolved: $resolved\n";
        echo "  => exists: " . (file_exists($resolved) ? 'YES' : 'NO') . "\n";
    }
    echo "\n";
}
