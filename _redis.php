<?php
$r = new Redis();
$r->connect('127.0.0.1', 6379);
foreach ([5,6,7,8] as $rid) {
    $key = "list:keyword:room:{$rid}";
    $len = $r->lLen($key);
    echo "Room {$rid}: LLEN={$len}\n";
    if ($len > 0) {
        $items = $r->lRange($key, 0, -1);
        foreach ($items as $i) echo "  {$i}\n";
    }
}
