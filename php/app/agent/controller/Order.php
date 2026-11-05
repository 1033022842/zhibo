<?php
declare(strict_types=1);

namespace app\agent\controller;

use app\agent\service\AgentView;
use app\live\service\AgentService;

/**
 * 代理名下充值 / 订阅订单（只读，团队口径）
 */
final class Order extends Base
{
    private const STATUS = [0 => '待审核', 1 => '已通过', 2 => '已拒绝', 3 => '已关闭'];

    public function index(): void
    {
        $this->requirePerm('order');

        $selfId = $this->agentId();
        $page   = max(1, (int) ($this->request->param('page') ?? 1));
        $filter = [
            'keyword' => mb_substr(trim((string) ($this->request->param('kw') ?? '')), 0, 64),
            'status'  => (string) ($this->request->param('status') ?? ''),
            'biz'     => (string) ($this->request->param('biz') ?? ''),
            'from'    => (string) ($this->request->param('from') ?? ''),
            'to'      => (string) ($this->request->param('to') ?? ''),
        ];
        $data = AgentService::orderPage(AgentService::teamIds($selfId), $page, 30, $filter);

        $rows = '';
        foreach ($data['rows'] as $r) {
            $st    = (int) $r['status'];
            $chip  = match ($st) {
                1       => '<span class="chip on">已通过</span>',
                0       => '<span class="chip warn">待审核</span>',
                2       => '<span class="chip danger">已拒绝</span>',
                default => '<span class="chip off">已关闭</span>',
            };
            $biz   = (string) $r['biz_type'] === 'vip' ? '会员订阅' : '充值代币';
            $usd   = (float) $r['usd_amount'] > 0 ? (float) $r['usd_amount'] : (float) $r['pay_amount'];
            $ownerId = (int) $r['reg_agent_id'];
            $owner = $ownerId === $selfId
                ? '<span class="muted">本人</span>'
                : AgentView::h(AgentService::agentName($ownerId) ?: ('代理#' . $ownerId));

            $rows .= '<tr>'
                . '<td><div class="mono">' . AgentView::h((string) $r['order_no']) . '</div></td>'
                . '<td><div class="who-cell"><div><div class="nm">' . AgentView::h((string) $r['nickname']) . '</div>'
                . '<div class="sub">' . AgentView::h(AgentService::maskEmail((string) $r['email'])) . '</div></div></div></td>'
                . '<td>' . $owner . '</td>'
                . '<td>' . $biz . '</td>'
                . '<td>' . AgentView::h((string) $r['pay_channel']) . '</td>'
                . '<td class="num">' . number_format($usd, 2) . '</td>'
                . '<td class="num">' . number_format((float) $r['diamond_amount'], 2) . '</td>'
                . '<td>' . $chip . '</td>'
                . '<td class="nowrap">' . AgentView::h((string) $r['created_at']) . '</td>'
                . '<td class="nowrap">' . AgentView::h((string) ($r['paid_at'] ?? '') ?: '—') . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="10" class="empty">没有找到订单</td></tr>';
        }

        $pager = $this->pager('/agent/order/index', (int) $data['page'], (int) $data['pages'], (int) $data['total'], '笔', $filter);
        $opts  = static function (array $map, string $cur): string {
            $html = '';
            foreach ($map as $val => $label) {
                $html .= '<option value="' . AgentView::h((string) $val) . '"' . ((string) $val === $cur ? ' selected' : '') . '>'
                    . AgentView::h($label) . '</option>';
            }
            return $html;
        };
        $statusOpts = $opts(['' => '全部状态'] + self::STATUS, $filter['status']);
        $bizOpts    = $opts(['' => '全部类型', 'coin' => '充值代币', 'vip' => '会员订阅'], $filter['biz']);
        $kw   = AgentView::h($filter['keyword']);
        $from = AgentView::h($filter['from']);
        $to   = AgentView::h($filter['to']);
        $totalUsd = number_format((float) $data['paid_usd'], 2);

        $content = <<<HTML
<div class="head">
  <div>
    <h1>充值订单</h1>
    <div class="sub">你与下级代理名下会员的充值与订阅订单（只读，不含导出与用户隐私信息）。</div>
  </div>
</div>
<div class="stats">
  <div class="stat"><div class="ico indigo"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></div><div><div class="k">订单数</div><div class="v">{$data['total']}</div><div class="d">当前筛选</div></div></div>
  <div class="stat"><div class="ico emerald"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg></div><div><div class="k">已通过</div><div class="v">{$data['paid_count']}</div><div class="d">笔</div></div></div>
  <div class="stat"><div class="ico amber"><svg viewBox="0 0 24 24"><path d="M12 2v20"/><path d="M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><div><div class="k">已通过金额</div><div class="v">\${$totalUsd}</div><div class="d">USD</div></div></div>
</div>
<div class="card">
  <form class="toolbar" method="get" action="/agent/order/index">
    <input type="text" name="kw" value="{$kw}" placeholder="订单号 / 昵称 / 邮箱" maxlength="64">
    <select name="status">{$statusOpts}</select>
    <select name="biz">{$bizOpts}</select>
    <input type="date" name="from" value="{$from}">
    <input type="date" name="to" value="{$to}">
    <button class="btn-line" type="submit">筛选</button>
    <a class="btn-line" href="/agent/order/index">重置</a>
  </form>
  <div class="table-wrap"><table><thead><tr>
    <th>订单号</th><th>会员</th><th>归属代理</th><th>类型</th><th>支付渠道</th>
    <th class="num">金额(USD)</th><th class="num">代币</th><th>状态</th><th>下单时间</th><th>支付时间</th>
  </tr></thead><tbody>{$rows}</tbody></table></div>
  {$pager}
</div>
HTML;

        $this->render('order', '充值订单', $content);
    }

    /** 分页条（保留筛选参数） */
    private function pager(string $base, int $cur, int $pages, int $total, string $unit, array $filter): string
    {
        if ($total <= 0) {
            return '';
        }
        $qs = [];
        foreach (['keyword' => 'kw', 'status' => 'status', 'biz' => 'biz', 'from' => 'from', 'to' => 'to'] as $key => $name) {
            $val = trim((string) ($filter[$key] ?? ''));
            if ($val !== '') {
                $qs[$name] = $val;
            }
        }
        $link = static function (int $p) use ($base, $qs): string {
            $qs['page'] = $p;
            return $base . '?' . http_build_query($qs);
        };
        $html = '<div class="pager">';
        $html .= $cur > 1 ? '<a href="' . $link($cur - 1) . '">上一页</a>' : '<span class="muted">上一页</span>';
        $html .= '<span>第 ' . $cur . ' / ' . max(1, $pages) . ' 页</span>';
        $html .= $cur < $pages ? '<a href="' . $link($cur + 1) . '">下一页</a>' : '<span class="muted">下一页</span>';
        $html .= '<span class="muted">共 ' . number_format($total) . ' ' . $unit . '</span></div>';
        return $html;
    }
}
