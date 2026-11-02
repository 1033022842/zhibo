<?php
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\live\service\AgentService;
use think\facade\Db;

/**
 * 代理数据（总后台）
 * 访问: /admin/live.AgentReport/index?_key=<ADMIN_FLOAT_KEY>
 *
 * 维度：代理；时间按会员注册时间筛选，口径与「推广数据」一致（已支付订单）。
 */
final class AgentReport extends Backend
{
    protected array $noNeedLogin = ['index', 'members'];

    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    public function index(): void
    {
        if (!AgentService::ready()) {
            $this->renderPage(
                '<div class="card"><h2>代理系统尚未初始化</h2>'
                . '<div class="tip">请先在数据库执行 <code>php/sql/upgrade_agent.sql</code>，再刷新本页。</div></div>'
            );
            return;
        }

        $from = trim((string) $this->request->param('from', ''));
        $to   = trim((string) $this->request->param('to', ''));
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : '';
        $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : '';

        $agents = AgentService::allAgentStats($from, $to);

        $sumRegs = 0;
        $sumAmount = 0.0;
        $sumSub = 0.0;
        $sumBuyers = 0;
        $rows = '';
        foreach ($agents as $a) {
            $regs   = (int) $a['regs'];
            $buyers = (int) $a['buyers'];
            $amount = (float) $a['amount'];
            $subs   = (int) $a['subs'];
            $subAmt = (float) $a['sub_amount'];
            $conv   = $regs > 0 ? number_format($buyers / $regs * 100, 1) . '%' : '-';

            $sumRegs += $regs;
            $sumBuyers += $buyers;
            $sumAmount += $amount;
            $sumSub += $subAmt;

            $status = (int) $a['status'] === 1
                ? '<span class="chip on">启用</span>'
                : '<span class="chip off">停用</span>';

            $rows .= '<tr>'
                . '<td><b>' . $this->e((string) $a['agent_no']) . '</b><div class="muted">' . $this->e((string) $a['name']) . '</div></td>'
                . '<td>' . $this->e((string) $a['username']) . '</td>'
                . '<td>' . $status . '</td>'
                . '<td class="num">' . number_format($regs) . '</td>'
                . '<td class="num">' . number_format($buyers) . '</td>'
                . '<td class="num">' . $conv . '</td>'
                . '<td class="num">' . number_format($amount, 2) . '</td>'
                . '<td class="num">' . number_format($subs) . '</td>'
                . '<td class="num">' . number_format($subAmt, 2) . '</td>'
                . '<td class="ops"><a href="/admin/live.AgentReport/members?agent_id=' . (int) $a['id'] . $this->keyQuery('&') . '">会员明细</a></td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="10" class="empty">还没有代理，先到「代理管理」创建</td></tr>';
        }

        $fromEsc      = $this->e($from);
        $toEsc        = $this->e($to);
        $keyUrl       = $this->keyQuery();
        $agentCount   = number_format(count($agents));
        $sumRegsFmt   = number_format($sumRegs);
        $sumBuyersFmt = number_format($sumBuyers);
        $sumAmountFmt = number_format($sumAmount, 2);
        $sumSubFmt    = number_format($sumSub, 2);

        $content = <<<HTML
<div class="head"><div><h1>代理数据</h1>
  <div class="sub">按代理维度统计注册、充值、订阅与转化。时间筛选按会员注册时间；充值与订阅取已支付订单。</div></div></div>
<div class="card">
  <form class="toolbar" method="get" action="/admin/live.AgentReport/index">
    <span class="muted">注册时间</span>
    <input type="date" name="from" value="{$fromEsc}">
    <span class="muted">至</span>
    <input type="date" name="to" value="{$toEsc}">
    <button class="btn-line" type="submit" style="height:36px">筛选</button>
    <a class="btn-line" href="/admin/live.AgentReport/index{$keyUrl}" style="height:36px;line-height:34px">重置</a>
  </form>
</div>
<div class="stats">
  <div class="stat"><div class="k">代理数</div><div class="v">{$agentCount}</div></div>
  <div class="stat"><div class="k">注册总数</div><div class="v">{$sumRegsFmt}</div></div>
  <div class="stat"><div class="k">充值金额</div><div class="v">{$sumAmountFmt}</div><div class="d">付费人数 {$sumBuyersFmt}</div></div>
  <div class="stat"><div class="k">订阅金额</div><div class="v">{$sumSubFmt}</div></div>
</div>
<div class="card">
  <h2>按代理汇总</h2>
  <table><thead><tr>
    <th>代理</th><th>账号</th><th>状态</th>
    <th class="num">注册数</th><th class="num">付费人数</th><th class="num">转化率</th>
    <th class="num">充值金额</th><th class="num">订阅数</th><th class="num">订阅金额</th><th>操作</th>
  </tr></thead><tbody>{$rows}</tbody></table>
</div>
HTML;

        $this->renderPage($content);
    }

