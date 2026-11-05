<?php
/**
 * 域名访问统计（UV / PV / IP）
 * 访问: /admin/live.VisitReport/index?_key=<ADMIN_FLOAT_KEY>
 *       （后台菜单「营销推广 → 域名访问统计」iframe 内嵌，FloatAuth Cookie 免 _key）
 *
 * 数据来源：lp_promo_visit（前台 tracking.js 每个标签页会话落地写一行）。
 * 口径：
 *   PV = 行数（一个标签页会话一次上报，即访问次数）
 *   UV = COUNT(DISTINCT vid)（浏览器维度，vid 为空回退 sid）
 *   IP = COUNT(DISTINCT ip)
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\live\service\AgentAdminView;
use think\facade\Db;

final class VisitReport extends Backend
{
    protected array $noNeedLogin = ['index'];

    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    public function index(): void
    {
        $db = Db::connect('live_mysql');

        $from = trim((string) $this->request->param('from', ''));
        $to   = trim((string) $this->request->param('to', ''));
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-d', strtotime('-6 day'));
        $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : date('Y-m-d');

        $domain = trim((string) $this->request->param('domain', ''));
        $domain = preg_match('/^[a-z0-9.\-]{1,120}$/i', $domain) ? strtolower($domain) : '';

        $range = static function (string $col) use ($from, $to, $domain): array {
            $where = ["{$col} >= ?", "{$col} <= ?"];
            $bind  = [$from . ' 00:00:00', $to . ' 23:59:59'];
            if ($domain !== '') {
                $where[] = 'domain = ?';
                $bind[]  = $domain;
            }
            return [' AND ' . implode(' AND ', $where), $bind];
        };

        // 表不存在（升级脚本未跑）时不拖垮页面
        try {
            // 按域名汇总
            [$w1, $b1] = $range('created_at');
            $byDomain = $db->query(
                "SELECT domain,
                        COUNT(*) AS pv,
                        COUNT(DISTINCT NULLIF(vid, '')) AS uv,
                        COUNT(DISTINCT NULLIF(ip, '')) AS ip
                 FROM lp_promo_visit WHERE 1=1 {$w1}
                 GROUP BY domain ORDER BY pv DESC",
                $b1
            );

            // 按日趋势（全域名或指定域名）
            [$w2, $b2] = $range('created_at');
            $byDay = $db->query(
                "SELECT DATE(created_at) AS d,
                        COUNT(*) AS pv,
                        COUNT(DISTINCT NULLIF(vid, '')) AS uv,
                        COUNT(DISTINCT NULLIF(ip, '')) AS ip
                 FROM lp_promo_visit WHERE 1=1 {$w2}
                 GROUP BY DATE(created_at) ORDER BY d DESC LIMIT 120",
                $b2
            );

            $sumPv = $sumUv = $sumIp = 0;
            foreach ($byDomain as $r) {
                $sumPv += (int) $r['pv'];
                $sumUv += (int) $r['uv'];
                $sumIp += (int) $r['ip'];
            }
            $ready = true;
        } catch (\Throwable $e) {
            $ready = false;
            $byDomain = $byDay = [];
            $sumPv = $sumUv = $sumIp = 0;
        }

        // ---------- 渲染 ----------
        $esc = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

        $sumCards = '<div class="cards">'
            . '<div class="card"><div class="k">PV（访问次数）</div><div class="v">' . number_format($sumPv) . '</div></div>'
            . '<div class="card"><div class="k">UV（独立访客）</div><div class="v">' . number_format($sumUv) . '</div></div>'
            . '<div class="card"><div class="k">IP（独立 IP）</div><div class="v">' . number_format($sumIp) . '</div></div>'
            . '</div>';

        $domainRows = '';
        foreach ($byDomain as $r) {
            $d = (string) ($r['domain'] ?? '');
            $label = $d === '' ? '<span class="muted">未知 / 直连</span>' : $esc($d);
            $drill = $d === '' ? '' : ' <a class="btn-link" href="?from=' . $from . '&to=' . $to . '&domain=' . $esc($d) . '">按日</a>';
            $domainRows .= '<tr>'
                . '<td>' . $label . '</td>'
                . '<td class="num">' . number_format((int) $r['pv']) . '</td>'
                . '<td class="num">' . number_format((int) $r['uv']) . '</td>'
                . '<td class="num">' . number_format((int) $r['ip']) . '</td>'
                . '<td>' . $drill . '</td>'
                . '</tr>';
        }
        if ($domainRows === '') {
            $domainRows = '<tr><td colspan="5" class="empty">' . ($ready ? '该条件下暂无数据' : '统计表尚未就绪：请先在各站执行 php/sql/upgrade_promo_visit.sql') . '</td></tr>';
        }

        $dayRows = '';
        foreach ($byDay as $r) {
            $dayRows .= '<tr>'
                . '<td>' . $esc((string) $r['d']) . '</td>'
                . '<td class="num">' . number_format((int) $r['pv']) . '</td>'
                . '<td class="num">' . number_format((int) $r['uv']) . '</td>'
                . '<td class="num">' . number_format((int) $r['ip']) . '</td>'
                . '</tr>';
        }
        if ($dayRows === '') {
            $dayRows = '<tr><td colspan="4" class="empty">该条件下暂无数据</td></tr>';
        }

        $domainFilterHtml = $domain !== ''
            ? '（当前只看域名：<b>' . $esc($domain) . '</b> — <a href="?from=' . $from . '&to=' . $to . '">查看全部域名</a>）'
            : '';

        $qs = static fn(string $f, string $t): string => '/admin/live.VisitReport/index'
            . '?from=' . $f . '&to=' . $t . ($domain !== '' ? '&domain=' . urlencode($domain) : '');
        $presets = '<a href="' . $qs($today = date('Y-m-d'), $today) . '">今天</a>'
            . '<a href="' . $qs($d7 = date('Y-m-d', strtotime('-6 day')), $today) . '">近7天</a>'
            . '<a href="' . $qs($d30 = date('Y-m-d', strtotime('-29 day')), $today) . '">近30天</a>'
            . '<a href="' . $qs(date('Y-m-d', strtotime('-89 day')), $today) . '">近90天</a>';

        $content = <<<HTML
<style>
.wrap{max-width:1080px;margin:0 auto;padding:16px 18px 40px}
.form{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:12px 0 4px}
.form input[type=date]{padding:6px 8px;border:1px solid var(--line);border-radius:8px;background:#fff}
.form button,.form a.btn{padding:6px 12px;border:1px solid var(--line);border-radius:8px;background:#fff;cursor:pointer;color:var(--text);text-decoration:none}
.presets a{margin-right:14px;color:var(--primary);text-decoration:none;cursor:pointer}
.presets{margin:4px 0 14px;font-size:13px}
.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:10px 0 18px}
.card{background:#fff;border:1px solid var(--line);border-radius:10px;padding:14px 16px}
.card .k{color:var(--muted);font-size:12px;margin-bottom:6px}
.card .v{font-size:24px;font-weight:700}
h3.sec{margin:26px 0 8px;font-size:15px}
table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--line);border-radius:10px;overflow:hidden}
th,td{padding:9px 12px;border-bottom:1px solid var(--line);text-align:left;font-size:13px}
th{background:#f7f8fc;color:var(--muted);font-weight:600}
td.num{text-align:right;font-variant-numeric:tabular-nums}
tr:last-child td{border-bottom:0}
.empty{text-align:center;color:var(--muted)}
.muted{color:var(--muted)}
.btn-link{color:var(--primary);cursor:pointer;background:none;border:0;font-size:12px;padding:0}
.domain-note{margin:6px 0;font-size:13px;color:var(--text)}
</style>
<div class="wrap">
  <form class="form" method="get">
    <input type="date" name="from" value="{$from}">
    <span>至</span>
    <input type="date" name="to" value="{$to}">
    <input type="hidden" name="domain" value="{$domain}">
    <button type="submit">查询</button>
    <span class="presets">{$presets}</span>
  </form>
  <div class="domain-note">口径：PV = 访问次数（每个标签页会话上报一次）；UV = 独立访客（浏览器维度）；IP = 独立 IP。{$domainFilterHtml}</div>
  {$sumCards}
  <h3 class="sec">按域名汇总</h3>
  <table>
    <tr><th>域名</th><th style="text-align:right">PV</th><th style="text-align:right">UV</th><th style="text-align:right">IP</th><th>趋势</th></tr>
    {$domainRows}
  </table>
  <h3 class="sec">按日趋势</h3>
  <table>
    <tr><th>日期</th><th style="text-align:right">PV</th><th style="text-align:right">UV</th><th style="text-align:right">IP</th></tr>
    {$dayRows}
  </table>
</div>
HTML;

        $html = AgentAdminView::page(
            '域名访问统计',
            $from . ' 至 ' . $to . '（数据来源：落地访问上报 lp_promo_visit）',
            $content
        );

        echo $html;
        exit;
    }
}
