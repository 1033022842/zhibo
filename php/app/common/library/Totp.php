<?php
declare(strict_types=1);

namespace app\common\library;

/**
 * TOTP（RFC 6238，Google Authenticator 兼容）纯 PHP 实现
 *
 * 不依赖任何第三方库：base32 编解码自实现，HMAC 用 PHP 自带的 hash_hmac('sha1')。
 * 用于后台管理员登录二次验证（2FA）。
 */
final class Totp
{
    /** Base32 字母表（RFC 4648，Google Authenticator 用大写无填充） */
    private const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * 生成随机密钥（base32 字符串，默认 20 字节 = 160bit，32 位字符）
     */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /**
     * 校验动态码
     *
     * @param string $secret  base32 密钥
     * @param string $code    用户输入的 6 位码
     * @param int    $window  允许的时间窗口（前后各 N 个 30s 周期），默认 ±1
     * @param int    $digits  位数，默认 6
     */
    public static function verify(string $secret, string $code, int $window = 1, int $digits = 6): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if ($secret === '' || strlen($code) !== $digits) {
            return false;
        }
        $counter = (int) floor(time() / 30);
        // 常量时间比较，避免时序侧信道
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::at($secret, $counter + $i, $digits), $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 计算指定时间步长的动态码
     */
    public static function at(string $secret, int $counter, int $digits = 6): string
    {
        $key  = self::base32Decode($secret);
        // 8 字节大端计数器
        $binCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $part = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);
        $otp = $part % (10 ** $digits);
        return str_pad((string) $otp, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * 生成 otpauth:// 二维码 URI（前端本地渲染成二维码，不经过任何第三方）
     */
    public static function provisioningUri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer . ':' . $account);
        $query = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => 6,
            'period'    => 30,
        ]);
        return 'otpauth://totp/' . $label . '?' . $query;
    }

    /**
     * 生成一次性恢复码（明文返回，调用方负责哈希后入库）
     * 形如 4F7K-9Q2M，共 8 个
     */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // 去掉易混字符
        for ($i = 0; $i < $count; $i++) {
            $s = '';
            for ($j = 0; $j < 8; $j++) {
                $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $codes[] = substr($s, 0, 4) . '-' . substr($s, 4, 4);
        }
        return $codes;
    }

    /* ---------------- base32 ---------------- */

    public static function base32Encode(string $data): string
    {
        $out = '';
        $bits = 0;
        $buf  = 0;
        $len  = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $buf = ($buf << 8) | ord($data[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $out .= self::B32[($buf >> $bits) & 0x1F];
            }
        }
        if ($bits > 0) {
            $out .= self::B32[($buf << (5 - $bits)) & 0x1F];
        }
        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32) ?? '');
        $out = '';
        $bits = 0;
        $buf  = 0;
        $len  = strlen($b32);
        for ($i = 0; $i < $len; $i++) {
            $val = strpos(self::B32, $b32[$i]);
            if ($val === false) {
                continue;
            }
            $buf = ($buf << 5) | $val;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($buf >> $bits) & 0xFF);
            }
        }
        return $out;
    }
}
