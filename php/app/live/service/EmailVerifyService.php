<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\library\Email;
use app\common\web\ResultCode;
use think\facade\Cache;

final class EmailVerifyService
{
    private const CODE_TTL = 300;
    private const RATE_LIMIT_TTL = 60;
    private const DAILY_LIMIT = 5;
    private const CODE_LENGTH = 6;

    public function sendCode(string $email): void
    {
        $this->checkRateLimit($email);
        $this->checkDailyLimit($email);

        $code = $this->generateCode();

        Cache::store('redis')->set($this->codeKey($email), $code, self::CODE_TTL);
        Cache::store('redis')->set($this->rateKey($email), '1', self::RATE_LIMIT_TTL);

        $this->incrDailyCount($email);

        $this->sendEmail($email, $code);
    }

    public function verify(string $email, string $code): bool
    {
        $stored = Cache::store('redis')->get($this->codeKey($email));
        if (!$stored) {
            throw new BusinessException(ResultCode::VERIFY_CODE_EXPIRED);
        }
        if ((string)$stored !== (string)$code) {
            throw new BusinessException(ResultCode::VERIFY_CODE_ERROR);
        }
        Cache::store('redis')->delete($this->codeKey($email));
        return true;
    }

    private function checkRateLimit(string $email): void
    {
        if (Cache::store('redis')->get($this->rateKey($email))) {
            throw new BusinessException(ResultCode::EMAIL_SEND_TOO_FREQUENT);
        }
    }

    private function checkDailyLimit(string $email): void
    {
        $count = (int)Cache::store('redis')->get($this->dailyKey($email));
        if ($count >= self::DAILY_LIMIT) {
            throw new BusinessException(ResultCode::EMAIL_SEND_DAILY_LIMIT);
        }
    }

    private function incrDailyCount(string $email): void
    {
        $key = $this->dailyKey($email);
        $ttl = strtotime('tomorrow') - time();
        $count = (int)Cache::store('redis')->get($key);
        Cache::store('redis')->set($key, $count + 1, $ttl);
    }

    private function sendEmail(string $email, string $code): void
    {
        $mail = new Email();
        if (!$mail->configured) {
            throw new BusinessException(ResultCode::SERVER_ERROR, '邮件服务未配置');
        }

        try {
            $mail->isSMTP();
            $mail->addAddress($email);
            $mail->isHTML();
            $mail->setSubject('直播平台 - 注册验证码');
            $mail->Body = "您的验证码是：<b>{$code}</b>，有效期5分钟，请勿泄露给他人。";
            $mail->send();
        } catch (\PHPMailer\PHPMailer\Exception) {
            throw new BusinessException(ResultCode::SERVER_ERROR, '验证码邮件发送失败');
        }
    }

    private function generateCode(): string
    {
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= (string)random_int(0, 9);
        }
        return $code;
    }

    private function codeKey(string $email): string
    {
        return 'email_code:' . md5($email);
    }

    private function rateKey(string $email): string
    {
        return 'email_rate:' . md5($email);
    }

    private function dailyKey(string $email): string
    {
        return 'email_daily:' . md5($email) . ':' . date('Ymd');
    }
}
