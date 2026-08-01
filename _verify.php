<?php
require __DIR__.'/php/vendor/autoload.php';
$db = new PDO('mysql:host=127.0.0.1;port=3306;dbname=live_platform;charset=utf8mb4','root','root');
$s = $db->query("SELECT ma.id, ma.title, ma.file_url FROM lp_room_binding rb JOIN lp_playlist_template pt ON pt.id=rb.playlist_template_id JOIN lp_playlist_template_item pti ON pti.template_id=pt.id JOIN lp_media_asset ma ON ma.id=pti.asset_id WHERE rb.room_id=7 AND ma.asset_type='video' ORDER BY pti.seq LIMIT 5");
foreach ($s as $r) echo "#{$r['id']} {$r['file_url']}\n";
