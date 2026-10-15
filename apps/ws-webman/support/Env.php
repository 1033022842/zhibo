<?php
declare(strict_types=1);

namespace support;

/**
 * 极简 .env 载入器。
 *
 * 背景：webman 读取 .env 走的是 vlucas/phpdotenv，而本部署并没有安装该依赖
 * （vendor 里没有 Dotenv，composer.json 也没声明），于是
 * webman-framework 里 `class_exists('Dotenv\Dotenv')` 恒为 false，.env 从来没被读过：
 *
 *   getenv('DB_NAME')      => false → 退化成 config/database.php 的硬编码 'zhibo'
 *   getenv('REDIS_PASSWORD') => false → 退化成 ''
 *
 * Sugus 的 .env 恰好和 fallback 一致（zhibo/zhibo/12345678）所以没暴露；
 * HeyGirl 的库是 live_platform/root/root，于是 WS 服务里所有写库操作都在静默失败。
 *
 * 这里用 20 行把这个缺口补上，不引入新依赖。
 * 已安装正牌 dotenv 时直接让路，避免重复解析。
 */
final class Env
{
    public static function load(string $dir): void
    {
        if (class_exists('Dotenv\Dotenv')) {
            return;
        }

        $file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            if ($key === '' || getenv($key) !== false) {
                continue; // 已有环境变量优先，不覆盖
            }

            $len = strlen($val);
            if ($len >= 2 && ($val[0] === '"' || $val[0] === "'") && $val[$len - 1] === $val[0]) {
                $val = substr($val, 1, -1);
            }

            putenv("{$key}={$val}");
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
}
