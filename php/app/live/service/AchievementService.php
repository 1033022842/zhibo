<?php
declare(strict_types=1);

namespace app\live\service;

use think\facade\Db;

/**
 * 成就系统
 *
 * 站点原本没有成就体系，这里用「静态定义 + 实时统计」实现：
 * 每条成就声明一个统计指标与达标值，访问时按用户现有数据实时算进度，
 * 因此不需要埋点、也不需要额外的解锁记录表，老用户一上线就有正确进度。
 */
final class AchievementService
{
    /** 稀有度 -> 积分 */
    private const POINTS = [
        'common'    => 5,
        'uncommon'  => 10,
        'rare'      => 20,
        'epic'      => 40,
        'legendary' => 80,
    ];

    /**
     * 成就定义
     * [key, 名称, 说明, 分区, 稀有度, 达标值, 统计指标, 图标]
     */
    private const DEFS = [
        // ---- Getting started ----
        ['joined',        'Early Bird',        'You joined Sugus',                'Getting started', 'common',    1,    'joined',    'user'],
        ['profile',       'All Set',           'Add a nickname to your profile',  'Getting started', 'common',    1,    'profile',   'badge'],
        ['persona',       'Alter Ego',         'Create your own Persona',         'Getting started', 'rare',      1,    'persona',   'star'],
        ['first_chat',    'First Hello',       'Send your first message',         'Getting started', 'common',    1,    'chat',      'chat'],
        ['first_collect', 'Curator',           'Collect your first character',    'Getting started', 'common',    1,    'collect',   'heart'],

        // ---- Chatting ----
        ['chat_10',       'Chatty',            'Send 10 messages',                'Chatting',        'common',    10,   'chat',      'chat'],
        ['chat_100',      'Talkative',         'Send 100 messages',               'Chatting',        'uncommon',  100,  'chat',      'chat'],
        ['chat_1000',     'Soulmate',          'Send 1,000 messages',             'Chatting',        'rare',      1000, 'chat',      'heart'],
        ['collect_5',     'Collector',         'Collect 5 characters',            'Chatting',        'common',    5,    'collect',   'heart'],
        ['collect_25',    'Devoted',           'Collect 25 characters',           'Chatting',        'rare',      25,   'collect',   'heart'],
        ['affection_100', 'Heartbeat',         'Reach 100 affection',             'Chatting',        'uncommon',  100,  'affection', 'heart'],
        ['affection_500', 'Sweetheart',        'Reach 500 affection',             'Chatting',        'epic',      500,  'affection', 'heart'],
        ['unlock_1',      'Unlocked',          'Unlock your first content',       'Chatting',        'common',    1,    'unlock',    'lock'],
        ['unlock_25',     'Insider',           'Unlock 25 contents',              'Chatting',        'rare',      25,   'unlock',    'lock'],
        ['short_1',       'Binge Starter',     'Unlock your first short episode', 'Chatting',        'uncommon',  1,    'short',     'star'],
        ['short_20',      'Binge Master',      'Unlock 20 short episodes',        'Chatting',        'epic',      20,   'short',     'star'],

        // ---- Spending ----
        ['recharge_1',    'First Top-up',      'Complete your first top-up',      'Spending',        'uncommon',  1,    'recharge',  'coin'],
        ['recharge_5',    'Supporter',         'Top up 5 times',                  'Spending',        'rare',      5,    'recharge',  'coin'],
        ['recharge_20',   'Patron',            'Top up 20 times',                 'Spending',        'epic',      20,   'recharge',  'coin'],
        ['shop_1',        'Shopper',           'Buy something in the Shop',       'Spending',        'common',    1,    'shop',      'shop'],
        ['gift_1',        'Generous',          'Send your first gift',            'Spending',        'uncommon',  1,    'gift',      'gift'],
        ['gift_50',       'Big Spender',       'Send 50 gifts',                   'Spending',        'epic',      50,   'gift',      'gift'],
        ['spend_100',     'Invested',          'Spend 100 tokens',                'Spending',        'uncommon',  100,  'spend',     'coin'],
        ['spend_1000',    'Whale',             'Spend 1,000 tokens',              'Spending',        'epic',      1000, 'spend',     'coin'],
        ['vip_once',      'Premium',           'Subscribe to Premium',            'Spending',        'rare',      1,    'vip',       'crown'],
        ['vip_plus',      'Premium Plus',      'Subscribe to Premium Plus',       'Spending',        'legendary', 1,    'vip_plus',  'crown'],

        // ---- Community ----
        ['like_1',        'Nice One',          'Like your first post',            'Community',       'common',    1,    'like',      'heart'],
        ['like_50',       'Hype',              'Like 50 posts',                   'Community',       'rare',      50,   'like',      'heart'],
        ['pledge_1',      'Backer',            'Back a crowdfunding project',     'Community',       'rare',      1,    'pledge',    'star'],
        ['merchant',      'Verified Creator',  'Pass merchant verification',      'Community',       'epic',      1,    'merchant',  'badge'],

        // ---- Loyalty ----
        ['days_7',        'Week One',          'Be with us for 7 days',           'Loyalty',         'common',    7,    'days',      'calendar'],
        ['days_30',       'Regular',           'Be with us for 30 days',          'Loyalty',         'uncommon',  30,   'days',      'calendar'],
        ['days_365',      'Anniversary',       'Be with us for a year',           'Loyalty',         'legendary', 365,  'days',      'calendar'],
    ];

