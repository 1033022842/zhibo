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

        $stats = AgentService::stats($this->agentId(), $from, $to);
        $links = AgentService::topLinks($this->agentId(), 8);
        $doms  = AgentService::domains($this->agentId(), true);

        $rangeLabel = ['today' => '今日', '7d' => '近 7 天', '30d' => '近 30 天'][$range];
        $tabs = '';
        foreach (['today' => '今日', '7d' => '近 7 天', '30d' => '近 30 天'] as $key => $label) {
            $cls = $key === $range ? 'btn-line" style="border-color:#6366f1;color:#6366f1' : 'btn-line';
            $tabs .= '<a class="' . $cls . '" href="/agent/index/index?range=' . $key . '">' . $label . '</a>';
        }

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
            $page  = AgentService::memberPage($this->agentId(), 1, 8);
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
            $recent = '<div class="card"><h2>最近注册</h2><table>'
                . '<thead><tr><th>昵称</th><th>邮箱</th><th>来源链接</th><th>注册时间</th></tr></thead>'
                . '<tbody>' . $rows . '</tbody></table></div>';
        }

        $domainTip = '';
        if ($doms === []) {
            $domainTip = '<div class="alert err">总部还没有给你分配专属推广域名，暂时只能使用总部域名下的推广链接。</div>';
        }

        $content = <<<HTML
<div class="head">
  <div>
    <h1>数据概览</h1>
    <div class="sub">统计口径：注册按首触归因；充值与订阅取已支付订单。当前范围：{$rangeLabel}</div>
  </div>
  <div class="toolbar" style="margin:0">{$tabs}</div>
</div>
{$domainTip}
<div class="stats">
  <div class="stat"><div class="k">注册用户</div><div class="v">{$regs}</div><div class="d">{$rangeLabel}</div></div>
  <div class="stat"><div class="k">付费人数</div><div class="v">{$buyers}</div><div class="d">转化率 {$conv}</div></div>
  <div class="stat"><div class="k">充值金额</div><div class="v">{$amount}</div><div class="d">USD</div></div>
  <div class="stat"><div class="k">订阅</div><div class="v">{$subs}</div><div class="d">金额 {$subAmt} USD</div></div>
</div>
<div class="card">
  <h2>链接表现 TOP</h2>
  <table><thead><tr><th>推广链接名称</th><th class="num">注册数</th></tr></thead><tbody>{$linkRows}</tbody></table>
</div>
{$recent}
HTML;

        $this->render('overview', '数据概览', $content);
    }
}
