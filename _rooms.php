<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=live_platform;charset=utf8mb4', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "=== lp_playlist_template_item columns ===\n";
$cols = $pdo->query("DESCRIBE lp_playlist_template_item")->fetchAll();
foreach ($cols as $c) echo "  {$c['Field']} ({$c['Type']})\n";

echo "\n=== Template items ===\n";
$rows = $pdo->query("SELECT * FROM lp_playlist_template_item WHERE template_id IN (7,8,9)")->fetchAll();
foreach ($rows as $r) {
    echo "  id={$r['id']} template_id={$r['template_id']}";
    foreach ($r as $k => $v) {
        if (in_array($k, ['id', 'template_id'])) continue;
        if ($v !== null && $v !== '') echo " $k=$v";
    }
    echo "\n";
}
