<?php

declare(strict_types=1);

namespace app\common\library;

/**
 * 后台图片/文件字段的安全归一化。
 *
 * 背景：后台表单用的是 baUpload 上传组件，组件在下列情况会把 modelValue 回写成
 * 空串或「字符串数组」：
 *   1) 字段以 undefined 挂载（baTable.getEditData() 先把 form.items 置 {}，行数据异步回填）；
 *   2) 用户在上传组件里清空/删除图片；
 *   3) el-upload 单图 limit=1 被超出后重新 init。
 * 控制器若只写 `$data['x'] ?? $existing['x']`，只能挡住「键不存在」这一种情况：
 * 空串会直接把库里的图清掉，数组经 (string) 强转后变成字符串 'Array' 写进库，
 * 前端 <img src="Array"> 就是「图片裂开」。这里统一兜住这三种形态。
 *
 * 另：站内附件存相对路径的前提是「域名是本请求域名」，否则（CDN / 独立图床 / 其他部署域名）
 * 剥掉域名会让站点侧 404，这里只在同域时才剥。
 */
final class MediaUrl
{
    /**
     * 单值图片/文件字段（封面、视频、头像、海报……）
     *
     * @param mixed $posted   表单提交的值
     * @param mixed $existing 库里的历史值
     */
    public static function file(mixed $posted, mixed $existing = ''): string
    {
        $picked = self::pickScalar($posted);
        if ($picked === null) {
            return self::normalize(self::flatten($existing));
        }

        return self::normalize($picked);
    }

    /**
     * 多图字段（英文逗号分隔）
     */
    public static function files(mixed $posted, mixed $existing = ''): string
    {
        $picked = self::pickScalar($posted, true);
        $raw    = $picked ?? self::flatten($existing);

        $raw   = str_replace(['，', '；', ';', "\r", "\n"], ',', $raw);
        $items = [];
        foreach (explode(',', $raw) as $item) {
            $item = self::normalize(trim($item));
            if ($item !== '' && !self::isPlaceholder($item)) {
                $items[] = $item;
            }
        }

        return implode(',', array_values(array_unique($items)));
    }

    /**
     * 附件地址归一：反斜杠转正斜杠、去空白；同域 /storage 附件转相对路径，其余保持原样
     */
    public static function normalize(string $url): string
    {
        $url = trim(str_replace('\\', '/', $url));
        if ($url === '' || !preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($path === '' || !str_starts_with($path, '/storage/')) {
            return $url;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $self = self::requestHost();

        return ($self !== '' && $host === $self) ? $path : $url;
    }

    /**
     * 从提交值里取第一个有效标量；数组展开后逐个挑，空串 / 'Array' / 'undefined' 视为未提交
     *
     * @param bool $joinAll 多值字段：数组全部保留（逗号连接）
     */
    private static function pickScalar(mixed $posted, bool $joinAll = false): ?string
    {
        if (is_array($posted)) {
            $flat  = self::flatten($posted);
            $parts = [];
            foreach (explode(',', str_replace(['，', '；', ';', "\r", "\n"], ',', $flat)) as $part) {
                $part = trim($part);
                if ($part !== '' && !self::isPlaceholder($part)) {
                    $parts[] = $part;
                }
            }
            if (!$parts) {
                return null;
            }
            if ($joinAll) {
                return implode(',', $parts);
            }

            return $parts[0];
        }

        if ($posted === null) {
            return null;
        }

        $value = trim((string) $posted);
        if ($value === '' || self::isPlaceholder($value)) {
            return null;
        }

        return $value;
    }

    /**
     * 把任意值拍平成字符串（数组用逗号连接，嵌套数组递归）
     */
    private static function flatten(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_array($value)) {
            $parts = [];
            array_walk_recursive($value, static function ($item) use (&$parts): void {
                if (is_scalar($item)) {
                    $parts[] = (string) $item;
                }
            });

            return implode(',', $parts);
        }
        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        return (string) $value;
    }

    /**
     * 采集组件可能回写的占位字符串
     */
    private static function isPlaceholder(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['array', 'undefined', 'null', 'false', '[object object]'], true);
    }

    /**
     * 当前请求域名（CLI / 单测环境下返回空串，此时不做相对路径转换）
     */
    private static function requestHost(): string
    {
        if (!function_exists('request')) {
            return '';
        }

        try {
            $request = request();
            if (!$request || !method_exists($request, 'host')) {
                return '';
            }

            return strtolower((string) $request->host());
        } catch (\Throwable) {
            return '';
        }
    }
}
