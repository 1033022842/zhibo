<?php
/**
 * 对象存储内服务端复制（S3 CopyObject）：把一个 key 复制到另一个项目前缀，
 * 不经过本机带宽。用于把 sugus 的礼物视频补到其它品牌。
 *
 * 用法：php media_copy.php <targetProject> <idFrom> <idTo> [--dry]
 */

declare(strict_types=1);

$PHP_ROOT = getenv('SITE_PHP_ROOT') ?: '/www/wwwroot/douyin/php';
$ENV_FILE = $PHP_ROOT . '/.env';
$STORE    = $PHP_ROOT . '/public/storage';

function mediaEnv(string $file): array
{
    $out = [];
    $sec = '';
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === ';' || $line[0] === '#') {
            continue;
        }
        if (preg_match('/^\[(.+)\]$/', $line, $m)) {
            $sec = strtoupper(trim($m[1]));
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $k = strtoupper(trim(substr($line, 0, $pos)));
        $v = trim(substr($line, $pos + 1));
        if ($v !== '' && ($v[0] === '"' || $v[0] === "'")) {
            $v = trim($v, "\"'");
        }
        $out[$sec][$k] = $v;
    }
    return $out;
}

final class CopyClient
{
    private string $endpoint;
    private string $bucket;
    private string $region;
    private string $ak;
    private string $sk;
    private array $headers;

    public function __construct(array $cfg)
    {
        $this->endpoint = rtrim((string) $cfg['endpoint'], '/');
        $this->bucket   = (string) $cfg['bucket'];
        $this->region   = (string) ($cfg['region'] ?? 'us-east-1');
        $this->ak       = (string) $cfg['access_key'];
        $this->sk       = (string) $cfg['secret_key'];
        $this->headers  = (array) ($cfg['headers'] ?? []);
    }

    private function host(): string
    {
        $host = parse_url($this->endpoint, PHP_URL_HOST) ?: '';
        $port = parse_url($this->endpoint, PHP_URL_PORT);
        return $port ? $host . ':' . $port : $host;
    }

    private function enc(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    public function copy(string $srcKey, string $dstKey): array
    {
        $payloadHash = hash('sha256', '');
        $canonicalUri = $this->enc('/' . $this->bucket . '/' . ltrim($dstKey, '/'));
        $wirePath     = $this->enc('/' . ltrim($dstKey, '/'));
        $amzDate      = gmdate('Ymd\THis\Z');
        $dateStamp    = substr($amzDate, 0, 8);

        $hdr = [];
        foreach (['host' => $this->host()] + array_change_key_case($this->headers, CASE_LOWER) as $k => $v) {
            $hdr[strtolower($k)] = $v;
        }
        $hdr['x-amz-content-sha256'] = $payloadHash;
        $hdr['x-amz-copy-source']    = $this->enc('/' . $this->bucket . '/' . ltrim($srcKey, '/'));
        $hdr['x-amz-date']           = $amzDate;

        ksort($hdr);
        $canonicalHeaders = '';
        $signed           = [];
        foreach ($hdr as $k => $v) {
            $canonicalHeaders .= $k . ':' . trim(preg_replace('/\s+/', ' ', (string) $v)) . "\n";
            $signed[] = $k;
        }
        $signedStr = implode(';', $signed);

        $canonicalRequest = implode("\n", ['PUT', $canonicalUri, '', $canonicalHeaders, $signedStr, $payloadHash]);
        $scope            = "{$dateStamp}/{$this->region}/s3/aws4_request";
        $stringToSign     = implode("\n", ['AWS4-HMAC-SHA256', $amzDate, $scope, hash('sha256', $canonicalRequest)]);
        $kDate            = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->sk, true);
        $kRegion          = hash_hmac('sha256', $this->region, $kDate, true);
        $kService         = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning         = hash_hmac('sha256', 'aws4_request', $kService, true);
        $sig              = hash_hmac('sha256', $stringToSign, $kSigning);

        $send = [];
        foreach ($hdr as $k => $v) {
            if ($k === 'host') {
                continue;
            }
            $send[] = $k . ': ' . $v;
        }
        $send[] = 'Authorization: AWS4-HMAC-SHA256 Credential=' . $this->ak . '/' . $scope
            . ', SignedHeaders=' . $signedStr . ', Signature=' . $sig;

        $ch = curl_init($this->endpoint . $wirePath);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $send,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 120,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        return ['code' => $code, 'err' => $err, 'body' => substr((string) $body, 0, 200)];
    }
}

$env    = mediaEnv($ENV_FILE)['MEDIA'] ?? [];
$target = $argv[1] ?? '';
$from   = $argv[2] ?? '88';
$to     = (int) ($argv[3] ?? 135);
$dry    = in_array('--dry', $argv, true);

if ($target === '' || $target === ($env['PROJECT'] ?? 'sugus')) {
    fwrite(STDERR, "usage: php media_copy.php <targetProject> (<idFrom> <idTo> | <pathListFile>) [--dry]\n");
    exit(2);
}

$cfg = [
    'endpoint'   => $env['S3_ENDPOINT'],
    'bucket'     => $env['BUCKET'],
    'region'     => $env['REGION'] ?? 'us-east-1',
    'access_key' => $env['ACCESS_KEY'],
    'secret_key' => $env['SECRET_KEY'],
    'headers'    => ['X-Media-Key' => $env['GATEWAY_KEY'] ?? ''],
];
$cli  = $dry ? null : new CopyClient($cfg);
$src  = $env['PROJECT'] ?? 'sugus';
$stat = ['copied' => 0, 'failed' => 0, 'nofile' => 0];
$fail = [];

/** 真正执行一次复制；$rel 形如 chat_gifts/home-88/rose.mp4 */
function copyOne(?CopyClient $cli, string $src, string $target, string $rel, bool $dry, array &$stat, array &$fail): void
{
    $rel = ltrim($rel, '/');
    $rel = preg_replace('#^storage/#', '', $rel) ?? $rel;
    $s   = 'sec/' . $src . '/' . $rel;
    $d   = 'sec/' . $target . '/' . $rel;
    if ($dry) {
        echo $s, ' -> ', $d, "\n";
        $stat['copied']++;
        return;
    }
    $r = $cli->copy($s, $d);
    if ($r['code'] >= 200 && $r['code'] < 300) {
        $stat['copied']++;
    } else {
        $stat['failed']++;
        $fail[] = $d . ' code=' . $r['code'] . ' ' . ($r['err'] ?: $r['body']);
    }
}

if (is_file($from)) {
    // 清单模式：文件里每行一个站内相对路径，如 chat_gifts/icons2/rose.jpg
    foreach (file($from, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $rel) {
        $rel = trim($rel);
        if ($rel === '' || $rel[0] === '#') {
            continue;
        }
        copyOne($cli, $src, $target, $rel, $dry, $stat, $fail);
    }
} else {
    for ($id = (int) $from; $id <= $to; $id++) {
        $dir = $STORE . '/chat_gifts/home-' . $id;
        if (!is_dir($dir)) {
            continue;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if (!preg_match('/\.(mp4|webm)$/i', $f)) {
                continue;
            }
            copyOne($cli, $src, $target, 'chat_gifts/home-' . $id . '/' . $f, $dry, $stat, $fail);
        }
    }
}
echo json_encode(['target' => $target, 'stat' => $stat, 'fail' => array_slice($fail, 0, 5)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
