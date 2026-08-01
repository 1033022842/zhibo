<?php
$r = new Redis();
$r->connect('127.0.0.1', 6379);
$key = 'list:keyword:room:6';
while ($r->lPop($key)) {} // clear
$r->rPush($key, '{"room_id":6,"command_type":"keyword","params":{"keyword":"比心"}}');
echo "Sent, len: " . $r->lLen($key) . "\n";
sleep(2);
echo "After 2s: " . $r->lLen($key) . "\n";
