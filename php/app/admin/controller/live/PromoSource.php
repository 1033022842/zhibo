<?php
/**
 * 推广数据（多维度归因报表 + 推广链接生成器）
 * 访问: /admin/live.PromoSource/index?_key=<ADMIN_FLOAT_KEY>
 *
 * 归因维度（注册时记录在 lp_user 上，首触）：
 *   reg_domain   来源域名
 *   reg_platform 来源平台（facebook / tiktok / google / other）
 *   reg_channel  渠道参数 ?ch=
 *   reg_px       推广像素 ?px=
 *   reg_campaign 广告系列（campaign_name / campaign_id / utm_campaign）
 *   reg_adset    广告组（adset_name / adset_id / utm_term）
 *   reg_ad       广告（ad_name / ad_id / utm_content）
 *
 * 指标口径：充值/订阅都取「已支付订单」lp_recharge_order(status=1)，按 biz_type 区分买代币与订阅。
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

    /** 可选的归因维度：key => [名称, lp_user 列] */
    private const DIMS = [
        'domain'   => ['来源域名', 'reg_domain'],
        'platform' => ['平台', 'reg_platform'],
        'channel'  => ['渠道(ch)', 'reg_channel'],
        'px'       => ['推广像素', 'reg_px'],
        'campaign' => ['广告系列', 'reg_campaign'],
        'adset'    => ['广告组', 'reg_adset'],
        'ad'       => ['广告', 'reg_ad'],
    ];

    public function index(): void
    {
        $db = Db::connect('live_mysql');

        $dim  = (string) $this->request->param('dim', 'domain');
        if (!isset(self::DIMS[$dim])) {
            $dim = 'domain';
        }
        $col  = self::DIMS[$dim][1];

        $from = trim((string) $this->request->param('from', ''));
        $to   = trim((string) $this->request->param('to', ''));
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : '';
        $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : '';

        // 时间条件（按注册时间）。每条子查询用独立的参数名，避免重复占位符的兼容问题
        $mkWhere = static function (string $sfx) use ($from, $to): array {
            $w = '';
            $b = [];
            if ($from !== '') {
                $w .= ' AND u.created_at >= :from' . $sfx;
                $b['from' . $sfx] = $from . ' 00:00:00';
            }
            if ($to !== '') {
                $w .= ' AND u.created_at <= :to' . $sfx;
                $b['to' . $sfx] = $to . ' 23:59:59';
            }
            return [$w, $b];
        };
        [$w1, $b1] = $mkWhere('1');
        [$w2, $b2] = $mkWhere('2');
        [$w3, $b3] = $mkWhere('3');
        [$w4, $b4] = $mkWhere('4');
        [$w5, $b5] = $mkWhere('5');
        [$w6, $b6] = $mkWhere('6');

        // 各维度聚合（注册 / 充值 / 订阅）
        $sql = "SELECT d.k AS k, d.regs AS regs,
                       IFNULL(p.buyers,0) AS buyers, IFNULL(p.amount,0) AS amount,
                       IFNULL(v.subs,0) AS subs, IFNULL(v.amount,0) AS sub_amount
                FROM (
                    SELECT `{$col}` AS k, COUNT(*) AS regs
                    FROM lp_user u WHERE 1=1 {$w1}
                    GROUP BY `{$col}`
                ) d
                LEFT JOIN (
                    SELECT u.`{$col}` AS k, COUNT(DISTINCT o.user_id) AS buyers, SUM(o.pay_amount) AS amount
                    FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
                    WHERE o.status = 1 AND o.biz_type <> 'vip' {$w2}
                    GROUP BY u.`{$col}`
                ) p ON p.k = d.k
                LEFT JOIN (
                    SELECT u.`{$col}` AS k, COUNT(DISTINCT o.user_id) AS subs, SUM(o.pay_amount) AS amount
                    FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
                    WHERE o.status = 1 AND o.biz_type = 'vip' {$w3}
                    GROUP BY u.`{$col}`
                ) v ON v.k = d.k
                ORDER BY d.regs DESC, regs DESC
                LIMIT 500";
        $rows = $db->query($sql, array_merge($b1, $b2, $b3));

        // 顶部汇总
        $totalRegs = (int) $db->query("SELECT COUNT(*) AS c FROM lp_user u WHERE 1=1 {$w4}", $b4)[0]['c'] ?? 0;
        $withSrc   = (int) $db->query("SELECT COUNT(*) AS c FROM lp_user u WHERE u.reg_domain <> '' {$w4}", $b4)[0]['c'] ?? 0;
        $payAgg    = $db->query("SELECT COUNT(DISTINCT o.user_id) AS buyers, IFNULL(SUM(o.pay_amount),0) AS amount
                                 FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
                                 WHERE o.status = 1 AND o.biz_type <> 'vip' {$w5}", $b5)[0] ?? [];
        $subAgg    = $db->query("SELECT COUNT(DISTINCT o.user_id) AS subs, IFNULL(SUM(o.pay_amount),0) AS amount
                                 FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
                                 WHERE o.status = 1 AND o.biz_type = 'vip' {$w6}", $b6)[0] ?? [];

        // 表格行
        $dimLabel = self::DIMS[$dim][0];
        $tableRows = '';
        foreach ($rows as $r) {
            $k     = (string) ($r['k'] ?? '');
            $regs  = (int) ($r['regs'] ?? 0);
            $b     = (int) ($r['buyers'] ?? 0);
            $rate  = $regs > 0 ? number_format($b / $regs * 100, 1) . '%' : '-';
            $label = $k === ''
                ? '<span class="muted">' . ($dim === 'domain' ? '未知 / 直连' : '未填') . '</span>'
                : htmlspecialchars($k);
            $kEsc  = htmlspecialchars($k, ENT_QUOTES);
            $tableRows .= '<tr>'
                . '<td>' . $label . '</td>'
                . '<td class="num">' . $regs . '</td>'
                . '<td class="num">' . $b . '</td>'
                . '<td class="num">' . $rate . '</td>'
                . '<td class="num">' . number_format((float) ($r['amount'] ?? 0), 2) . '</td>'
                . '<td class="num">' . (int) ($r['subs'] ?? 0) . '</td>'
                . '<td class="num">' . number_format((float) ($r['sub_amount'] ?? 0), 2) . '</td>'
                . '<td><button type="button" class="btn-link" data-dim="' . $dim . '" data-val="' . $kEsc . '">查看会员</button></td>'
                . '</tr>';
        }
        if ($tableRows === '') {
            $tableRows = '<tr><td colspan="8" class="empty">该条件下暂无数据</td></tr>';
        }

        // 维度下拉
        $dimOptions = '';
        foreach (self::DIMS as $key => [$name, $_]) {
            $sel = $key === $dim ? ' selected' : '';
            $dimOptions .= '<option value="' . $key . '"' . $sel . '>' . $name . '</option>';
        }

        // 快捷时间
        $today = date('Y-m-d');
        $d7    = date('Y-m-d', strtotime('-6 day'));
        $d30   = date('Y-m-d', strtotime('-29 day'));
        $qs    = static fn(string $f, string $t): string => '/admin/live.PromoSource/index?dim=' . urlencode($dim)
            . ($f !== '' ? '&from=' . $f : '') . ($t !== '' ? '&to=' . $t : '');
        $presets = '<a href="' . $qs($today, $today) . '">今天</a>'
            . '<a href="' . $qs($d7, $today) . '">近7天</a>'
            . '<a href="' . $qs($d30, $today) . '">近30天</a>'
            . '<a href="/admin/live.PromoSource/index?dim=' . urlencode($dim) . '">全部</a>';

        // 推广链接生成器
        $svc     = new PromoService();
        $domains = $svc->siteDomains();
        $domainOptions = '';
        foreach ($domains as $d) {
            $domainOptions .= '<option value="' . htmlspecialchars($d, ENT_QUOTES) . '">' . htmlspecialchars($d) . '</option>';
        }
        if ($domainOptions === '') {
            $domainOptions = '<option value="">（暂无已收录域名，先去前台访问一次或到「推广与像素」手动添加）</option>';
        }
        $pxOptions = '<option value="">不绑定像素（按域名规则自动匹配）</option>';
        foreach ($svc->pixels(true) as $p) {
            $pid     = (int) $p['id'];
            $pfLabel = PromoService::PIXEL_PLATFORMS[(string) $p['platform']] ?? (string) $p['platform'];
            $pname   = trim((string) $p['name']);
            $plabel  = $pfLabel . ($pname !== '' ? ' · ' . $pname : '') . '（#' . $pid . '）';
            $pxOptions .= '<option value="' . $pid . '">' . htmlspecialchars($plabel, ENT_QUOTES) . '</option>';
        }
        $domainCount = count($domains);

        $totalRegsFmt  = number_format($totalRegs);
        $withSrcFmt    = number_format($withSrc);
        $unknownFmt    = number_format($totalRegs - $withSrc);
        $payAmtFmt     = number_format((float) ($payAgg['amount'] ?? 0), 2);
        $payBuyersFmt  = number_format((int) ($payAgg['buyers'] ?? 0));
        $subAmtFmt     = number_format((float) ($subAgg['amount'] ?? 0), 2);
        $subSubsFmt    = number_format((int) ($subAgg['subs'] ?? 0));
        $fromVal       = htmlspecialchars($from, ENT_QUOTES);
        $toVal         = htmlspecialchars($to, ENT_QUOTES);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>推广数据</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;background:#f4f5fb;color:#1f2430;font-size:14px}
.container{max-width:1180px;margin:0 auto;padding:28px 24px 60px}
h1{font-size:23px;font-weight:700;color:#1a1f36}
.sub{margin-top:6px;font-size:13px;color:#8a90a3;line-height:1.7}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:22px 0}
.stat{background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:16px;box-shadow:0 1px 3px rgba(30,34,60,.04)}
.stat .v{font-size:20px;font-weight:700;color:#1a1f36}
.stat .k{font-size:12px;color:#8a90a3;margin-top:3px}
.card{background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:20px;margin-bottom:18px;box-shadow:0 1px 3px rgba(30,34,60,.04)}
.card h2{font-size:15px;font-weight:700;margin-bottom:14px;color:#1a1f36}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
select,input{height:38px;padding:0 12px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;color:#1f2430;background:#fff;outline:none}
select{min-width:170px}
input{flex:1;min-width:180px}
.btn{height:38px;padding:0 18px;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff}
.presets a{display:inline-block;margin-right:10px;font-size:13px;color:#6366f1;text-decoration:none}
.presets a:hover{text-decoration:underline}
.link-box{margin-top:12px;display:flex;gap:10px;align-items:center}
.link-box input{background:#fafbfe;font-family:Consolas,Menlo,monospace;font-size:13px}
table{width:100%;border-collapse:collapse}
th{background:#fafbfe;font-weight:600;font-size:12px;color:#8a90a3;text-align:left;padding:12px 14px;border-bottom:1px solid #eef0f6;white-space:nowrap}
td{padding:12px 14px;border-bottom:1px solid #f2f3f8;font-size:13px;color:#3a4051}
td.num,th.num{text-align:right;font-variant-numeric:tabular-nums}
tr:last-child td{border-bottom:none}
.empty{text-align:center;color:#9aa0b0;padding:26px 0}
.muted{color:#9aa0b0}
.tip{font-size:12px;color:#9aa0b0;margin-top:10px;line-height:1.7}
.btn-link{padding:4px 10px;border:1px solid #6366f1;border-radius:8px;background:#fff;color:#6366f1;font-size:12px;cursor:pointer}
.btn-link:hover{background:#eef0ff}
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
    <h1>推广数据</h1>
    <div class="sub">按 <b>来源域名 / 平台 / 渠道 / 推广像素 / 广告系列 / 广告组 / 广告</b> 归因统计注册与付费（首触归因，注册时记录）。
        充值/订阅金额取「已支付订单」口径。</div>

    <div class="stats">
        <div class="stat"><div class="v">{$totalRegsFmt}</div><div class="k">总注册数</div></div>
        <div class="stat"><div class="v">{$withSrcFmt}</div><div class="k">带来源域名</div></div>
        <div class="stat"><div class="v">{$payBuyersFmt} / {$payAmtFmt}</div><div class="k">充值人数 / 金额(USD)</div></div>
        <div class="stat"><div class="v">{$subSubsFmt} / {$subAmtFmt}</div><div class="k">订阅人数 / 金额(USD)</div></div>
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
        <div class="tip">时间按「注册时间」过滤。广告系列/广告组/广告请在 FB 广告的「网址参数」里带上，例如：
            <code>campaign_name={{campaign.name}}&amp;adset_name={{adset.name}}&amp;ad_name={{ad.name}}</code>
            （也兼容 <code>utm_campaign</code> / <code>utm_term</code> / <code>utm_content</code>）。</div>
    </div>

    <div class="card">
        <h2>按{$dimLabel}统计<span class="muted" style="font-weight:400"> · 点「查看会员」看该来源的注册用户</span></h2>
        <table>
            <thead><tr>
                <th>{$dimLabel}</th><th class="num">注册数</th><th class="num">充值人数</th><th class="num">付费率</th>
                <th class="num">充值金额</th><th class="num">订阅人数</th><th class="num">订阅金额</th><th>操作</th>
            </tr></thead>
            <tbody>{$tableRows}</tbody>
        </table>
    </div>

    <div class="card">
        <h2>推广链接生成器<span class="muted" style="font-weight:400"> · 已收录 {$domainCount} 个域名</span></h2>
        <div class="row">
            <select id="domSel">{$domainOptions}</select>
            <input id="chInput" placeholder="渠道参数 ch（可选，如 fb1 / tk2）">
            <select id="pxSel">{$pxOptions}</select>
            <button class="btn" onclick="genLink()">生成链接</button>
        </div>
        <div class="link-box">
            <input id="linkOut" readonly placeholder="点「生成链接」得到可复制的推广链接">
            <button class="btn" onclick="copyLink()">复制</button>
        </div>
        <div class="tip">绑定像素后生成 <b>?px=像素ID</b>：从该链接进来的访客<b>只打这一条像素</b>（不受域名规则影响），跳转到其它页面也保持绑定。<br>
            想让数据能按广告系列/组/广告区分：在 FB 广告的「网址参数」里再加
            <code>campaign_name={{campaign.name}}&amp;adset_name={{adset.name}}&amp;ad_name={{ad.name}}</code>。</div>
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
                <thead><tr><th>ID</th><th>昵称</th><th>邮箱</th><th>平台</th><th>渠道</th><th>广告系列</th><th>广告组</th><th>广告</th><th>注册时间</th><th class="num">充值</th><th class="num">订阅</th></tr></thead>
                <tbody id="memRows"></tbody>
            </table>
        </div>
    </div>
</div>
<script>
function genLink(){
    var d = document.getElementById('domSel').value;
    var ch = document.getElementById('chInput').value.trim();
    var px = document.getElementById('pxSel').value;
    if(!d){ document.getElementById('linkOut').value=''; return; }
    var qs = [];
    if(ch){ qs.push('ch=' + encodeURIComponent(ch)); }
    if(px){ qs.push('px=' + encodeURIComponent(px)); }
    var url = 'https://' + d + '/';
    if(qs.length){ url += '?' + qs.join('&'); }
    document.getElementById('linkOut').value = url;
}
function copyLink(){
    var el = document.getElementById('linkOut');
    if(!el.value) return;
    try { el.select(); document.execCommand('copy'); alert('已复制：' + el.value); } catch(e) { alert('复制失败，请手动选择复制'); }
}

/*---------- 查看会员：某个维度值下的注册用户明细 ----------*/
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
            if(!rows.length){ document.getElementById('memRows').innerHTML = '<tr><td colspan="11" class="empty">该来源暂无注册会员</td></tr>'; return; }
            document.getElementById('memRows').innerHTML = rows.map(function(u){
                return '<tr>'
                    + '<td>' + esc(u.id) + '</td>'
                    + '<td>' + (esc(u.nickname) || '—') + '</td>'
                    + '<td>' + (esc(u.email) || '—') + '</td>'
                    + '<td>' + (esc(u.reg_platform) || '—') + '</td>'
                    + '<td>' + (esc(u.reg_channel) || '—') + '</td>'
                    + '<td>' + (esc(u.reg_campaign) || '—') + '</td>'
                    + '<td>' + (esc(u.reg_adset) || '—') + '</td>'
                    + '<td>' + (esc(u.reg_ad) || '—') + '</td>'
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
        $dim = (string) $this->request->param('dim', 'domain');
        if (!isset(self::DIMS[$dim])) {
            $dim = 'domain';
        }
        $col = self::DIMS[$dim][1];
        $val = (string) $this->request->param('val', '');

        $db = Db::connect('live_mysql');

        $agg = $db->query("SELECT COUNT(DISTINCT u.id) AS total,
                COUNT(DISTINCT p.user_id) AS buyers, IFNULL(SUM(p.pay_amount),0) AS amount,
                COUNT(DISTINCT s.user_id) AS subs, IFNULL(SUM(s.pay_amount),0) AS sub_amount
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
                u.reg_platform, u.reg_channel, u.reg_campaign, u.reg_adset, u.reg_ad, u.created_at,
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
