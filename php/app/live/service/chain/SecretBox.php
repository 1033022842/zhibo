<?php
declare(strict_types=1);

namespace app\live\service\chain;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;

/**
 * 敏感配置（热钱包私钥）的加密存储
 *
 * 后台录入的私钥要落库，但明文私钥一旦随数据库泄露/备份流出，热钱包里的钱就没了。
 * 因此用 AES-256-GCM 加密后再入库，密钥单独放在 .env（[PAYOUT] SECRET），
 * 即「密文在库里、密钥不在库里」——只拿到一份数据库文件解不出来。
 */
final class SecretBox
{
    private const PREFIX = 'v1:';
    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN = 12;
    private const TAG_LEN = 16;

    public static function hasSecret(): bool
    {
        return self::secret() !== '';
    }

    public static function isEncrypted(string $value): bool
    {
        return str_starts_with(trim($value), self::PREFIX);
    }

    public static function encrypt(string $plain): string
    {
        $iv  = random_bytes(self::IV_LEN);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN);
        if ($cipher === false) {
            throw new BusinessException(ResultCode::SERVER_ERROR, '私钥加密失败，请检查 openssl 扩展');
        }
        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /** 解密失败返回 ''（密钥变更/数据损坏），由调用方给出可读提示 */
    public static function decrypt(string $blob): string
    {
        if (!self::isEncrypted($blob)) {
            return '';
        }
        $raw = base64_decode(substr(trim($blob), strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) <= self::IV_LEN + self::TAG_LEN) {
            return '';
        }
        $iv     = substr($raw, 0, self::IV_LEN);
        $tag    = substr($raw, self::IV_LEN, self::TAG_LEN);
        $cipher = substr($raw, self::IV_LEN + self::TAG_LEN);

        $plain = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : $plain;
    }

    private static function secret(): string
    {
        return trim((string) env('payout.secret', ''));
    }

    private static function key(): string
    {
        $secret = self::secret();
        if ($secret === '') {
            throw new BusinessException(
                ResultCode::PARAM_ERROR,
                '未配置 [PAYOUT] SECRET，无法加密保存热钱包私钥；请在 .env 的 [PAYOUT] 段补一行 SECRET = <随机字符串>'
            );
        }
        return hash('sha256', 'payout:' . $secret, true);
    }
}
