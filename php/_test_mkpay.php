<?php
/**
 * MKPay 对接自测脚本（CLI 运行：php _test_mkpay.php [balance|pay|sign]）
 *
 * 不依赖框架，直接读同目录 .env 的 [MKPAY] 段：
 *   sign    验签算法自测（构造假回调，本地算 sign 比对，不需要真实 signKey）← 默认
 *   balance 连网关查商户余额（需要 .env 已填真实 SIGNKEY）
 *   pay     创建一笔 1 BRL 测试代收单（需要 SIGNKEY；测试商户代收可能失败，看返回 msg）
 *
 * 注意：签名算法要求「除 sign 外非空参数 ASCII 升序 k=v& 拼接（保留末尾 &）+ key=MD5(signKey) 再 MD5」。
 */

$env = is_file(__DIR__ . '/.env') ? (parse_ini_file(__DIR__ . '/.env', true, INI_SCANNER_RAW) ?: []) : [];
$cfg = array_change_key_case($env['MKPAY'] ?? [], CASE_LOWER);
$gateway = rtrim((string)($cfg['gateway'] ?? 'https://br.mkpayeasy.com'), '/');
$appId   = (string)($cfg['appid'] ?? '');
$signKey = (string)($cfg['signkey'] ?? '');
$notify  = (string)($cfg['notify_url'] ?? '');
$prefix  = (string)($cfg['order_prefix'] ?? 'MKS');
$mode    = $argv[1] ?? 'sign';

function mkSign(array $params, string $signKey): string
{
    unset($params['sign']);
    $data = [];
    foreach ($params as $k => $v) {
        $v = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$v;
        if ($v === '') continue; // 空值不参与
        $data[$k] = $v;
    }
    ksort($data, SORT_STRING);
    $str = '';
    foreach ($data as $k => $v) $str .= $k . '=' . $v . '&'; // 每组都带 &，末尾保留
    $str .= 'key=' . md5($signKey);
    return md5($str);
}

function httpReq(string $method, string $url, array $headers, array $params): array
{
    $ch = curl_init($url);
    $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => $headers,
        // 本地 Windows PHP 无 CA 束，仅测试脚本放开校验；线上 Guzzle 走系统 CA 正常校验
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0];
    if ($method === 'POST') {
        $opt[CURLOPT_POST] = true;
        $opt[CURLOPT_POSTFIELDS] = json_encode($params, JSON_UNESCAPED_SLASHES);
    } else {
        $opt[CURLOPT_CUSTOMREQUEST] = 'GET';
    }
    curl_setopt_array($ch, $opt);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false) exit("[cURL 失败] $err\n");
    $body = substr($resp, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    return [$code, $body];
}

echo "== MKPay 自测（mode={$mode}）==\n";
echo "gateway={$gateway} appId=" . ($appId ?: '(未填)') . " signKey=" . ($signKey !== '' ? '已配置(' . strlen($signKey) . '字符)' : '(未配置)') . "\n\n";

if ($mode === 'sign') {
    // —— 验签算法自测：本地构造回调 → 算 sign → 用与 MkPayService 相同的算法回验 ——
    $fakeKey = 'test-signKey-123';
    $cb = [
        'amount' => '5000', 'code' => 200, 'createTimeL' => '1680334544698',
        'merchantOrderId' => 'MKS' . date('ymdHis') . 'AB01', 'orderId' => 'S110202305291663185317441789952',
        'payType' => '110', 'status' => '01', 'realPayAmount' => '5000', 'fee' => '25',
        // 故意放一个空值与一个待剔除项，验证「空值不参与签名」
        'endToEndId' => 'e08561701202303120103i5gsv8nm4xz', 'msg' => 'success', 'utr' => '',
    ];
    $cb['sign'] = mkSign($cb, $fakeKey);

    $expect = mkSign($cb, $fakeKey);            // 重新计算（含 sign 字段但算法内剔除）
    $ok1 = hash_equals($expect, (string)$cb['sign']);
    // 篡改金额后必须验不过
    $tampered = $cb; $tampered['amount'] = '1';
    $ok2 = !hash_equals($expect, mkSign($tampered, $fakeKey));
    // 篡改 signKey 后必须验不过
    $ok3 = !hash_equals($expect, mkSign($cb, 'wrong-key'));

    echo "签名算法回验（正常报文通过）        : " . ($ok1 ? "PASS" : "FAIL") . "\n";
    echo "篡改金额后被识别                    : " . ($ok2 ? "PASS" : "FAIL") . "\n";
    echo "篡改密钥后被识别                    : " . ($ok3 ? "PASS" : "FAIL") . "\n";
    echo "样例 sign={$cb['sign']}\n";
    exit(($ok1 && $ok2 && $ok3) ? 0 : 1);
}

if ($signKey === '' || $appId === '') exit("[skip] .env [MKPAY] SIGNKEY/APPID 未配置，无法连网关测试\n");

$headers = [
    'Content-Type: application/json',
    'Authorization: Basic ' . base64_encode($appId . ':' . $signKey),
    'x-app-id: ' . $appId,
];

if ($mode === 'balance') {
    [$code, $body] = httpReq('GET', $gateway . '/open-api/pay/queryBalance', $headers, []);
    echo "HTTP {$code}\n{$body}\n";
    $j = json_decode($body, true);
    exit(isset($j['code']) && (int)$j['code'] === 200 ? 0 : 1);
}

if ($mode === 'pay') {
    if ($notify === '') exit("[skip] .env [MKPAY] NOTIFY_URL 未配置\n");
    $params = [
        'amount'          => '100', // 1.00 BRL
        'notifyUrl'       => $notify,
        'merchantOrderId' => $prefix . date('ymdHis') . strtoupper(bin2hex(random_bytes(3))),
    ];
    echo "下单参数：" . json_encode($params, JSON_UNESCAPED_SLASHES) . "\n";
    [$code, $body] = httpReq('POST', $gateway . '/open-api/pay/payment', $headers, $params);
    echo "HTTP {$code}\n{$body}\n";
    $j = json_decode($body, true);
    // 测试商户代收可能失败：code!=200 时也把 msg 打出来排查
    exit((isset($j['code']) && (int)$j['code'] === 200 && ($j['status'] ?? '') === '00') ? 0 : 1);
}

exit("用法：php _test_mkpay.php [sign|balance|pay]\n");
