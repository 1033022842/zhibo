<?php
// 直接模拟 channel worker 的 consumeKeyword 逻辑
$redis = new Redis();
@$redis->connect('127.0.0.1', 6379, 2.0);

$pong = @$redis->ping();
echo "Redis PING: " . ($pong ? 'OK' : 'FAIL') . " (raw=" . var_export($pong, true) . ")\n";

$key = 'list:keyword:room:6';
$len = $redis->lLen($key);
echo "LLEN $key: $len\n";

// 模拟 consumeKeyword
while ($len > 0) {
    $payload = $redis->lPop($key);
    echo "lPop result: " . var_export($payload, true) . "\n";
    if (!$payload || !is_string($payload)) {
        echo "  FAIL: not a string\n";
        break;
    }
    $data = json_decode($payload, true);
    if (!is_array($data)) {
        echo "  FAIL: not valid JSON\n";
        break;
    }
    $params = $data['params'] ?? [];
    $keyword = $params['keyword'] ?? '';
    echo "  keyword=$keyword\n";
    $len--;
}

echo "Remaining LLEN: " . $redis->lLen($key) . "\n";
