<?php
/**
 * 众筹项目管理 - 独立页面（无需前端编译）
 * 访问: /admin/live.Crowdfunding/index
 */

declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\admin\model\live\CrowdfundingProject as ProjectModel;
use think\facade\Db;

final class Crowdfunding extends Backend
{
    protected object $model;

    protected array $noNeedLogin = ['index', 'detailJson'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new ProjectModel();
    }

    /**
     * 众筹项目列表
     */
    public function index(): void
    {
        $keyword = $this->request->param('search', '');
        $status  = $this->request->param('status', '');

        $query = $this->model->alias('p')
            ->join('lp_user u', 'u.id = p.user_id', 'LEFT')
            ->field('p.*, u.nickname, u.user_no');

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('p.title', 'like', "%{$keyword}%")
                  ->whereOr('p.persona_name', 'like', "%{$keyword}%")
                  ->whereOr('u.nickname', 'like', "%{$keyword}%");
            });
        }
        if ($status !== '') {
            $query->where('p.status', (int)$status);
        }

        $list = $query->order('p.id', 'desc')->paginate(15);
        $statusMap = [0 => '进行中', 1 => '已成功', 2 => '已失败'];

        $html = $this->renderPage($list, $statusMap);
        response($html)->send();
        exit;
    }

    /**
     * 众筹详情 JSON
     */
    public function detailJson(): void
    {
        $id = $this->request->param('id/d', 0);
        $project = $this->model->find($id);
        if (!$project) {
            $this->error('项目不存在');
            return;
        }

        // 获取发起者昵称
        $user = Db::connect('live_mysql')
            ->table('lp_user')
            ->where('id', (int)$project->user_id)
            ->field('nickname')
            ->find();

        $statusMap = [0 => '进行中', 1 => '已成功', 2 => '已失败'];

        // 获取支持人数
        $pledgeCount = Db::connect('live_mysql')
            ->table('lp_crowdfunding_pledge')
            ->where('project_id', $id)
            ->count();

        // 获取已筹金额
        $pledgeSum = Db::connect('live_mysql')
            ->table('lp_crowdfunding_pledge')
            ->where('project_id', $id)
            ->sum('amount');

        $target = (float)$project->target_amount;
        $raised = (float)($pledgeSum ?: $project->raised_amount);
        $progress = $target > 0 ? min(100, round(($raised / $target) * 100, 1)) : 0;

        $this->success('', [
            'id'              => (int)$project->id,
            'user_id'         => (int)$project->user_id,
            'nickname'        => $user['nickname'] ?? '',
            'title'           => $project->title,
            'persona_name'    => $project->persona_name,
            'description'     => $project->description,
            'cover_url'       => $project->cover_url,
            'target_amount'   => (float)$project->target_amount,
            'raised_amount'   => $raised,
            'supporter_count' => (int)$project->supporter_count,
            'pledge_count'    => $pledgeCount,
            'deadline'        => $project->deadline,
            'status'          => (int)$project->status,
            'status_text'     => $statusMap[$project->status] ?? '未知',
            'persona_id'      => $project->persona_id ? (int)$project->persona_id : null,
            'progress_percent' => $progress,
            'created_at'      => $project->created_at,
            'updated_at'      => $project->updated_at,
        ]);
    }

    private function renderPage($list, $statusMap): string
    {
        $rows = '';
        foreach ($list->items() as $row) {
            $st    = $statusMap[$row['status']] ?? '未知';
            $stCls = match((int)$row['status']) {
                0 => 'active',
                1 => 'success',
                2 => 'failed',
                default => ''
            };
            $nick  = htmlspecialchars($row['nickname'] ?: '-');
            $no    = htmlspecialchars($row['user_no']);
            $title = htmlspecialchars($row['title']);
            $pname = htmlspecialchars($row['persona_name']);
            $target = number_format((float)$row['target_amount'], 0);
            $raised = number_format((float)$row['raised_amount'], 0);
            $scount = (int)$row['supporter_count'];
            $deadline = $row['deadline'];
            $time  = $row['created_at'];

            $rows .= <<<ROW
            <tr>
                <td>{$row['id']}</td>
                <td>{$nick}<br><small style="color:#999">{$no}</small></td>
                <td>{$title}</td>
                <td>{$pname}</td>
                <td>{$target}</td>
                <td>{$raised}</td>
                <td>{$scount}</td>
                <td>{$deadline}</td>
                <td><span class="status-badge {$stCls}">{$st}</span></td>
                <td>{$time}</td>
                <td><button class="btn btn-detail" onclick="showDetail({$row['id']})">查看</button></td>
            </tr>
            ROW;
        }

        $pager = $list->render();

        $keywordVal = htmlspecialchars($this->request->param('search', ''));
        $statusVal  = (int)$this->request->param('status', '');
        $statusAll  = $statusVal === '' || $statusVal === -1 ? 'selected' : '';
        $status0    = $statusVal === 0 ? 'selected' : '';
        $status1    = $statusVal === 1 ? 'selected' : '';
        $status2    = $statusVal === 2 ? 'selected' : '';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>众筹项目列表</title>
            <style>
                *{margin:0;padding:0;box-sizing:border-box}
                body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f5f7fa;color:#333;font-size:14px}
                .container{max-width:1600px;margin:0 auto;padding:20px}
                h1{font-size:22px;margin-bottom:20px;color:#1a1a2e}
                .toolbar{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;align-items:center}
                .toolbar input,.toolbar select{padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px}
                .toolbar input{width:260px}
                .table-wrap{overflow-x:auto}
                table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);min-width:1100px}
                th,td{padding:10px 14px;text-align:left;border-bottom:1px solid #eee;white-space:nowrap}
                th{background:#f8f9fc;font-weight:600;font-size:13px}
                tr:hover{background:#f9fafb}
                td{font-size:13px}
                .btn{padding:6px 16px;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:500}
                .btn-detail{background:#409eff;color:#fff}
                .btn:hover{opacity:.85}
                .status-badge{padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600}
                .active{background:rgba(64,158,255,.12);color:#409eff}
                .success{background:rgba(0,212,170,.12);color:#00d4aa}
                .failed{background:rgba(255,45,85,.12);color:#ff2d55}
                .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;align-items:center;justify-content:center}
                .modal-overlay.active{display:flex}
                .modal{background:#fff;border-radius:12px;padding:24px;width:640px;max-width:90vw;max-height:80vh;overflow-y:auto}
                .modal h3{margin-bottom:16px}
                .modal dl{margin-bottom:12px}
                .modal dt{font-weight:600;margin-top:12px;color:#666;font-size:12px}
                .modal dd{margin-top:4px;font-size:14px;word-break:break-all}
                .modal .cover-img{max-width:200px;max-height:150px;border-radius:8px;object-fit:cover}
                .modal .progress-bar{height:8px;background:#eee;border-radius:4px;margin-top:4px;overflow:hidden}
                .modal .progress-bar .fill{height:100%;background:#409eff;border-radius:4px;transition:width .3s}
                .page-bar{text-align:center;margin-top:20px}
                .page-bar ul{display:inline-flex;gap:4px;list-style:none}
                .page-bar li a,.page-bar li span{padding:6px 12px;border:1px solid #ddd;border-radius:4px;color:#333;text-decoration:none;font-size:13px}
                .page-bar .active span{background:#409eff;color:#fff;border-color:#409eff}
            </style>
        </head>
        <body>
            <div class="container">
                <h1>众筹项目列表</h1>
                <form class="toolbar" method="get">
                    <input name="search" placeholder="搜索标题/角色名/发起者" value="{$keywordVal}">
                    <select name="status">
                        <option value="" {$statusAll}>全部状态</option>
                        <option value="0" {$status0}>进行中</option>
                        <option value="1" {$status1}>已成功</option>
                        <option value="2" {$status2}>已失败</option>
                    </select>
                    <button class="btn btn-detail" type="submit">搜索</button>
                </form>
                <div class="table-wrap">
                <table>
                    <thead><tr>
                        <th>ID</th><th>发起者</th><th>项目标题</th><th>角色名</th><th>目标(钻)</th><th>已筹(钻)</th><th>支持人数</th><th>截止时间</th><th>状态</th><th>创建时间</th><th>操作</th>
                    </tr></thead>
                    <tbody>{$rows}</tbody>
                </table>
                </div>
                <div class="page-bar">{$pager}</div>
            </div>

            <div class="modal-overlay" id="detailModal">
                <div class="modal" id="detailContent"></div>
            </div>

            <script>
            function showDetail(id) {
                document.getElementById('detailModal').classList.add('active');
                document.getElementById('detailContent').innerHTML = '<p>加载中...</p>';
                fetch('/admin/live.Crowdfunding/detailJson?id=' + id)
                    .then(r => r.json())
                    .then(d => {
                        if (d.code === 1) {
                            var p = d.data;
                            var coverHtml = p.cover_url
                                ? '<img src="' + p.cover_url + '" class="cover-img" />'
                                : '<span style="color:#999">无封面</span>';
                            var desc = p.description || '暂无描述';
                            document.getElementById('detailContent').innerHTML =
                                '<h3>' + htmlEscape(p.title) + '</h3>' +
                                '<dl>' +
                                '<dt>发起者</dt><dd>' + htmlEscape(p.nickname) + ' (ID: ' + p.user_id + ')</dd>' +
                                '<dt>角色名称</dt><dd>' + htmlEscape(p.persona_name) + '</dd>' +
                                '<dt>封面</dt><dd>' + coverHtml + '</dd>' +
                                '<dt>目标金额</dt><dd>' + p.target_amount + ' 钻石</dd>' +
                                '<dt>已筹金额</dt><dd>' + p.raised_amount + ' 钻石</dd>' +
                                '<dt>进度</dt><dd><div class="progress-bar"><div class="fill" style="width:' + p.progress_percent + '%"></div></div>' + p.progress_percent + '%</dd>' +
                                '<dt>支持人数</dt><dd>' + p.supporter_count + ' (记录数: ' + (p.pledge_count || 0) + ')</dd>' +
                                '<dt>截止时间</dt><dd>' + p.deadline + '</dd>' +
                                '<dt>状态</dt><dd>' + p.status_text + '</dd>' +
                                '<dt>描述</dt><dd>' + htmlEscape(desc).replace(/\\n/g, '<br>') + '</dd>' +
                                '<dt>关联角色ID</dt><dd>' + (p.persona_id || '未关联') + '</dd>' +
                                '<dt>创建时间</dt><dd>' + p.created_at + '</dd>' +
                                '</dl>' +
                                '<button class="btn btn-detail" onclick="closeDetail()">关闭</button>';
                        } else {
                            document.getElementById('detailContent').innerHTML = '<p style="color:red">加载失败: ' + (d.msg || '') + '</p>';
                        }
                    })
                    .catch(function() {
                        document.getElementById('detailContent').innerHTML = '<p style="color:red">网络错误</p>';
                    });
            }
            function closeDetail() {
                document.getElementById('detailModal').classList.remove('active');
            }
            function htmlEscape(s) {
                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }
            document.getElementById('detailModal').addEventListener('click', function(e) {
                if (e.target === this) closeDetail();
            });
            </script>
        </body>
        </html>
        HTML;
    }
}
