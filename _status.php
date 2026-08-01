<?php
echo "=== 1. PHP processes ===\n";
$res = [];
exec('tasklist /fi "imagename eq php.exe" /fo csv /nh 2>&1', $res);
foreach ($res as $line) {
    if (trim($line)) echo "  $line\n";
}

echo "\n=== 2. ffmpeg processes ===\n";
$res = [];
exec('tasklist /fi "imagename eq ffmpeg.exe" /fo csv /nh 2>&1', $res);
foreach ($res as $line) {
    if (trim($line)) echo "  $line\n";
}

echo "\n=== 3. Netstat ws-webman ===\n";
$res = [];
exec('netstat -ano | findstr ":8788" 2>&1', $res);
foreach ($res as $r) echo "  $r\n";

echo "\n=== 4. Redis queue ===\n";
$r = new Redis();
$r->connect('127.0.0.1', 6379);
for ($i = 5; $i <= 8; $i++) {
    echo "  room-$i: " . $r->lLen("list:keyword:room:$i") . "\n";
}

echo "\n=== 5. Push test keyword ===\n";
$p = json_encode(['room_id'=>6,'command_type'=>'keyword','params'=>['keyword'=>'比心'],'created_at'=>date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE);
echo "  rpush room-6: " . $r->rpush('list:keyword:room:6', $p) . "\n";
echo "  time: " . date('H:i:s') . "\n";
sleep(5);
echo "  queue after 5s: " . $r->lLen("list:keyword:room:6") . "\n";

echo "\n=== 6. room-6.log tail (worker/GIFT/overlay lines) ===\n";
$f = 'd:/phpstudy_pro/WWW/douyin/php/runtime/channel-worker/room-6.log';
if (file_exists($f)) {
    $size = filesize($f);
    $fp = fopen($f, 'r');
    fseek($fp, max(0, $size - 8192));
    $t = stream_get_contents($fp);
    fclose($fp);
    foreach (explode("\n", $t) as $l) {
        if (preg_match('/worker|GIFT|礼物|切换|重启|killed|启动|崩溃|Conversion|Error|Failed|ffmpeg/i', $l)) {
            echo "  $l\n";
        }
    }
} else {
    echo "  日志文件不存在\n";
}

echo "\n=== 7. overlay_6.txt ===\n";
$f = 'd:/phpstudy_pro/WWW/douyin/services/channel-worker/runtime/overlay_6.txt';
if (file_exists($f)) {
    echo "  " . file_get_contents($f);
}