    public function members(): void
    {
        if (!AgentService::ready()) {
            $this->renderPage('<div class="card"><h2>代理系统尚未初始化</h2></div>');
            return;
        }

        $agentId = (int) $this->request->param('agent_id', 0);
        $agent   = $agentId > 0
            ? Db::connect('live_mysql')->table('lp_agent')->where('id', $agentId)->find()
            : null;
        if (!$agent) {
            $this->renderPage('<div class="card"><h2>代理不存在</h2><div class="actions">'
                . '<a class="btn-line" href="/admin/live.AgentReport/index' . $this->keyQuery() . '">返回</a></div></div>');
            return;
        }

        $page    = max(1, (int) $this->request->param('page', 1));
        $keyword = mb_substr(trim((string) $this->request->param('kw', '')), 0, 64);
        $data    = AgentService::memberPage($agentId, $page, 50, $keyword);

        $rows = '';
        foreach ($data['rows'] as $m) {
            $rows .= '<tr>'
                . '<td>' . (int) $m['id'] . '</td>'
                . '<td>' . $this->e((string) $m['nickname']) . '</td>'
                . '<td>' . $this->e((string) $m['email']) . '</td>'
                . '<td>' . $this->e((string) (($m['reg_link_name'] ?? '') !== '' ? $m['reg_link_name'] : '直接访问')) . '</td>'
                . '<td class="num">' . number_format((float) $m['pay_amount'], 2) . '</td>'
                . '<td class="num">' . number_format((float) $m['sub_amount'], 2) . '</td>'
                . '<td>' . $this->e((string) $m['created_at']) . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="7" class="empty">没有找到会员</td></tr>';
        }

        $pager = '';
        $qs    = $keyword !== '' ? '&kw=' . urlencode($keyword) : '';
        $base  = '/admin/live.AgentReport/members?agent_id=' . $agentId . $this->keyQuery('&') . $qs;
        $cur   = (int) $data['page'];
        $pages = (int) $data['pages'];
        if ($pages > 1) {
            $prev = $cur > 1 ? '<a href="' . $base . '&page=' . ($cur - 1) . '">上一页</a>' : '<span class="muted">上一页</span>';
            $next = $cur < $pages ? '<a href="' . $base . '&page=' . ($cur + 1) . '">下一页</a>' : '<span class="muted">下一页</span>';
            $pager = '<div class="pager">' . $prev . '<span>第 ' . $cur . ' / ' . $pages . ' 页</span>' . $next
                . '<span class="muted">共 ' . number_format((int) $data['total']) . ' 人</span></div>';
        }

        $name = $this->e((string) $agent['name']);
        $no   = $this->e((string) $agent['agent_no']);
        $kw   = $this->e($keyword);
        $back     = '/admin/live.AgentReport/index' . $this->keyQuery();
        $totalFmt = number_format((int) $data['total']);

        $content = <<<HTML
<div class="head"><div><h1>会员明细 · {$name}</h1>
  <div class="sub">代理编号 {$no} · 共 {$totalFmt} 人</div></div>
  <div><a class="btn-line" href="{$back}">返回代理数据</a></div></div>
<div class="card">
  <form class="toolbar" method="get" action="/admin/live.AgentReport/members">
    <input type="hidden" name="agent_id" value="{$agentId}">
    <input type="text" name="kw" value="{$kw}" placeholder="搜索昵称或邮箱" maxlength="64">
    <button class="btn-line" type="submit" style="height:36px">搜索</button>
  </form>
  <table><thead><tr>
    <th>ID</th><th>昵称</th><th>邮箱</th><th>来源链接</th>
    <th class="num">累计充值(USD)</th><th class="num">累计订阅(USD)</th><th>注册时间</th>
  </tr></thead><tbody>{$rows}</tbody></table>
  {$pager}
</div>
HTML;

        $this->renderPage($content);
    }

