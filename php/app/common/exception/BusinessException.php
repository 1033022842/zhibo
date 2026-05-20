<?php
declare(strict_types=1);

namespace app\common\exception;

use app\common\web\ResultCode;
use RuntimeException;

class BusinessException extends RuntimeException
{
    public function __construct(
        public readonly ResultCode $resultCode,
        string $message = '',
        int $httpCode = 200
    ) {
        parent::__construct($message ?: $resultCode->getMsg(), $httpCode);
    }
}
