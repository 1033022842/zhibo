<?php
$ch = curl_init('http://127.0.0.1:8000/api/v1/rooms/1');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
$body = curl_exec($ch);
curl_close($ch);

$d = json_decode($body, true);
$play = $d['data']['play'] ?? [];
echo "hls_url: " . ($play['hls_url'] ?? 'N/A') . "\n";
echo "stream_alias: " . ($play['stream_alias'] ?? 'N/A') . "\n";
