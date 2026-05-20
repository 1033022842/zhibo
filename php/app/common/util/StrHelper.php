<?php
declare(strict_types=1);

namespace app\common\util;

final class StrHelper
{
    public static function orderNo(string $prefix = ''): string
    {
        return $prefix . date('YmdHis') . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public static function taskNo(string $prefix = 'T'): string
    {
        return $prefix . date('YmdHis') . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public static function deviceId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
