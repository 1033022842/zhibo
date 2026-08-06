<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=live_platform;charset=utf8mb4', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "--- playlist template 2 ---\n";
$a = $pdo->query("SELECT * FROM lp_playlist_template WHERE id = 2")->fetch();
echo json_encode($a, JSON_UNESCAPED_UNICODE) . "\n";

echo "--- playlist items for template 2 ---\n";
$s = $pdo->query("SELECT pti.*, ma.title, ma.file_url FROM lp_playlist_template_item pti JOIN lp_media_asset ma ON ma.id = pti.asset_id WHERE pti.template_id = 2 ORDER BY pti.seq");
while ($r = $s->fetch()) {
    echo "  seq={$r['seq']} title={$r['title']} file={$r['file_url']}\n";
}

echo "--- stream template 1 ---\n";
$a = $pdo->query("SELECT * FROM lp_stream_template WHERE id = 1")->fetch();
echo json_encode($a, JSON_UNESCAPED_UNICODE) . "\n";
