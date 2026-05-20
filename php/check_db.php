<?php
require __DIR__ . '/vendor/autoload.php';

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=live_platform;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query('SELECT * FROM lp_user');
    echo "=== lp_user ===" . PHP_EOL;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }

    $stmt = $pdo->query('SELECT * FROM lp_user_auth');
    echo "=== lp_user_auth ===" . PHP_EOL;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }

    $stmt = $pdo->query('SELECT * FROM lp_user_profile');
    echo "=== lp_user_profile ===" . PHP_EOL;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
