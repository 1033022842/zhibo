<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\facade\Db;

class Dashboard extends Backend
{
    public function initialize(): void
    {
        parent::initialize();
    }

    public function index(): void
    {
        $db    = Db::connect('live_mysql');
        $today = date('Y-m-d');

        // ── 统计区间（默认今日，半开区间 [start, end) 避免边界重复计数）──
        [$from, $to] = $this->range();
        $start = $from . ' 00:00:00';
        $end   = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';
        $todayStart = $today . ' 00:00:00';
        $todayEnd   = date('Y-m-d', strtotime($today . ' +1 day')) . ' 00:00:00';

        // ── 用户 ──
        $usersTotal  = $db->table('lp_user')->where('is_deleted', 0)->count();
        $usersToday  = $db->table('lp_user')->where('is_deleted', 0)
            ->where('created_at', '>=', $todayStart)->where('created_at', '<', $todayEnd)->count();
        $usersPeriod = $db->table('lp_user')->where('is_deleted', 0)
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)->count();

        // ── 直播间 ──
        $roomsTotal = $db->table('lp_room')->where('status', 1)->count();
        $roomsLive  = $this->srsLiveCount();

        // ── 充值（统一折算成 USD，status: 0待审 1已通过 2拒绝 3关闭）──
        // 法币单的 pay_amount 存的是本币实付（如 4748.81 INR），不能直接当 USD 累加；
        // 有 usd_amount 的按原始 USD 标价，其余（链上 / 网关）本来就是 USD。
        $usdSum = "SUM(CASE WHEN usd_amount > 0 THEN usd_amount ELSE pay_amount END)";

        $rechargePeriodCnt   = $db->table('lp_recharge_order')->where('status', 1)
            ->where('paid_at', '>=', $start)->where('paid_at', '<', $end)->count();
        $rechargePeriodUsd   = (float) ($db->table('lp_recharge_order')->where('status', 1)
            ->where('paid_at', '>=', $start)->where('paid_at', '<', $end)->value($usdSum) ?: 0);
        $rechargePeriodUsers = (int) ($db->query(
            "SELECT COUNT(DISTINCT user_id) AS c FROM lp_recharge_order "
            . "WHERE status = 1 AND paid_at >= ? AND paid_at < ?",
            [$start, $end]
        )[0]['c'] ?? 0);

        $rechargeTotalCnt = $db->table('lp_recharge_order')->where('status', 1)->count();
        $rechargeTotalUsd = (float) ($db->table('lp_recharge_order')->where('status', 1)->value($usdSum) ?: 0);
        $rechargePending  = $db->table('lp_recharge_order')->where('status', 0)->count();
        $rechargePendingUsd = (float) ($db->table('lp_recharge_order')->where('status', 0)->value($usdSum) ?: 0);

        // 首充：该用户历史上首笔成功充值落在统计区间内；复充 = 区间充值人数 - 首充人数
        $firstRow = $db->query(
            "SELECT COUNT(*) AS c FROM ("
            . "SELECT user_id, MIN(paid_at) AS fp FROM lp_recharge_order "
            . "WHERE status = 1 AND paid_at IS NOT NULL GROUP BY user_id"
            . ") t WHERE t.fp >= ? AND t.fp < ?",
            [$start, $end]
        );
        $rechargeFirstUsers  = (int) ($firstRow[0]['c'] ?? 0);
        $rechargeRepeatUsers = max(0, $rechargePeriodUsers - $rechargeFirstUsers);

