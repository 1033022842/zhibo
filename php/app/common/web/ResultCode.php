<?php
declare(strict_types=1);

namespace app\common\web;

enum ResultCode: string
{
    case SUCCESS          = '00000';
    case FAIL             = '99999';
    case PARAM_ERROR      = 'A0001';
    case RECORD_NOT_FOUND = 'A0002';
    case ACCESS_TOKEN_INVALID = 'A0100';
    case ACCESS_TOKEN_EXPIRED = 'A0101';
    case NO_PERMISSION    = 'A0300';
    case USER_NOT_FOUND   = 'B0100';
    case USER_DISABLED    = 'B0101';
    case AUTH_FAILED      = 'B0102';
    case BALANCE_NOT_ENOUGH = 'C0100';
    case WALLET_FROZEN    = 'C0101';
    case ORDER_NOT_FOUND  = 'C0200';
    case ORDER_EXPIRED    = 'C0201';
    case PAY_CALLBACK_FAIL = 'C0300';
    case GIFT_NOT_FOUND   = 'D0100';
    case ROOM_NOT_FOUND   = 'E0100';
    case ROOM_OFFLINE     = 'E0101';
    case ROOM_SWITCHING   = 'E0102';
    case TASK_NOT_FOUND   = 'F0100';
    case TASK_EXPIRED     = 'F0101';
    case TASK_ALREADY_DONE = 'F0102';
    case RATE_LIMIT       = 'G0100';
    case SERVER_ERROR     = 'H0001';

    public function getMsg(): string
    {
        return match($this) {
            self::SUCCESS => '成功',
            self::FAIL => '失败',
            self::PARAM_ERROR => '参数错误',
            self::RECORD_NOT_FOUND => '记录不存在',
            self::ACCESS_TOKEN_INVALID => 'Token无效',
            self::ACCESS_TOKEN_EXPIRED => 'Token已过期',
            self::NO_PERMISSION => '无权限',
            self::USER_NOT_FOUND => '用户不存在',
            self::USER_DISABLED => '用户已禁用',
            self::AUTH_FAILED => '认证失败',
            self::BALANCE_NOT_ENOUGH => '钻石余额不足',
            self::WALLET_FROZEN => '钱包已冻结',
            self::ORDER_NOT_FOUND => '订单不存在',
            self::ORDER_EXPIRED => '订单已过期',
            self::PAY_CALLBACK_FAIL => '支付回调验签失败',
            self::GIFT_NOT_FOUND => '礼物不存在',
            self::ROOM_NOT_FOUND => '房间不存在',
            self::ROOM_OFFLINE => '房间已下线',
            self::ROOM_SWITCHING => '房间正在切换中',
            self::TASK_NOT_FOUND => '任务不存在',
            self::TASK_EXPIRED => '任务已过期',
            self::TASK_ALREADY_DONE => '任务已完成',
            self::RATE_LIMIT => '请求过于频繁',
            self::SERVER_ERROR => '服务器内部错误',
        };
    }
}
