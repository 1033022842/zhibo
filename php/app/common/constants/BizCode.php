<?php
declare(strict_types=1);

namespace app\common\constants;

final class BizCode
{
    const RECHARGE = 'recharge';
    const GIFT     = 'gift';
    const REFUND   = 'refund';
    const ADJUST   = 'adjust';

    const DIRECTION_INCOME = 1;
    const DIRECTION_EXPENSE = 2;

    const ASSET_DIAMOND = 'diamond';

    const ROOM_MODE_PUBLIC      = 'public';
    const ROOM_MODE_INTERACTION = 'interaction';
    const ROOM_MODE_PRIVILEGE   = 'privilege';

    const TASK_PENDING    = 'pending';
    const TASK_ACCEPTED   = 'accepted';
    const TASK_PROCESSING = 'processing';
    const TASK_COMPLETED  = 'completed';
    const TASK_FAILED     = 'failed';
    const TASK_EXPIRED    = 'expired';

    const TRIGGER_GIFT    = 'gift';
    const TRIGGER_TIMEOUT = 'timeout';
    const TRIGGER_SYSTEM  = 'system';

    const PAY_CHANNEL_USDT_TRC20 = 'usdt_trc20';
}
