<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\common\util\StrHelper;
use think\facade\Db;
use think\facade\Log;

/**
 * 会员订阅服务
 */
final class VipService
{
    /**
     * 启用套餐列表（公开，含层级与权益）
     */
    public function plans(): array
    {
        $rows = Db::connect('live_mysql')->table('lp_vip_plan')
            ->where('status', 1)
            ->order('sort', 'asc')
            ->field('id, name, level, months, tokens_month, usd_price, diamond_price, daily_diamond, benefits, chat_daily_limit, image_daily_limit, discount_percent, custom_video')
            ->select()->toArray();

        // 同层级同 tokens 档位的「1 个月价」：作为多月档的对比划线价
        $monthly = [];
        foreach ($rows as $row) {
            if ((int) $row['months'] === 1) {
                $monthly[$row['level'] . '|' . (int) $row['tokens_month']] = (float) $row['usd_price'];
            }
        }

        foreach ($rows as &$row) {
            $months = max(1, (int) $row['months']);
            $row['benefits']        = $this->decodeBenefits($row['benefits'] ?? '');
            $row['tokens_month']    = (int) $row['tokens_month'];
            $row['price_per_month'] = round((float) $row['usd_price'] / $months, 2);
            $row['compare_price']   = $months > 1
                ? ($monthly[$row['level'] . '|' . (int) $row['tokens_month']] ?? 0)
                : 0;
        }
        unset($row);

        return $rows;
    }

    /**
     * 我的会员状态（含权益与当日额度，惰性判断到期）
     */
    public function status(int $userId): array
    {
        [$user, $isActive, $expireAt, $plan] = $this->resolve($userId);

        $today     = date('Y-m-d');
        $claimable = $isActive && $plan
            && ($user['vip_last_claim_date'] ?? null) !== $today
            && (float) ($plan['daily_diamond'] ?? 0) > 0;

        return array_merge($this->buildEntitlements($userId, $isActive, $plan), [
            'expire_at'       => $isActive ? $expireAt : null,
            'remain_days'     => $isActive ? (int) ceil((strtotime($expireAt) - time()) / 86400) : 0,
            'claimable_today' => $claimable,
        ]);
    }

    /**
     * 当前用户权益（聊天/生成额度、折扣、层级），供各业务校验复用
     */
    public function entitlements(int $userId): array
    {
        [, $isActive, , $plan] = $this->resolve($userId);

        return $this->buildEntitlements($userId, $isActive, $plan);
    }

    /**
     * 免费用户每日聊天条数（0=不限）
     */
    public function freeChatLimit(): int
    {
        return max(0, (int) WalletService::config('free_chat_daily_limit', '20'));
    }

    /**
     * 会员折扣价（代币数；无折扣返回原价）
     */
    public function discounted(int $price, int $discountPercent): int
    {
        if ($price <= 0 || $discountPercent <= 0) {
            return max(0, $price);
        }
        $percent = max(0, min(90, $discountPercent));

        return max(1, (int) round($price * (100 - $percent) / 100));
    }

    /**
     * 读取用户与当前生效套餐
     *
     * @return array{0: array, 1: bool, 2: string, 3: ?array}
     */
    private function resolve(int $userId): array
    {
        $user = Db::connect('live_mysql')->table('lp_user')
            ->where('id', $userId)
            ->field('id, vip_expire_at, vip_last_claim_date')
            ->find();

        $expireAt = (string) ($user['vip_expire_at'] ?? '');
        $isActive = $expireAt !== '' && strtotime($expireAt) > time();
        $plan     = $isActive ? $this->activePlan($userId) : null;

        return [$user ?: [], $isActive, $expireAt, $plan];
    }

