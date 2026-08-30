<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\common\util\StrHelper;
use think\facade\Db;

/**
 * 会员订阅服务
 */
final class VipService
{
    /**
     * 启用套餐列表（公开）
     */
    public function plans(): array
    {
        return Db::connect('live_mysql')->table('lp_vip_plan')
            ->where('status', 1)
            ->order('sort', 'asc')
            ->field('id, name, months, usd_price, diamond_price, daily_diamond')
            ->select()->toArray();
    }

    /**
     * 我的会员状态（惰性判断到期）
     */
    public function status(int $userId): array
    {
        $user = Db::connect('live_mysql')->table('lp_user')
            ->where('id', $userId)
            ->field('vip_expire_at, vip_last_claim_date')
            ->find();

        $expireAt = $user['vip_expire_at'] ?? null;
        $isActive = $expireAt && strtotime((string)$expireAt) > time();
        $claimable = false;
        $today = date('Y-m-d');

        if ($isActive) {
            $plan = $this->activePlanByExpire($userId, (string)$expireAt);
            $claimable = ($user['vip_last_claim_date'] ?? null) !== $today && ($plan['daily_diamond'] ?? 0) > 0;
        }

        return [
            'is_vip'          => (bool)$isActive,
            'expire_at'       => $isActive ? (string)$expireAt : null,
            'remain_days'     => $isActive ? (int)ceil((strtotime((string)$expireAt) - time()) / 86400) : 0,
            'daily_diamond'   => $isActive ? (float)($plan['daily_diamond'] ?? 0) : 0,
            'claimable_today' => $claimable,
        ];
    }

    /**
     * 购买会员（钻石支付，到期顺延）
     */
    public function buy(int $userId, int $planId): array
    {
        $plan = Db::connect('live_mysql')->table('lp_vip_plan')
            ->where('id', $planId)->where('status', 1)->find();
        if (!$plan) {
            throw new BusinessException(ResultCode::RECORD_NOT_FOUND, '套餐不存在或已下架');
        }

        $wallet = new WalletService();
        $price = (float)$plan['diamond_price'];
        if ($price > 0) {
            $wallet->debit($userId, $price, 'vip_buy', $planId, '购买会员：' . $plan['name']);
        }

        try {
            $user = Db::connect('live_mysql')->table('lp_user')
                ->where('id', $userId)->field('vip_expire_at')->find();
            $now = time();
            $base = !empty($user['vip_expire_at']) && strtotime((string)$user['vip_expire_at']) > $now
                ? strtotime((string)$user['vip_expire_at'])
                : $now;
            $expireTo = date('Y-m-d H:i:s', strtotime('+' . (int)$plan['months'] . ' months', $base));

            $orderNo = StrHelper::orderNo('VIP');
            Db::connect('live_mysql')->table('lp_vip_order')->insert([
                'order_no'       => $orderNo,
                'user_id'        => $userId,
                'plan_id'        => $planId,
                'diamond_amount' => $price,
                'expire_from'    => date('Y-m-d H:i:s', $base),
                'expire_to'      => $expireTo,
                'status'         => 1,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
            Db::connect('live_mysql')->table('lp_user')
                ->where('id', $userId)->update(['vip_expire_at' => $expireTo]);

            return [
                'order_no'   => $orderNo,
                'expire_to'  => $expireTo,
                'plan_name'  => (string)$plan['name'],
                'paid'       => $price,
            ];
        } catch (\Throwable $e) {
            // 下单失败退钻
            if ($price > 0) {
                $wallet->credit($userId, $price, 'vip_refund', '会员购买失败退款');
            }
            throw new BusinessException(ResultCode::SERVER_ERROR, '开通失败: ' . $e->getMessage());
        }
    }

    /**
     * 会员每日领取钻石
     */
    public function claimDaily(int $userId): array
    {
        $status = $this->status($userId);
        if (!$status['is_vip']) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '会员已过期或未开通');
        }
        if (!$status['claimable_today']) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '今日已领取，明天再来');
        }

        $today = date('Y-m-d');
        $affected = Db::connect('live_mysql')->table('lp_user')
            ->where('id', $userId)
            ->whereRaw('(vip_last_claim_date IS NULL OR vip_last_claim_date < ?)', [$today])
            ->update(['vip_last_claim_date' => $today]);
        if ($affected === 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '今日已领取');
        }

        (new WalletService())->credit($userId, (float)$status['daily_diamond'], 'vip_daily', 0, '会员每日领取');

        return ['claimed' => (float)$status['daily_diamond']];
    }

    /**
     * 根据到期时间找当前有效套餐（取该用户最近一单）
     */
    private function activePlanByExpire(int $userId, string $expireAt): array
    {
        $rows = Db::connect('live_mysql')->query(
            "SELECT p.daily_diamond FROM lp_vip_order o
             JOIN lp_vip_plan p ON p.id = o.plan_id
             WHERE o.user_id = ? AND o.status = 1 AND o.expire_to = ?
             ORDER BY o.id DESC LIMIT 1",
            [$userId, $expireAt]
        );
        return $rows[0] ?? ['daily_diamond' => 20];
    }
}
