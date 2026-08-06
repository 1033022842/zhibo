<?php
require __DIR__ . '/../php/vendor/autoload.php';

$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=live_platform;charset=utf8mb4',
    'root', 'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec("UPDATE lp_room_binding SET persona='白毛女' WHERE room_id=1");
echo "Updated persona for room 1\n";

$stmt = $pdo->query('SELECT room_id, persona FROM lp_room_binding WHERE room_id=1');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Room 1: " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
