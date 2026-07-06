<?php
/**
 * 商家认证审核 - 独立页面（无需前端编译）
 * 访问: /admin/live.MerchantCertification/index
 */

declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\admin\model\live\MerchantCertification as CertModel;
use think\facade\Db;

final class MerchantCertification extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new CertModel();
    }

    /**
     * 审核列表页
     */
    public function index(): void
    {
        $keyword = $this->request->param('search', '');
        $status  = $this->request->param('status', '');

        $query = $this->model->alias('c')
            ->join('lp_user u', 'u.id = c.user_id', 'LEFT')
            ->field('c.*, u.nickname, u.user_no');

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('u.nickname', 'like', "%{$keyword}%")
                  ->whereOr('c.email', 'like', "%{$keyword}%");
            });
        }
        if ($status !== '') {
            $query->where('c.status', (int)$status);
        }

        $list = $query->order('c.id', 'desc')->paginate(15);
        $statusMap = [0 => '待审核', 1 => '已通过', 2 => '已拒绝'];

        $html = $this->renderPage($list, $statusMap);
        response($html)->send();
        exit;
    }

    /**
     * 审核通过 (AJAX)
     */
    public function approve(): void
    {
        $id = $this->request->param('id/d', 0);
        $cert = $this->model->find($id);
        if (!$cert) { $this->error('记录不存在'); }
        if ($cert->status != 0) { $this->error('不在待审核状态'); }
        $cert->status = 1;
        $cert->save();
        $this->success('审核通过');
    }

    /**
     * 审核拒绝 (AJAX)
     */
    public function reject(): void
    {
        $id = $this->request->param('id/d', 0);
        $reason = $this->request->param('reason', '');
        if (empty(trim($reason))) { $this->error('请填写拒绝原因'); }
        $cert = $this->model->find($id);
        if (!$cert) { $this->error('记录不存在'); }
        if ($cert->status != 0) { $this->error('不在待审核状态'); }
        $cert->status = 2;
        $cert->reject_reason = trim($reason);
        $cert->save();
        $this->success('已拒绝');
    }

    /**
     * 认证详情 JSON（给弹窗用）
     */
    public function detailJson(): void
    {
        $userId = $this->request->param('user_id/d', 0);
        $cert = $this->model->where('user_id', $userId)->find();
        if (!$cert) {
            $this->error('认证记录不存在');
            return;
        }

        $user = \think\facade\Db::connect('live_mysql')
            ->table('lp_user')
            ->where('id', $userId)
            ->field('nickname')
            ->find();

        $this->success('', [
            'id'            => (int)$cert->id,
            'user_id'       => (int)$cert->user_id,
            'email'         => $cert->email,
            'id_card_front' => $cert->id_card_front,
            'id_card_back'  => $cert->id_card_back,
            'status'        => (int)$cert->status,
            'reject_reason' => $cert->reject_reason,
            'created_at'    => $cert->created_at,
            'nickname'      => $user['nickname'] ?? '',
        ]);
    }

    private function renderPage($list, $statusMap): string
    {
        $rows = '';
        foreach ($list->items() as $row) {
            $st   = $statusMap[$row['status']] ?? '未知';
            $stCls = match((int)$row['status']) { 0 => 'pending', 1 => 'passed', 2 => 'rejected', default => '' };
            $email = htmlspecialchars($row['email']);
            $name  = htmlspecialchars($row['nickname'] ?: '-');
            $no    = htmlspecialchars($row['user_no']);
            $reason = htmlspecialchars($row['reject_reason']);
            $time  = $row['created_at'];

            $actions = '';
            if ((int)$row['status'] === 0) {
                $actions = <<<ACT
                <button class="btn btn-sm btn-success" onclick="doApprove({$row['id']})">通过</button>
                <button class="btn btn-sm btn-danger" onclick="showReject({$row['id']})">拒绝</button>
                ACT;
            }

            $front = htmlspecialchars($row['id_card_front']);
            $back  = htmlspecialchars($row['id_card_back']);

            $rows .= <<<ROW
            <tr>
                <td>{$row['id']}</td>
                <td>{$name}<br><small style="color:#999">{$no}</small></td>
                <td>{$email}</td>
                <td>
                    <a href="{$front}" target="_blank"><img src="{$front}" style="width:60px;height:40px;object-fit:cover;border-radius:4px" /></a>
                    <a href="{$back}" target="_blank"><img src="{$back}" style="width:60px;height:40px;object-fit:cover;border-radius:4px;margin-left:4px" /></a>
                </td>
                <td><span class="status-badge {$stCls}">{$st}</span></td>
                <td><span style="font-size:12px;color:#999">{$reason}</span></td>
                <td>{$time}</td>
                <td>{$actions}</td>
            </tr>
            ROW;
        }

        $pager = $list->render();

        return <<<HTML
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>商家认证审核</title>
            <style>
                *{margin:0;padding:0;box-sizing:border-box}
                body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f5f7fa;color:#333;font-size:14px}
                .container{max-width:1400px;margin:0 auto;padding:20px}
                h1{font-size:22px;margin-bottom:20px;color:#1a1a2e}
                .toolbar{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;align-items:center}
                .toolbar input,.toolbar select{padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px}
                .toolbar input{width:260px}
                table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06)}
                th,td{padding:12px 16px;text-align:left;border-bottom:1px solid #eee}
                th{background:#f8f9fc;font-weight:600;white-space:nowrap}
                tr:hover{background:#f9fafb}
                .btn{padding:6px 16px;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:500;margin:0 2px}
                .btn-success{background:#00d4aa;color:#fff}
                .btn-danger{background:#ff2d55;color:#fff}
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
                .toast.success{background:#00d4aa}
                .toast.error{background:#ff2d55}
            </style>
        </head>
        <body>
            <div class="container">
                <h1>商家认证审核</h1>
                <form class="toolbar" method="get">
                    <input name="search" placeholder="搜索用户昵称/邮箱" value="{$keywordVal}">
                    <select name="status">
                        <option value="">全部状态</option>
                        <option value="0" {$status0}>待审核</option>
                        <option value="1" {$status1}>已通过</option>
                        <option value="2" {$status2}>已拒绝</option>
                    </select>
                    <button class="btn btn-success" type="submit">搜索</button>
                </form>
                <table>
                    <thead><tr>
                        <th>ID</th><th>用户</th><th>认证邮箱</th><th>证件图片</th><th>状态</th><th>拒绝原因</th><th>提交时间</th><th>操作</th>
                    </tr></thead>
                    <tbody>{$rows}</tbody>
                </table>
                <div class="pager">{$pager}</div>
            </div>

            <div class="modal-overlay" id="rejectModal">
                <div class="modal">
                    <h3>拒绝原因</h3>
                    <textarea id="rejectReason" placeholder="请输入拒绝原因"></textarea>
                    <input type="hidden" id="rejectId">
                    <div class="modal-actions">
                        <button class="btn" onclick="closeReject()">取消</button>
                        <button class="btn btn-danger" onclick="doReject()">确认拒绝</button>
                    </div>
                </div>
            </div>

            <div class="toast" id="toast"></div>

            <script>
            function showReject(id) {
                document.getElementById('rejectId').value = id;
                document.getElementById('rejectReason').value = '';
                document.getElementById('rejectModal').classList.add('active');
            }
            function closeReject() {
                document.getElementById('rejectModal').classList.remove('active');
            }
            function toast(msg, type) {
                var el = document.getElementById('toast');
                el.textContent = msg;
                el.className = 'toast ' + type;
                el.style.display = 'block';
                setTimeout(function(){ el.style.display = 'none'; }, 2000);
            }
            function doApprove(id) {
                if (!confirm('确认审核通过？')) return;
                fetch('/admin/live.MerchantCertification/approve?id=' + id, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' }
                }).then(r => r.json()).then(d => {
                    if (d.code === 1) { toast('审核通过', 'success'); setTimeout(function(){ location.reload(); }, 800); }
                    else { toast(d.msg || '操作失败', 'error'); }
                }).catch(function(){ toast('网络错误', 'error'); });
            }
            function doReject() {
                var id = document.getElementById('rejectId').value;
                var reason = document.getElementById('rejectReason').value.trim();
                if (!reason) { toast('请填写拒绝原因', 'error'); return; }
                fetch('/admin/live.MerchantCertification/reject', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                    body: 'id=' + id + '&reason=' + encodeURIComponent(reason)
                }).then(r => r.json()).then(d => {
                    if (d.code === 1) { toast('已拒绝', 'success'); setTimeout(function(){ location.reload(); }, 800); }
                    else { toast(d.msg || '操作失败', 'error'); }
                }).catch(function(){ toast('网络错误', 'error'); });
            }
            </script>
        </body>
        </html>
        HTML;
    }
}
