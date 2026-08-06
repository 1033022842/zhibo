<?php
require __DIR__ . '/../php/vendor/autoload.php';

$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=live_platform;charset=utf8mb4',
    'root', 'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== lp_room_state_snapshot ===\n";
$rows = $pdo->query('SELECT * FROM lp_room_state_snapshot')->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== lp_room (status=1) ===\n";
$rows = $pdo->query("SELECT id, title, status, persona_id FROM lp_room WHERE status=1")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== MediaMTX check ===\n";
$ch = curl_init('http://127.0.0.1:9997/v3/paths/list');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 2);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
$result = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);
echo "MediaMTX result: " . ($err ? "ERROR: $err" : ($result ?: 'empty')) . "\n";
