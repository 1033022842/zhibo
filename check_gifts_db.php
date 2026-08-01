<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=live_platform;charset=utf8mb4', 'root', 'root');
$stmt = $pdo->query("SELECT id, name, trigger_mode, trigger_duration_sec FROM lp_gift WHERE status=1");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
$stmt2 = $pdo->query("SELECT gk.gift_id, gk.keyword FROM lp_gift_keyword gk");
while ($r2 = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    echo "keyword: " . json_encode($r2, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
