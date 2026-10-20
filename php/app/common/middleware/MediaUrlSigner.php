<?php
declare(strict_types=1);

namespace app\common\middleware;

use Closure;
use think\Request;
use think\Response;
use app\common\library\media\Media;

/**
 * 输出层素材地址改写
 *
 * 把响应里所有「素材类字段」的 /storage/xxx 换成存储服务器的签名地址，
 * 这样全部历史数据、后台表单、前端接口都不用改字段，只在这里统一出签。
 *
 * - JSON：按字段名（*_url / cover / poster / video / image / icon / avatar ...）递归匹配
 * - HTML：改写 src/href/poster 等属性里的 /storage/ 路径（后台 float 页面预览图）
 *
 * 未启用独立存储（Media::enabled()=false）时直接放行，零影响。
 */
class MediaUrlSigner
{
    /** 字段名白名单（大小写不敏感，子串匹配） */
    private const KEY_PATTERN = '/(url|cover|poster|video|image|avatar|icon|thumb|banner|media|file|photo|picture|src|background|persona)/i';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!Media::enabled()) {
            return $response;
        }

        $type = strtolower((string) $response->getHeader('Content-Type'));
        if ($type === '') {
            return $response;
        }

        try {
            if (str_contains($type, 'application/json')) {
                $this->signJson($response);
                // 响应里带有时效签名地址，浏览器/代理不能再缓存这份 JSON：
                // 否则过期后拿到的还是旧签名，图片/视频会 410 裂开（前端已有多处本地缓存，双保险）
                $response->header(['Cache-Control' => 'no-store, no-cache, must-revalidate']);
            } elseif (str_contains($type, 'text/html')) {
                $this->signHtml($response);
                $response->header(['Cache-Control' => 'no-store, no-cache, must-revalidate']);
            }
        } catch (\Throwable) {
            // 改写失败不能影响正常响应
        }

        return $response;
    }

    private function signJson(Response $response): void
    {
        $raw = $response->getContent();
        if ($raw === '') {
            return;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return;
        }
        $changed = false;
        $data    = $this->walk($data, '', $changed);
        if ($changed) {
            $response->content(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    private function walk(mixed $node, string $key, bool &$changed): mixed
    {
        if (is_array($node)) {
            $isList = array_is_list($node);
            foreach ($node as $k => $v) {
                // 列表元素沿用父级字段名（images: ["/storage/...", ...]）
                $childKey = $isList ? $key : (string) $k;
                $node[$k] = $this->walk($v, $childKey, $changed);
            }
            return $node;
        }

        if (!is_string($node) || $node === '') {
            return $node;
        }
        if (!preg_match(self::KEY_PATTERN, $key)) {
            return $node;
        }
        if (!Media::isStoragePath($node)) {
            return $node;
        }
        $signed = Media::url($node);
        if ($signed !== $node) {
            $changed = true;
        }
        return $signed;
    }

    private function signHtml(Response $response): void
    {
        $html = $response->getContent();
        if ($html === '' || !str_contains($html, '/storage/')) {
            return;
        }

        $changed = false;
        $html = preg_replace_callback(
            '#(["\'])((?:https?:)?//[^"\']+?/storage/[^"\']+|/?(?:public/)?storage/[^"\']+)\1#i',
            function (array $m) use (&$changed): string {
                $q      = $m[1];
                $target = $m[2];
                if (!Media::isStoragePath($target)) {
                    return $m[0];
                }
                $signed = Media::url($target);
                if ($signed === $target) {
                    return $m[0];
                }
                $changed = true;
                return $q . htmlspecialchars($signed, ENT_QUOTES) . $q;
            },
            $html
        );

        if ($changed && is_string($html)) {
            $response->content($html);
        }
    }
}
