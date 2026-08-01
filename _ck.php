<?php
$f = 'd:/phpstudy_pro/WWW/douyin/php/runtime/channel-worker/room-6.log';
echo "Size: " . filesize($f) . " bytes, Modified: " . date('H:i:s', filemtime($f)) . "\n";

$fp = fopen($f, 'r');
fseek($fp, max(0, filesize($f) - 4096));
$tail = stream_get_contents($fp);
fclose($fp);

$interesting = [];
foreach (explode("\n", $tail) as $l) {
    if (strpos($l, 'GIFT') !== false || strpos($l, '礼物') !== false ||
        strpos($l, 'movie') !== false || strpos($l, 'zmq') !== false ||
        strpos($l, 'Conversion') !== false || strpos($l, 'heartbeat') !== false ||
        strpos($l, 'ffmpeg 已退出') !== false || strpos($l, 'ZMQ 端口') !== false) {
        $interesting[] = $l;
    }
}
echo "Found " . count($interesting) . " lines:\n";
foreach (array_slice($interesting, -20) as $l) echo "  " . $l . "\n";

$r = new Redis();
$r->connect('127.0.0.1', 6379);
echo "\nRedis room-6 queue: " . $r->lLen('list:keyword:room:6') . "\n";
