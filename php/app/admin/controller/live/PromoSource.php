<?php
/**
 * 流量来源（访问 / 注册 / 付费 多维度报表）
 * 访问: /admin/live.PromoSource/index?_key=<ADMIN_FLOAT_KEY>
 *
 * 数据来源：
 *   访问：lp_promo_visit（前台 tracking.js 每个会话落地写一行，按访问时间筛选）
 *   注册：lp_user 的 reg_* 首触归因（按注册时间筛选）
 *   付费：lp_recharge_order 已支付订单（按会员注册时间筛选，口径同会员列表）
 *
 * 归因维度：
 *   平台      reg_platform   （x / instagram / tiktok / reddit / threads / youtube …）
 *   来源域名  reg_domain
 *   推广链接  reg_link_name
 *   渠道(ch)  reg_channel
 *   渠道/像素 reg_px
 *   来源网站  lp_promo_visit.referer（只有访问数据，注册/付费无法按 Referer 拆）
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\live\service\PromoService;
use think\facade\Db;

final class PromoSource extends Backend
{
    protected array $noNeedLogin = ['index', 'members'];

    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    /**
     * 可选归因维度：key => [名称, lp_promo_visit 列, lp_user 列(null=只统计访问)]
     */
    private const DIMS = [
        'platform' => ['平台', 'platform', 'reg_platform'],
        'domain'   => ['来源域名', 'domain', 'reg_domain'],
        'link'     => ['推广链接名称', 'link_name', 'reg_link_name'],
        'channel'  => ['渠道(ch)', 'channel', 'reg_channel'],
        'px'       => ['渠道 / 像素', 'px', 'reg_px'],
        'referer'  => ['来源网站(Referer)', 'referer', null],
    ];

    public function index(): void
    {
        $db = Db::connect('live_mysql');

        $dim = (string) $this->request->param('dim', 'platform');
        if (!isset(self::DIMS[$dim])) {
            $dim = 'platform';
        }
        [$dimLabel, $visitCol, $userCol] = self::DIMS[$dim];

        $from = trim((string) $this->request->param('from', ''));
        $to   = trim((string) $this->request->param('to', ''));
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : '';
        $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : '';

        // 各表按自己的事件时间过滤（访问=访问时间，注册=注册时间，付费=会员注册时间）
        $range = static function (string $col) use ($from, $to): array {
            $where = [];
            $bind  = [];
            if ($from !== '') {
                $where[] = $col . ' >= ?';
                $bind[]  = $from . ' 00:00:00';
            }
            if ($to !== '') {
                $where[] = $col . ' <= ?';
                $bind[]  = $to . ' 23:59:59';
            }
            return [$where === [] ? '' : ' AND ' . implode(' AND ', $where), $bind];
        };

        [$visitWhere, $visitBind] = $range('v.created_at');
        [$userWhere, $userBind]   = $range('u.created_at');

        // ---------- 访问 ----------
        $visitRows  = [];
        $visitReady = true;
        try {
            $visitRows = $db->query(
                "SELECT v.`{$visitCol}` AS k, COUNT(*) AS pv,
                        COUNT(DISTINCT CASE WHEN v.vid <> '' THEN v.vid ELSE v.sid END) AS uv
                 FROM lp_promo_visit v WHERE 1=1 {$visitWhere}
                 GROUP BY v.`{$visitCol}`",
                $visitBind
            );
        } catch (\Throwable $e) {
            // 升级脚本还没跑时不拖垮整个报表
            $visitReady = false;
        }
        $visitMap = [];
        foreach ($visitRows as $r) {
            $visitMap[(string) ($r['k'] ?? '')] = [
                'pv' => (int) ($r['pv'] ?? 0),
                'uv' => (int) ($r['uv'] ?? 0),
            ];
        }

        // ---------- 注册 / 付费 ----------
        $regMap = [];
        $payMap = [];
        $subMap = [];
        if ($userCol !== null) {
            foreach ($db->query(
                "SELECT u.`{$userCol}` AS k, COUNT(*) AS c
                 FROM lp_user u WHERE 1=1 {$userWhere}
                 GROUP BY u.`{$userCol}`",
                $userBind
            ) as $r) {
                $regMap[(string) ($r['k'] ?? '')] = (int) ($r['c'] ?? 0);
            }

            foreach ($db->query(
                "SELECT u.`{$userCol}` AS k, COUNT(DISTINCT o.user_id) AS buyers,
                        IFNULL(SUM(o.pay_amount),0) AS amount
                 FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
                 WHERE o.status = 1 AND o.biz_type <> 'vip' {$userWhere}
                 GROUP BY u.`{$userCol}`",
                $userBind
            ) as $r) {
                $payMap[(string) ($r['k'] ?? '')] = [
                    'buyers' => (int) ($r['buyers'] ?? 0),
                    'amount' => (float) ($r['amount'] ?? 0),
                ];
            }

            foreach ($db->query(
                "SELECT u.`{$userCol}` AS k, COUNT(DISTINCT o.user_id) AS subs,
                        IFNULL(SUM(o.pay_amount),0) AS amount
                 FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
                 WHERE o.status = 1 AND o.biz_type = 'vip' {$userWhere}
                 GROUP BY u.`{$userCol}`",
                $userBind
            ) as $r) {
                $subMap[(string) ($r['k'] ?? '')] = [
                    'subs'   => (int) ($r['subs'] ?? 0),
                    'amount' => (float) ($r['amount'] ?? 0),
                ];
            }
        }

        // ---------- 顶部汇总 ----------
        $totalVisit = $visitReady ? ($db->query(
            "SELECT COUNT(*) AS pv, COUNT(DISTINCT CASE WHEN v.vid <> '' THEN v.vid ELSE v.sid END) AS uv
             FROM lp_promo_visit v WHERE 1=1 {$visitWhere}",
            $visitBind
        )[0] ?? []) : [];
        $totalPv = (int) ($totalVisit['pv'] ?? 0);
        $totalUv = (int) ($totalVisit['uv'] ?? 0);

        $totalRegs = (int) ($db->query(
            "SELECT COUNT(*) AS c FROM lp_user u WHERE 1=1 {$userWhere}",
            $userBind
        )[0]['c'] ?? 0);

        $payAgg = $db->query(
            "SELECT COUNT(DISTINCT o.user_id) AS buyers, IFNULL(SUM(o.pay_amount),0) AS amount
             FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
             WHERE o.status = 1 AND o.biz_type <> 'vip' {$userWhere}",
            $userBind
        )[0] ?? [];
        $subAgg = $db->query(
            "SELECT COUNT(DISTINCT o.user_id) AS subs, IFNULL(SUM(o.pay_amount),0) AS amount
             FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
             WHERE o.status = 1 AND o.biz_type = 'vip' {$userWhere}",
            $userBind
        )[0] ?? [];
        $payAgg = [
            'buyers' => (int) ($payAgg['buyers'] ?? 0),
            'amount' => (float) ($payAgg['amount'] ?? 0),
        ];
        $subAgg = [
            'subs'   => (int) ($subAgg['subs'] ?? 0),
            'amount' => (float) ($subAgg['amount'] ?? 0),
        ];

        // ---------- 按平台总览（固定展示，便于直接看各平台投入产出） ----------
        $pfVisitMap = [];
        if ($visitReady) {
            foreach ($db->query(
                "SELECT v.platform AS k, COUNT(*) AS pv,
                        COUNT(DISTINCT CASE WHEN v.vid <> '' THEN v.vid ELSE v.sid END) AS uv
                 FROM lp_promo_visit v WHERE 1=1 {$visitWhere}
                 GROUP BY v.platform",
                $visitBind
            ) as $r) {
                $pfVisitMap[(string) ($r['k'] ?? '')] = [
                    'pv' => (int) ($r['pv'] ?? 0),
                    'uv' => (int) ($r['uv'] ?? 0),
                ];
            }
        }
        $pfRegMap = [];
        $pfPayMap = [];
        foreach ($db->query(
            "SELECT u.reg_platform AS k, COUNT(*) AS c
             FROM lp_user u WHERE 1=1 {$userWhere}
             GROUP BY u.reg_platform",
            $userBind
        ) as $r) {
            $pfRegMap[(string) ($r['k'] ?? '')] = (int) ($r['c'] ?? 0);
        }
        foreach ($db->query(
            "SELECT u.reg_platform AS k, COUNT(DISTINCT o.user_id) AS buyers,
                    IFNULL(SUM(o.pay_amount),0) AS amount
             FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
             WHERE o.status = 1 AND o.biz_type <> 'vip' {$userWhere}
             GROUP BY u.reg_platform",
            $userBind
        ) as $r) {
            $pfPayMap[(string) ($r['k'] ?? '')] = [
                'buyers' => (int) ($r['buyers'] ?? 0),
                'amount' => (float) ($r['amount'] ?? 0),
            ];
        }

        $pfKeys = array_values(array_unique(array_merge(
            array_keys($pfVisitMap),
            array_keys($pfRegMap),
            array_keys($pfPayMap)
        )));
        usort($pfKeys, static function ($a, $b) use ($pfVisitMap, $pfRegMap): int {
            $va = (int) ($pfVisitMap[$a]['pv'] ?? 0);
            $vb = (int) ($pfVisitMap[$b]['pv'] ?? 0);
            if ($va !== $vb) {
                return $vb <=> $va;
            }
            return ((int) ($pfRegMap[$b] ?? 0)) <=> ((int) ($pfRegMap[$a] ?? 0));
        });
        $pfKeys = array_slice($pfKeys, 0, 12);
        $pfMaxPv = 0;
        foreach ($pfKeys as $k) {
            $pfMaxPv = max($pfMaxPv, (int) ($pfVisitMap[$k]['pv'] ?? 0));
        }

        $platformBars = '';
        foreach ($pfKeys as $k) {
            $key      = (string) $k;
            $label    = PromoService::platformLabel($key);
            $pv       = (int) ($pfVisitMap[$key]['pv'] ?? 0);
            $uv       = (int) ($pfVisitMap[$key]['uv'] ?? 0);
            $regs     = (int) ($pfRegMap[$key] ?? 0);
            $buyers   = (int) ($pfPayMap[$key]['buyers'] ?? 0);
            $amount   = (float) ($pfPayMap[$key]['amount'] ?? 0);
            $width    = $pfMaxPv > 0 ? max(2, round($pv / $pfMaxPv * 100)) : 2;
            $regRate  = $uv > 0 ? number_format($regs / $uv * 100, 1) . '%' : '—';
            $payRate  = $regs > 0 ? number_format($buyers / $regs * 100, 1) . '%' : '—';
            $platformBars .= '<div class="pf-row">'
                . '<div class="pf-name" title="' . htmlspecialchars($key, ENT_QUOTES) . '">' . htmlspecialchars($label, ENT_QUOTES) . '</div>'
                . '<div class="pf-bar"><span style="width:' . $width . '%"></span></div>'
                . '<div class="pf-nums"><b>' . number_format($pv) . '</b> 访问 · '
                    . number_format($uv) . ' 访客 · ' . number_format($regs) . ' 注册 · '
                    . number_format($buyers) . ' 付费</div>'
                . '<div class="pf-side">注册 ' . $regRate . ' · 付费 ' . $payRate . ' · $' . number_format($amount, 2) . '</div>'
                . '</div>';
        }
        if (!$visitReady) {
            $platformBars = '<div class="empty">访问表尚未创建，请先在数据库执行 <b>php/sql/upgrade_promo_visit.sql</b></div>';
        } elseif ($platformBars === '') {
            $platformBars = '<div class="empty">暂无访问数据（前台 tracking.js 上报后自动出现）</div>';
        }

        // ---------- 表格 ----------
        $keys = array_values(array_unique(array_merge(
            array_keys($visitMap),
            array_keys($regMap),
            array_keys($payMap),
            array_keys($subMap)
        )));
        usort($keys, static function ($a, $b) use ($visitMap, $regMap): int {
            $ua = (int) ($visitMap[$a]['uv'] ?? 0);
            $ub = (int) ($visitMap[$b]['uv'] ?? 0);
            if ($ua !== $ub) {
                return $ub <=> $ua;
            }
            return ((int) ($regMap[$b] ?? 0)) <=> ((int) ($regMap[$a] ?? 0));
        });

        $tableRows = '';
        foreach (array_slice($keys, 0, 500) as $k) {
            $key    = (string) $k;
            $visits = $visitMap[$key] ?? ['pv' => 0, 'uv' => 0];
            $regs   = (int) ($regMap[$key] ?? 0);
            $pay    = $payMap[$key] ?? ['buyers' => 0, 'amount' => 0.0];
            $sub    = $subMap[$key] ?? ['subs' => 0, 'amount' => 0.0];

            $uv = (int) $visits['uv'];
            $pv = (int) $visits['pv'];
            $regRate = $userCol !== null && $uv > 0 ? number_format($regs / $uv * 100, 1) . '%' : '—';
            $payRate = $userCol !== null && $regs > 0 ? number_format((int) $pay['buyers'] / $regs * 100, 1) . '%' : '—';
            $payRateUv = $userCol !== null && $uv > 0 ? number_format((int) $pay['buyers'] / $uv * 100, 1) . '%' : '—';

            if ($key === '') {
                $label = '<span class="muted">' . ($dim === 'domain' ? '未知 / 直连' : '未填 / 未识别') . '</span>';
            } else {
                $text  = $dim === 'platform' ? PromoService::platformLabel($key) : $key;
                $label = htmlspecialchars($text);
            }
            $keyEsc = htmlspecialchars($key, ENT_QUOTES);
            $ops    = $userCol !== null
                ? '<button type="button" class="btn-link" data-dim="' . $dim . '" data-val="' . $keyEsc . '">查看会员</button>'
                : '<span class="muted">—</span>';

            $tableRows .= '<tr>'
                . '<td>' . $label . '</td>'
                . '<td class="num">' . number_format($uv) . '</td>'
                . '<td class="num">' . number_format($pv) . '</td>'
                . '<td class="num">' . number_format($regs) . '</td>'
                . '<td class="num">' . $regRate . '</td>'
                . '<td class="num">' . (int) $pay['buyers'] . '</td>'
                . '<td class="num">' . $payRate . '</td>'
                . '<td class="num">' . $payRateUv . '</td>'
                . '<td class="num">' . number_format((float) $pay['amount'], 2) . '</td>'
                . '<td class="num">' . (int) $sub['subs'] . ' / ' . number_format((float) $sub['amount'], 2) . '</td>'
                . '<td>' . $ops . '</td>'
                . '</tr>';
        }
        if ($tableRows === '') {
            $tableRows = '<tr><td colspan="11" class="empty">该条件下暂无数据</td></tr>';
        }

        // ---------- 表单 ----------
        $dimOptions = '';
        foreach (self::DIMS as $key => [$name, $_v, $_u]) {
            $sel = $key === $dim ? ' selected' : '';
            $dimOptions .= '<option value="' . $key . '"' . $sel . '>' . $name . '</option>';
        }

        $today = date('Y-m-d');
        $d7    = date('Y-m-d', strtotime('-6 day'));
        $d30   = date('Y-m-d', strtotime('-29 day'));
        $qs    = static fn(string $f, string $t): string => '/admin/live.PromoSource/index?dim=' . urlencode($dim)
            . ($f !== '' ? '&from=' . $f : '') . ($t !== '' ? '&to=' . $t : '');
        $presets = '<a href="' . $qs($today, $today) . '">今天</a>'
            . '<a href="' . $qs($d7, $today) . '">近7天</a>'
            . '<a href="' . $qs($d30, $today) . '">近30天</a>'
            . '<a href="/admin/live.PromoSource/index?dim=' . urlencode($dim) . '">全部</a>';

        $domainCount = count((new PromoService())->siteDomains());
        $dimTip      = $userCol === null
            ? '<span class="muted" style="font-weight:400"> · 该维度只有访问数据，注册/付费请切到「平台」</span>'
            : '<span class="muted" style="font-weight:400"> · 点「查看会员」看该来源的注册用户</span>';

        $totalPvFmt   = number_format($totalPv);
        $totalUvFmt   = number_format($totalUv);
        $totalRegsFmt = number_format($totalRegs);
        $regRateFmt   = $totalUv > 0 ? number_format($totalRegs / $totalUv * 100, 1) . '%' : '—';
        $buyersFmt    = number_format((int) ($payAgg['buyers'] ?? 0));
        $payRateFmt   = $totalRegs > 0 ? number_format((int) ($payAgg['buyers'] ?? 0) / $totalRegs * 100, 1) . '%' : '—';
        $payAmtFmt    = number_format((float) ($payAgg['amount'] ?? 0), 2);
        $subsFmt      = number_format((int) ($subAgg['subs'] ?? 0));
        $subAmtFmt    = number_format((float) ($subAgg['amount'] ?? 0), 2);
        $fromVal      = htmlspecialchars($from, ENT_QUOTES);
        $toVal        = htmlspecialchars($to, ENT_QUOTES);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>流量来源</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;background:#f4f5fb;color:#1f2430;font-size:14px}
.container{max-width:1320px;margin:0 auto;padding:28px 24px 60px}
h1{font-size:23px;font-weight:700;color:#1a1f36}
.sub{margin-top:6px;font-size:13px;color:#8a90a3;line-height:1.7}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin:22px 0}
.stat{background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:16px;box-shadow:0 1px 3px rgba(30,34,60,.04)}
.stat .v{font-size:20px;font-weight:700;color:#1a1f36}
.stat .k{font-size:12px;color:#8a90a3;margin-top:3px}
.stat .s{font-size:12px;color:#6366f1;margin-top:5px}
.card{background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:20px;margin-bottom:18px;box-shadow:0 1px 3px rgba(30,34,60,.04)}
.card h2{font-size:15px;font-weight:700;margin-bottom:14px;color:#1a1f36}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
select,input{height:38px;padding:0 12px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;color:#1f2430;background:#fff;outline:none}
select{min-width:190px}
input{flex:1;min-width:180px}
.btn{height:38px;padding:0 18px;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff}
.presets a{display:inline-block;margin-right:10px;font-size:13px;color:#6366f1;text-decoration:none}
.presets a:hover{text-decoration:underline}
table{width:100%;border-collapse:collapse}
th{background:#fafbfe;font-weight:600;font-size:12px;color:#8a90a3;text-align:left;padding:12px 12px;border-bottom:1px solid #eef0f6;white-space:nowrap}
td{padding:12px 12px;border-bottom:1px solid #f2f3f8;font-size:13px;color:#3a4051}
td.num,th.num{text-align:right;font-variant-numeric:tabular-nums}
tr:last-child td{border-bottom:none}
.empty{text-align:center;color:#9aa0b0;padding:26px 0}
.muted{color:#9aa0b0}
.tip{font-size:12px;color:#9aa0b0;margin-top:10px;line-height:1.8}
.btn-link{padding:4px 10px;border:1px solid #6366f1;border-radius:8px;background:#fff;color:#6366f1;font-size:12px;cursor:pointer}
.btn-link:hover{background:#eef0ff}
/* 平台分布 */
.pf-row{display:grid;grid-template-columns:120px 1fr 270px 210px;align-items:center;gap:14px;padding:9px 0;border-bottom:1px solid #f6f7fb}
.pf-row:last-child{border-bottom:none}
.pf-name{font-weight:600;color:#1a1f36;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pf-bar{height:12px;border-radius:6px;background:#eef0f8;overflow:hidden}
.pf-bar span{display:block;height:100%;border-radius:6px;background:linear-gradient(90deg,#4f7cff,#7aa2ff)}
.pf-nums{font-size:12px;color:#5b6072;font-variant-numeric:tabular-nums}
.pf-nums b{color:#1a1f36}
.pf-side{font-size:12px;color:#8a90a3;text-align:right;font-variant-numeric:tabular-nums}
@media (max-width:900px){.pf-row{grid-template-columns:100px 1fr}.pf-nums,.pf-side{display:none}}
/* 会员明细弹窗 */
.mem-mask{display:none;position:fixed;inset:0;z-index:1000;background:rgba(20,22,40,.45)}
.mem-mask.open{display:flex;align-items:center;justify-content:center}
.mem-panel{width:min(1080px,94vw);max-height:82vh;display:flex;flex-direction:column;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 20px 60px rgba(20,22,40,.25)}
.mem-head{display:flex;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid #eef0f6}
.mem-head b{font-size:15px;color:#1a1f36}
.mem-head .mem-x{margin-left:auto;width:28px;height:28px;border:none;border-radius:8px;background:#f2f3f8;color:#6b7280;cursor:pointer}
.mem-body{overflow:auto;padding:4px 6px 10px}
</style>
</head>
<body>
<div class="container">
    <h1>流量来源</h1>
    <div class="sub">看清每个平台带来多少<b>访问 → 注册 → 付费</b>：X / TikTok / Instagram / Reddit / Threads / YouTube / Facebook / Google 等。
        访问按<b>访问时间</b>统计，注册按<b>注册时间</b>，充值/订阅取「已支付订单」。</div>

    <div class="stats">
        <div class="stat"><div class="v">{$totalUvFmt}</div><div class="k">访客数（UV）</div></div>
        <div class="stat"><div class="v">{$totalPvFmt}</div><div class="k">访问次数（点击）</div></div>
        <div class="stat"><div class="v">{$totalRegsFmt}</div><div class="k">注册数</div><div class="s">注册率 {$regRateFmt}</div></div>
        <div class="stat"><div class="v">{$buyersFmt}</div><div class="k">付费人数</div><div class="s">付费率 {$payRateFmt}</div></div>
        <div class="stat"><div class="v">{$payAmtFmt}</div><div class="k">充值金额(USD)</div></div>
        <div class="stat"><div class="v">{$subsFmt} / {$subAmtFmt}</div><div class="k">订阅人数 / 金额(USD)</div></div>
    </div>

    <div class="card">
        <h2>筛选</h2>
        <form class="row" method="get" action="/admin/live.PromoSource/index">
            <select name="dim">{$dimOptions}</select>
            <input type="date" name="from" value="{$fromVal}" style="flex:0 0 170px">
            <span class="muted">至</span>
            <input type="date" name="to" value="{$toVal}" style="flex:0 0 170px">
            <button class="btn" type="submit">查询</button>
        </form>
        <div class="presets" style="margin-top:10px">{$presets}</div>
        <div class="tip">时间口径：访问按访问时间、注册按注册时间、订单按会员注册时间。跨阶段对比建议用同一时间段看趋势，不要拿「今天访问」直接除「今天注册」当最终转化率。</div>
    </div>

    <div class="card">
        <h2>平台分布<span class="muted" style="font-weight:400"> · 访问量 / 注册 / 付费（当前时间范围）</span></h2>
        {$platformBars}
        <div class="tip">平台识别优先级：推广链接参数（?px= / ?ch= / ?l=）→ 链接名称关键词 → Referer 域名。
            社媒 App 内置浏览器经常不带 Referer，<b>给每个平台单独生成一条推广链接</b>（渠道配置里选对应平台后复制链接）最准确。
            <a href="/admin/live.PromoSetting/index?tab=config" style="color:#6366f1;text-decoration:none">去渠道配置创建平台链接 →</a></div>
    </div>

    <div class="card">
        <h2>按{$dimLabel}统计{$dimTip}</h2>
        <table>
            <thead><tr>
                <th>{$dimLabel}</th><th class="num">访客数</th><th class="num">访问次数</th>
                <th class="num">注册数</th><th class="num">注册率</th>
                <th class="num">付费人数</th><th class="num">付费率(注册)</th><th class="num">付费率(访客)</th>
                <th class="num">充值金额</th><th class="num">订阅人数 / 金额</th><th>操作</th>
            </tr></thead>
            <tbody>{$tableRows}</tbody>
        </table>
        <div class="tip">当前已收录 <b>{$domainCount}</b> 个推广域名。曝光量（展现）无法从站内采集：自然流量平台不提供展现数据，付费广告需接各平台 Marketing API；本页提供的是站内可验证的<b>点击/访客/注册/付费</b>。</div>
    </div>
</div>

<div id="memMask" class="mem-mask">
    <div class="mem-panel">
        <div class="mem-head">
            <b id="memTitle">会员明细</b>
            <span id="memSum" class="muted"></span>
            <button type="button" class="mem-x" onclick="closeMembers()">✕</button>
        </div>
        <div class="mem-body">
            <table>
                <thead><tr><th>ID</th><th>昵称</th><th>邮箱</th><th>平台</th><th>渠道</th><th>推广链接</th><th>注册时间</th><th class="num">充值</th><th class="num">订阅</th></tr></thead>
                <tbody id="memRows"></tbody>
            </table>
        </div>
    </div>
</div>
<script>
function esc(s){
    return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
}
function openMembers(dim, val, label){
    document.getElementById('memTitle').textContent = '会员明细 · ' + label;
    document.getElementById('memSum').textContent = '加载中…';
    document.getElementById('memRows').innerHTML = '';
    document.getElementById('memMask').classList.add('open');
    fetch('/admin/live.PromoSource/members?dim=' + encodeURIComponent(dim) + '&val=' + encodeURIComponent(val))
        .then(function(r){ return r.json(); })
        .then(function(d){
            var rows = (d && d.data && d.data.list) || [];
            var sum = (d && d.data) || {};
            document.getElementById('memSum').textContent =
                '共 ' + (sum.total || 0) + ' 人 · 充值 ' + (sum.buyers || 0) + ' 人 / ' + (sum.amount || 0)
                + ' · 订阅 ' + (sum.subs || 0) + ' 人 / ' + (sum.sub_amount || 0);
            if(!rows.length){ document.getElementById('memRows').innerHTML = '<tr><td colspan="9" class="empty">该来源暂无注册会员</td></tr>'; return; }
            document.getElementById('memRows').innerHTML = rows.map(function(u){
                return '<tr>'
                    + '<td>' + esc(u.id) + '</td>'
                    + '<td>' + (esc(u.nickname) || '—') + '</td>'
                    + '<td>' + (esc(u.email) || '—') + '</td>'
                    + '<td>' + (esc(u.reg_platform) || '—') + '</td>'
                    + '<td>' + (esc(u.reg_channel) || '—') + '</td>'
                    + '<td>' + (esc(u.reg_link_name) || '—') + '</td>'
                    + '<td>' + esc(u.created_at) + '</td>'
                    + '<td class="num">' + esc(u.paid) + '</td>'
                    + '<td class="num">' + esc(u.sub_paid) + '</td>'
                    + '</tr>';
            }).join('');
        })
        .catch(function(){
            document.getElementById('memSum').textContent = '读取失败';
        });
}
function closeMembers(){
    document.getElementById('memMask').classList.remove('open');
}
document.addEventListener('click', function(e){
    if(e.target && e.target.id === 'memMask') closeMembers();
    var btn = e.target && e.target.closest ? e.target.closest('.btn-link') : null;
    if(!btn) return;
    var dim = btn.getAttribute('data-dim');
    var val = btn.getAttribute('data-val') || '';
    var label = val === '' ? '未填 / 未知' : val;
    openMembers(dim, val, label);
});
</script>
</body>
</html>
HTML;

        response($html)->send();
        exit;
    }

    /**
     * 某个维度值下的注册会员明细（本页「查看会员」用）
     * 参数：dim（维度 key），val（维度值，可为空字符串）
     */
    public function members(): void
    {
        $dim = (string) $this->request->param('dim', 'platform');
        if (!isset(self::DIMS[$dim])) {
            $dim = 'platform';
        }
        [, , $col] = self::DIMS[$dim];
        $val = (string) $this->request->param('val', '');

        if ($col === null) {
            $payload = json_encode([
                'code' => '00000',
                'msg'  => 'ok',
                'data' => ['total' => 0, 'buyers' => 0, 'subs' => 0, 'amount' => '0.00', 'sub_amount' => '0.00', 'list' => []],
            ], JSON_UNESCAPED_UNICODE);
            response($payload, 200, ['Content-Type' => 'application/json; charset=utf-8'])->send();
            exit;
        }

        $db = Db::connect('live_mysql');

        $agg = $db->query("SELECT COUNT(DISTINCT u.id) AS total,
                COUNT(DISTINCT p.user_id) AS buyers,
                COUNT(DISTINCT s.user_id) AS subs
            FROM lp_user u
            LEFT JOIN lp_recharge_order p ON p.user_id = u.id AND p.status = 1 AND p.biz_type <> 'vip'
            LEFT JOIN lp_recharge_order s ON s.user_id = u.id AND s.status = 1 AND s.biz_type = 'vip'
            WHERE u.`{$col}` = :v", ['v' => $val])[0] ?? [];

        // JOIN 会因多订单产生笛卡尔行，金额改用子查询单独统计
        $amounts = $db->query("SELECT
                IFNULL((SELECT SUM(o.pay_amount) FROM lp_recharge_order o JOIN lp_user u2 ON u2.id = o.user_id
                        WHERE u2.`{$col}` = :v1 AND o.status = 1 AND o.biz_type <> 'vip'),0) AS amount,
                IFNULL((SELECT SUM(o.pay_amount) FROM lp_recharge_order o JOIN lp_user u2 ON u2.id = o.user_id
                        WHERE u2.`{$col}` = :v2 AND o.status = 1 AND o.biz_type = 'vip'),0) AS sub_amount",
            ['v1' => $val, 'v2' => $val])[0] ?? [];

        $list = $db->query("SELECT u.id, u.user_no, u.nickname, u.email,
                u.reg_platform, u.reg_channel, u.reg_link_name, u.created_at,
                IFNULL((SELECT SUM(o.pay_amount) FROM lp_recharge_order o
                        WHERE o.user_id = u.id AND o.status = 1 AND o.biz_type <> 'vip'),0) AS paid,
                IFNULL((SELECT SUM(o.pay_amount) FROM lp_recharge_order o
                        WHERE o.user_id = u.id AND o.status = 1 AND o.biz_type = 'vip'),0) AS sub_paid
            FROM lp_user u
            WHERE u.`{$col}` = :v
            ORDER BY u.id DESC
            LIMIT 300", ['v' => $val]);

        foreach ($list as &$u) {
            $u['paid']     = number_format((float) ($u['paid'] ?? 0), 2, '.', '');
            $u['sub_paid'] = number_format((float) ($u['sub_paid'] ?? 0), 2, '.', '');
        }
        unset($u);

        $payload = json_encode([
            'code' => '00000',
            'msg'  => 'ok',
            'data' => [
                'total'      => (int) ($agg['total'] ?? 0),
                'buyers'     => (int) ($agg['buyers'] ?? 0),
                'subs'       => (int) ($agg['subs'] ?? 0),
                'amount'     => number_format((float) ($amounts['amount'] ?? 0), 2, '.', ''),
                'sub_amount' => number_format((float) ($amounts['sub_amount'] ?? 0), 2, '.', ''),
                'list'       => $list,
            ],
        ], JSON_UNESCAPED_UNICODE);

        response($payload, 200, ['Content-Type' => 'application/json; charset=utf-8'])->send();
        exit;
    }
}
