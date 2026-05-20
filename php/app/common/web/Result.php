<?php
declare(strict_types=1);

namespace app\common\web;

final class Result
{
    public static function success(mixed $data = null, string $msg = ''): array
    {
        return [
            'code' => ResultCode::SUCCESS->value,
            'msg'  => $msg ?: ResultCode::SUCCESS->getMsg(),
            'data' => $data,
        ];
    }

    public static function page(array $list, int $total): array
    {
        return [
            'code' => ResultCode::SUCCESS->value,
            'msg'  => ResultCode::SUCCESS->getMsg(),
            'data' => [
                'list'  => $list,
                'total' => $total,
            ],
        ];
    }

    public static function cursor(array $list, ?string $cursor, bool $hasMore): array
    {
        return [
            'code' => ResultCode::SUCCESS->value,
            'msg'  => ResultCode::SUCCESS->getMsg(),
            'data' => [
                'list'     => $list,
                'cursor'   => $cursor,
                'has_more' => $hasMore,
            ],
        ];
    }

    public static function fail(ResultCode $code, string $msg = ''): array
    {
        return [
            'code' => $code->value,
            'msg'  => $msg ?: $code->getMsg(),
            'data' => null,
        ];
    }
}
