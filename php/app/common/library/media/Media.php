<?php
declare(strict_types=1);

namespace app\common\library\media;

/**
 * 素材存储门面
 *
 * 数据库里依旧只存站内相对路径 /storage/xxx（保持历史数据零改动），
 * 对外输出时由 url() 统一转成对象存储 + 签名 URL：
 *
 *   /storage/chat_media/home-1/1.jpg
 *     → https://<endpoint>/sec/<project>/chat_media/home-1/1.jpg?e=<exp>&s=<sig>
 *
 * 公开素材（封面/头图/礼物动效等，见 public_prefixes）走 /pub/，可被 CDN 长缓存；
 * 其余一律 /sec/，由存储端 nginx secure_link 校验签名。
 */
final class Media
{
    private static ?S3Client $s3 = null;
    private static array $cfg = [];

    public static function config(): array
    {
        if (self::$cfg) {
            return self::$cfg;
        }
        $cfg = (array) config('media');
        self::$cfg = $cfg;
        return $cfg;
    }

    public static function enabled(): bool
    {
        $cfg = self::config();
        return !empty($cfg['enabled'])
            && (string) ($cfg['endpoint'] ?? '') !== ''
            && (string) ($cfg['sign_secret'] ?? '') !== '';
    }

    /** 对象存储是否接入写路径（上传落 S3） */
    public static function storageEnabled(): bool
    {
        return !empty(self::config()['storage_enabled']) && self::s3Configured();
    }

    /** S3 连接参数是否齐全（迁移命令等运维工具用它，不看业务开关） */
    public static function s3Configured(): bool
    {
        $cfg = self::config();
        return (string) ($cfg['s3_endpoint'] ?? '') !== ''
            && (string) ($cfg['bucket'] ?? '') !== '';
    }

    public static function s3(): S3Client
    {
        if (self::$s3 === null) {
            $cfg = self::config();
            self::$s3 = new S3Client([
                'endpoint'   => $cfg['s3_endpoint'] ?? '',
                'bucket'     => $cfg['bucket'] ?? '',
                'region'     => $cfg['region'] ?? 'us-east-1',
                'access_key' => $cfg['access_key'] ?? '',
                'secret_key' => $cfg['secret_key'] ?? '',
                'headers'    => $cfg['s3_headers'] ?? [],
                'verify'     => $cfg['s3_verify'] ?? true,
                'path_mode'  => $cfg['s3_path_mode'] ?? 'key',
            ]);
        }
        return self::$s3;
    }

    /**
     * /storage/xxx → 对象 key（带 项目前缀 + 公开/私有两级）
     */
    public static function keyFor(string $path): string
    {
        $rel = self::relPath($path);
        $cfg = self::config();
        $project = trim((string) ($cfg['project'] ?? 'sugus'), '/');
        $tier    = self::isPublic($rel) ? 'pub' : 'sec';
        return $tier . '/' . $project . '/' . $rel;
    }

    /** 是否公开素材（首层目录命中白名单） */
    public static function isPublic(string $rel): bool
    {
        $rel = ltrim($rel, '/');
        $first = strtolower((string) (explode('/', $rel)[0] ?? ''));
        $prefixes = (array) (self::config()['public_prefixes'] ?? []);
        return $first !== '' && in_array($first, array_map('strtolower', $prefixes), true);
    }