        // ── 送礼（代币消耗）──
        $giftPeriodCnt     = $db->table('lp_gift_order')->where('status', 1)
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)->count();
        $giftPeriodDiamonds = (float) $db->table('lp_gift_order')->where('status', 1)
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)->sum('total_price');
        $giftPeriodUsers   = (int) ($db->query(
            "SELECT COUNT(DISTINCT user_id) AS c FROM lp_gift_order "
            . "WHERE status = 1 AND user_id > 0 AND created_at >= ? AND created_at < ?",
            [$start, $end]
        )[0]['c'] ?? 0);

        $giftTotalCnt      = $db->table('lp_gift_order')->where('status', 1)->count();
        $giftTotalDiamonds = (float) $db->table('lp_gift_order')->where('status', 1)->sum('total_price');

        // 主播实收（钱包流水最准；平台抽成不落流水，用「送礼总额 - 主播实收」推导）
        $anchorIncome = (float) $db->table('lp_wallet_ledger')
            ->where('biz_type', 'gift_income')->where('direction', 1)
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)->sum('amount');

        // ── 提现（status: 0待审核 1已打款 2已拒绝）──
        $withdraw = $this->withdrawStats($db, $start, $end);

        // ── 平台代币余额 ──
        $walletDiamonds = (float) $db->table('lp_wallet_account')->sum('diamond_balance');

        // ── 最近充值订单 ──
        $latestRecharge = $db->table('lp_recharge_order')->alias('o')
            ->field("o.order_no, o.pay_amount, o.usd_amount, o.pay_currency, o.fiat_channel_id, o.diamond_amount, o.status, o.created_at, u.nickname, fc.name as fiat_channel_name")
            ->leftJoin('lp_user u', 'u.id = o.user_id')
            ->leftJoin('lp_fiat_channel fc', 'fc.id = o.fiat_channel_id')
            ->order('o.id', 'desc')->limit(8)->select()->toArray();
        $statusMap = [0 => '待审核', 1 => '已通过', 2 => '已拒绝', 3 => '已关闭'];

        // ── 最近送礼 ──
        $latestGift = $db->table('lp_gift_order')->alias('g')
            ->field("g.quantity, g.total_price, g.created_at, u.nickname, r.title room_title, f.name gift_name")
            ->leftJoin('lp_user u', 'u.id = g.user_id')
            ->leftJoin('lp_room r', 'r.id = g.room_id')
            ->leftJoin('lp_gift f', 'f.id = g.gift_id')
            ->where('g.status', 1)
            ->order('g.id', 'desc')->limit(8)->select()->toArray();

        // ── 最近提现 ──
        $latestWithdraw = $this->latestWithdraw($db);
        $withdrawStatusMap = [0 => '待审核', 1 => '已打款', 2 => '已拒绝'];

        $this->success('', [
            'remark' => get_route_remark(),
            'range'  => ['from' => $from, 'to' => $to],
            'stats' => [
                'users_total'           => $usersTotal,
                'users_today'           => $usersToday,
                'users_period'          => $usersPeriod,
                'rooms_total'           => $roomsTotal,
                'rooms_live'            => $roomsLive,

                'recharge_period_usd'   => round($rechargePeriodUsd, 2),
                'recharge_period_cnt'   => $rechargePeriodCnt,
                'recharge_period_users' => $rechargePeriodUsers,
                'recharge_first_users'  => $rechargeFirstUsers,
                'recharge_repeat_users' => $rechargeRepeatUsers,
                'recharge_total_usd'    => round($rechargeTotalUsd, 2),
                'recharge_total_cnt'    => $rechargeTotalCnt,
                'recharge_pending'      => $rechargePending,
                'recharge_pending_usd'  => round($rechargePendingUsd, 2),

                'gift_period_diamonds'  => round($giftPeriodDiamonds, 2),
                'gift_period_cnt'       => $giftPeriodCnt,
                'gift_period_users'     => $giftPeriodUsers,
                'gift_total_diamonds'   => round($giftTotalDiamonds, 2),
                'gift_total_cnt'        => $giftTotalCnt,
                'anchor_income'         => round($anchorIncome, 2),

                'withdraw_period_usd'   => round($withdraw['period_usd'], 2),
                'withdraw_period_cnt'   => $withdraw['period_cnt'],
                'withdraw_period_users' => $withdraw['period_users'],
                'withdraw_total_usd'    => round($withdraw['total_usd'], 2),
                'withdraw_pending'      => $withdraw['pending'],
                'withdraw_pending_usd'  => round($withdraw['pending_usd'], 2),

                'wallet_diamonds'       => round($walletDiamonds, 2),
            ],
            'latest_recharge' => array_map(function ($r) use ($statusMap) {
                $r['status_text']    = $statusMap[(int) $r['status']] ?? '未知';
                $r['pay_amount']     = (float) $r['pay_amount'];
                $r['diamond_amount'] = (int) $r['diamond_amount'];

                // 法币单：法币实付单独成列，USDT 列回到原始 USD 标价，避免「49.99 显示成 4748.81」
                $currency = strtoupper(trim((string) ($r['pay_currency'] ?? '')));
                $isFiat   = $currency !== '';
                $r['fiat_currency'] = $isFiat ? $currency : '';
                $r['fiat_amount']   = $isFiat ? (float) $r['pay_amount'] : null;
                $r['fiat_channel']  = $isFiat ? trim((string) ($r['fiat_channel_name'] ?? '')) : '';
                $r['usd_amount']    = $isFiat && (float) $r['usd_amount'] > 0
                    ? (float) $r['usd_amount']
                    : (float) $r['pay_amount'];
                unset($r['pay_currency'], $r['fiat_channel_id'], $r['fiat_channel_name']);
                return $r;
            }, $latestRecharge),
            'latest_gift' => array_map(function ($r) {
                $r['total_price'] = (float) $r['total_price'];
                return $r;
            }, $latestGift),
            'latest_withdraw' => array_map(function ($r) use ($withdrawStatusMap) {
                $r['status_text']    = $withdrawStatusMap[(int) $r['status']] ?? '未知';
                $r['diamond_amount'] = (float) $r['diamond_amount'];
                $r['actual_usdt']    = (float) $r['actual_usdt'];
                unset($r['usdt_amount']);
                return $r;
            }, $latestWithdraw),
        ]);
    }

    /** 解析统计区间：from / to（Y-m-d），非法或缺失时默认今日 */
    private function range(): array
    {
        $from = trim((string) $this->request->param('from', ''));
        $to   = trim((string) $this->request->param('to', ''));

        $valid = function (string $d): bool {
            return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
        };

        $today = date('Y-m-d');
        $from  = $valid($from) ? $from : $today;
        $to    = $valid($to) ? $to : $today;

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    /** 提现聚合统计（lp_withdrawal 来自升级脚本，缺失时返回全 0 不阻断控制台） */
    private function withdrawStats($db, string $start, string $end): array
    {
        try {
            $periodCnt = $db->table('lp_withdrawal')->where('status', 1)
                ->where('created_at', '>=', $start)->where('created_at', '<', $end)->count();
            $periodUsers = (int) ($db->query(
                "SELECT COUNT(DISTINCT user_id) AS c FROM lp_withdrawal "
                . "WHERE status = 1 AND created_at >= ? AND created_at < ?",
                [$start, $end]
            )[0]['c'] ?? 0);
            $periodUsd = (float) ($db->table('lp_withdrawal')->where('status', 1)
                ->where('created_at', '>=', $start)->where('created_at', '<', $end)->sum('actual_usdt') ?: 0);
            $totalUsd  = (float) ($db->table('lp_withdrawal')->where('status', 1)->sum('actual_usdt') ?: 0);
            $pending   = $db->table('lp_withdrawal')->where('status', 0)->count();
            $pendingUsd = (float) ($db->table('lp_withdrawal')->where('status', 0)->sum('actual_usdt') ?: 0);

            return [
                'period_cnt'  => $periodCnt,
                'period_users' => $periodUsers,
                'period_usd'  => $periodUsd,
                'total_usd'   => $totalUsd,
                'pending'     => $pending,
                'pending_usd' => $pendingUsd,
            ];
        } catch (\Throwable $e) {
            return [
                'period_cnt' => 0, 'period_users' => 0, 'period_usd' => 0, 'total_usd' => 0,
                'pending'    => 0, 'pending_usd' => 0,
            ];
        }
    }

    /** 最近提现记录（表缺失时返回空数组） */
    private function latestWithdraw($db): array
    {
        try {
            return $db->table('lp_withdrawal')->alias('w')
                ->field("w.order_no, w.diamond_amount, w.usdt_amount, w.actual_usdt, w.network, w.status, w.created_at, u.nickname")
                ->leftJoin('lp_user u', 'u.id = w.user_id')
                ->order('w.id', 'desc')->limit(8)->select()->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** SRS 在线推流路数（本机 API，失败返回 -1 表示未知） */
    private function srsLiveCount(): int
    {
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 2]]);
            $raw = @file_get_contents('http://127.0.0.1:1986/api/v1/streams/', false, $ctx);
            if ($raw === false) {
                return -1;
            }
            $data = json_decode($raw, true);
            return isset($data['streams']) ? count($data['streams']) : -1;
        } catch (\Throwable $e) {
            return -1;
        }
    }
}
