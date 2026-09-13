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

        // ── 用户 ──
        $usersTotal = $db->table('lp_user')->count();
        $usersToday = $db->table('lp_user')->whereLike('created_at', $today . '%')->count();

        // ── 直播间 ──
        $roomsTotal = $db->table('lp_room')->where('status', 1)->count();
        $roomsLive  = $this->srsLiveCount();

        // ── 充值（USDT，status: 0待审 1已通过 2拒绝 3关闭）──
        $rechargeTodayCount = $db->table('lp_recharge_order')->where('status', 1)->whereLike('paid_at', $today . '%')->count();
        $rechargeTodayUsd   = (float) $db->table('lp_recharge_order')->where('status', 1)->whereLike('paid_at', $today . '%')->sum('pay_amount');
        $rechargeTotalUsd   = (float) $db->table('lp_recharge_order')->where('status', 1)->sum('pay_amount');
        $rechargePending    = $db->table('lp_recharge_order')->where('status', 0)->count();

        // ── 送礼（钻石消耗）──
        $giftTodayCount    = $db->table('lp_gift_order')->where('status', 1)->whereLike('created_at', $today . '%')->count();
        $giftTodayDiamonds = (float) $db->table('lp_gift_order')->where('status', 1)->whereLike('created_at', $today . '%')->sum('total_price');
        $giftTotalDiamonds = (float) $db->table('lp_gift_order')->where('status', 1)->sum('total_price');

        // ── 平台钻石余额 ──
        $walletDiamonds = (float) $db->table('lp_wallet_account')->sum('diamond_balance');

        // ── 近 7 天趋势（送礼钻石 / 充值USD / 新增用户）──
        $start = date('Y-m-d', strtotime('-6 days'));
        $rows = $db->table('lp_gift_order')
            ->field("DATE(created_at) d, COUNT(*) c, SUM(total_price) diamonds")
            ->where('status', 1)->where('created_at', '>=', $start)
            ->group('d')->select()->toArray();
        $giftByDay = array_column($rows, null, 'd');

        $rows = $db->table('lp_recharge_order')
            ->field("DATE(paid_at) d, SUM(pay_amount) usd")
            ->where('status', 1)->where('paid_at', '>=', $start)
            ->group('d')->select()->toArray();
        $rechargeByDay = array_column($rows, null, 'd');

        $rows = $db->table('lp_user')
            ->field("DATE(created_at) d, COUNT(*) c")
            ->where('created_at', '>=', $start)
            ->group('d')->select()->toArray();
        $userByDay = array_column($rows, null, 'd');

        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $trend[] = [
                'date'          => substr($d, 5),
                'gift_diamonds' => (float) ($giftByDay[$d]['diamonds'] ?? 0),
                'gift_count'    => (int) ($giftByDay[$d]['c'] ?? 0),
                'recharge_usd'  => (float) ($rechargeByDay[$d]['usd'] ?? 0),
                'new_users'     => (int) ($userByDay[$d]['c'] ?? 0),
            ];
        }

        // ── 最近充值订单 ──
        $latestRecharge = $db->table('lp_recharge_order')->alias('o')
            ->field("o.order_no, o.pay_amount, o.diamond_amount, o.status, o.created_at, u.nickname")
            ->leftJoin('lp_user u', 'u.id = o.user_id')
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

        $this->success('', [
            'remark' => get_route_remark(),
            'stats' => [
                'users_total'         => $usersTotal,
                'users_today'         => $usersToday,
                'rooms_total'         => $roomsTotal,
                'rooms_live'          => $roomsLive,
                'recharge_today_usd'  => round($rechargeTodayUsd, 2),
                'recharge_today_cnt'  => $rechargeTodayCount,
                'recharge_pending'    => $rechargePending,
                'recharge_total_usd'  => round($rechargeTotalUsd, 2),
                'gift_today_diamonds' => round($giftTodayDiamonds, 2),
                'gift_today_cnt'      => $giftTodayCount,
                'gift_total_diamonds' => round($giftTotalDiamonds, 2),
                'wallet_diamonds'     => round($walletDiamonds, 2),
            ],
            'trend'          => $trend,
            'latest_recharge'=> array_map(function ($r) use ($statusMap) {
                $r['status_text']    = $statusMap[(int) $r['status']] ?? '未知';
                $r['pay_amount']     = (float) $r['pay_amount'];
                $r['diamond_amount'] = (int) $r['diamond_amount'];
                return $r;
            }, $latestRecharge),
            'latest_gift'    => array_map(function ($r) {
                $r['total_price'] = (float) $r['total_price'];
                return $r;
            }, $latestGift),
        ]);
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
