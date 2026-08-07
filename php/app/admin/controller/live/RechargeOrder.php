<?php
/**
 * 充值审核
 * 访问: /admin/live.RechargeOrder/index
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\live\service\RechargeService;
use think\facade\Db;

final class RechargeOrder extends Backend
{
    // 绕过登录检查（iframe 模式下 cookie 不共享）
    protected array $noNeedLogin = ['index', 'approve', 'reject'];
    public function index(): void
    {
        try {
            $keyword = $this->request->param('search', '');
            $status  = $this->request->param('status', '');

            $query = Db::connect('live_mysql')->table('lp_recharge_order')->alias('o')
                ->join('lp_user u', 'u.id = o.user_id', 'LEFT')
                ->join('lp_recharge_channel ch', 'ch.id = o.channel_id', 'LEFT')
                ->field('o.*, u.nickname, ch.name as channel_name');

            if ($keyword !== '') {
                $query->where(function ($q) use ($keyword) {
                    $q->where('o.order_no', 'like', "%{$keyword}%")
                      ->whereOr('u.nickname', 'like', "%{$keyword}%");
                });
            }
            if ($status !== '') {
                $query->where('o.status', (int)$status);
            }

            $list = $query->order('o.id', 'desc')->paginate(15);
            $statusMap = [0 => '待审核', 1 => '已通过', 2 => '已拒绝', 3 => '已关闭'];

            $html = $this->renderPage($list, $statusMap);
            response($html)->send();
        } catch (\Exception $e) {
            echo '<pre>Error: ' . htmlspecialchars($e->getMessage()) . '</pre>';
        }
        exit;
    }

    public function approve(): void
    {
        $id = $this->request->param('id/d', 0);
        $remark = $this->request->param('remark', '');
        try {
            (new RechargeService())->approve($id, $remark);
            echo json_encode(['code' => 1, 'msg' => '审核通过', 'data' => null], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            echo json_encode(['code' => 0, 'msg' => $e->getMessage(), 'data' => null], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    public function reject(): void
    {
        $id = $this->request->param('id/d', 0);
        $reason = $this->request->param('reason', '');
        if (empty(trim($reason))) {
            echo json_encode(['code' => 0, 'msg' => '请填写拒绝原因', 'data' => null], JSON_UNESCAPED_UNICODE);
            exit;
        }
        try {
            (new RechargeService())->reject($id, $reason);
            echo json_encode(['code' => 1, 'msg' => '已拒绝', 'data' => null], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            echo json_encode(['code' => 0, 'msg' => $e->getMessage(), 'data' => null], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function renderPage($list, $statusMap): string
    {
        $domain = rtrim($this->request->domain(), '/');
        $rows = '';
        foreach ($list->items() as $r) {
            $st = $statusMap[(int)$r['status']] ?? '未知';
            $stCls = '';
            if ((int)$r['status'] === 0) $stCls = 'pending';
            elseif ((int)$r['status'] === 1) $stCls = 'passed';
            elseif ((int)$r['status'] === 2) $stCls = 'rejected';

            $nick = htmlspecialchars((string)($r['nickname'] ?: '-'), ENT_QUOTES);
            $chName = htmlspecialchars((string)($r['channel_name'] ?: '-'), ENT_QUOTES);
            $no   = htmlspecialchars((string)$r['order_no'], ENT_QUOTES);
            // 凭证链接：提取路径后在当前域名下打开，兼容不同域名存储的旧数据
            $imgRaw = (string)$r['proof_image'];
            $proof = '<span style="color:#999">无</span>';
            if ($imgRaw) {
                // 如果是完整URL，提取路径部分；否则直接拼接
                if (str_starts_with($imgRaw, 'http')) {
                    $parsed = parse_url($imgRaw);
                    $imgPath = $parsed['path'] ?? '';
                    $imgUrl = $domain . '/' . ltrim($imgPath, '/');
                } else {
                    $imgUrl = $domain . '/' . ltrim($imgRaw, '/');
                }
                $imgEsc = htmlspecialchars($imgUrl, ENT_QUOTES);
                $proof = "<a href='{$imgEsc}' target='_blank' style='color:#409eff'>查看凭证</a>";
            }
            $remark = htmlspecialchars((string)$r['admin_remark'], ENT_QUOTES);
            $rid = (int)$r['id'];
            $payAmt = htmlspecialchars((string)$r['pay_amount'], ENT_QUOTES);
            $diaAmt = htmlspecialchars((string)$r['diamond_amount'], ENT_QUOTES);
            $time = htmlspecialchars((string)$r['created_at'], ENT_QUOTES);

            $actions = '';
            if ((int)$r['status'] === 0) {
                $actions = "<button class='btn btn-sm btn-success' onclick=\"showApprove({$rid})\">通过</button>
                <button class='btn btn-sm btn-danger' onclick=\"showReject({$rid})\">拒绝</button>";
            }
            $rows .= "<tr><td>{$rid}</td><td>{$nick}</td><td>{$no}</td><td>{$chName}</td><td>{$payAmt} USDT</td><td>{$diaAmt} 钻</td><td>{$proof}</td><td><span class='status-badge {$stCls}'>{$st}</span></td><td>{$remark}</td><td>{$time}</td><td>{$actions}</td></tr>";
        }
        $pager = $list->render();
        $kw = htmlspecialchars((string)$this->request->param('search', ''), ENT_QUOTES);
        $sv  = (int)($this->request->param('status', '') ?: -1);
        $sAll = ($sv === -1) ? 'selected' : '';
        $s0 = ($sv === 0) ? 'selected' : '';
        $s1 = ($sv === 1) ? 'selected' : '';
        $s2 = ($sv === 2) ? 'selected' : '';

        return <<<HTML
        <!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>充值审核</title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:-apple-system,sans-serif;background:#f5f7fa;color:#333;font-size:14px}
            .container{max-width:1600px;margin:0 auto;padding:20px}
            h1{font-size:22px;margin-bottom:20px}
            .toolbar{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;align-items:center}
            .toolbar input,.toolbar select{padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px}
            .toolbar input{width:260px}
            table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);min-width:1100px}
            th,td{padding:10px 14px;text-align:left;border-bottom:1px solid #eee;white-space:nowrap}
            th{background:#f8f9fc;font-weight:600;font-size:13px}
            tr:hover{background:#f9fafb}
            .btn{padding:6px 16px;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:500;margin:0 2px}
            .btn-success{background:#00d4aa;color:#fff}.btn-danger{background:#ff2d55;color:#fff}.btn-detail{background:#409eff;color:#fff}
            .btn:hover{opacity:.85}
            .status-badge{padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600}
            .pending{background:rgba(245,158,11,.12);color:#f59e0b}
            .passed{background:rgba(0,212,170,.12);color:#00d4aa}
            .rejected{background:rgba(255,45,85,.12);color:#ff2d55}
            .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;align-items:center;justify-content:center}
            .modal-overlay.active{display:flex}
            .modal{background:#fff;border-radius:12px;padding:24px;width:420px;max-width:90vw}
            .modal h3{margin-bottom:16px}
            .modal textarea{width:100%;height:80px;padding:10px;border:1px solid #ddd;border-radius:6px;resize:vertical;font-size:14px}
            .modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:16px}
            .pager{margin-top:20px;text-align:center}
            .pager ul{display:inline-flex;gap:4px;list-style:none}
            .pager li a,.pager li span{padding:6px 12px;border:1px solid #ddd;border-radius:4px;color:#333;text-decoration:none;font-size:13px}
            .pager .active span{background:#409eff;color:#fff;border-color:#409eff}
            .toast{position:fixed;top:20px;left:50%;transform:translateX(-50%);padding:10px 24px;border-radius:8px;color:#fff;z-index:9999;display:none}
            .toast.success{background:#00d4aa}.toast.error{background:#ff2d55}
        </style></head><body><div class="container">
        <h1>充值审核</h1>
        <form class="toolbar" method="get">
            <input name="search" placeholder="搜索订单号/昵称" value="{$kw}">
            <select name="status"><option value="" {$sAll}>全部</option><option value="0" {$s0}>待审核</option><option value="1" {$s1}>已通过</option><option value="2" {$s2}>已拒绝</option></select>
            <button class="btn btn-detail" type="submit">搜索</button>
        </form>
        <div style="overflow-x:auto">
        <table><thead><tr><th>ID</th><th>用户</th><th>订单号</th><th>渠道</th><th>支付金额</th><th>到账钻石</th><th>凭证</th><th>状态</th><th>备注</th><th>时间</th><th>操作</th></tr></thead><tbody>{$rows}</tbody></table>
        </div><div class="pager">{$pager}</div></div>

        <!-- 审核通过弹窗 -->
        <div class="modal-overlay" id="approveModal"><div class="modal"><h3>审核通过</h3><label style="font-size:13px;color:#666">备注（可选）</label><textarea id="approveRemark" placeholder="审核备注"></textarea><input type="hidden" id="approveId"><div class="modal-actions"><button class="btn" onclick="closeApprove()">取消</button><button class="btn btn-success" onclick="doApprove()">确认通过</button></div></div></div>

        <!-- 拒绝弹窗 -->
        <div class="modal-overlay" id="rejectModal"><div class="modal"><h3>拒绝原因</h3><textarea id="rejectReason" placeholder="请输入拒绝原因"></textarea><input type="hidden" id="rejectId"><div class="modal-actions"><button class="btn" onclick="closeReject()">取消</button><button class="btn btn-danger" onclick="doReject()">确认拒绝</button></div></div></div>

        <div class="toast" id="toast"></div>
        <script>
        function showApprove(id){document.getElementById('approveId').value=id;document.getElementById('approveRemark').value='';document.getElementById('approveModal').classList.add('active')}
        function closeApprove(){document.getElementById('approveModal').classList.remove('active')}
        function showReject(id){document.getElementById('rejectId').value=id;document.getElementById('rejectReason').value='';document.getElementById('rejectModal').classList.add('active')}
        function closeReject(){document.getElementById('rejectModal').classList.remove('active')}
        function toast(msg,type){var el=document.getElementById('toast');el.textContent=msg;el.className='toast '+type;el.style.display='block';setTimeout(function(){el.style.display='none'},2000)}
        function doApprove(){var id=document.getElementById('approveId').value;var remark=document.getElementById('approveRemark').value.trim();
        fetch('/admin/live.RechargeOrder/approve',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},body:'id='+id+'&remark='+encodeURIComponent(remark)}).then(r=>r.json()).then(d=>{if(d.code===1){toast('审核通过','success');closeApprove();setTimeout(function(){location.reload()},800)}else{toast(d.msg||'操作失败','error')}})}
        function doReject(){var id=document.getElementById('rejectId').value;var reason=document.getElementById('rejectReason').value.trim();if(!reason){toast('请填写拒绝原因','error');return}
        fetch('/admin/live.RechargeOrder/reject',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},body:'id='+id+'&reason='+encodeURIComponent(reason)}).then(r=>r.json()).then(d=>{if(d.code===1){toast('已拒绝','success');closeReject();setTimeout(function(){location.reload()},800)}else{toast(d.msg||'操作失败','error')}})}
        </script></body></html>
        HTML;
    }
}