    /** 去掉 /storage 或 /sec|<pub>/<project> 前缀，返回纯相对路径 */
    public static function relPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('/[?#].*$/', '', $path) ?? $path;
        $path = preg_replace('#^https?://[^/]+/#i', '', $path) ?? $path;
        $path = preg_replace('#^/?(?:public/)?storage/#i', '', $path) ?? $path;
        $project = preg_quote(trim((string) (self::config()['project'] ?? 'sugus'), '/'), '#');
        $path = preg_replace('#^/?(?:sec|pub)/' . $project . '/#i', '', $path) ?? $path;
        return ltrim($path, '/');
    }

    /**
     * 站内路径 → 对外的（签名）URL
     * 已带签名参数的 URL 原样返回，避免重复签名。
     */
    public static function url(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        // 外链（非本站 storage）保持原样
        if (!self::isStoragePath($path)) {
            return $path;
        }
        if (!self::enabled()) {
            return $path;
        }
        // 已经是存储端的地址（签名过的 /sec|/pub）直接返回，避免二次签名
        if (self::isSignedUrl($path)) {
            return $path;
        }
        // 历史库里的地址可能带无关查询串，去掉后再签
        $path = preg_replace('/[?#].*$/', '', $path) ?? $path;

        $key  = self::keyFor($path);
        $base = rtrim((string) self::config()['endpoint'], '/');

        // 公开素材不加签名（/pub/ 本来就不校验，且未签名更适合 CDN 长缓存）
        if (self::isPublic(self::relPath($path))) {
            return $base . '/' . self::encodePath($key);
        }

        $ttl    = (int) (self::config()['sign_ttl'] ?? 21600);
        $bucket = max(60, (int) (self::config()['sign_bucket_sec'] ?? 3600));

        // 过期时间对齐到时间桶，同一小时内所有请求拿到同一个 URL（利于浏览器/CDN 缓存）
        $now  = time();
        $exp  = (int) (ceil(($now + 1) / $bucket) * $bucket) + $ttl;
        $sig  = self::sign('/' . $key, $exp);

        return $base . '/' . self::encodePath($key) . '?e=' . $exp . '&s=' . $sig;
    }

    /** 是否已经是存储端地址（/sec|/pub/<project>/...） */
    private static function isSignedUrl(string $url): bool
    {
        $project = preg_quote(trim((string) (self::config()['project'] ?? 'sugus'), '/'), '#');
        return (bool) preg_match('#^(?:https?:)?//[^/]+/(?:sec|pub)/' . $project . '/#i', $url)
            || (bool) preg_match('#^/(?:sec|pub)/' . $project . '/#i', $url);
    }

    /** 生成 nginx secure_link 签名：base64url(md5("$exp$uri $secret")) */
    public static function sign(string $uri, int $exp): string
    {
        $secret = (string) (self::config()['sign_secret'] ?? '');
        $raw    = md5($exp . $uri . ' ' . $secret, true);
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * 签名 URL（或任意带 /storage/ 的地址）→ 站内规范路径 /storage/xxx
     * 用于把后台表单回传的临时签名地址还原成可入库的稳定路径。
     */
    public static function toStoragePath(string $url): string
    {
        $url = trim($url);
        if ($url === '' || !self::isStoragePath($url)) {
            return $url;
        }
        $rel = self::relPath($url);
        // 去掉残留的查询串（签名参数）
        $rel = preg_replace('/[?#].*$/', '', $rel) ?? $rel;
        return '/storage/' . ltrim($rel, '/');
    }

    /** 判定是否本站存储路径（/storage/xxx、/public/storage/xxx、带域名的同款路径、已签名地址） */
    public static function isStoragePath(string $url): bool
    {
        if (str_starts_with($url, 'data:') || str_starts_with($url, 'blob:')) {
            return false;
        }
        if (preg_match('#^(?:https?:)?//[^/]+/(?:public/)?storage/#i', $url)) {
            return true;
        }
        if (preg_match('#^/?(?:public/)?storage/#i', $url)) {
            return true;
        }
        $project = preg_quote(trim((string) (self::config()['project'] ?? 'sugus'), '/'), '#');
        return (bool) preg_match('#^(?:https?:)?//[^/]+/(?:sec|pub)/' . $project . '/#i', $url)
            || (bool) preg_match('#^/(?:sec|pub)/' . $project . '/#i', $url);
    }

    public static function encodePath(string $key): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $key)));
    }

    // ────────────────────────── 写路径 ──────────────────────────

    public static function putFile(string $localPath, string $storagePath, string $contentType = ''): bool
    {
        $contentType = $contentType !== '' ? $contentType : self::guessMime($localPath);
        return self::s3()->putFile($localPath, self::keyFor($storagePath), $contentType);
    }

    public static function putContents(string $body, string $storagePath, string $contentType = 'application/octet-stream'): bool
    {
        return self::s3()->putContents($body, self::keyFor($storagePath), $contentType);
    }

    public static function exists(string $storagePath): bool
    {
        return self::s3()->exists(self::keyFor($storagePath));
    }

    public static function delete(string $storagePath): bool
    {
        return self::s3()->delete(self::keyFor($storagePath));
    }

    /** 列出某个目录下的对象 key，返回相对该目录的文件名 */
    public static function listDir(string $storageDir): array
    {
        $prefix = self::keyFor(rtrim($storageDir, '/') . '/');
        $keys   = self::s3()->list($prefix);
        $out    = [];
        foreach ($keys as $k) {
            $name = substr($k, strlen($prefix));
            if ($name !== '' && !str_contains($name, '/')) {
                $out[] = $name;
            }
        }
        sort($out);
        return $out;
    }

    public static function guessMime(string $file): string
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            'mp4'         => 'video/mp4',
            'webm'        => 'video/webm',
            'mp3'         => 'audio/mpeg',
            'wav'         => 'audio/wav',
            'svg'         => 'image/svg+xml',
            default       => 'application/octet-stream',
        };
    }
}
