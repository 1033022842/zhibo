<?php
declare(strict_types=1);

namespace app\common\enums;

enum TaskStatus: string
{
    case PENDING    = 'pending';
    case ACCEPTED   = 'accepted';
    case PROCESSING = 'processing';
    case COMPLETED  = 'completed';
    case FAILED     = 'failed';
    case EXPIRED    = 'expired';
}
