<?php
declare(strict_types=1);

namespace app\live\service;

use think\facade\Db;

/**
 * AI 生成额度余额（每天补充，上限封顶）
 *
 * 规则：余额 = min(上限, 余额 + 断签天数 × 每天补充次数)
 *
 * 与旧逻辑的区别：旧逻辑按「当天已创建任务数」判断，每天 0 点直接回到满额，
 * 没用完的次数不累积；新逻辑是把次数存成余额，每天补 1 次，最多累积到套餐上限。
 *
 * 例（上限 2、每天补 1）：
 *   D1 余额 2 → 用 2 次 → 0
 *   D2 +1 → 1 → 用 1 次 → 0
 *   D3 +1 → 1
 *   若某天没用，余额会在次日被补到上限 2（不会超过）。
 */
final class GenCreditService
{
    private function db()
    {
        return Db::connect('live_mysql');
    }

    /**
     * 业务日期
     * 目前直接用服务器时区（UTC），与旧逻辑的 date('Y-m-d') 一致。
     * 若要改成北京时间等，只改这一处。
     */
    private function today(): string
    {
        return date('Y-m-d');
    }

    /**
     * 该用户是否已有额度记录
     */
    public function exists(int $userId): bool
    {
        return (bool) $this->db()->table('lp_user_gen_credit')
            ->where('user_id', $userId)
            ->count();
    }

    /**
     * 结算「按天补充」并返回当前余额（首次调用会建记录）
     *
     * @param int $cap             余额上限（套餐 image_daily_limit）
     * @param int $dailyAdd        每天补充次数
     * @param int $fallbackBalance 首次建记录时的初始余额（灰度当天 = 上限 - 当天已用）
     */
    public function refillAndGet(int $userId, int $cap, int $dailyAdd, int $fallbackBalance = 0): int
    {
        $cap   = max(0, $cap);
        $add   = max(0, $dailyAdd);
        $init  = max(0, min($cap, $fallbackBalance));
        $today = $this->today();

        // 单条原子 SQL：
        //  - DATEDIFF = 0（当天重复调用）→ 余额不变
        //  - 断签 N 天 → 补 N × $add，但 LEAST(cap, ...) 封顶
        //  - 套餐上限变化 → 自动收敛到新上限
        $this->db()->execute(
            'INSERT INTO `lp_user_gen_credit` (`user_id`, `balance`, `last_refill_date`, `updated_at`)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
               `balance`          = LEAST(?, `balance` + GREATEST(0, DATEDIFF(?, `last_refill_date`)) * ?),
               `last_refill_date` = ?,
               `updated_at`       = NOW()',
            [$userId, $init, $today, $cap, $today, $add, $today]
        );

        $balance = $this->db()->table('lp_user_gen_credit')
            ->where('user_id', $userId)
            ->value('balance');

        return (int) ($balance ?? 0);
    }

    /**
     * 消耗一次（条件更新，天然防并发超额）
     */
    public function consume(int $userId): bool
    {
        $affected = $this->db()->execute(
            'UPDATE `lp_user_gen_credit` SET `balance` = `balance` - 1
             WHERE `user_id` = ? AND `balance` > 0',
            [$userId]
        );

        return (int) $affected === 1;
    }

    /**
     * 退回一次（上传/建任务失败时，避免白扣）
     */
    public function refund(int $userId, int $cap): void
    {
        try {
            $this->db()->execute(
                'UPDATE `lp_user_gen_credit` SET `balance` = LEAST(?, `balance` + 1)
                 WHERE `user_id` = ?',
                [max(0, $cap), $userId]
            );
        } catch (\Throwable $e) {
            // 退回失败不影响主流程
        }
    }
}
