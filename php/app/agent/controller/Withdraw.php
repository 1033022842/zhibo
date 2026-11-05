<?php
declare(strict_types=1);

namespace app\agent\controller;

use app\agent\service\AgentView;
use app\live\service\AgentService;

/**
 * 代理名下提现记录（只读，团队口径）
 */
final class Withdraw extends Base
{
    private const STATUS = [0 => '待审核', 1 => '已打款', 2 => '已拒绝'];

    public function index(): void
    {
        $this->requirePerm('withdraw');

        $selfId = $this->agentId();
        $page   = max(1, (int) ($this->request->param('page') ?? 1));
        $filter = [
            'keyword' => mb_substr(trim((string) ($this->request->param('kw') ?? '')), 0, 64),
            'status'  => (string) ($this->request->param('status') ?? ''),
        ];
        $data = AgentService::withdrawPage(AgentService::teamIds($selfId), $page, 30, $filter);

        $rows = '';
        foreach ($data['rows'] as $r) {
            $st   = (int) $r['status'];
            $chip = match ($st) {
                1       => '<span class="chip on">已打款</span>',
                2       => '<span class="chip danger">已拒绝</span>',
                default => '<span class="chip warn">待审核</span>',
            };
            $ownerId = (int) $r['reg_agent_id'];
            $owner = $ownerId === $selfId
                ? '<span class="muted">本人</span>'
                : AgentView::h(AgentService::agentName($ownerId) ?: ('代理#' . $ownerId));
            $addr  = trim((string) $r['address']);
            $addr  = $addr === '' ? '—' : (mb_strlen($addr) > 14 ? mb_substr($addr, 0, 8) . '…' . mb_substr($addr, -4) : $addr);
            $note  = trim((string) ($r['reject_reason'] ?? ''));
            $tx    = trim((string) ($r['tx_hash'] ?? ''));

            $rows .= '<tr>'
                . '<td><div class="mono">' . AgentView::h((string) $r['order_no']) . '</div></td>'
                . '<td><div class="who-cell"><div><div class="nm">' . AgentView::h((string) $r['nickname']) . '</div>'
                . '<div class="sub">' . AgentView::h(AgentService::maskEmail((string) $r['email'])) . '</div></div></div></td>'
                . '<td>' . $owner . '</td>'
                . '<td class="num">' . number_format((float) $r['diamond_amount'], 2) . '</td>'
                . '<td class="num">' . number_format((float) $r['usdt_amount'], 2) . '</td>'
                . '<td>' . AgentView::h((string) $r['network']) . '</td>'
                . '<td><span class="mono">' . AgentView::h($addr) . '</span></td>'
                . '<td>' . $chip . '</td>'
                . '<td>' . ($note !== '' ? AgentView::h($note) : ($tx !== '' ? '<span class="mono">' . AgentView::h(mb_substr($tx, 0, 10)) . '…</span>' : '—')) . '</td>'
                . '<td class="nowrap">' . AgentView::h((string) $r['created_at']) . '</td>'
                . '<td class="nowrap">' . AgentView::h((string) ($r['paid_at'] ?? '') ?: '—') . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="11" class="empty">没有找到提现记录</td></tr>';
        }

        $qs = [];
        if ($filter['keyword'] !== '') {
            $qs['kw'] = $filter['keyword'];
        }
        if ($filter['status'] !== '') {
            $qs['status'] = $filter['status'];
        }
        $pager = '';
        $cur   = (int) $data['page'];
        $pages = (int) $data['pages'];
        if ((int) $data['total'] > 0) {
            $link = static function (int $p) use ($qs): string {
                $qs['page'] = $p;
                return '/agent/withdraw/index?' . http_build_query($qs);
            };
            $pager = '<div class="pager">'
                . ($cur > 1 ? '<a href="' . $link($cur - 1) . '">上一页</a>' : '<span class="muted">上一页</span>')
                . '<span>第 ' . $cur . ' / ' . max(1, $pages) . ' 页</span>'
                . ($cur < $pages ? '<a href="' . $link($cur + 1) . '">下一页</a>' : '<span class="muted">下一页</span>')
                . '<span class="muted">共 ' . number_format((int) $data['total']) . ' 笔</span></div>';
        }

        $statusOpts = '';
        foreach (['' => '全部状态'] + self::STATUS as $val => $label) {
            $statusOpts .= '<option value="' . AgentView::h((string) $val) . '"'
                . ((string) $val === $filter['status'] ? ' selected' : '') . '>' . AgentView::h($label) . '</option>';
        }
        $kw       = AgentView::h($filter['keyword']);
        $paidUsdt = number_format((float) $data['paid_usdt'], 2);

        $content = <<<HTML
<div class="head">
  <div>
    <h1>提现记录</h1>
    <div class="sub">你与下级代理名下会员的提现申请与打款状态（只读）；审核与打款由总部财务处理。</div>
  </div>
</div>
<div class="stats">
  <div class="stat"><div class="ico indigo"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></div><div><div class="k">提现笔数</div><div class="v">{$data['total']}</div><div class="d">当前筛选</div></div></div>
  <div class="stat"><div class="ico emerald"><svg viewBox="0 0 24 24"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg></div><div><div class="k">已打款</div><div class="v">\${$paidUsdt}</div><div class="d">USDT</div></div></div>
</div>
<div class="card">
  <form class="toolbar" method="get" action="/agent/withdraw/index">
    <input type="text" name="kw" value="{$kw}" placeholder="订单号 / 昵称 / 邮箱" maxlength="64">
    <select name="status">{$statusOpts}</select>
    <button class="btn-line" type="submit">筛选</button>
    <a class="btn-line" href="/agent/withdraw/index">重置</a>
  </form>
  <div class="table-wrap"><table><thead><tr>
    <th>订单号</th><th>会员</th><th>归属代理</th><th class="num">代币</th><th class="num">USDT</th>
    <th>网络</th><th>地址</th><th>状态</th><th>备注 / TxHash</th><th>申请时间</th><th>打款时间</th>
  </tr></thead><tbody>{$rows}</tbody></table></div>
  {$pager}
  <div class="tip">地址已做脱敏展示；如需核对完整地址或处理异常提现，请联系总部管理员。</div>
</div>
HTML;

        $this->render('withdraw', '提现记录', $content);
    }
}
