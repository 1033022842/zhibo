<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use think\facade\Db;
use think\facade\Log;

/**
 * 邀请好友（Affiliate Program / Inviter 页）
 *
 * 规则：
 *   1. 每个用户有唯一「邀请码」（自定义短码），邀请链接 = https://<站点>/?code=<邀请码>
 *   2. 好友通过邀请链接注册 → 落 lp_invite_record 绑定邀请关系
 *   3. 好友首次成为高级会员（VIP 生效）→ 邀请人与被邀请人各得 invite_reward_tokens 个代币（默认 100），每次邀请只发一次
 *   4. 好友每次充值成功 → 直接邀请人（一级）按实付 USD × 返佣比例拿代币佣金；比例支持全局 + 单人专属
 */
final class InviteService
{
    /** 邀请人奖励流水类型 */
    public const BIZ_INVITER = 'invite_reward';
    /** 被邀请人奖励流水类型 */
    public const BIZ_INVITEE = 'invite_bonus';
    /** 充值佣金流水类型 */
    public const BIZ_RECHARGE_COMMISSION = 'invite_recharge_commission';

    private const CODE_LEN = 6;
    /** 去掉易混的 l / o / 0 / 1 */
    private const CODE_ALPHABET = 'abcdefghijkmnpqrstuvwxyz23456789';

    /**
     * 单边奖励代币数（运营可在 lp_platform_config 里改 invite_reward_tokens）
     */
    public static function rewardTokens(): float
    {
        return max(0.0, (float) WalletService::config('invite_reward_tokens', '100'));
    }

    /**
     * 全局充值返佣比例（0~1，运营在后台「广告渠道配置」里改，0 = 关闭）
     */
    public static function globalRechargeRate(): float
    {
        return max(0.0, min(1.0, (float) WalletService::config('invite_recharge_rate', '0')));
    }

    /**
     * 某邀请人的充值返佣比例：优先个人专属比例，未设置（NULL）时用全局
     */
    public function rechargeRate(int $inviterId): float
    {
        if ($inviterId > 0) {
            $custom = Db::connect('live_mysql')->table('lp_user')
                ->where('id', $inviterId)->value('invite_recharge_rate');
            if ($custom !== null && $custom !== '') {
                return max(0.0, min(1.0, (float) $custom));
            }
        }

        return self::globalRechargeRate();
    }

    /**
     * 我的邀请码：没有就生成一个（并发下以库里最终值为准）
     */
    public function ensureCode(int $userId): string
    {
        $db   = Db::connect('live_mysql');
        $code = (string) $db->table('lp_user')->where('id', $userId)->value('invite_code');
        if ($code !== '') {
            return $code;
        }

        for ($i = 0; $i < 5; $i++) {
            $candidate = $this->randomCode();
            if ($db->table('lp_user')->where('invite_code', $candidate)->find()) {
                continue;
            }
            $db->table('lp_user')->where('id', $userId)->whereNull('invite_code')
                ->update(['invite_code' => $candidate]);

            $saved = (string) $db->table('lp_user')->where('id', $userId)->value('invite_code');
            if ($saved !== '') {
                return $saved;
            }
        }

        throw new BusinessException(ResultCode::SERVER_ERROR, '邀请码生成失败，请重试');
    }

