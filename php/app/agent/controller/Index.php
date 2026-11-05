<?php
declare(strict_types=1);

namespace app\agent\controller;

use app\agent\service\AgentView;
use app\live\service\AgentService;

/**
 * 代理概览
 */
final class Index extends Base
{
    public function index(): void
    {
        $agent = $this->agent();
        if (!AgentService::hasPerm($agent, 'overview')) {
            $this->redirect('/agent/account/index');
        }
        $range = (string) ($this->request->param('range', '7d') ?? '7d');
        if (!in_array($range, ['today', '7d', '30d'], true)) {
            $range = '7d';
        }

        $to   = date('Y-m-d');
        $from = match ($range) {
            'today' => $to,
            '30d'   => date('Y-m-d', strtotime('-29 days')),
            default => date('Y-m-d', strtotime('-6 days')),
        };

        $teamIds  = AgentService::teamIds($this->agentId());
        $teamSize = count($teamIds) - 1;

        $stats = AgentService::stats($teamIds, $from, $to);
        $links = AgentService::topLinks($teamIds, 8);
        $doms  = AgentService::effectiveDomains($this->agentId(), true);

        $rangeLabel = ['today' => '今日', '7d' => '近 7 天', '30d' => '近 30 天'][$range];
        $tabs = '';
        foreach (['today' => '今日', '7d' => '近 7 天', '30d' => '近 30 天'] as $key => $label) {
            $cls = $key === $range ? 'seg-item is-active' : 'seg-item';
            $tabs .= '<a class="' . $cls . '" href="/agent/index/index?range=' . $key . '">' . $label . '</a>';
        }
        $tabs = '<div class="seg">' . $tabs . '</div>';

        $regs   = number_format($stats['regs']);
        $buyers = number_format($stats['buyers']);
        $amount = number_format($stats['amount'], 2);
        $subs   = number_format($stats['subs']);
        $subAmt = number_format($stats['sub_amount'], 2);
        $conv   = $stats['regs'] > 0 ? number_format($stats['conversion'], 1) . '%' : '-';

        $linkRows = '';
        foreach ($links as $row) {
            $name = (string) ($row['k'] ?? '');
            $linkRows .= '<tr><td>' . AgentView::h($name) . '</td><td class="num">' . (int) $row['c'] . '</td></tr>';
        }
        if ($linkRows === '') {
            $linkRows = '<tr><td colspan="2" class="empty">该时间段还没有按链接归因的注册</td></tr>';
        }

        $recent = '';
        if (AgentService::hasPerm($agent, 'member')) {
            $page  = AgentService::memberPage($teamIds, 1, 8);
            $rows  = '';
            foreach ($page['rows'] as $m) {
                $rows .= '<tr>'
                    . '<td>' . AgentView::h((string) $m['nickname']) . '</td>'
                    . '<td>' . AgentView::h(AgentService::maskEmail((string) $m['email'])) . '</td>'
                    . '<td>' . AgentView::h((string) ($m['reg_link_name'] ?: '直接访问')) . '</td>'
                    . '<td>' . AgentView::h((string) $m['created_at']) . '</td>'
                    . '</tr>';
            }
            if ($rows === '') {
                $rows = '<tr><td colspan="4" class="empty">还没有代理归因的注册</td></tr>';
            }
            $recent = '<div class="card"><h2>最近注册</h2><div class="table-wrap"><table>'
                . '<thead><tr><th>昵称</th><th>邮箱</th><th>来源链接</th><th>注册时间</th></tr></thead>'
                . '<tbody>' . $rows . '</tbody></table></div></div>';
        }

        $domainTip = '';
        if ($doms === []) {
            $domainTip = '<div class="alert err">总部或你的上级还没有分配推广域名，暂时无法创建推广链接。</div>';
        }
        $teamNote = $teamSize > 0 ? '（团队合计，含 ' . $teamSize . ' 个下级代理）' : '';

        $content = <<<HTML
<div class="head">
  <div>
    <h1>数据概览</h1>
    <div class="sub">统计口径：注册按首触归因；充值与订阅取已支付订单。当前范围：{$rangeLabel}{$teamNote}</div>
  </div>
  <div style="margin:0">{$tabs}</div>
</div>
{$domainTip}
<div class="stats">
  <div class="stat"><div class="ico indigo"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg></div><div><div class="k">注册用户</div><div class="v">{$regs}</div><div class="d">{$rangeLabel}</div></div></div>
  <div class="stat"><div class="ico emerald"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></div><div><div class="k">付费人数</div><div class="v">{$buyers}</div><div class="d">转化率 {$conv}</div></div></div>
  <div class="stat"><div class="ico amber"><svg viewBox="0 0 24 24"><path d="M12 2v20"/><path d="M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><div><div class="k">充值金额</div><div class="v">{$amount}</div><div class="d">USD</div></div></div>
  <div class="stat"><div class="ico violet"><svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg></div><div><div class="k">订阅</div><div class="v">{$subs}</div><div class="d">金额 {$subAmt} USD</div></div></div>
</div>
<div class="card">
  <h2>链接表现 TOP</h2>
  <div class="table-wrap"><table><thead><tr><th>推广链接名称</th><th class="num">注册数</th></tr></thead><tbody>{$linkRows}</tbody></table></div>
</div>
{$recent}
HTML;

        $this->render('overview', '数据概览', $content);
    }
}
