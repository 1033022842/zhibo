<?php
/**
 * 推广来源（按域名/渠道统计注册与充值，不依赖任何像素）
 * 访问: /admin/live.PromoSource/index?_key=<ADMIN_FLOAT_KEY>
 *
 * 原理：用户注册时记录「来源域名 reg_domain」与「渠道参数 reg_channel(?ch=)」。
 *  - 每个域名本身就是一条渠道：推广链接直接用各域名首页（https://域名/）。
 *  - 同一域名想再细分，就加 ?ch=xxx。
 * 来源域名优先取注册请求显式携带的值，其次用 Origin / Referer 兜底。
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\live\service\PromoService;
use think\facade\Db;

final class PromoSource extends Backend
{
    protected array $noNeedLogin = ['index'];

    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    public function index(): void
    {
        $db = Db::connect('live_mysql');

        // 前端域名（自动收录 + 后台可维护），用于生成推广链接
        $domains = (new PromoService())->siteDomains();

        // 按来源域名统计注册数
        $regRows = $db->table('lp_user')
            ->field('reg_domain, COUNT(*) AS regs')
            ->group('reg_domain')
            ->select()->toArray();

        // 按来源域名统计充值（充值流水 recharge 收入项）
        $payRows = $db->table('lp_wallet_ledger l')
            ->join('lp_user u', 'u.id = l.user_id')
            ->where('l.biz_type', 'recharge')->where('l.direction', 1)
            ->field('u.reg_domain AS dom, SUM(l.amount) AS amt, COUNT(DISTINCT l.user_id) AS buyers')
            ->group('u.reg_domain')
            ->select()->toArray();

        $payMap = [];
        foreach ($payRows as $r) {
            $payMap[(string) $r['dom']] = [
                'amount' => (float) $r['amt'],
                'buyers' => (int) $r['buyers'],
            ];
        }

        // 合并：域名 => [注册数, 充值金额, 充值人数]
        $map = [];
        foreach ($regRows as $r) {
            $dom = (string) $r['reg_domain'];
            $map[$dom] = [
                'regs'   => (int) $r['regs'],
                'amount' => $payMap[$dom]['amount'] ?? 0.0,
                'buyers' => $payMap[$dom]['buyers'] ?? 0,
            ];
        }

        $totalRegs = 0;
        $withSource = 0;
        $domainRows = '';
        foreach ($map as $dom => $v) {
            $totalRegs += $v['regs'];
            if ($dom !== '') {
                $withSource += $v['regs'];
            }
            $label = $dom === '' ? '<span class="muted">未知 / 直连</span>' : htmlspecialchars($dom);
            $payRate = $v['regs'] > 0 ? number_format($v['buyers'] / $v['regs'] * 100, 1) . '%' : '-';
            $domainRows .= '<tr>'
                . '<td>' . $label . '</td>'
                . '<td class="num">' . $v['regs'] . '</td>'
                . '<td class="num">' . $v['buyers'] . '</td>'
                . '<td class="num">' . $payRate . '</td>'
                . '<td class="num">' . number_format($v['amount'], 2) . '</td>'
                . '</tr>';
        }
        if ($domainRows === '') {
            $domainRows = '<tr><td colspan="5" class="empty">暂无数据</td></tr>';
        }

        // 按渠道参数统计
        $chRows = $db->table('lp_user')
            ->where('reg_channel', '<>', '')
            ->field('reg_channel, COUNT(*) AS regs')
            ->group('reg_channel')
            ->order('regs', 'desc')
            ->select()->toArray();
        $channelRows = '';
        foreach ($chRows as $r) {
            $channelRows .= '<tr><td>' . htmlspecialchars((string) $r['reg_channel']) . '</td><td class="num">' . (int) $r['regs'] . '</td></tr>';
        }
        if ($channelRows === '') {
            $channelRows = '<tr><td colspan="2" class="empty">暂无渠道数据（推广链接带 ?ch=xxx 后才有）</td></tr>';
        }

        // 域名下拉（生成推广链接用）
        $domainOptions = '';
        foreach ($domains as $d) {
            $domainOptions .= '<option value="' . htmlspecialchars($d, ENT_QUOTES) . '">' . htmlspecialchars($d) . '</option>';
        }
        if ($domainOptions === '') {
            $domainOptions = '<option value="">（暂无已收录域名，先去前台访问一次或到「推广与像素」手动添加）</option>';
        }

        // 像素下拉（生成推广链接用：绑定后该链接只打这一条像素）
        $pxOptions = '<option value="">不绑定像素（按域名规则自动匹配）</option>';
        foreach ((new PromoService())->pixels(true) as $p) {
            $pid     = (int) $p['id'];
            $pfLabel = PromoService::PIXEL_PLATFORMS[(string) $p['platform']] ?? (string) $p['platform'];
            $pname   = trim((string) $p['name']);
            $plabel  = $pfLabel . ($pname !== '' ? ' · ' . $pname : '') . '（#' . $pid . '）';
            $pxOptions .= '<option value="' . $pid . '">' . htmlspecialchars($plabel, ENT_QUOTES) . '</option>';
        }

        $totalRegsFmt   = number_format($totalRegs);
        $withSourceFmt  = number_format($withSource);
        $unknownFmt     = number_format($totalRegs - $withSource);
        $domainCount    = count($domains);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>推广来源</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;background:#f4f5fb;color:#1f2430;font-size:14px}
.container{max-width:1100px;margin:0 auto;padding:28px 24px 60px}
h1{font-size:23px;font-weight:700;color:#1a1f36}
.sub{margin-top:6px;font-size:13px;color:#8a90a3;line-height:1.7}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:22px 0}
.stat{background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:18px;box-shadow:0 1px 3px rgba(30,34,60,.04)}
.stat .v{font-size:22px;font-weight:700;color:#1a1f36}
.stat .k{font-size:12px;color:#8a90a3;margin-top:3px}
.card{background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:20px;margin-bottom:18px;box-shadow:0 1px 3px rgba(30,34,60,.04)}
.card h2{font-size:15px;font-weight:700;margin-bottom:14px;color:#1a1f36}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
select,input{height:38px;padding:0 12px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;color:#1f2430;background:#fff;outline:none}
select{min-width:220px}
input{flex:1;min-width:200px}
.btn{height:38px;padding:0 18px;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff}
.link-box{margin-top:12px;display:flex;gap:10px;align-items:center}
.link-box input{background:#fafbfe;font-family:Consolas,Menlo,monospace;font-size:13px}
table{width:100%;border-collapse:collapse}
th{background:#fafbfe;font-weight:600;font-size:12px;color:#8a90a3;text-align:left;padding:12px 14px;border-bottom:1px solid #eef0f6;white-space:nowrap}
td{padding:12px 14px;border-bottom:1px solid #f2f3f8;font-size:13px;color:#3a4051}
td.num{text-align:right;font-variant-numeric:tabular-nums}
tr:last-child td{border-bottom:none}
.empty{text-align:center;color:#9aa0b0;padding:26px 0}
.muted{color:#9aa0b0}
.tip{font-size:12px;color:#9aa0b0;margin-top:10px;line-height:1.7}
</style>
</head>
<body>
<div class="container">
    <h1>推广来源</h1>
    <div class="sub">按「注册来源域名 / 渠道参数」统计，不依赖任何像素。每个域名本身就是一条渠道：推广链接直接用域名首页，需要细分就加 <b>?ch=xxx</b>。</div>

    <div class="stats">
        <div class="stat"><div class="v">{$totalRegsFmt}</div><div class="k">总注册数</div></div>
        <div class="stat"><div class="v">{$withSourceFmt}</div><div class="k">带来源域名</div></div>
        <div class="stat"><div class="v">{$unknownFmt}</div><div class="k">无来源（老数据/直连）</div></div>
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
        <div class="tip">提示：把生成好的链接分别投放到不同渠道，用户注册后就会带上来源域名/渠道，本页即可看到各域名带来的注册与充值。<br>
            绑定像素后生成 <b>?px=像素ID</b>：从该链接进来的访客<b>只打这一条像素</b>（不受域名规则影响），跳转到其它页面也保持绑定。</div>
    </div>

    <div class="card">
        <h2>按来源域名统计</h2>
        <table>
            <thead><tr><th>来源域名</th><th class="num">注册数</th><th class="num">充值人数</th><th class="num">付费率</th><th class="num">充值金额</th></tr></thead>
            <tbody>{$domainRows}</tbody>
        </table>
    </div>

    <div class="card">
        <h2>按渠道参数统计（?ch=）</h2>
        <table>
            <thead><tr><th>渠道</th><th class="num">注册数</th></tr></thead>
            <tbody>{$channelRows}</tbody>
        </table>
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
</script>
</body>
</html>
HTML;

        response($html)->send();
        exit;
    }
}
