<?php
/**
 * 提现审核
 * 访问: /admin/live.Withdrawal/index?_key=<ADMIN_FLOAT_KEY>
 * 通过 = 人工链上打款后点确认（可填 tx hash）；拒绝 = 自动退钻
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\live\service\WithdrawService;
use think\facade\Db;

final class Withdrawal extends Backend
{
    protected array $noNeedLogin = ['index', 'approve', 'reject'];
    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
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
            (new WithdrawService())->approve($id, $txHash);
            echo json_encode(['code' => 1, 'msg' => '已确认打款'], JSON_UNESCAPED_UNICODE);
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
            echo json_encode(['code' => 0, 'msg' => '请填写拒绝原因（将退回钻石）'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        try {
            (new WithdrawService())->reject($id, $reason);
            echo json_encode(['code' => 1, 'msg' => '已拒绝并退钻'], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            echo json_encode(['code' => 0, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function renderPage(array $page): string
    {
        $rows = '';
        $map = [0 => '待审核', 1 => '已打款', 2 => '已拒绝'];
        foreach ($page['data'] ?? [] as $r) {
            $st = $map[(int)$r['status']] ?? '?';
            $cls = [(int)$r['status']] === 0 ? 'pending' : ((int)$r['status'] === 1 ? 'passed' : 'rejected');
            $actions = '';
            if ((int)$r['status'] === 0) {
                $rid = (int)$r['id'];
                $actions = "<button class='btn btn-sm btn-success' onclick=\"showApprove({$rid})\">确认打款</button>
                <button class='btn btn-sm btn-danger' onclick=\"showReject({$rid})\">拒绝</button>";
            }
            $rows .= "<tr>
                <td>{$r['id']}</td>
                <td>" . htmlspecialchars((string)($r['nickname'] ?: '-')) . "</td>
                <td>" . htmlspecialchars((string)$r['order_no']) . "</td>
                <td>{$r['diamond_amount']} 钻</td>
                <td>{$r['actual_usdt']} USDT</td>
                <td title='" . htmlspecialchars((string)$r['address']) . "'>" . htmlspecialchars(substr((string)$r['address'], 0, 12)) . "…</td>
                <td>{$r['network']}</td>
                <td><span class='status-badge {$cls}'>{$st}</span></td>
                <td>" . htmlspecialchars((string)($r['tx_hash'] ?: '-')) . "</td>
                <td>" . htmlspecialchars((string)($r['reject_reason'] ?: '-')) . "</td>
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

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>提现审核</title>
<style>
body{font-family:system-ui,sans-serif;background:#1a1d2e;color:#fff;margin:0;padding:20px}
h2{margin:0 0 16px}
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
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:99;align-items:center;justify-content:center}
.modal.show{display:flex}
.modal-box{background:#242840;padding:24px;border-radius:12px;width:360px}
.modal-box input,.modal-box textarea{width:100%;padding:8px;margin:6px 0 14px;border-radius:6px;border:1px solid #444;background:#1a1d2e;color:#fff;box-sizing:border-box}
.hint{font-size:12px;color:#889}
</style>
</head>
<body>
<h2>提现审核（人工打款模式）</h2>
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
<thead><tr><th>ID</th><th>用户</th><th>订单号</th><th>钻石</th><th>实际到账</th><th>地址</th><th>网络</th><th>状态</th><th>TxHash</th><th>拒绝原因</th><th>申请时间</th><th>操作</th></tr></thead>
<tbody>{$rows}</tbody>
</table>
<div class="page">{$pager}</div>

<div class="modal" id="approveModal"><div class="modal-box">
<h3>确认打款</h3>
<p class="hint">请先在链上完成 USDT 转账，再点确认（可填交易哈希备查）</p>
<input type="hidden" id="approveId">
<input id="txHash" placeholder="Tx Hash（可选）">
<button class="btn btn-success" onclick="doApprove()">确认已打款</button>
<button class="btn" style="background:#444;color:#fff" onclick="closeModal('approveModal')">取消</button>
</div></div>

<div class="modal" id="rejectModal"><div class="modal-box">
<h3>拒绝提现</h3>
<p class="hint">拒绝后钻石自动退回用户钱包</p>
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
function doApprove(){var id=document.getElementById('approveId').value;var tx=document.getElementById('txHash').value;
fetch('/admin/live.Withdrawal/approve',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id='+id+'&tx_hash='+encodeURIComponent(tx)}).then(r=>r.json()).then(d=>{if(d.code===1){location.reload()}else{alert(d.msg)}})}
function doReject(){var id=document.getElementById('rejectId').value;var reason=document.getElementById('reason').value;
if(!reason){alert('请填写拒绝原因');return}
fetch('/admin/live.Withdrawal/reject',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id='+id+'&reason='+encodeURIComponent(reason)}).then(r=>r.json()).then(d=>{if(d.code===1){location.reload()}else{alert(d.msg)}})}
</script>
</body>
</html>
HTML;
    }
}
