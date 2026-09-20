<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use think\facade\Db;

/**
 * 用户每日额度计数（免费用户聊天条数、生成次数等）
 */
final class QuotaService
{
    /**
     * 当日已使用次数
     */
    public function used(int $userId, string $key): int
    {
        $used = Db::connect('live_mysql')->table('lp_user_quota')
            ->where('user_id', $userId)
            ->where('quota_date', date('Y-m-d'))
            ->where('quota_key', $key)
            ->value('used');

        return (int) ($used ?? 0);
    }

    /**
     * 累加当日使用次数（并发安全：唯一键 + 自增）
     */
    public function incr(int $userId, string $key, int $step = 1): int
    {
        $db  = Db::connect('live_mysql');
        $now = date('Y-m-d H:i:s');
        $db->execute(
            'INSERT INTO `lp_user_quota` (`user_id`, `quota_date`, `quota_key`, `used`, `created_at`, `updated_at`)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `used` = `used` + ?, `updated_at` = ?',
            [$userId, date('Y-m-d'), $key, $step, $now, $now, $step, $now]
        );

        return $this->used($userId, $key);
    }

    /**
     * 额度校验：超限抛业务异常
     *
     * @param int    $limit 0 表示不限
     */
    public function check(int $userId, string $key, int $limit, string $message = ''): void
    {
        if ($limit <= 0) {
            return;
        }
        if ($this->used($userId, $key) >= $limit) {
            throw new BusinessException(
                ResultCode::RATE_LIMIT,
                $message !== '' ? $message : "You've reached today's limit ({$limit}). Upgrade your membership for more."
            );
        }
    }

    /**
     * 额度信息（前端展示用）
     */
    public function info(int $userId, string $key, int $limit): array
    {
        $used = $this->used($userId, $key);
        return [
            'key'       => $key,
            'limit'     => $limit,
            'used'      => $used,
            'remaining' => $limit > 0 ? max(0, $limit - $used) : -1,
        ];
    }
}