    /**
     * 邀请页数据：我的邀请码 + 收益统计 + 邀请记录
     */
    public function overview(int $userId): array
    {
        $code = $this->ensureCode($userId);
        $db   = Db::connect('live_mysql');

        $row = $db->table('lp_invite_record')
            ->where('inviter_id', $userId)
            ->field('COUNT(*) AS invited_count,
                     SUM(CASE WHEN premium_at IS NOT NULL THEN 1 ELSE 0 END) AS premium_friends,
                     SUM(reward_tokens) AS total_earned')
            ->find() ?: [];

        $records = $db->table('lp_invite_record')->alias('ir')
            ->leftJoin('lp_user u', 'u.id = ir.invitee_id')
            ->where('ir.inviter_id', $userId)
            ->field('ir.registered_at, ir.premium_at, ir.reward_tokens, u.nickname, u.avatar')
            ->order('ir.id', 'desc')
            ->limit(20)
            ->select()->toArray();

        $items = [];
        foreach ($records as $record) {
            $nickname = trim((string) ($record['nickname'] ?? ''));
            $items[] = [
                'nickname'      => $nickname !== '' ? $nickname : 'Friend',
                'avatar'        => (string) ($record['avatar'] ?? ''),
                'registered_at' => (string) ($record['registered_at'] ?? ''),
                'premium_at'    => (string) ($record['premium_at'] ?? ''),
                'reward_tokens' => (float) ($record['reward_tokens'] ?? 0),
            ];
        }

        // 充值佣金：累计发放 + 明细（只统计我作为邀请人的记录）
        $commissionStat = $db->table('lp_invite_commission')
            ->where('inviter_id', $userId)
            ->field('COUNT(*) AS orders, COUNT(DISTINCT invitee_id) AS friends, SUM(tokens) AS tokens')
            ->find() ?: [];

        $commissionRows = $db->table('lp_invite_commission')->alias('ic')
            ->leftJoin('lp_user u', 'u.id = ic.invitee_id')
            ->where('ic.inviter_id', $userId)
            ->field('ic.created_at, ic.pay_usd, ic.rate, ic.tokens, u.nickname, u.avatar')
            ->order('ic.id', 'desc')
            ->limit(20)
            ->select()->toArray();

        $commissions = [];
        foreach ($commissionRows as $item) {
            $nickname = trim((string) ($item['nickname'] ?? ''));
            $commissions[] = [
                'nickname'   => $nickname !== '' ? $nickname : 'Friend',
                'avatar'     => (string) ($item['avatar'] ?? ''),
                'pay_usd'    => (float) ($item['pay_usd'] ?? 0),
                'rate'       => round((float) ($item['rate'] ?? 0) * 100, 2),
                'tokens'     => (float) ($item['tokens'] ?? 0),
                'created_at' => (string) ($item['created_at'] ?? ''),
            ];
        }

        return [
            'code'               => $code,
            'reward_tokens'      => self::rewardTokens(),
            'invited_count'      => (int) ($row['invited_count'] ?? 0),
            'premium_friends'    => (int) ($row['premium_friends'] ?? 0),
            'total_earned'       => (float) ($row['total_earned'] ?? 0),
            'recharge_rate'      => round($this->rechargeRate($userId) * 100, 2),
            'commission_tokens'  => (float) ($commissionStat['tokens'] ?? 0),
            'commission_orders'  => (int) ($commissionStat['orders'] ?? 0),
            'commission_friends' => (int) ($commissionStat['friends'] ?? 0),
            'commissions'        => $commissions,
            'records'            => $items,
        ];
    }

    /**
     * 自定义邀请码（3-25 位小写字母 / 数字 / 连字符，全局唯一）
     */
    public function saveCode(int $userId, string $slug): array
    {
        $slug = strtolower(trim($slug));
        if (!preg_match('/^[a-z0-9-]{3,25}$/', $slug)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'Invite link can only contain letters, numbers and hyphens (3-25 characters).');
        }

        $db    = Db::connect('live_mysql');
        $owner = (int) $db->table('lp_user')->where('invite_code', $slug)->value('id');
        if ($owner > 0 && $owner !== $userId) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'This invite link is already taken.');
        }

        $db->table('lp_user')->where('id', $userId)->update(['invite_code' => $slug]);

        return ['code' => $slug];
    }

    /**
     * 注册时绑定邀请关系（邀请码无效 / 自己邀请自己都直接忽略，不影响注册）
     */
    public function bind(int $inviteeId, string $code): void
    {
        $code = strtolower(trim($code));
        if ($code === '' || $inviteeId <= 0) {
            return;
        }

        $db        = Db::connect('live_mysql');
        $inviterId = (int) $db->table('lp_user')
            ->where('invite_code', $code)->where('status', 1)->value('id');
        if ($inviterId <= 0 || $inviterId === $inviteeId) {
            Log::info('[invite] 邀请码无效或自邀，忽略 code=' . $code . ' invitee=' . $inviteeId);
            return;
        }
        if ($db->table('lp_invite_record')->where('invitee_id', $inviteeId)->find()) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $db->table('lp_invite_record')->insert([
            'inviter_id'    => $inviterId,
            'invitee_id'    => $inviteeId,
            'invite_code'   => $code,
            'registered_at' => $now,
            'created_at'    => $now,
        ]);
    }

    /**
     * 被邀请人成为高级会员：记录会员时间，并给双方各发一次奖励
     *
     * 由 VipService::activate() 调用；内部已兜异常，失败只记日志，不影响开通
     */
    public function onVipActivated(int $userId): void
    {
        $db     = Db::connect('live_mysql');
        $record = $db->table('lp_invite_record')->where('invitee_id', $userId)->find();
        if (!$record) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        if (empty($record['premium_at'])) {
            $db->table('lp_invite_record')->where('id', (int) $record['id'])->whereNull('premium_at')
                ->update(['premium_at' => $now, 'updated_at' => $now]);
        }

        $amount = self::rewardTokens();
        if ($amount <= 0 || !empty($record['reward_at'])) {
            return;
        }

        // 先抢占发奖资格：并发 / 重复回调下只发一次
        $claimed = $db->table('lp_invite_record')
            ->where('id', (int) $record['id'])->whereNull('reward_at')
            ->update(['reward_tokens' => $amount, 'reward_at' => $now, 'updated_at' => $now]);
        if ($claimed === 0) {
            return;
        }

        $wallet = new WalletService();
        $pairs  = [
            [(int) $record['inviter_id'], self::BIZ_INVITER, '邀请好友成为高级会员'],
            [$userId, self::BIZ_INVITEE, '好友邀请奖励'],
        ];
        foreach ($pairs as [$uid, $bizType, $remark]) {
            try {
                $wallet->credit($uid, $amount, $bizType, (int) $record['id'], $remark);
            } catch (\Throwable $e) {
                Log::error('[invite] 发奖失败 record=' . $record['id'] . ' uid=' . $uid . ' err=' . $e->getMessage());
            }
        }
    }

    /**
     * 好友充值到账：给直接邀请人（一级）发充值佣金
     *
     * 佣金 = 实付 USD × 返佣比例 ÷ 代币美元单价（diamond_to_usdt_rate），以代币入账。
     * 由 RechargeService::settleOrder() 在订单结算时调用；同一订单只发一次（uk_order 兜底），
     * 单价/比例取当时值，后续调整比例不影响已发佣金。
     */
    public function onRechargePaid(int $inviteeId, int $orderId, string $orderNo, float $payUsd): void
    {
        if ($inviteeId <= 0 || $orderId <= 0 || $payUsd <= 0) {
            return;
        }

        $db     = Db::connect('live_mysql');
        $record = $db->table('lp_invite_record')->where('invitee_id', $inviteeId)->find();
        if (!$record) {
            return;
        }

        $inviterId = (int) $record['inviter_id'];
        if ($inviterId <= 0 || $inviterId === $inviteeId) {
            return;
        }

        $rate = $this->rechargeRate($inviterId);
        if ($rate <= 0) {
            return;
        }

        // 代币美元单价：1 代币 = 多少 USDT（与提现口径一致）
        $tokenUsd = (float) WalletService::config('diamond_to_usdt_rate', '0.01');
        if ($tokenUsd <= 0) {
            return;
        }

        $tokens = round($payUsd * $rate / $tokenUsd, 2);
        if ($tokens <= 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        try {
            $db->table('lp_invite_commission')->insert([
                'inviter_id' => $inviterId,
                'invitee_id' => $inviteeId,
                'order_id'   => $orderId,
                'order_no'   => $orderNo,
                'pay_usd'    => round($payUsd, 2),
                'rate'       => $rate,
                'tokens'     => $tokens,
                'created_at' => $now,
            ]);
        } catch (\Throwable $e) {
            // 唯一键冲突 = 该订单已发过佣金，直接跳过
            Log::info('[invite] 充值佣金已存在，跳过 order=' . $orderNo . ' err=' . $e->getMessage());
            return;
        }

        try {
            (new WalletService())->credit(
                $inviterId,
                $tokens,
                self::BIZ_RECHARGE_COMMISSION,
                $orderId,
                '好友充值返佣 ' . $orderNo
            );
        } catch (\Throwable $e) {
            Log::error('[invite] 充值佣金入账失败 order=' . $orderNo . ' inviter=' . $inviterId . ' err=' . $e->getMessage());
        }
    }

    private function randomCode(): string
    {
        $alphabet = self::CODE_ALPHABET;
        $max      = strlen($alphabet) - 1;
        $code     = '';
        for ($i = 0; $i < self::CODE_LEN; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }
}
