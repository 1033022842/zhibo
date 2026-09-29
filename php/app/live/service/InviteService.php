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
 */
final class InviteService
{
    /** 邀请人奖励流水类型 */
    public const BIZ_INVITER = 'invite_reward';
    /** 被邀请人奖励流水类型 */
    public const BIZ_INVITEE = 'invite_bonus';

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
     * 邀请页数据：我的邀请码 + 收益统计
     */
    public function overview(int $userId): array
    {
        $code = $this->ensureCode($userId);

        $row = Db::connect('live_mysql')->table('lp_invite_record')
            ->where('inviter_id', $userId)
            ->field('COUNT(*) AS invited_count,
                     SUM(CASE WHEN premium_at IS NOT NULL THEN 1 ELSE 0 END) AS premium_friends,
                     SUM(reward_tokens) AS total_earned')
            ->find() ?: [];

        return [
            'code'            => $code,
            'reward_tokens'   => self::rewardTokens(),
            'invited_count'   => (int) ($row['invited_count'] ?? 0),
            'premium_friends' => (int) ($row['premium_friends'] ?? 0),
            'total_earned'    => (float) ($row['total_earned'] ?? 0),
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
