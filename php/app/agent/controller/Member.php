<?php
declare(strict_types=1);

namespace app\agent\controller;

use app\agent\service\AgentView;
use app\live\service\AgentService;

/**
 * 代理名下会员明细（邮箱打码，不含登录凭证/设备/IP）
 */
final class Member extends Base
{
    public function index(): void
    {
        $this->requirePerm('member');

        $page    = max(1, (int) ($this->request->param('page') ?? 1));
        $keyword = mb_substr(trim((string) ($this->request->param('kw') ?? '')), 0, 64);
        $data    = AgentService::memberPage($this->agentId(), $page, 30, $keyword);

        $rows = '';
        foreach ($data['rows'] as $m) {
            $pay = (float) ($m['pay_amount'] ?? 0);
            $sub = (float) ($m['sub_amount'] ?? 0);
            $rows .= '<tr>'
                . '<td>' . AgentView::h((string) $m['nickname']) . '</td>'
                . '<td>' . AgentView::h(AgentService::maskEmail((string) $m['email'])) . '</td>'
                . '<td>' . AgentView::h((string) (($m['reg_link_name'] ?? '') !== '' ? $m['reg_link_name'] : '直接访问')) . '</td>'
                . '<td class="num">' . number_format($pay, 2) . '</td>'
                . '<td class="num">' . number_format($sub, 2) . '</td>'
                . '<td>' . AgentView::h((string) $m['created_at']) . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="empty">没有找到会员</td></tr>';
        }

        $pager  = '';
        $qs     = $keyword !== '' ? '&kw=' . urlencode($keyword) : '';
        $cur    = (int) $data['page'];
        $pages  = (int) $data['pages'];
        if ($pages > 1) {
            $prev = $cur > 1
                ? '<a href="/agent/member/index?page=' . ($cur - 1) . $qs . '">上一页</a>'
                : '<span class="muted">上一页</span>';
            $next = $cur < $pages
                ? '<a href="/agent/member/index?page=' . ($cur + 1) . $qs . '">下一页</a>'
                : '<span class="muted">下一页</span>';
            $pager = '<div class="pager">' . $prev . '<span>第 ' . $cur . ' / ' . $pages . ' 页</span>' . $next
                . '<span class="muted">共 ' . number_format((int) $data['total']) . ' 人</span></div>';
        } elseif ((int) $data['total'] > 0) {
            $pager = '<div class="pager"><span class="muted">共 ' . number_format((int) $data['total']) . ' 人</span></div>';
        }

        $kw = AgentView::h($keyword);

        $content = <<<HTML
<div class="head">
  <div>
    <h1>会员明细</h1>
    <div class="sub">仅展示通过你名下链接或专属域名注册的会员；邮箱已打码，不提供导出。</div>
  </div>
</div>
<div class="card">
  <form class="toolbar" method="get" action="/agent/member/index">
    <input type="text" name="kw" value="{$kw}" placeholder="搜索昵称或邮箱" maxlength="64">
    <button class="btn-line" type="submit">搜索</button>
  </form>
  <table><thead><tr>
    <th>昵称</th><th>邮箱</th><th>来源链接</th>
    <th class="num">累计充值(USD)</th><th class="num">累计订阅(USD)</th><th>注册时间</th>
  </tr></thead><tbody>{$rows}</tbody></table>
  {$pager}
</div>
HTML;

        $this->render('member', '会员明细', $content);
    }
}
