<?php
$r = new Redis(); $r->connect('127.0.0.1', 6379);
$p = json_encode(['room_id'=>6,'command_type'=>'keyword','params'=>['keyword'=>'比心'],'created_at'=>date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE);
echo "rpush=" . $r->rpush('list:keyword:room:6', $p) . "\n";
sleep(4);
$f='d:/phpstudy_pro/WWW/douyin/php/runtime/channel-worker/room-6.log';
$fp=fopen($f,'r'); fseek($fp,max(0,filesize($f)-3000)); $t=stream_get_contents($fp); fclose($fp);
foreach(explode("\n",$t) as $l) {
    if(strpos($l,'GIFT')!==false||strpos($l,'礼物')!==false||strpos($l,'DB ERROR')!==false||strpos($l,'movie')!==false) echo $l."\n";
}
