<?php
/**
 * 会员订阅记录
 * 访问: /admin/live.VipOrder/index
 *
 * 数据源 lp_vip_order —— 三类会员单都在这里：
 *   代币购买（order_no = VIP…）、网关/链上付款（order_no = RC…）、后台手动开通（order_no = ADMIN…）
 * 之前后台只在「充值订单」里看得到网关付款那一类，代币购买与手动开通的记录无处可查。
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use think\facade\Db;

final class VipOrder extends Backend
{
    // 绕过登录检查（iframe 模式下 cookie 不共享），与 充值审核 一致
    protected array $noNeedLogin = ['index'];

    // 飘页访问守卫：URL ?_key= 首次校验后走 Cookie（密钥 .env ADMIN_FLOAT_KEY）
    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    public function index(): void
    {
        try {
            $db = Db::connect('live_mysql');

            $keyword  = trim((string) $this->request->param('search', ''));
            $planId   = (string) $this->request->param('plan_id', '');
            $active   = (string) $this->request->param('active', '');
            $dateFrom = trim((string) $this->request->param('date_from', ''));
            $dateTo   = trim((string) $this->request->param('date_to', ''));

            $now = date('Y-m-d H:i:s');

            $query = $db->table('lp_vip_order')->alias('o')
                ->join('lp_user u', 'u.id = o.user_id', 'LEFT')
                ->join('lp_vip_plan p', 'p.id = o.plan_id', 'LEFT')
                ->field('o.*, u.nickname, u.vip_expire_at, p.name as plan_name, p.level as plan_level, p.months as plan_months');

            if ($keyword !== '') {
                $query->where(function ($q) use ($keyword) {
                    $q->whereLike('o.order_no', "%{$keyword}%")
                      ->whereOr('u.nickname', 'like', "%{$keyword}%");
                    if (ctype_digit($keyword)) {
                        $q->whereOr('o.user_id', (int) $keyword);
                    }
                });
            }
            if ($planId !== '') { $query->where('o.plan_id', (int) $planId); }
            if ($dateFrom !== '') { $query->where('o.created_at', '>=', $dateFrom . ' 00:00:00'); }
            if ($dateTo !== '') { $query->where('o.created_at', '<=', $dateTo . ' 23:59:59'); }
            if ($active === '1') { $query->where('o.expire_to', '>=', $now); }
            if ($active === '0') { $query->where('o.expire_to', '<', $now); }

            $list = $query->order('o.id', 'desc')->paginate(15);

            // 统计
            $total      = $db->table('lp_vip_order')->count();
            $activeVips = $db->table('lp_user')->where('vip_expire_at', '>', $now)->count();
            $monthCount = $db->table('lp_vip_order')->where('created_at', '>=', date('Y-m-01 00:00:00'))->count();
            $cancelled  = $db->table('lp_user')->where('vip_auto_renew', 0)->whereNotNull('vip_cancel_at')->count();

            $statsHtml = '<div class="stats">'
                . '<div class="stat"><span>订阅/开通单总数</span><strong>' . $total . '</strong></div>'
                . '<div class="stat"><span>当前有效会员</span><strong class="c-green">' . $activeVips . '</strong></div>'
                . '<div class="stat"><span>本月新增</span><strong class="c-green">' . $monthCount . '</strong></div>'
                . '<div class="stat"><span>已取消续订</span><strong class="c-orange">' . $cancelled . '</strong></div>'
                . '</div>';

            // 套餐下拉（含已下架套餐，历史单也要能筛）
            $planSels = '<option value="">全部套餐</option>';
            foreach ($db->table('lp_vip_plan')->field('id,name,status')->order('sort', 'asc')->select()->toArray() as $p) {
                $planSels .= '<option value="' . (int) $p['id'] . '"' . ($planId === (string) $p['id'] ? ' selected' : '') . '>'
                    . htmlspecialchars((string) $p['name'], ENT_QUOTES) . ((int) $p['status'] === 1 ? '' : '（已下架）') . '</option>';
            }

            $activeSels = '';
            foreach (['' => '全部', '1' => '生效中', '0' => '已到期'] as $val => $label) {
                $activeSels .= '<option value="' . $val . '"' . ($active === (string) $val ? ' selected' : '') . '>' . $label . '</option>';
            }

            $rows = '';
            foreach ($list->items() as $r) {
                $userId   = (int) $r['user_id'];
                $expireTo = (string) $r['expire_to'];
                $isActive = $expireTo !== '' && strtotime($expireTo) > time();
                $src      = $this->sourceOf($r);

                $activeBadge = $isActive
                    ? '<span class="badge passed">生效中</span>'
                    : '<span class="badge closed">已到期</span>';
                $srcBadge = match ($src) {
                    'admin'  => '<span class="badge warn">后台开通</span>',
                    'gw'     => '<span class="badge info">网关/链上</span>',
                    'coin'   => '<span class="badge purple">代币购买</span>',
                    default  => '<span class="badge closed">其它</span>',
                };

                $rows .= '<tr>'
                    . '<td>' . (int) $r['id'] . '</td>'
                    . '<td>' . htmlspecialchars((string) $r['order_no'], ENT_QUOTES) . '</td>'
                    . '<td>' . $userId . '</td>'
                    . '<td>' . htmlspecialchars((string) ($r['nickname'] ?? ''), ENT_QUOTES) . '</td>'
                    . '<td>' . htmlspecialchars((string) ($r['plan_name'] ?? ('套餐#' . (int) $r['plan_id'])), ENT_QUOTES)
                        . ($r['plan_months'] ? ' <span class="muted">/' . (int) $r['plan_months'] . '个月</span>' : '') . '</td>'
                    . '<td>' . number_format((float) $r['diamond_amount'], 2) . '</td>'
                    . '<td>' . $srcBadge . '</td>'
                    . '<td>' . htmlspecialchars((string) $r['expire_from'], ENT_QUOTES) . '</td>'
                    . '<td>' . htmlspecialchars($expireTo, ENT_QUOTES) . '</td>'
                    . '<td>' . $activeBadge . '</td>'
                    . '<td>' . htmlspecialchars((string) $r['created_at'], ENT_QUOTES) . '</td>'
                    . '</tr>';
            }
            if ($rows === '') {
                $rows = '<tr><td colspan="11" style="text-align:center;color:#999;padding:24px">没有符合条件的订阅记录</td></tr>';
            }

            echo strtr(self::TEMPLATE, [
                '{{STATS}}'       => $statsHtml,
                '{{KW}}'          => htmlspecialchars($keyword, ENT_QUOTES),
                '{{SEL_PLAN}}'    => $planSels,
                '{{SEL_ACTIVE}}'  => $activeSels,
                '{{DATE_FROM}}'   => htmlspecialchars($dateFrom, ENT_QUOTES),
                '{{DATE_TO}}'     => htmlspecialchars($dateTo, ENT_QUOTES),
                '{{ROWS}}'        => $rows,
                '{{PAGER}}'       => $list->render(),
            ]);
        } catch (\Throwable $e) {
            echo '<pre>Error: ' . htmlspecialchars($e->getMessage()) . '</pre>';
        }
        exit;
    }

    /** 订单来源：按单号前缀识别（lp_vip_order 不存支付渠道） */
    private function sourceOf(array $r): string
    {
        $no = (string) $r['order_no'];
        if (str_starts_with($no, 'ADMIN')) {
            return 'admin';
        }
        if (str_starts_with($no, 'RC')) {
            return 'gw';
        }
        if (str_starts_with($no, 'VIP')) {
            return 'coin';
        }

        return 'other';
    }

    private const TEMPLATE = <<<'HTML'
