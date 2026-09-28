<?php
/**
 * 充值审核
 * 访问: /admin/live.RechargeOrder/index
 *
 * 覆盖两类订单：
 *   链上充值（lp_recharge_channel，usdt_trc20 / eth）—— 扫链自动到账，异常单人工补
 *   网关支付（star-pay / FlowPay）—— 回调自动到账，回调丢失时由 recharge:scan 查单补单
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\live\service\RechargeService;
use think\facade\Db;

final class RechargeOrder extends Backend
{
    // 绕过登录检查（iframe 模式下 cookie 不共享）
    protected array $noNeedLogin = ['index', 'approve', 'reject', 'batchApprove', 'batchReject', 'handleCallback'];

    // 飘页访问守卫：URL ?_key= 首次校验后走 Cookie（密钥 .env ADMIN_FLOAT_KEY）
    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    /** 一次批量操作最多处理多少笔，防止误点全表 */
    private const BATCH_MAX = 200;

    private const STATUS_MAP = [0 => '待审核', 1 => '已通过', 2 => '已拒绝', 3 => '已关闭'];

    public function index(): void
    {
        try {
            $db = Db::connect('live_mysql');

            $keyword   = trim((string) $this->request->param('search', ''));
            $status    = (string) $this->request->param('status', '');
            $channel   = (string) $this->request->param('channel', '');
            $bizType   = (string) $this->request->param('biz_type', '');
            $dateFrom  = trim((string) $this->request->param('date_from', ''));
            $dateTo    = trim((string) $this->request->param('date_to', ''));

            $query = $db->table('lp_recharge_order')->alias('o')
                ->join('lp_user u', 'u.id = o.user_id', 'LEFT')
                ->join('lp_recharge_channel ch', 'ch.id = o.channel_id', 'LEFT')
                ->field('o.*, u.nickname, ch.name as channel_name');

            if ($keyword !== '') {
                $query->where(function ($q) use ($keyword) {
                    $q->whereLike('o.order_no', "%{$keyword}%")
                      ->whereOr('o.system_ref', 'like', "%{$keyword}%")
                      ->whereOr('u.nickname', 'like', "%{$keyword}%");
                });
            }
            if ($status !== '') {
                $query->where('o.status', (int) $status);
            }
            if ($channel !== '') {
                $query->where('o.pay_channel', $channel);
            }
            if ($bizType !== '') {
                $query->where('o.biz_type', $bizType);
            }
            if ($dateFrom !== '') {
                $query->where('o.created_at', '>=', $dateFrom . ' 00:00:00');
            }
            if ($dateTo !== '') {
                $query->where('o.created_at', '<=', $dateTo . ' 23:59:59');
            }

            $list = $query->order('o.id', 'desc')->paginate(15);

            $html = $this->renderPage($list, [
                'keyword'   => $keyword,
                'status'    => $status,
                'channel'   => $channel,
                'biz_type'  => $bizType,
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
            ]);
            response($html)->send();
        } catch (\Exception $e) {
            echo '<pre>Error: ' . htmlspecialchars($e->getMessage()) . '</pre>';
        }
        exit;
    }

    public function approve(): void
    {
        $this->doReview('approve');
    }

    public function reject(): void
    {
        $this->doReview('reject');
    }

    /**
     * 批量审核：ids 逗号分隔，逐笔调用，单笔失败不影响其它
     */
    public function batchApprove(): void
    {
        $this->doReview('approve', true);
    }

    public function batchReject(): void
    {
        $this->doReview('reject', true);
    }

    /**
     * 把一条未匹配的链上转账标记为已人工处理
     */
    public function handleCallback(): void
    {
        $id = $this->request->param('id/d', 0);
        try {
            if ($id <= 0) {
                throw new \InvalidArgumentException('参数错误');
            }
            (new RechargeService())->markCallbackHandled($id);
            echo json_encode(['code' => 1, 'msg' => '已标记处理', 'data' => null], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            echo json_encode(['code' => 0, 'msg' => $e->getMessage(), 'data' => null], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /**
     * 审核通过 / 拒绝（单笔与批量共用）
     */
    private function doReview(string $action, bool $batch = false): void
    {
        $remark = trim((string) $this->request->param($action === 'approve' ? 'remark' : 'reason', ''));

        if ($batch) {
            $raw  = (string) $this->request->param('ids', '');
            $ids  = array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)))));
            $ids  = array_slice($ids, 0, self::BATCH_MAX);
        } else {
            $ids = [(int) $this->request->param('id/d', 0)];
        }

        if (!$ids) {
            echo json_encode(['code' => 0, 'msg' => '请先勾选订单', 'data' => null], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($action === 'reject' && $remark === '') {
            echo json_encode(['code' => 0, 'msg' => '请填写拒绝原因', 'data' => null], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $service = new RechargeService();
        $ok      = 0;
        $fails   = [];
        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }
            try {
                if ($action === 'approve') {
                    $service->approve($id, $remark);
                } else {
                    $service->reject($id, $remark);
                }
                $ok++;
            } catch (\Throwable $e) {
                $fails[] = "#{$id} " . $e->getMessage();
            }
        }

        $msg = $action === 'approve' ? "已通过 {$ok} 笔" : "已拒绝 {$ok} 笔";
        if ($fails) {
            $msg .= '，失败 ' . count($fails) . ' 笔：' . implode('；', array_slice($fails, 0, 3));
        }

        echo json_encode([
            'code' => $ok > 0 ? 1 : 0,
            'msg'  => $msg,
            'data' => ['ok' => $ok, 'fail' => $fails],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ------------------------------------------------------------------ 渲染 */

    private function renderPage($list, array $f): string
    {
        $db     = Db::connect('live_mysql');
        $domain = rtrim($this->request->domain(), '/');
        $now    = time();

        // ---- 顶部统计 ----
        $today          = date('Y-m-d');
        $pending        = (int) $db->table('lp_recharge_order')->where('status', 0)->count();
        $todayCount     = (int) $db->table('lp_recharge_order')->where('status', 1)->whereLike('paid_at', $today . '%')->count();
        $todayAmount    = (float) $db->table('lp_recharge_order')->where('status', 1)->whereLike('paid_at', $today . '%')->sum('pay_amount');
        $unmatchedCount = (int) $db->table('lp_payment_callback_log')->where('verify_status', 0)->count();

        // ---- 渠道（筛选项 + 地址健康检查）----
        $channels = $db->table('lp_recharge_channel')->order('id', 'asc')->select()->toArray();
        $sels     = '';
        foreach ($channels as $c) {
            $val  = htmlspecialchars((string) $c['type'], ENT_QUOTES);
            $sels .= '<option value="' . $val . '"' . ($f['channel'] === (string) $c['type'] ? ' selected' : '') . '>'
                . htmlspecialchars((string) $c['name'], ENT_QUOTES) . '</option>';
        }
        foreach (['starpay' => 'star-pay（网关）', 'flowpay' => 'FlowPay（网关）'] as $val => $label) {
            $sels .= '<option value="' . $val . '"' . ($f['channel'] === $val ? ' selected' : '') . '>' . $label . '</option>';
        }

        $alerts = '';
        foreach ($channels as $c) {
            if ((int) $c['status'] !== 1) {
                continue;
            }
            $err = RechargeService::addressError((string) $c['type'], (string) $c['address']);
            if ($err === '') {
                continue;
            }
            $alerts .= '<div class="alert">收款地址异常：<b>' . htmlspecialchars((string) $c['name'], ENT_QUOTES)
                . '</b> — ' . htmlspecialchars($err, ENT_QUOTES)
                . '（address=' . htmlspecialchars((string) $c['address'], ENT_QUOTES) . '）'
                . '，该渠道扫链已自动跳过，用户也转不进来，请尽快到「充值渠道」更正。</div>';
        }

        // ---- 表格 ----
        $rows      = '';
        $detailMap = [];
        foreach ($list->items() as $r) {
            $rid    = (int) $r['id'];
            $st     = (int) $r['status'];
            $stName = self::STATUS_MAP[$st] ?? '未知';
            $stCls  = [0 => 'pending', 1 => 'passed', 2 => 'rejected', 3 => 'closed'][$st] ?? '';

            $payChannel = (string) $r['pay_channel'];
            $unit       = $this->unitOf($payChannel);
            $channelLbl = $this->channelLabel($r);
            $isVip      = (string) ($r['biz_type'] ?? 'coin') === 'vip';
            $bizLbl     = $isVip ? '会员订阅' : '充值钻石';
            // 已关闭的订单不参与审核
            $reviewable = in_array($st, [0, 3], true);

            $expired = $st === 0 && !empty($r['expire_at']) && strtotime((string) $r['expire_at']) < $now;

            $proof = '<span style="color:#999">无</span>';
            $imgRaw = (string) $r['proof_image'];
            if ($imgRaw !== '') {
                if (str_starts_with($imgRaw, 'http')) {
                    $parsed  = parse_url($imgRaw);
                    $imgPath = $parsed['path'] ?? '';
                    $imgUrl  = $domain . '/' . ltrim($imgPath, '/');
                } else {
                    $imgUrl = $domain . '/' . ltrim($imgRaw, '/');
                }
                $proof = "<a href=\"javascript:void(0)\" onclick=\"showProof('"
                    . htmlspecialchars($imgUrl, ENT_QUOTES) . "')\" style='color:#409eff'>查看凭证</a>";
            }

            $ref = (string) ($r['system_ref'] ?? '');
            $refCell = $ref !== ''
                ? '<span title="' . htmlspecialchars($ref, ENT_QUOTES) . '">' . htmlspecialchars(mb_substr($ref, 0, 14), ENT_QUOTES) . (mb_strlen($ref) > 14 ? '…' : '') . '</span>'
                : '<span style="color:#bbb">-</span>';

            $chk = $reviewable
                ? '<input type="checkbox" class="rowchk" value="' . $rid . '" onclick="syncBulk()">'
                : '';

            $actions = '';
            if ($st === 0) {
                $actions = "<button class='btn btn-sm btn-success' onclick=\"showApprove({$rid})\">通过</button>"
                    . "<button class='btn btn-sm btn-danger' onclick=\"showReject({$rid})\">拒绝</button>";
            } elseif ($st === 3) {
                $actions = "<button class='btn btn-sm btn-success' onclick=\"showApprove({$rid})\">补单</button>"
                    . "<button class='btn btn-sm btn-danger' onclick=\"showReject({$rid})\">拒绝</button>";
            }

            $rows .= '<tr>'
                . '<td>' . $chk . '</td>'
                . '<td>' . $rid . '</td>'
                . '<td>' . htmlspecialchars((string) ($r['nickname'] ?: '-'), ENT_QUOTES) . '</td>'
                . '<td><a href="javascript:void(0)" onclick="showDetail(' . $rid . ')" class="olink">' . htmlspecialchars((string) $r['order_no'], ENT_QUOTES) . '</a></td>'
                . '<td>' . htmlspecialchars($channelLbl, ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars($bizLbl, ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars((string) $r['pay_amount'], ENT_QUOTES) . ' ' . $unit . '</td>'
                . '<td>' . htmlspecialchars((string) $r['diamond_amount'], ENT_QUOTES) . ($isVip ? '' : ' 钻') . '</td>'
                . '<td>' . $refCell . '</td>'
                . '<td>' . $proof . '</td>'
                . '<td><span class="status-badge ' . $stCls . '">' . $stName . '</span>'
                . ($expired ? ' <span class="status-badge pending">已过期</span>' : '') . '</td>'
                . '<td class="remark" title="' . htmlspecialchars((string) $r['admin_remark'], ENT_QUOTES) . '">'
                . htmlspecialchars(mb_substr((string) $r['admin_remark'], 0, 24), ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars((string) $r['created_at'], ENT_QUOTES) . '</td>'
                . '<td>' . $actions . '</td>'
                . '</tr>';

            $detailMap[$rid] = [
                'ID'        => $rid,
                '订单号'     => (string) $r['order_no'],
                '用户'       => ($r['nickname'] ?: '-') . ' (id=' . (int) $r['user_id'] . ')',
                '渠道'       => $channelLbl,
                '渠道类型'   => $payChannel . ($r['chain_type'] ? ' / ' . $r['chain_type'] : ''),
                '业务'       => $bizLbl . ($isVip && !empty($r['vip_plan_id']) ? '（套餐 #' . (int) $r['vip_plan_id'] . '）' : '')
                    . ($r['package_id'] ? '（充值套餐 #' . (int) $r['package_id'] . '）' : ''),
                '应付金额'   => (string) $r['pay_amount'] . ' ' . $unit,
                '到账'       => (string) $r['diamond_amount'] . ($isVip ? '（会员天数按套餐）' : ' 钻'),
                '网关/链上单号' => $ref !== '' ? $ref : '-',
                '收银台链接' => (string) ($r['pay_url'] ?? '') !== '' ? (string) $r['pay_url'] : '-',
                '状态'       => $stName . ($expired ? '（已过期，待关单）' : ''),
                '创建时间'   => (string) $r['created_at'],
                '过期时间'   => (string) ($r['expire_at'] ?? '-'),
                '支付时间'   => (string) ($r['paid_at'] ?? '-'),
                '审核时间'   => (string) ($r['reviewed_at'] ?? '-'),
                '备注'       => (string) ($r['admin_remark'] ?? ''),
            ];
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="14" style="text-align:center;color:#999;padding:26px">暂无符合条件的订单</td></tr>';
        }

        // ---- 异常链上转账（扫链没匹配到订单的入账）----
        $service   = new RechargeService();
        $callbacks = $service->unmatchedCallbacks(30);
        $cbRows    = '';
        foreach ($callbacks as $cb) {
            $cand      = $service->callbackCandidates($cb);
            $candHtml  = '<span style="color:#bbb">无匹配订单</span>';
            if ($cand) {
                $parts = [];
                foreach ($cand as $c) {
                    $tag = (int) $c['status'] === 3 ? '补单' : '通过';
                    $parts[] = '<a href="javascript:void(0)" onclick="showApprove(' . (int) $c['id'] . ')" class="olink">'
                        . htmlspecialchars((string) $c['order_no'], ENT_QUOTES) . '（' . $tag . '）</a>';
                }
                $candHtml = implode('、', $parts);
            }

            $ts     = (int) ($cb['block_timestamp'] ?? 0);
            $gwName = (string) $cb['gateway'] === 'eth' ? 'ETH' : 'USDT-TRC20';

            $cbRows .= '<tr>'
                . '<td>' . (int) $cb['id'] . '</td>'
                . '<td>' . htmlspecialchars($gwName, ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars((string) $cb['amount'], ENT_QUOTES) . '</td>'
                . '<td title="' . htmlspecialchars((string) $cb['from_address'], ENT_QUOTES) . '">'
                . htmlspecialchars(mb_substr((string) $cb['from_address'], 0, 16), ENT_QUOTES) . '…</td>'
                . '<td title="' . htmlspecialchars((string) $cb['payload_hash'], ENT_QUOTES) . '">'
                . htmlspecialchars(mb_substr((string) $cb['payload_hash'], 0, 16), ENT_QUOTES) . '…</td>'
                . '<td>' . htmlspecialchars($ts > 0 ? date('Y-m-d H:i:s', $ts) : '-', ENT_QUOTES) . '</td>'
                . '<td>' . $candHtml . '</td>'
                . '<td><button class="btn btn-sm" onclick="markCallback(' . (int) $cb['id'] . ')">标记已处理</button></td>'
                . '</tr>';
        }

        $callbacksHtml = '';
        if ($cbRows !== '') {
            $callbacksHtml = '<details class="cbwrap" open><summary>待人工处理的链上转账（' . count($callbacks)
                . ' 笔）—— 扫链没匹配到订单的入账，核对后在右侧订单上点「补单」</summary>'
                . '<div style="overflow-x:auto"><table><thead><tr><th>ID</th><th>链</th><th>金额</th><th>付款地址</th>'
                . '<th>交易号</th><th>链上时间</th><th>可能对应的订单</th><th>操作</th></tr></thead>'
                . '<tbody>' . $cbRows . '</tbody></table></div></details>';
        }

        $statsHtml = '<div class="stats">'
            . '<div class="stat"><span>待审核</span><strong class="c-orange">' . $pending . '</strong></div>'
            . '<div class="stat"><span>今日到账</span><strong class="c-green">' . $todayCount . ' 笔</strong></div>'
            . '<div class="stat"><span>今日金额</span><strong class="c-green">' . number_format($todayAmount, 2) . ' USD</strong></div>'
            . '<div class="stat"><span>异常链上转账</span><strong class="c-red">' . $unmatchedCount . '</strong></div>'
            . '</div>';

        $selStatus = '';
        foreach (['' => '全部'] + self::STATUS_MAP as $val => $label) {
            $selStatus .= '<option value="' . $val . '"' . ($f['status'] === (string) $val ? ' selected' : '') . '>' . $label . '</option>';
        }
        $selBiz = '';
        foreach (['' => '全部', 'coin' => '充值钻石', 'vip' => '会员订阅'] as $val => $label) {
            $selBiz .= '<option value="' . $val . '"' . ($f['biz_type'] === (string) $val ? ' selected' : '') . '>' . $label . '</option>';
        }

        return strtr(self::TEMPLATE, [
            '{{STATS}}'     => $statsHtml,
            '{{ALERTS}}'    => $alerts,
            '{{CALLBACKS}}' => $callbacksHtml,
            '{{KW}}'        => htmlspecialchars($f['keyword'], ENT_QUOTES),
            '{{SEL_STATUS}}' => $selStatus,
            '{{SEL_CHANNEL}}' => '<option value="">全部</option>' . $sels,
            '{{SEL_BIZ}}'   => $selBiz,
            '{{DATE_FROM}}' => htmlspecialchars($f['date_from'], ENT_QUOTES),
            '{{DATE_TO}}'   => htmlspecialchars($f['date_to'], ENT_QUOTES),
            '{{ROWS}}'      => $rows,
            '{{PAGER}}'     => $list->render(),
            '{{DETAIL}}'    => json_encode((object) $detailMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ]);
    }

    /** 支付金额单位：链上按币种，网关按法币 */
    private function unitOf(string $payChannel): string
    {
        return match ($payChannel) {
            'usdt_trc20' => 'USDT',
            'eth'        => 'ETH',
            default      => 'USD',
        };
    }

    private function channelLabel(array $r): string
    {
        $payChannel = (string) $r['pay_channel'];
        if ($payChannel === 'starpay') {
            return 'star-pay（网关）';
        }
        if ($payChannel === 'flowpay') {
            return 'FlowPay（网关）';
        }
        return (string) ($r['channel_name'] ?: $payChannel);
    }

    private const TEMPLATE = <<<'HTML'
<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>充值审核</title>
<style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:-apple-system,sans-serif;background:#f5f7fa;color:#333;font-size:14px}
    .container{max-width:1800px;margin:0 auto;padding:20px}
    h1{font-size:22px;margin-bottom:16px}
    .stats{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px}
    .stat{background:#fff;border-radius:8px;padding:12px 18px;box-shadow:0 2px 8px rgba(0,0,0,.06);min-width:150px}
    .stat span{display:block;color:#888;font-size:12px;margin-bottom:6px}
    .stat strong{font-size:20px}
    .c-orange{color:#f59e0b}.c-green{color:#00b894}.c-red{color:#ff2d55}
    .alert{background:#fff4f4;border:1px solid #ffd0d0;color:#c0392b;border-radius:8px;padding:12px 16px;margin-bottom:16px;line-height:1.6}
    .toolbar{display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;align-items:center;background:#fff;padding:14px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
    .toolbar input,.toolbar select{padding:7px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;background:#fff}
    .toolbar input[type=text],.toolbar input:not([type]){width:220px}
    .toolbar label{color:#666;font-size:13px}
    .btn{padding:6px 16px;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:500;margin:0 2px;background:#e9edf2;color:#333}
    .btn-sm{padding:4px 12px;font-size:12px}
    .btn-success{background:#00d4aa;color:#fff}.btn-danger{background:#ff2d55;color:#fff}
    .btn-primary{background:#409eff;color:#fff}
    .btn:hover{opacity:.85}
    .bulkbar{display:flex;align-items:center;gap:10px;margin-bottom:10px;color:#666}
    table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);min-width:1400px}
    th,td{padding:10px 12px;text-align:left;border-bottom:1px solid #eee;white-space:nowrap}
    th{background:#f8f9fc;font-weight:600;font-size:13px}
    tr:hover{background:#f9fafb}
    td.remark{max-width:180px;overflow:hidden;text-overflow:ellipsis}
    .olink{color:#409eff;text-decoration:none}
    .status-badge{padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;display:inline-block}
    .pending{background:rgba(245,158,11,.12);color:#f59e0b}
    .passed{background:rgba(0,212,170,.12);color:#00b894}
    .rejected{background:rgba(255,45,85,.12);color:#ff2d55}
    .closed{background:rgba(120,120,120,.12);color:#777}
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;align-items:center;justify-content:center}
    .modal-overlay.active{display:flex}
    .modal{background:#fff;border-radius:12px;padding:24px;width:460px;max-width:92vw;max-height:88vh;overflow:auto}
    .modal h3{margin-bottom:16px}
    .modal textarea{width:100%;height:80px;padding:10px;border:1px solid #ddd;border-radius:6px;resize:vertical;font-size:14px}
    .modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:16px}
    .pager{margin-top:20px;text-align:center}
    .pager ul{display:inline-flex;gap:4px;list-style:none}
    .pager li a,.pager li span{padding:6px 12px;border:1px solid #ddd;border-radius:4px;color:#333;text-decoration:none;font-size:13px;background:#fff}
    .pager .active span{background:#409eff;color:#fff;border-color:#409eff}
    .toast{position:fixed;top:20px;left:50%;transform:translateX(-50%);padding:10px 24px;border-radius:8px;color:#fff;z-index:9999;display:none}
    .toast.success{background:#00d4aa}.toast.error{background:#ff2d55}
    .cbwrap{background:#fff;border-radius:8px;padding:12px 16px;margin-top:18px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
    .cbwrap summary{cursor:pointer;font-weight:600;color:#c0392b;margin-bottom:10px}
    .cbwrap table{min-width:1100px;box-shadow:none}
    .dt-table{width:100%;border-collapse:collapse}
    .dt-table th{width:120px;background:#fafbfc;font-weight:600;color:#666;vertical-align:top}
    .dt-table td{word-break:break-all;white-space:normal}
    .proof-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.82);z-index:1000;align-items:center;justify-content:center}
    .proof-modal.show{display:flex}
    .proof-box{max-width:86%;max-height:88%;display:flex;flex-direction:column;align-items:center;gap:10px}
    .proof-box img{max-width:100%;max-height:82vh;border-radius:10px;background:#fff;box-shadow:0 10px 40px rgba(0,0,0,.5)}
    .proof-tip{color:#aab;font-size:12px}
</style></head><body><div class="container">
<h1>充值审核</h1>
{{STATS}}
{{ALERTS}}
<form class="toolbar" method="get">
    <input type="text" name="search" placeholder="搜索订单号 / 网关单号 / 昵称" value="{{KW}}">
    <select name="status">{{SEL_STATUS}}</select>
    <select name="channel">{{SEL_CHANNEL}}</select>
    <select name="biz_type">{{SEL_BIZ}}</select>
    <label>下单时间</label>
    <input type="date" name="date_from" value="{{DATE_FROM}}">
    <span>~</span>
    <input type="date" name="date_to" value="{{DATE_TO}}">
    <button class="btn btn-primary" type="submit">搜索</button>
    <a class="btn" href="?">重置</a>
</form>
<div class="bulkbar">
    <span id="bulkCount">已选 0 笔</span>
    <button class="btn btn-sm btn-success" onclick="showApprove(null, true)">批量通过</button>
    <button class="btn btn-sm btn-danger" onclick="showReject(null, true)">批量拒绝</button>
</div>
<div style="overflow-x:auto">
<table><thead><tr>
    <th><input type="checkbox" id="chkAll" onclick="toggleAll(this)"></th>
    <th>ID</th><th>用户</th><th>订单号</th><th>渠道</th><th>业务</th><th>支付金额</th><th>到账</th>
    <th>网关/链上单号</th><th>凭证</th><th>状态</th><th>备注</th><th>下单时间</th><th>操作</th>
</tr></thead><tbody>{{ROWS}}</tbody></table>
</div>
<div class="pager">{{PAGER}}</div>
{{CALLBACKS}}
</div>

<div class="modal-overlay" id="approveModal"><div class="modal"><h3 id="approveTitle">审核通过</h3>
    <p id="approveHint" style="font-size:13px;color:#888;margin-bottom:10px"></p>
    <label style="font-size:13px;color:#666">备注（可选）</label>
    <textarea id="approveRemark" placeholder="审核备注，例如：已核对链上转账 / 手工补单"></textarea>
    <div class="modal-actions"><button class="btn" onclick="closeApprove()">取消</button>
    <button class="btn btn-success" onclick="doApprove()">确认通过</button></div></div></div>

<div class="modal-overlay" id="rejectModal"><div class="modal"><h3 id="rejectTitle">拒绝原因</h3>
    <p id="rejectHint" style="font-size:13px;color:#888;margin-bottom:10px"></p>
    <textarea id="rejectReason" placeholder="请输入拒绝原因"></textarea>
    <div class="modal-actions"><button class="btn" onclick="closeReject()">取消</button>
    <button class="btn btn-danger" onclick="doReject()">确认拒绝</button></div></div></div>

<div class="modal-overlay" id="detailModal"><div class="modal" style="width:640px">
    <h3>订单详情</h3><table class="dt-table" id="detailBody"></table>
    <div class="modal-actions"><button class="btn" onclick="closeDetail()">关闭</button></div></div></div>

<div class="proof-modal" id="proofModal" onclick="closeProof(event)">
    <div class="proof-box"><img id="proofImg" src="" alt="支付凭证"><div class="proof-tip">点击空白处关闭</div></div>
</div>

<div class="toast" id="toast"></div>
<script>
var DETAILS = {{DETAIL}};

function toast(msg, type) {
    var el = document.getElementById('toast');
    el.textContent = msg;
    el.className = 'toast ' + type;
    el.style.display = 'block';
    setTimeout(function () { el.style.display = 'none'; }, 2600);
}

function toggleAll(box) {
    document.querySelectorAll('.rowchk').forEach(function (c) { c.checked = box.checked; });
    syncBulk();
}
function syncBulk() {
    var n = document.querySelectorAll('.rowchk:checked').length;
    document.getElementById('bulkCount').textContent = '已选 ' + n + ' 笔';
}
function selectedIds() {
    return Array.prototype.map.call(document.querySelectorAll('.rowchk:checked'), function (c) { return c.value; });
}

var approveTargets = [];
function showApprove(id, batch) {
    var sel = batch ? selectedIds() : [String(id)];
    if (!sel.length) { toast('请先勾选订单', 'error'); return; }
    approveTargets = sel;
    document.getElementById('approveTitle').textContent = batch ? '批量审核通过' : '审核通过';
    document.getElementById('approveHint').textContent = '将对 ' + sel.length + ' 笔订单加钻/开通会员：' + sel.slice(0, 6).join('、') + (sel.length > 6 ? ' …' : '');
    document.getElementById('approveRemark').value = '';
    document.getElementById('approveModal').classList.add('active');
}
function closeApprove() { document.getElementById('approveModal').classList.remove('active'); }

var rejectTargets = [];
function showReject(id, batch) {
    var sel = batch ? selectedIds() : [String(id)];
    if (!sel.length) { toast('请先勾选订单', 'error'); return; }
    rejectTargets = sel;
    document.getElementById('rejectTitle').textContent = batch ? '批量拒绝' : '拒绝';
    document.getElementById('rejectHint').textContent = '将对 ' + sel.length + ' 笔订单标记为已拒绝：' + sel.slice(0, 6).join('、') + (sel.length > 6 ? ' …' : '');
    document.getElementById('rejectReason').value = '';
    document.getElementById('rejectModal').classList.add('active');
}
function closeReject() { document.getElementById('rejectModal').classList.remove('active'); }

function post(url, body, onOk) {
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
        body: body
    }).then(function (r) { return r.json(); }).then(function (d) {
        if (d.code === 1) { toast(d.msg || '操作成功', 'success'); onOk && onOk(); setTimeout(function () { location.reload(); }, 1200); }
        else { toast(d.msg || '操作失败', 'error'); }
    }).catch(function () { toast('网络错误', 'error'); });
}

function doApprove() {
    var remark = document.getElementById('approveRemark').value.trim();
    post('/admin/live.RechargeOrder/batchApprove', 'ids=' + approveTargets.join(',') + '&remark=' + encodeURIComponent(remark), closeApprove);
}
function doReject() {
    var reason = document.getElementById('rejectReason').value.trim();
    if (!reason) { toast('请填写拒绝原因', 'error'); return; }
    post('/admin/live.RechargeOrder/batchReject', 'ids=' + rejectTargets.join(',') + '&reason=' + encodeURIComponent(reason), closeReject);
}

function markCallback(id) {
    post('/admin/live.RechargeOrder/handleCallback', 'id=' + id);
}

function showDetail(id) {
    var d = DETAILS[id];
    if (!d) { return; }
    var html = '';
    Object.keys(d).forEach(function (k) {
        var v = d[k] === '' ? '-' : String(d[k]);
        if (k === '收银台链接' && v !== '-') {
            v = '<a class="olink" href="' + v + '" target="_blank" rel="noopener">' + v + '</a>';
        } else {
            v = v.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
        html += '<tr><th>' + k + '</th><td>' + v + '</td></tr>';
    });
    document.getElementById('detailBody').innerHTML = html;
    document.getElementById('detailModal').classList.add('active');
}
function closeDetail() { document.getElementById('detailModal').classList.remove('active'); }

function showProof(url) {
    document.getElementById('proofImg').src = url;
    document.getElementById('proofModal').classList.add('show');
}
function closeProof(e) {
    if (e.target.id === 'proofModal' || e.target.classList.contains('proof-box')) {
        document.getElementById('proofModal').classList.remove('show');
        document.getElementById('proofImg').src = '';
    }
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeApprove(); closeReject(); closeDetail(); }
});
</script>
</body></html>
HTML;
}
