<?php
declare(strict_types=1);

namespace app\live\service\chain;

use kornrunner\Keccak;
use kornrunner\Secp256k1;
use Mdanter\Ecc\Curves\CurveFactory;
use Mdanter\Ecc\Curves\SecgCurve;

/**
 * 链上打款底层密码学工具：keccak256 / base58check / secp256k1 签名与地址推导
 *
 * TRON 与 EVM 共用这一套实现，避免为每条链各引一个 SDK。
 * 依赖 kornrunner/secp256k1 + kornrunner/keccak（纯 PHP，需要 ext-gmp）。
 */
final class ChainCrypto
{
    private const BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public static function keccak256(string $bin): string
    {
        return strtolower(Keccak::hash($bin, 256));
    }

    /** 去掉 0x 前缀并转小写 */
    public static function normalizeHex(string $hex): string
    {
        $hex = strtolower(trim($hex));
        return str_starts_with($hex, '0x') ? substr($hex, 2) : $hex;
    }

    /** hex → 二进制（奇数长度左侧补 0） */
    public static function hexToBin(string $hex): string
    {
        $hex = self::normalizeHex($hex);
        if ($hex === '') {
            return '';
        }
        if (strlen($hex) % 2 === 1) {
            $hex = '0' . $hex;
        }
        $bin = hex2bin($hex);
        if ($bin === false) {
            throw new \InvalidArgumentException('非法 hex 字符串');
        }
        return $bin;
    }

    /** 十进制字符串/整数 → 最简大端 hex（0 返回空串，符合 RLP 规则） */
    public static function intToMinimalHex(string|int $value): string
    {
        $dec = ltrim((string) $value, '+');
        if ($dec === '' || $dec === '0' || preg_match('/^0+$/', $dec)) {
            return '';
        }
        $hex = gmp_strval(gmp_init($dec, 10), 16);
        return strlen($hex) % 2 === 1 ? '0' . $hex : $hex;
    }

    /**
     * 十六进制大整数 → RLP 要求的最简字节表示
     *
     * 去掉整字节的前导 0x00；去掉后若长度为奇数再补一个 0 对齐半字节（不是补一个零字节）。
     */
    public static function toMinimalHex(string $hex): string
    {
        $hex = ltrim(self::normalizeHex($hex), '0');
        if ($hex === '') {
            return '';
        }
        return strlen($hex) % 2 === 1 ? '0' . $hex : $hex;
    }

    /** 固定宽度左补 0 的 hex（ABI 编码用） */
    public static function padLeft(string $hex, int $bytes): string
    {
        return str_pad(self::normalizeHex($hex), $bytes * 2, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------ base58

    public static function base58Encode(string $bin): string
    {
        if ($bin === '') {
            return '';
        }
        $num  = gmp_init(bin2hex($bin), 16);
        $base = gmp_init(58);
        $out  = '';
        while (gmp_cmp($num, 0) > 0) {
            [$num, $rem] = gmp_div_qr($num, $base);
            $out = self::BASE58_ALPHABET[gmp_intval($rem)] . $out;
        }
        for ($i = 0, $n = strlen($bin); $i < $n && $bin[$i] === "\x00"; $i++) {
            $out = '1' . $out;
        }
        return $out;
    }

    public static function base58Decode(string $s): string
    {
        if ($s === '') {
            return '';
        }
        $num  = gmp_init(0);
        $base = gmp_init(58);
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $pos = strpos(self::BASE58_ALPHABET, $s[$i]);
            if ($pos === false) {
                throw new \InvalidArgumentException('非法 base58 字符');
            }
            $num = gmp_add(gmp_mul($num, $base), gmp_init($pos));
        }
        $hex = gmp_strval($num, 16);
        $bin = ($hex === '0' || $hex === '') ? '' : (string) hex2bin(strlen($hex) % 2 === 1 ? '0' . $hex : $hex);
        for ($i = 0, $n = strlen($s); $i < $n && $s[$i] === '1'; $i++) {
            $bin = "\x00" . $bin;
        }
        return $bin;
    }

    public static function base58CheckEncode(string $bin): string
    {
        $check = substr(hash('sha256', hash('sha256', $bin, true), true), 0, 4);
        return self::base58Encode($bin . $check);
    }

    /** 校验并返回 payload（去除 4 字节校验和后的二进制）；不合法返回 null */
    public static function base58CheckDecode(string $s): ?string
    {
        try {
            $bin = self::base58Decode($s);
        } catch (\Throwable) {
            return null;
        }
        if (strlen($bin) < 5) {
            return null;
        }
        $payload = substr($bin, 0, -4);
        $expect  = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);
        return hash_equals($expect, substr($bin, -4)) ? $payload : null;
    }

