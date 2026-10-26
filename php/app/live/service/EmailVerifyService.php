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

    public function sendLoginCode(string $email, string $ip): void
    {
        $redis = Cache::store('redis')->handler();
        $key = 'email_login:' . hash('sha256', strtolower($email));
        $allowed = $redis->eval(
            "if redis.call('EXISTS',KEYS[1]) == 1 then return 0 end
             if tonumber(redis.call('GET',KEYS[2]) or '0') >= 10 then return 0 end
             if tonumber(redis.call('GET',KEYS[3]) or '0') >= 20 then return 0 end
             redis.call('SET',KEYS[1],1,'EX',60)
             redis.call('INCR',KEYS[2]); redis.call('EXPIRE',KEYS[2],86400)
             redis.call('INCR',KEYS[3]); redis.call('EXPIRE',KEYS[3],3600)
             return 1",
            [$key . ':rate', $key . ':daily', 'email_login_ip:' . hash('sha256', $ip)],
            3
        );
        if ((int) $allowed !== 1) {
            throw new BusinessException(ResultCode::EMAIL_SEND_TOO_FREQUENT, 'Please wait before requesting another code.');
        }
        $code = $this->generateCode();
        $redis->setex($key . ':code', self::CODE_TTL, hash('sha256', $code));
        $redis->del($key . ':attempts');
        try {
            $mail = new Email();
            if (!$mail->configured) {
                throw new \RuntimeException('Mail service not configured');
            }
            $mail->isSMTP();
            $mail->addAddress($email);
            $mail->isHTML();
            $mail->setSubject('Your sign-in verification code');
            $mail->Body = "Your sign-in code is <b>{$code}</b>. It expires in 5 minutes. "
                . "Do not share this code. If you did not request it, ignore this email.";
            $mail->AltBody = "Your sign-in code is {$code}. It expires in 5 minutes.";
            if (!$mail->send()) {
                throw new \RuntimeException('Mail delivery failed');
            }
        } catch (\Throwable) {
            $redis->del($key . ':code');
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Unable to send email. Please try again later.');
        }
    }

    public function verifyLoginCode(string $email, string $code): void
    {
        $key = 'email_login:' . hash('sha256', strtolower($email));
        // 登录码独立命名空间，原子核验并销毁，最多尝试五次。
        $result = Cache::store('redis')->handler()->eval(
            "local stored = redis.call('GET',KEYS[1])
             if not stored then return 0 end
             local attempts = redis.call('INCR',KEYS[2])
             redis.call('EXPIRE',KEYS[2],300)
             if attempts > 5 then redis.call('DEL',KEYS[1]); return 0 end
             if stored ~= ARGV[1] then
                 if attempts >= 5 then redis.call('DEL',KEYS[1]) end
                 return 0
             end
             redis.call('DEL',KEYS[1],KEYS[2]); return 1",
            [$key . ':code', $key . ':attempts', hash('sha256', $code)],
            2
        );
        if ((int) $result !== 1) {
            throw new BusinessException(ResultCode::VERIFY_CODE_ERROR, 'Invalid or expired sign-in code.');
        }
    }

    public function sendResetCode(string $email, string $ip): void
    {
        $redis = Cache::store('redis')->handler();
        $key = 'password_reset:' . hash('sha256', strtolower($email));
        // 邮箱与 IP 限流在 Redis 内原子执行，防止并发发码绕过限制。
        $allowed = $redis->eval(
            "if redis.call('EXISTS',KEYS[1]) == 1 then return 0 end
             if tonumber(redis.call('GET',KEYS[2]) or '0') >= 5 then return 0 end
             if tonumber(redis.call('GET',KEYS[3]) or '0') >= 20 then return 0 end
             redis.call('SET',KEYS[1],1,'EX',60)
             redis.call('INCR',KEYS[2]); redis.call('EXPIRE',KEYS[2],86400)
             redis.call('INCR',KEYS[3]); redis.call('EXPIRE',KEYS[3],3600)
             return 1",
            [$key . ':rate', $key . ':daily', 'password_reset_ip:' . hash('sha256', $ip)],
            3
        );
        if ((int) $allowed !== 1) {
            throw new BusinessException(ResultCode::EMAIL_SEND_TOO_FREQUENT, 'Please wait before requesting another code.');
        }

        $code = $this->generateCode();
        $redis->setex($key . ':code', self::CODE_TTL, hash('sha256', $code));
        $redis->del($key . ':attempts');
        try {
            $mail = new Email();
            if (!$mail->configured) {
                throw new \RuntimeException('Mail service not configured');
            }
            $mail->isSMTP();
            $mail->addAddress($email);
            $mail->isHTML();
            $mail->setSubject('Password reset verification code');
            $mail->Body = "Your password reset code is <b>{$code}</b>. It expires in 5 minutes. "
                . "Do not share this code. If you did not request a password reset, ignore this email.";
            $mail->AltBody = "Your password reset code is {$code}. It expires in 5 minutes.";
            if (!$mail->send()) {
                throw new \RuntimeException('Mail delivery failed');
            }
        } catch (\Throwable $e) {
            $redis->del($key . ':code');
            \think\facade\Log::error('[password-reset:mail] ' . $e->getMessage());
            throw new BusinessException(ResultCode::SERVER_ERROR, 'Unable to send email. Please try again later.');
        }
    }

    public function verifyResetCode(string $email, string $code): void
    {
        $key = 'password_reset:' . hash('sha256', strtolower($email));
        // 成功后立即销毁验证码，错误最多尝试五次；注册验证码不能用于找回密码。
        $result = Cache::store('redis')->handler()->eval(
            "local stored = redis.call('GET',KEYS[1])
             if not stored then return 0 end
             local attempts = redis.call('INCR',KEYS[2])
             redis.call('EXPIRE',KEYS[2],300)
             if attempts > 5 then redis.call('DEL',KEYS[1]); return 0 end
             if stored ~= ARGV[1] then
                 if attempts >= 5 then redis.call('DEL',KEYS[1]) end
                 return 0
             end
             redis.call('DEL',KEYS[1],KEYS[2]); return 1",
            [$key . ':code', $key . ':attempts', hash('sha256', $code)],
            2
        );
        if ((int) $result !== 1) {
            throw new BusinessException(ResultCode::VERIFY_CODE_ERROR, 'Invalid or expired code. Please request a new code.');
        }
    }

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