    /**
     * 组装权益：会员按套餐层级，非会员按免费额度
     */
    private function buildEntitlements(int $userId, bool $isActive, ?array $plan): array
    {
        $quota = new QuotaService();

        if (!$isActive) {
            $freeLimit = $this->freeChatLimit();

            return [
                'is_vip'            => false,
                'level'             => '',
                'level_name'        => 'Free',
                'plan_name'         => '',
                'plan_months'       => 0,
                'plan_tokens'       => 0,
                'daily_diamond'     => 0,
                'benefits'          => [],
                'chat_daily_limit'  => $freeLimit,
                'image_daily_limit' => 0,
                'discount_percent'  => 0,
                'custom_video'      => false,
                'chat_quota'        => $quota->info($userId, 'chat', $freeLimit),
            ];
        }

        // 会员到期时间被人工设置、查不到订单时按基础层兜底
        $plan = $plan ?: [
            'level' => 'premium', 'name' => 'Premium', 'daily_diamond' => 20, 'benefits' => '',
            'chat_daily_limit' => 0, 'image_daily_limit' => 3, 'discount_percent' => 10, 'custom_video' => 1,
        ];

        $level     = (string) ($plan['level'] ?? 'premium');
        $chatLimit = (int) ($plan['chat_daily_limit'] ?? 0);

        return [
            'is_vip'            => true,
            'level'             => $level,
            'level_name'        => $level === 'premium_plus' ? 'Premium Plus' : 'Premium',
            'plan_name'         => (string) ($plan['name'] ?? ''),
            'plan_months'       => (int) ($plan['months'] ?? 0),
            'plan_tokens'       => (int) ($plan['tokens_month'] ?? 0),
            'daily_diamond'     => (float) ($plan['daily_diamond'] ?? 0),
            'benefits'          => $this->decodeBenefits($plan['benefits'] ?? ''),
            'chat_daily_limit'  => $chatLimit,
            'image_daily_limit' => (int) ($plan['image_daily_limit'] ?? 0),
            'discount_percent'  => max(0, min(90, (int) ($plan['discount_percent'] ?? 0))),
            'custom_video'      => (bool) ($plan['custom_video'] ?? false),
            // 解锁「未购买内容的封面去模糊」由平台开关控制（默认关闭，避免泄露付费内容）
            'unblur_preview'    => WalletService::config('vip_unblur_locked_preview', '0') === '1',
            'chat_quota'        => $quota->info($userId, 'chat', $chatLimit),
        ];
    }

