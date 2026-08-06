<?php
echo "DB_USER env: '" . (getenv('DB_USER') ?: '(not set)') . "'\n";
echo "DB_PASSWORD env: '" . (getenv('DB_PASSWORD') ?: '(not set)') . "'\n";
echo "DB_NAME env: '" . (getenv('DB_NAME') ?: '(not set)') . "'\n";
echo "HLS_OUTPUT_DIR env: '" . (getenv('HLS_OUTPUT_DIR') ?: '(not set)') . "'\n";
require "/www/wwwroot/douyin/services/channel-worker/src/Database.php";
try {
    $db = new ChannelWorker\Database();
    $p = $db->pdo();
    $r = $p->query("SELECT 1")->fetch();
    echo "DB OK\n";
} catch (Exception $e) {
    echo "DB FAIL: " . $e->getMessage() . "\n";
}