    private function renderPage(string $content, int $status = 200): void
    {
        http_response_code($status);
        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>代理数据</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;background:#f4f5fb;color:#1f2430;font-size:14px}
.container{max-width:1280px;margin:0 auto;padding:28px 24px 60px}
.head{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;margin-bottom:18px}
h1{font-size:23px;font-weight:700;color:#1a1f36}
.sub{margin-top:6px;font-size:13px;color:#8a90a3;line-height:1.7}
.card{background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:20px;margin-bottom:18px;box-shadow:0 1px 3px rgba(30,34,60,.04)}
.card h2{font-size:15px;font-weight:700;margin-bottom:14px;color:#1a1f36}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px}
.stat{background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:16px;box-shadow:0 1px 3px rgba(30,34,60,.04)}
.stat .k{font-size:12px;color:#8a90a3}
.stat .v{font-size:22px;font-weight:700;color:#1a1f36;margin-top:6px;font-variant-numeric:tabular-nums}
.stat .d{font-size:11.5px;color:#9aa0b0;margin-top:4px}
.toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.toolbar input{height:36px;padding:0 11px;border:1px solid #e2e5ee;border-radius:9px;outline:none}
.toolbar input:focus{border-color:#6366f1}
.btn-line{height:34px;line-height:32px;padding:0 14px;border:1px solid #e2e5ee;border-radius:9px;font-size:13px;color:#5b6072;text-decoration:none;background:#fff;display:inline-block;cursor:pointer}
table{width:100%;border-collapse:collapse}
th{background:#fafbfe;font-weight:600;font-size:12px;color:#8a90a3;text-align:left;padding:11px 12px;border-bottom:1px solid #eef0f6;white-space:nowrap}
td{padding:11px 12px;border-bottom:1px solid #f2f3f8;font-size:13px;color:#3a4051;vertical-align:top}
td.num,th.num{text-align:right;font-variant-numeric:tabular-nums}
tr:last-child td{border-bottom:none}
.empty{text-align:center;color:#9aa0b0;padding:26px 0}
.muted{color:#9aa0b0}
.chip{font-size:12px;padding:2px 9px;border-radius:999px;white-space:nowrap}
.chip.on{background:#e8f7ef;color:#00a870}
.chip.off{background:#f2f3f5;color:#999}
td.ops a{color:#6366f1;text-decoration:none;white-space:nowrap}
.pager{display:flex;align-items:center;gap:10px;margin-top:16px;font-size:12.5px;color:#8a90a3}
.pager a{color:#6366f1;text-decoration:none}
.tip{font-size:13px;color:#6b7080;line-height:1.8}
code{background:#f7f8fc;border:1px solid #eef0f6;border-radius:6px;padding:2px 6px;font-family:Consolas,Menlo,monospace;font-size:12.5px}
</style>
</head>
<body><div class="container">{$content}</div></body>
</html>
HTML;
        response($html)->send();
        exit;
    }

    private function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private function keyQuery(string $prefix = '?'): string
    {
        $key = (string) $this->request->param('_key', '');
        return $key !== '' ? $prefix . '_key=' . urlencode($key) : '';
    }
}
