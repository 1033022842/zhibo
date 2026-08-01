<?php
$r = new Redis();
$r->connect('127.0.0.1', 6379);
while ($r->lPop('list:keyword:room:6')) {}
echo "cleared, remaining: " . $r->lLen('list:keyword:room:6');
