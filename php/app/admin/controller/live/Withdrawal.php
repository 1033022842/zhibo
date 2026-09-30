<?php
/**
 * 提现审核 / 链上打款
 * 访问: /admin/live.Withdrawal/index?_key=<ADMIN_FLOAT_KEY>
 * 通过 = 自动链上打款（.env [PAYOUT] MODE=auto）或人工转账后登记 TxHash；拒绝 = 自动退钻
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\live\service\ChainPayoutService;
use app\live\service\WithdrawService;

final class Withdrawal extends Backend
{
    protected array $noNeedLogin = ['index', 'approve', 'reject', 'retry'];
    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    private const PAY_TEXT = [
        WithdrawService::PAY_PENDING    => '待打款',
        WithdrawService::PAY_BROADCAST  => '链上确认中',
        WithdrawService::PAY_CONFIRMED  => '已到账',
        WithdrawService::PAY_FAILED     => '打款失败',
        WithdrawService::PAY_PROCESSING => '处理中',
    ];

    public function index(): void
    {
        try {
            $keyword = $this->request->param('search', '');
            $status  = $this->request->param('status', '');

            $svc = new WithdrawService();
            $page = $svc->pendingList($keyword, $status === '' ? -1 : (int)$status);
            $html = $this->renderPage($page);
            response($html)->send();
        } catch (\Exception $e) {
            echo '<pre>Error: ' . htmlspecialchars($e->getMessage()) . '</pre>';
        }
        exit;
    }

    public function approve(): void
    {
        $id = (int) $this->request->param('id', 0);
        $txHash = trim((string) $this->request->param('tx_hash', ''));
        try {
            $result = (new WithdrawService())->approve($id, $txHash);
            $msg = ($result['mode'] ?? '') === 'manual'
                ? '已确认打款'
                : '已提交链上打款，等待链上确认（TxHash ' . $result['tx_hash'] . '）';
            echo json_encode(['code' => 1, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            echo json_encode(['code' => 0, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /** 打款失败后重试（请先确认热钱包没有出账） */
    public function retry(): void
    {
        $id = (int) $this->request->param('id', 0);
        try {
            $result = (new WithdrawService())->retry($id);
            echo json_encode(['code' => 1, 'msg' => '已重新提交链上打款（TxHash ' . $result['tx_hash'] . '）'], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            echo json_encode(['code' => 0, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    public function reject(): void
    {
        $id = (int) $this->request->param('id', 0);
        $reason = trim((string) $this->request->param('reason', ''));
        if ($reason === '') {
            echo json_encode(['code' => 0, 'msg' => '请填写拒绝原因（将退回代币）'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        try {
            (new WithdrawService())->reject($id, $reason);
            echo json_encode(['code' => 1, 'msg' => '已拒绝并退代币'], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            echo json_encode(['code' => 0, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function renderPage(array $page): string
    {
        $autoMode = (new ChainPayoutService())->isAutoEnabled();

        $rows = '';
        $map = [0 => '待审核', 1 => '已打款', 2 => '已拒绝'];
        foreach ($page['data'] ?? [] as $r) {
            $st    = $map[(int)$r['status']] ?? '?';
            $cls   = (int)$r['status'] === 0 ? 'pending' : ((int)$r['status'] === 1 ? 'passed' : 'rejected');
            $pay   = (int)($r['pay_state'] ?? 0);
            $payTx = self::PAY_TEXT[$pay] ?? '?';
            $payCls = [0 => 'pending', 1 => 'pending', 2 => 'passed', 3 => 'rejected', 4 => 'pending'][$pay] ?? 'pending';

            $rid = (int)$r['id'];
            $actions = '';
            if ($pay === WithdrawService::PAY_PENDING) {
                $actions .= "<button class='btn btn-sm btn-success' onclick=\"showApprove({$rid})\">确认打款</button>";
            } elseif ($pay === WithdrawService::PAY_FAILED) {
                $actions .= "<button class='btn btn-sm btn-warn' onclick=\"showRetry({$rid})\">重试打款</button>";
            }
            if (in_array($pay, [WithdrawService::PAY_PENDING, WithdrawService::PAY_FAILED], true)) {
                $actions .= "<button class='btn btn-sm btn-danger' onclick=\"showReject({$rid})\">拒绝</button>";
            }
            if ($actions === '') {
                $actions = "<span class='hint'>-</span>";
            }

            $txHash  = (string)($r['tx_hash'] ?? '');
            $payErr  = (string)($r['pay_error'] ?? '');
            $remark  = $payErr !== '' ? $payErr : (string)($r['reject_reason'] ?? '');

            $rows .= "<tr>
                <td>{$r['id']}</td>
                <td>" . htmlspecialchars((string)($r['nickname'] ?: '-')) . "</td>
                <td>" . htmlspecialchars((string)$r['order_no']) . "</td>
                <td>{$r['diamond_amount']} 代币</td>
                <td>{$r['actual_usdt']} USDT</td>
                <td title='" . htmlspecialchars((string)$r['address']) . "'>" . htmlspecialchars(substr((string)$r['address'], 0, 12)) . "…</td>
                <td>{$r['network']}</td>
                <td><span class='status-badge {$cls}'>{$st}</span></td>
                <td><span class='status-badge {$payCls}'>{$payTx}</span></td>
                <td>" . ($txHash !== '' ? "<a class='tx' target='_blank' href='" . $this->explorerUrl((string)$r['network'], $txHash) . "'>" . htmlspecialchars(substr($txHash, 0, 16)) . "…</a>" : '-') . "</td>
                <td>" . htmlspecialchars($remark !== '' ? mb_substr($remark, 0, 40) : '-') . "</td>
                <td>{$r['created_at']}</td>
                <td>{$actions}</td>
            </tr>";
        }

        $sv = (int)($this->request->param('status', '') === '' ? -1 : $this->request->param('status', -1));
        $sAll = $sv === -1 ? 'selected' : '';
        $s0 = $sv === 0 ? 'selected' : '';
        $s1 = $sv === 1 ? 'selected' : '';
        $s2 = $sv === 2 ? 'selected' : '';
        $kw = htmlspecialchars((string)$this->request->param('search', ''));

        $pager = (string)($page['render'] ?? '');

        $modeNote = $autoMode
            ? '当前为 <b style="color:#28c76f">自动链上打款</b>：点「确认打款」即由热钱包自动转账并回写 TxHash。'
            : '当前为 <b style="color:#f59e0b">人工打款</b>：请先自行链上转账，再点「确认打款」并填写 TxHash。';
        $modeNote .= " <a class='tx' href='/admin/live.PayoutSetting/index'>打款配置 →</a>";

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>提现审核</title>
<style>
body{font-family:system-ui,sans-serif;background:#1a1d2e;color:#fff;margin:0;padding:20px}
h2{margin:0 0 8px}
.mode{margin:0 0 16px;font-size:13px;color:#9aa}
.toolbar{display:flex;gap:10px;margin-bottom:14px}
.toolbar input,.toolbar select{padding:8px 12px;border-radius:6px;border:1px solid #444;background:#242840;color:#fff}
.toolbar button{padding:8px 18px;border:none;border-radius:6px;background:#4f6ef7;color:#fff;cursor:pointer}
table{width:100%;border-collapse:collapse;background:#20243a;border-radius:8px;overflow:hidden}
th,td{padding:10px 12px;text-align:left;font-size:13px;border-bottom:1px solid #2c3050}
th{background:#191d32;color:#9aa}
.status-badge{padding:2px 10px;border-radius:10px;font-size:12px}
.status-badge.pending{background:rgba(245,158,11,.15);color:#f59e0b}
.status-badge.passed{background:rgba(0,200,120,.15);color:#28c76f}
.status-badge.rejected{background:rgba(255,80,80,.15);color:#ff5050}
.btn{padding:4px 12px;border:none;border-radius:6px;cursor:pointer;margin-right:4px}
.btn-success{background:#28c76f;color:#fff}
.btn-danger{background:#ff5050;color:#fff}
.btn-warn{background:#f59e0b;color:#fff}
.tx{color:#6ea8ff;text-decoration:none}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:99;align-items:center;justify-content:center}
.modal.show{display:flex}
.modal-box{background:#242840;padding:24px;border-radius:12px;width:380px}
.modal-box input,.modal-box textarea{width:100%;padding:8px;margin:6px 0 14px;border-radius:6px;border:1px solid #444;background:#1a1d2e;color:#fff;box-sizing:border-box}
.hint{font-size:12px;color:#889}
</style>
</head>
<body>
<h2>提现审核</h2>
<p class="mode">{$modeNote}</p>
<div class="toolbar">
    <input id="kw" placeholder="订单号/地址" value="{$kw}">
    <select id="st">
        <option value="" {$sAll}>全部</option>
        <option value="0" {$s0}>待审核</option>
        <option value="1" {$s1}>已打款</option>
        <option value="2" {$s2}>已拒绝</option>
    </select>
    <button onclick="doSearch()">搜索</button>
</div>
<table>
<thead><tr><th>ID</th><th>用户</th><th>订单号</th><th>代币</th><th>实际到账</th><th>地址</th><th>网络</th><th>状态</th><th>打款状态</th><th>TxHash</th><th>失败/拒绝原因</th><th>申请时间</th><th>操作</th></tr></thead>
<tbody>{$rows}</tbody>
</table>
<div class="page">{$pager}</div>

<div class="modal" id="approveModal"><div class="modal-box">
<h3>确认打款</h3>
<p class="hint">{$modeNote}</p>
<input type="hidden" id="approveId">
<input id="txHash" placeholder="Tx Hash（人工打款时必填；自动打款留空）">
<button class="btn btn-success" onclick="doApprove()">确认</button>
<button class="btn" style="background:#444;color:#fff" onclick="closeModal('approveModal')">取消</button>
</div></div>

<div class="modal" id="rejectModal"><div class="modal-box">
<h3>拒绝提现</h3>
<p class="hint">拒绝后代币自动退回用户钱包</p>
<input type="hidden" id="rejectId">
<textarea id="reason" rows="2" placeholder="拒绝原因"></textarea>
<button class="btn btn-danger" onclick="doReject()">确认拒绝</button>
<button class="btn" style="background:#444;color:#fff" onclick="closeModal('rejectModal')">取消</button>
</div></div>

<script>
function doSearch(){var k=document.getElementById('kw').value;var s=document.getElementById('st').value;location.href='?search='+encodeURIComponent(k)+'&status='+s}
function showApprove(id){document.getElementById('approveId').value=id;document.getElementById('txHash').value='';document.getElementById('approveModal').classList.add('show')}
function showReject(id){document.getElementById('rejectId').value=id;document.getElementById('reason').value='';document.getElementById('rejectModal').classList.add('show')}
function closeModal(m){document.getElementById(m).classList.remove('show')}
function post(url,body,cb){fetch(url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body}).then(r=>r.json()).then(d=>{if(d.code===1){cb?cb(d):location.reload()}else{alert(d.msg)}})}
function doApprove(){var id=document.getElementById('approveId').value;var tx=document.getElementById('txHash').value;
post('/admin/live.Withdrawal/approve','id='+id+'&tx_hash='+encodeURIComponent(tx),function(d){alert(d.msg);location.reload()})}
function doReject(){var id=document.getElementById('rejectId').value;var reason=document.getElementById('reason').value;
if(!reason){alert('请填写拒绝原因');return}
post('/admin/live.Withdrawal/reject','id='+id+'&reason='+encodeURIComponent(reason))}
function showRetry(id){if(!confirm('请先确认热钱包没有对该地址出账，否则会重复打款。确定重试？')){return}
post('/admin/live.Withdrawal/retry','id='+id,function(d){alert(d.msg);location.reload()})}
</script>
</body>
</html>
HTML;
    }

    /** 区块浏览器链接，方便运营核对链上交易 */
    private function explorerUrl(string $network, string $txHash): string
    {
        return match ($network) {
            'TRC20' => 'https://tronscan.org/#/transaction/' . $txHash,
            'BEP20' => 'https://bscscan.com/tx/' . $txHash,
            default => 'https://etherscan.io/tx/' . $txHash,
        };
    }
}
