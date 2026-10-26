<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\library\Email;
use app\common\web\ResultCode;
use think\facade\Db;

final class RegistrationSettingService
{
    public const EMAIL_VERIFY_KEY = 'ai_register_email_verify';

    public static function emailVerifyRequired(): bool
    {
        try {
            return (string) Db::connect('live_mysql')->table('lp_platform_config')
                ->where('key', self::EMAIL_VERIFY_KEY)->value('value') === '1';
        } catch (\Throwable) {
            return false;
        }
    }

    public static function mailConfigured(): bool
    {
        try {
            return (new Email())->configured;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function saveEmailVerify(bool $enabled): void
    {
        if ($enabled && !self::mailConfigured()) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '请先在系统配置中完成邮件 SMTP 配置，再开启注册邮箱验证');
        }
        Db::connect('live_mysql')->execute(
            'INSERT INTO lp_platform_config (`key`, `value`, remark, updated_at) VALUES (?, ?, ?, NOW())'
            . ' ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()',
            [self::EMAIL_VERIFY_KEY, $enabled ? '1' : '0', '注册邮箱验证：1开启，0关闭（默认关闭）']
        );
    }
}