<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>会员订阅记录</title>
<style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:-apple-system,sans-serif;background:#f5f7fa;color:#333;font-size:14px}
    .container{max-width:1700px;margin:0 auto;padding:20px}
    h1{font-size:22px;margin-bottom:6px}
    .lead{color:#888;font-size:13px;margin-bottom:16px}
    .stats{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px}
    .stat{background:#fff;border-radius:8px;padding:12px 18px;box-shadow:0 2px 8px rgba(0,0,0,.06);min-width:150px}
    .stat span{display:block;color:#888;font-size:12px;margin-bottom:6px}
    .stat strong{font-size:20px}
    .c-orange{color:#f59e0b}.c-green{color:#00b894}.c-red{color:#ff2d55}
    .toolbar{display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;align-items:center;background:#fff;padding:14px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
    .toolbar input,.toolbar select{padding:7px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;background:#fff}
    .toolbar input[type=text],.toolbar input:not([type]){width:240px}
    .toolbar label{color:#666;font-size:13px}
    .btn{padding:7px 18px;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:500;background:#409eff;color:#fff}
    .btn:hover{opacity:.85}
    table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);min-width:1250px}
    th,td{padding:10px 12px;text-align:left;border-bottom:1px solid #eee;white-space:nowrap}
    th{background:#f8f9fc;font-weight:600;font-size:13px}
    tr:hover{background:#f9fafb}
    .muted{color:#aaa;font-size:12px}
    .badge{padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;display:inline-block}
    .passed{background:rgba(0,212,170,.12);color:#00b894}
    .closed{background:#eef0f4;color:#8a93a3}
    .warn{background:rgba(245,158,11,.12);color:#f59e0b}
    .info{background:rgba(64,158,255,.12);color:#409eff}
    .purple{background:rgba(139,92,246,.12);color:#8b5cf6}
    .pager{margin-top:14px}
</style></head><body><div class="container">
<h1>会员订阅记录</h1>
<div class="lead">代币购买 / 网关与链上付款 / 后台手动开通 三类会员单都在这里（网关付款的详情可在「充值审核」里查）。</div>
{{STATS}}
<form class="toolbar" method="get" action="/admin/live.VipOrder/index">
    <input type="text" name="search" value="{{KW}}" placeholder="订单号 / 昵称 / 用户ID">
    <select name="plan_id">{{SEL_PLAN}}</select>
    <select name="active">{{SEL_ACTIVE}}</select>
    <label>下单时间</label>
    <input type="date" name="date_from" value="{{DATE_FROM}}">
    <span>-</span>
    <input type="date" name="date_to" value="{{DATE_TO}}">
    <button class="btn" type="submit">筛选</button>
</form>
<div style="overflow-x:auto"><table>
<thead><tr>
    <th>ID</th><th>订单号</th><th>用户ID</th><th>昵称</th><th>套餐</th><th>实付代币</th>
    <th>来源</th><th>生效开始</th><th>到期时间</th><th>状态</th><th>支付时间</th>
</tr></thead>
<tbody>{{ROWS}}</tbody>
</table></div>
<div class="pager">{{PAGER}}</div>
</div></body></html>
HTML;
}
