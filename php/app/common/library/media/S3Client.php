<?php
declare(strict_types=1);

namespace app\common\library\media;

/**
 * 极简 S3 客户端（AWS Signature V4），只依赖 curl + hash
 *
 * 不引入 aws-sdk-php：服务器 vendor 不走网络安装，且本项目只用到
 * PUT / GET / HEAD / DELETE / ListObjectsV2 五个操作。
 *
 * 约定：path-style  —  http://endpoint/<bucket>/<key>
 * 大文件 PUT 用 UNSIGNED-PAYLOAD 流式上传，避免整块读进内存。
 */
final class S3Client
{
    private string $endpoint;
    private string $bucket;
    private string $region;
    private string $accessKey;
    private string $secretKey;

    /** 自定义请求头（如通过 nginx 网关访问时带上的校验头） */
    private array $headers = [];

    /** TLS 校验：true/false，或 CA bundle 路径 */
    private bool|string $verify;

    /**
     * 线上路径模式：
     *   bucket = 标准 path-style（endpoint + /bucket/key，直连 MinIO）
     *   key    = 网关模式（endpoint + /key，nginx 补 /bucket 前缀后转发，签名仍按 /bucket/key）
     */
    private string $pathMode;

    public function __construct(array $cfg)
    {
        $this->endpoint  = rtrim((string) ($cfg['endpoint'] ?? ''), '/');
        $this->bucket    = (string) ($cfg['bucket'] ?? '');
        $this->region    = (string) ($cfg['region'] ?? 'us-east-1');
        $this->accessKey = (string) ($cfg['access_key'] ?? '');
        $this->secretKey = (string) ($cfg['secret_key'] ?? '');
        $this->verify    = $cfg['verify'] ?? true;
        $this->pathMode  = (string) ($cfg['path_mode'] ?? 'bucket');

        foreach ((array) ($cfg['headers'] ?? []) as $k => $v) {
            $this->headers[(string) $k] = (string) $v;
        }
    }

    public function bucket(): string
    {
        return $this->bucket;
    }

    /** 上传本地文件（流式，UNSIGNED-PAYLOAD） */
    public function putFile(string $localPath, string $key, string $contentType = 'application/octet-stream'): bool
    {
        if (!is_file($localPath)) {
            throw new \RuntimeException('local file not found: ' . $localPath);
        }
        $fh = fopen($localPath, 'rb');
        if (!$fh) {
            throw new \RuntimeException('cannot open local file: ' . $localPath);
        }
        try {
            $resp = $this->request('PUT', $key, [
                'Content-Type' => $contentType,
                'Content-Length' => (string) filesize($localPath),
            ], null, $fh, 'UNSIGNED-PAYLOAD');
            return $resp['code'] >= 200 && $resp['code'] < 300;
        } finally {
            fclose($fh);
        }
    }

    /** 上传内存内容（小文件） */
    public function putContents(string $body, string $key, string $contentType = 'application/octet-stream'): bool
    {
        $resp = $this->request('PUT', $key, [
            'Content-Type'   => $contentType,
            'Content-Length' => (string) strlen($body),
        ], $body);
        return $resp['code'] >= 200 && $resp['code'] < 300;
    }

    public function exists(string $key): bool
    {
        $resp = $this->request('HEAD', $key);
        return $resp['code'] >= 200 && $resp['code'] < 300;
    }

    public function size(string $key): int
    {
        $resp = $this->request('HEAD', $key);
        if ($resp['code'] < 200 || $resp['code'] >= 300) {
            return -1;
        }
        return (int) ($resp['headers']['content-length'] ?? -1);
    }

    public function delete(string $key): bool
    {
        $resp = $this->request('DELETE', $key);
        return $resp['code'] >= 200 && $resp['code'] < 300;
    }

    /**
     * 列出目录（ListObjectsV2，自动翻页）
     * @return array<int,string> 相对 prefix 的 key 列表
     */
    public function list(string $prefix, int $limit = 0): array
    {
        $out   = [];
        $token = '';
        do {
            $query = ['list-type' => '2', 'prefix' => $prefix];
            if ($token !== '') {
                $query['continuation-token'] = $token;
            }
            $resp = $this->request('GET', '', [], null, null, null, $query);
            if ($resp['code'] < 200 || $resp['code'] >= 300) {
                break;
            }
            $xml = @simplexml_load_string($resp['body']);
            if ($xml === false) {
                break;
            }
            foreach ($xml->Contents as $c) {
                $out[] = (string) $c->Key;
                if ($limit > 0 && count($out) >= $limit) {
                    return $out;
                }
            }
            $truncated = (string) ($xml->IsTruncated ?? 'false') === 'true';
            $token     = $truncated ? (string) ($xml->NextContinuationToken ?? '') : '';
        } while ($truncated && $token !== '');

        return $out;
    }