    /**
     * 用户的成就概览（含分区与每条进度）
     */
    public function forUser(int $userId): array
    {
        $stats   = $this->stats($userId);
        $sections = [];
        $unlocked = 0;
        $points   = 0;
        $totalPoints = 0;

        foreach (self::DEFS as $def) {
            [$key, $name, $desc, $cat, $rarity, $target, $metric, $icon] = $def;

            $value     = (int) ($stats[$metric] ?? 0);
            $isUnlocked = $value >= $target;
            $pts       = self::POINTS[$rarity] ?? 5;

            $totalPoints += $pts;
            if ($isUnlocked) {
                $unlocked++;
                $points += $pts;
            }

            if (!isset($sections[$cat])) {
                $sections[$cat] = ['label' => $cat, 'unlocked' => 0, 'total' => 0, 'items' => []];
            }
            $sections[$cat]['total']++;
            if ($isUnlocked) {
                $sections[$cat]['unlocked']++;
            }
            $sections[$cat]['items'][] = [
                'key'      => $key,
                'name'     => $name,
                'desc'     => $desc,
                'rarity'   => $rarity,
                'icon'     => $icon,
                'points'   => $pts,
                'target'   => $target,
                'value'    => min($value, $target),
                'unlocked' => $isUnlocked,
            ];
        }

        return [
            'unlocked'     => $unlocked,
            'total'        => count(self::DEFS),
            'points'       => $points,
            'total_points' => $totalPoints,
            'signed_in'    => $userId > 0,
            'sections'     => array_values($sections),
        ];
    }

    /**
     * 各项统计指标（一次算齐，供各成就取用）
     */
    private function stats(int $userId): array
    {
        $db = Db::connect('live_mysql');

        // 未登录（$userId = 0）：全部指标归零，页面展示完整清单但均为未解锁
        if ($userId <= 0) {
            return array_fill_keys([
                'joined', 'profile', 'persona', 'chat', 'collect', 'affection', 'unlock',
                'short', 'shop', 'spend', 'recharge', 'vip', 'vip_plus', 'gift', 'like',
                'pledge', 'merchant', 'days',
            ], 0);
        }

        $user = $db->table('lp_user')
            ->where('id', $userId)
            ->field('nickname, created_at')
            ->find() ?: [];

        $count = function (string $table, array $where) use ($db, $userId): int {
            return (int) $db->table($table)->where('user_id', $userId)->where($where)->count();
        };

        $days = 0;
        if (!empty($user['created_at'])) {
            $days = (int) floor((time() - strtotime((string) $user['created_at'])) / 86400);
        }

        $spend = (float) $db->table('lp_wallet_ledger')
            ->where('user_id', $userId)->where('direction', 2)->sum('amount');

        $affection = (int) $db->table('lp_ai_affection')
            ->where('user_id', $userId)->max('affection');

        $isPlus = (string) ((new VipService())->entitlements($userId)['level'] ?? '') === 'premium_plus';

        return [
            'joined'    => 1,
            'profile'   => trim((string) ($user['nickname'] ?? '')) !== '' ? 1 : 0,
            'persona'   => (int) $db->table('lp_user_persona')->where('user_id', $userId)->count(),
            'chat'      => $count('lp_ai_chat_message', ['role' => 'user']),
            'collect'   => (int) $db->table('lp_ai_collect')->where('user_id', $userId)->count(),
            'affection' => $affection,
            'unlock'    => (int) $db->table('lp_ai_media_unlock')->where('user_id', $userId)->count(),
            'short'     => $count('lp_wallet_ledger', ['biz_type' => 'short_unlock']),
            'shop'      => $count('lp_wallet_ledger', ['biz_type' => 'shop_buy']),
            'spend'     => (int) floor($spend),
            'recharge'  => $count('lp_recharge_order', ['status' => 1]),
            'vip'       => $count('lp_vip_order', ['status' => 1]),
            'vip_plus'  => $isPlus ? 1 : 0,
            'gift'      => $count('lp_gift_order', ['status' => 1]),
            'like'      => (int) $db->table('lp_post_like')->where('user_id', $userId)->count(),
            'pledge'    => (int) $db->table('lp_crowdfunding_pledge')->where('user_id', $userId)->count(),
            'merchant'  => $count('lp_merchant_certification', ['status' => 1]),
            'days'      => max(0, $days),
        ];
    }
}