    /**
     * 权益展示文案（JSON 数组）
     */
    private function decodeBenefits($raw): array
    {
        $list = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($list)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn($v) => trim((string) $v), $list), static fn($v) => $v !== ''));
    }

    /**
     * 订阅管理（设置页 Current plan / Payment date / Subscription renewal）
     */
    public function subscription(int $userId): array
    {
        $status = $this->status($userId);
        $db     = Db::connect('live_mysql');

        $user = $db->table('lp_user')
            ->where('id', $userId)
            ->field('id, vip_auto_renew, vip_cancel_reason, vip_cancel_at')
            ->find() ?: [];

        // 当前生效订阅单的支付时间，对应设置页的「Payment date」
        $order = $db->query(
            "SELECT o.created_at FROM lp_vip_order o
             WHERE o.user_id = ? AND o.status = 1 AND o.expire_to >= ?
             ORDER BY o.id DESC LIMIT 1",
            [$userId, date('Y-m-d H:i:s')]
        );

        return [
            'level_name'    => (string) ($status['level_name'] ?? 'Free'),
            'is_vip'        => (bool) ($status['is_vip'] ?? false),
            'expire_at'     => $status['expire_at'] ?? null,
            'remain_days'   => (int) ($status['remain_days'] ?? 0),
            'paid_at'       => (string) ($order[0]['created_at'] ?? ''),
            'auto_renew'    => (int) ($user['vip_auto_renew'] ?? 1) === 1,
            'cancel_reason' => (string) ($user['vip_cancel_reason'] ?? ''),
            'cancel_at'     => (string) ($user['vip_cancel_at'] ?? ''),
        ];
    }

    /**
     * 取消订阅：只记录意图（到期后不再续订），会员权益保留至 vip_expire_at
     */
    public function cancel(int $userId, string $reason = '', string $description = ''): array
    {
        $status = $this->status($userId);
        if (empty($status['is_vip'])) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '当前没有生效中的订阅');
        }

        $reason      = mb_substr(trim($reason), 0, 32);
        $description = mb_substr(trim($description), 0, 255);

        Db::connect('live_mysql')->table('lp_user')->where('id', $userId)->update([
            'vip_auto_renew'         => 0,
            'vip_cancel_reason'      => $reason,
            'vip_cancel_description' => $description,
            'vip_cancel_at'          => date('Y-m-d H:i:s'),
        ]);

        Log::info('[vip] 用户取消订阅 uid=' . $userId . ' reason=' . $reason . ' desc=' . $description);

        return $this->subscription($userId);
    }

    /**
     * 购买会员（代币支付，到期顺延）
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
            return $this->activate($userId, $planId, '', $price);
        } catch (\Throwable $e) {
            // 下单失败退钻
            if ($price > 0) {
                $wallet->credit($userId, $price, 'vip_refund', '会员购买失败退款');
            }
            throw new BusinessException(ResultCode::SERVER_ERROR, '开通失败: ' . $e->getMessage());
        }
    }

    /**
     * 开通/顺延会员（到期时间在现有会员基础上顺延）
     *
     * @param string $orderNo 外部订单号（star-pay 订阅单；为空则新建）
     * @param float  $paid    实付代币数（网关订阅单为 0）
     */
    public function activate(int $userId, int $planId, string $orderNo = '', float $paid = 0): array
    {
        $db = Db::connect('live_mysql');
        $plan = $db->table('lp_vip_plan')->where('id', $planId)->find();
        if (!$plan) {
            throw new BusinessException(ResultCode::RECORD_NOT_FOUND, '套餐不存在');
        }

        $orderNo = $orderNo !== '' ? $orderNo : StrHelper::orderNo('VIP');
        // 幂等：同一订单号已生效则直接返回原结果
        $exists = $db->table('lp_vip_order')->where('order_no', $orderNo)->find();
        if ($exists) {
            return [
                'order_no'  => $orderNo,
                'expire_to' => (string) $exists['expire_to'],
                'plan_name' => (string) $plan['name'],
                'paid'      => (float) $exists['diamond_amount'],
            ];
        }

        $db->startTrans();
        try {
            $user = $db->table('lp_user')
                ->where('id', $userId)->field('vip_expire_at')->lock(true)->find();
            $now = time();
            $base = !empty($user['vip_expire_at']) && strtotime((string)$user['vip_expire_at']) > $now
                ? strtotime((string)$user['vip_expire_at'])
                : $now;
            $expireTo = date('Y-m-d H:i:s', strtotime('+' . (int)$plan['months'] . ' months', $base));

            $db->table('lp_vip_order')->insert([
                'order_no'       => $orderNo,
                'user_id'        => $userId,
                'plan_id'        => $planId,
                'diamond_amount' => $paid,
                'expire_from'    => date('Y-m-d H:i:s', $base),
                'expire_to'      => $expireTo,
                'status'         => 1,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
            $db->table('lp_user')
                ->where('id', $userId)->update([
                    'vip_expire_at'          => $expireTo,
                    // 新购/续订后重置「取消订阅」意图
                    'vip_auto_renew'         => 1,
                    'vip_cancel_reason'      => '',
                    'vip_cancel_description' => '',
                    'vip_cancel_at'          => null,
                ]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        // 邀请奖励：被邀请人首次成为高级会员时，邀请人与他各得代币（失败不影响开通）
        try {
            (new InviteService())->onVipActivated($userId);
        } catch (\Throwable $e) {
            Log::error('[invite] 会员邀请奖励处理失败 uid=' . $userId . ' err=' . $e->getMessage());
        }

        return [
            'order_no'  => $orderNo,
            'expire_to' => $expireTo,
            'plan_name' => (string)$plan['name'],
            'paid'      => $paid,
        ];
    }

    /**
     * 会员每日领取代币
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
     * 当前生效套餐：有效期内层级最高的一单（Premium Plus 优先）
     */
    private function activePlan(int $userId): ?array
    {
        $rows = Db::connect('live_mysql')->query(
            "SELECT p.* FROM lp_vip_order o
             JOIN lp_vip_plan p ON p.id = o.plan_id
             WHERE o.user_id = ? AND o.status = 1 AND o.expire_to >= ?
             ORDER BY (p.level = 'premium_plus') DESC, o.id DESC
             LIMIT 1",
            [$userId, date('Y-m-d H:i:s')]
        );

        return $rows[0] ?? null;
    }
}
