<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=live_platform;charset=utf8mb4', 'root', 'root', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "--- room 1 绑定 ---\n";
    $s = $pdo->query("SELECT * FROM lp_room_binding WHERE room_id = 1");
    while ($r = $s->fetch()) { echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n"; }

    echo "--- media_asset 总数 ---\n";
    $cnt = $pdo->query("SELECT COUNT(*) FROM lp_media_asset WHERE status = 1")->fetchColumn();
    echo "$cnt\n";

    echo "--- media_asset 按 persona 分组 ---\n";
    $s = $pdo->query("SELECT persona, COUNT(*) as cnt FROM lp_media_asset WHERE status = 1 GROUP BY persona");
    while ($r = $s->fetch()) { echo "{$r['persona']}: {$r['cnt']}\n"; }

    echo "--- 白毛女 关键词分布 (前10) ---\n";
    $kw = [];
    $s = $pdo->query("SELECT keywords FROM lp_media_asset WHERE persona = '白毛女' AND status = 1");
    while ($r = $s->fetch()) {
        foreach (explode(',', $r['keywords']) as $k) {
            $k = trim($k);
            if ($k !== '') $kw[$k] = ($kw[$k] ?? 0) + 1;
        }
    }
    arsort($kw);
    foreach (array_slice($kw, 0, 10) as $k => $v) { echo "  $k: $v\n"; }

    echo "--- 视频样本 (白毛女) ---\n";
    $s = $pdo->query("SELECT title, file_url, keywords FROM lp_media_asset WHERE persona = '白毛女' AND status = 1 LIMIT 3");
    while ($r = $s->fetch()) {
        echo "  title={$r['title']} file={$r['file_url']} kw={$r['keywords']}\n";
    }
} catch (Exception $e) {
    echo "ERR: " . $e->getMessage() . "\n";
}