    /**
     * @param array<string,string> $query 原始（未编码）查询参数
     * @return array{code:int,headers:array<string,string>,body:string}
     */
    private function request(
        string $method,
        string $key,
        array $headers = [],
        ?string $body = null,
        $stream = null,
        ?string $payloadHash = null,
        array $query = []
    ): array {
        // bucket 根路径必须带结尾斜杠（网关 proxy_pass 补的是 "/sugus-media/"）
        $path = '/' . $this->bucket . '/' . ltrim($key, '/');
        // canonical_uri 用「未经编码」的原生路径再按 S3 规则逐段编码
        $canonicalUri = $this->canonicalUri($path);

        // 线上请求路径：默认与签名路径一致；网关模式下由 nginx 补 bucket 前缀
        $wirePath = $this->pathMode === 'key'
            ? $this->canonicalUri('/' . $key)
            : $canonicalUri;

        $queryParts = [];
        foreach ($query as $k => $v) {
            $queryParts[] = [rawurlencode((string) $k), rawurlencode((string) $v)];
        }
        usort($queryParts, fn($a, $b) => strcmp($a[0], $b[0]));
        $canonicalQuery = implode('&', array_map(fn($p) => $p[0] . '=' . $p[1], $queryParts));

        if ($payloadHash === null) {
            $payloadHash = hash('sha256', (string) $body);
        }

        $amzDate  = gmdate('Ymd\THis\Z');
        $dateStamp = substr($amzDate, 0, 8);

        // 必须先统一成小写再排序：SignedHeaders 要求按小写字典序（X- 在 C- 之后，混排会签错）
        $allHeaders = [];
        foreach (['host' => $this->hostHeader()] + $this->headers + $headers as $k => $v) {
            $allHeaders[strtolower((string) $k)] = $v;
        }
        $allHeaders['x-amz-content-sha256'] = $payloadHash;
        $allHeaders['x-amz-date']           = $amzDate;

        ksort($allHeaders);
        $canonicalHeaders = '';
        $signedHeaders    = [];
        foreach ($allHeaders as $k => $v) {
            $canonicalHeaders .= $k . ':' . trim(preg_replace('/\s+/', ' ', (string) $v)) . "\n";
            $signedHeaders[]   = $k;
        }
        $signedHeadersStr = implode(';', $signedHeaders);

        $canonicalRequest = implode("\n", [
            $method,
            $canonicalUri,
            $canonicalQuery,
            $canonicalHeaders,
            $signedHeadersStr,
            $payloadHash,
        ]);

        $scope       = "{$dateStamp}/{$this->region}/s3/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $kDate    = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $auth = "AWS4-HMAC-SHA256 Credential={$this->accessKey}/{$scope}, SignedHeaders={$signedHeadersStr}, Signature={$signature}";

        // 发送用真实 URL（key 逐段编码后的路径）
        $url = $this->endpoint . $wirePath . ($canonicalQuery !== '' ? '?' . $canonicalQuery : '');

        $sendHeaders = [];
        foreach ($allHeaders as $k => $v) {
            if (strtolower((string) $k) === 'host') continue;
            $sendHeaders[] = $k . ': ' . $v;
        }
        $sendHeaders[] = 'Authorization: ' . $auth;

        $respHeaders = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $sendHeaders,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 600,
            CURLOPT_SSL_VERIFYPEER => $this->verify !== false,
            CURLOPT_SSL_VERIFYHOST => $this->verify !== false ? 2 : 0,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$respHeaders) {
                $len  = strlen($line);
                $trim = trim($line);
                if ($trim === '' || str_starts_with($trim, 'HTTP/')) return $len;
                $pos = strpos($trim, ':');
                if ($pos !== false) {
                    $respHeaders[strtolower(substr($trim, 0, $pos))] = trim(substr($trim, $pos + 1));
                }
                return $len;
            },
        ]);

        if ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }
        if (is_string($this->verify)) {
            curl_setopt($ch, CURLOPT_CAINFO, $this->verify);
        }
        if ($stream !== null) {
            curl_setopt($ch, CURLOPT_UPLOAD, true);
            curl_setopt($ch, CURLOPT_INFILE, $stream);
            curl_setopt($ch, CURLOPT_INFILESIZE, (int) ($headers['Content-Length'] ?? 0));
        } elseif ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $out  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($out === false && $err !== '') {
            throw new \RuntimeException('S3 request failed: ' . $err);
        }

        return [
            'code'    => $code,
            'headers' => $respHeaders,
            'body'    => is_string($out) ? $out : '',
        ];
    }

    /** host 头：endpoint 里可能带端口 */
    private function hostHeader(): string
    {
        $host = parse_url($this->endpoint, PHP_URL_HOST) ?: '';
        $port = parse_url($this->endpoint, PHP_URL_PORT);
        if ($port) {
            $host .= ':' . $port;
        }
        return $host;
    }

    /** S3 canonical URI：/ 保留，其余段 rawurlencode 一次 */
    private function canonicalUri(string $path): string
    {
        $segments = explode('/', $path);
        $encoded  = array_map(fn($s) => rawurlencode($s), $segments);
        return implode('/', $encoded);
    }
}
