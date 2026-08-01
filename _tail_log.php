<?php
$f = 'D:/phpstudy_pro/WWW/douyin/php/runtime/channel-worker/room-6.log';
$size = filesize($f);
$fp = fopen($f, 'r');
fseek($fp, max(0, $size - 5000));
$t = stream_get_contents($fp);
fclose($fp);
foreach (explode("\n", $t) as $l) {
    if (strpos($l, '[worker]') !== false || strpos($l, 'GIFT') !== false) {
        echo $l . "\n";
    }
}