    // ------------------------------------------------------------------ 密钥

    /** 私钥(64位hex) → 未压缩公钥（'04' + X + Y，共 130 位 hex） */
    public static function publicKeyFromPrivate(string $privateKey): string
    {
        $privateKey = self::normalizeHex($privateKey);
        if (!preg_match('/^[0-9a-f]{64}$/', $privateKey)) {
            throw new \InvalidArgumentException('私钥必须是 64 位十六进制字符串');
        }
        $generator = CurveFactory::getGeneratorByName(SecgCurve::NAME_SECP_256K1);
        $key       = $generator->getPrivateKeyFrom(gmp_init($privateKey, 16));
        $point     = $key->getPublicKey()->getPoint();

        return '04'
            . str_pad(gmp_strval($point->getX(), 16), 64, '0', STR_PAD_LEFT)
            . str_pad(gmp_strval($point->getY(), 16), 64, '0', STR_PAD_LEFT);
    }

    /** 未压缩公钥 → EVM 地址（0x 开头 42 位） */
    public static function publicKeyToEvmAddress(string $publicKey): string
    {
        $hash = self::keccak256(self::hexToBin(substr(self::normalizeHex($publicKey), 2)));
        return '0x' . substr($hash, -40);
    }

    /** 未压缩公钥 → TRON 地址（base58check，0x41 前缀） */
    public static function publicKeyToTronAddress(string $publicKey): string
    {
        $hash = self::keccak256(self::hexToBin(substr(self::normalizeHex($publicKey), 2)));
        return self::base58CheckEncode(self::hexToBin('41' . substr($hash, -40)));
    }

    /**
     * secp256k1 签名
     *
     * @return array{r:string, s:string, recid:int, compact:string, tron:string}
     *         compact = r||s（128 位 hex）；tron = r||s||recid（130 位 hex）
     */
    public static function sign(string $hashHex, string $privateKey): array
    {
        $signature = (new Secp256k1())->sign(self::normalizeHex($hashHex), self::normalizeHex($privateKey));

        /** @var \kornrunner\Signature\Signature $signature */
        return [
            'r'       => gmp_strval($signature->getR(), 16),
            's'       => gmp_strval($signature->getS(), 16),
            'recid'   => $signature->getRecoveryParam(),
            'compact' => $signature->toHex(),
            'tron'    => $signature->toHex() . sprintf('%02x', $signature->getRecoveryParam()),
        ];
    }

    /** 校验签名（离线自检用） */
    public static function verify(string $hashHex, string $signatureHex, string $publicKey): bool
    {
        return (new Secp256k1())->verify(
            self::normalizeHex($hashHex),
            self::normalizeHex($signatureHex),
            self::normalizeHex($publicKey)
        );
    }

    /**
     * 金额（人类可读）→ 链上最小单位整数
     *
     * 用 bcmath 做定点换算，避免 float 精度丢失（USDT-TRC20 只有 6 位小数）。
     */
    public static function toUnits(string $amount, int $decimals): string
    {
        $amount = trim($amount);
        if (!preg_match('/^\d+(\.\d+)?$/', $amount)) {
            throw new \InvalidArgumentException('金额格式不正确');
        }
        $units = bcmul($amount, bcpow('10', (string) $decimals), 0);
        if (bccomp($units, '0', 0) <= 0) {
            throw new \InvalidArgumentException('打款金额必须大于 0');
        }
        return $units;
    }

    /** 链上最小单位整数 → 人类可读金额（字符串，去尾零） */
    public static function fromUnits(string|int $units, int $decimals): string
    {
        $dec = bcdiv((string) $units, bcpow('10', (string) $decimals), $decimals);
        return rtrim(rtrim($dec, '0'), '.') ?: '0';
    }
}
