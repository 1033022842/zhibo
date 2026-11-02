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
            $subs    = (int) $a['subs'];
            $subAmt  = (float) $a['sub_amount'];
            $convPct = $regs > 0 ? min(100, $buyers / $regs * 100) : 0;
            $conv    = $regs > 0 ? number_format($buyers / $regs * 100, 1) . '%' : '-';

            $sumRegs += $regs;
            $sumBuyers += $buyers;
            $sumAmount += $amount;
            $sumSub += $subAmt;

            $status = (int) $a['status'] === 1
                ? '<span class="chip on">启用</span>'
                : '<span class="chip off">停用</span>';

            $rows .= '<tr>'
                . '<td><div class="agent-name">' . $this->e((string) $a['name']) . '</div>'
                . '<div class="agent-no">' . $this->e((string) $a['agent_no']) . '</div></td>'
                . '<td>' . $this->e((string) $a['username']) . '</td>'
                . '<td>' . $status . '</td>'
                . '<td class="num">' . number_format($regs) . '</td>'
                . '<td class="num">' . number_format($buyers) . '</td>'
                . '<td style="min-width:110px"><div style="font-size:12.5px;color:#3a4051">' . $conv . '</div>'
                . '<div class="bar"><i style="width:' . number_format($convPct, 1, '.', '') . '%"></i></div></td>'
                . '<td class="num">$' . number_format($amount, 2) . '</td>'
                . '<td class="num">' . number_format($subs) . '</td>'
                . '<td class="num">$' . number_format($subAmt, 2) . '</td>'
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

        $manageUrl = '/admin/live.Agent/index' . $this->keyQuery();
        $rangeText = ($from !== '' || $to !== '')
            ? '统计范围：' . ($from !== '' ? $from : '最早') . ' 至 ' . ($to !== '' ? $to : '今天')
            : '统计范围：全部时间';

        $content = <<<HTML
<div class="card">
  <div class="card-head">
    <div><h2>统计范围</h2><div class="hint">按会员注册时间筛选；充值与订阅取已支付订单</div></div>
    <span class="muted" style="font-size:12px">{$rangeText}</span>
  </div>
  <form class="toolbar" method="get" action="/admin/live.AgentReport/index">
    <span class="muted">注册时间</span>
    <input type="date" name="from" value="{$fromEsc}">
    <span class="muted">至</span>
    <input type="date" name="to" value="{$toEsc}">
    <button class="btn-line" type="submit">筛选</button>
    <a class="btn-line" href="/admin/live.AgentReport/index{$keyUrl}">重置</a>
  </form>
</div>
<div class="stats">
  <div class="stat"><div class="ico indigo"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div><div><div class="k">代理数</div><div class="v">{$agentCount}</div><div class="d">参与统计的代理</div></div></div>
  <div class="stat"><div class="ico violet"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg></div><div><div class="k">注册总数</div><div class="v">{$sumRegsFmt}</div><div class="d">代理归因注册</div></div></div>
  <div class="stat"><div class="ico emerald"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></div><div><div class="k">充值金额</div><div class="v">{$sumAmountFmt}</div><div class="d">付费人数 {$sumBuyersFmt}</div></div></div>
  <div class="stat"><div class="ico amber"><svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg></div><div><div class="k">订阅金额</div><div class="v">{$sumSubFmt}</div><div class="d">VIP 订阅收入</div></div></div>
</div>
<div class="card">
  <div class="card-head">
    <div><h2>按代理汇总</h2><div class="hint">转化率 = 付费人数 / 注册数；点击「会员明细」可下钻</div></div>
    <a class="btn-line" href="{$manageUrl}">代理管理</a>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>代理</th><th>账号</th><th>状态</th>
        <th class="num">注册数</th><th class="num">付费人数</th><th>转化率</th>
        <th class="num">充值金额</th><th class="num">订阅数</th><th class="num">订阅金额</th><th>操作</th>
      </tr></thead>
      <tbody>{$rows}</tbody>
    </table>
  </div>
</div>
HTML;

        $this->renderPage($content, '代理数据', '按代理维度统计注册、充值、订阅与转化。时间筛选按会员注册时间；充值与订阅取已支付订单。');
    }

    public function members(): void
    {
        if (!AgentService::ready()) {
            $this->renderPage('<div class="card"><h2>代理系统尚未初始化</h2><div class="tip">请先执行 <code>php/sql/upgrade_agent.sql</code>。</div></div>', '代理数据', '代理功能尚未初始化');
            return;
        }

        $agentId = (int) $this->request->param('agent_id', 0);
        $agent   = $agentId > 0
            ? Db::connect('live_mysql')->table('lp_agent')->where('id', $agentId)->find()
            : null;
        if (!$agent) {
            $this->renderPage('<div class="card"><h2>代理不存在</h2><div class="actions">'
                . '<a class="btn-line" href="/admin/live.AgentReport/index' . $this->keyQuery() . '">返回</a></div></div>',
                '代理数据', '未找到对应代理');
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
<div class="card">
  <div class="card-head">
    <div><h2>会员明细 · {$name}</h2><div class="hint">代理编号 {$no} · 共 {$totalFmt} 人</div></div>
    <a class="btn-line" href="{$back}">返回代理数据</a>
  </div>
  <form class="toolbar" method="get" action="/admin/live.AgentReport/members">
    <input type="hidden" name="agent_id" value="{$agentId}">
    <input type="text" name="kw" value="{$kw}" placeholder="搜索昵称或邮箱" maxlength="64">
    <button class="btn-line" type="submit">搜索</button>
  </form>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>ID</th><th>昵称</th><th>邮箱</th><th>来源链接</th>
        <th class="num">累计充值(USD)</th><th class="num">累计订阅(USD)</th><th>注册时间</th>
      </tr></thead>
      <tbody>{$rows}</tbody>
    </table>
  </div>
  {$pager}
</div>
HTML;

        $this->renderPage($content, '会员明细', '代理 ' . $name . '（' . $no . '）名下的会员，按注册时间倒序。');
    }

    private function renderPage(string $content, string $title = '代理数据', string $subtitle = '', string $actions = '', int $status = 200): void
    {
        http_response_code($status);
        $html = \app\live\service\AgentAdminView::page($title, $subtitle, $content, $actions);
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
