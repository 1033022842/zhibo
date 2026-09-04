<?php
/**
 * 众筹项目管理 - 独立页面（无需前端编译）
 * 访问: /admin/live.Crowdfunding/index
 */

declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\admin\model\live\CrowdfundingProject as ProjectModel;
use app\live\service\CrowdfundingService;
use think\facade\Db;

final class Crowdfunding extends Backend
{
    protected object $model;

    protected array $noNeedLogin = ['index', 'detailJson', 'edit', 'delete'];

    // 飘页访问守卫：URL ?_key= 首次校验后走 Cookie（密钥 .env ADMIN_FLOAT_KEY）
    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

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

        // 顶部统计
        $stats = [
            'total'   => (int)$this->model->count(),
            'active'  => (int)$this->model->where('status', 0)->count(),
            'success' => (int)$this->model->where('status', 1)->count(),
            'failed'  => (int)$this->model->where('status', 2)->count(),
            'raised'  => (float)$this->model->sum('raised_amount'),
        ];

        $html = $this->renderPage($list, $statusMap, $stats);
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

        // 支持记录列表
        $pledges = Db::connect('live_mysql')
            ->table('lp_crowdfunding_pledge')
            ->alias('pl')
            ->join('lp_user u', 'u.id = pl.user_id', 'LEFT')
            ->where('pl.project_id', $id)
            ->field('pl.id, pl.user_id, pl.amount, pl.status, pl.created_at, u.nickname')
            ->order('pl.id', 'desc')
            ->select()
            ->toArray();

        $pledgeStatusMap = [0 => '冻结中', 1 => '已划转', 2 => '已退款'];
        $pledgeList = array_map(function ($p) use ($pledgeStatusMap) {
            return [
                'id'          => (int)$p['id'],
                'nickname'    => $p['nickname'] ?? '',
                'amount'      => (float)$p['amount'],
                'status'      => (int)$p['status'],
                'status_text' => $pledgeStatusMap[$p['status']] ?? '未知',
                'created_at'  => $p['created_at'],
            ];
        }, $pledges);

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
            'pledges'         => $pledgeList,
            'created_at'      => $project->created_at,
            'updated_at'      => $project->updated_at,
        ]);
    }

    /**
     * 编辑众筹项目内容
     */
    public function edit(): void
    {
        $id = $this->request->param('id/d', 0);
        $project = $this->model->find($id);
        if (!$project) {
            $this->error('项目不存在');
            return;
        }

        $title        = trim((string)$this->request->post('title', ''));
        $personaName  = trim((string)$this->request->post('persona_name', ''));
        $description  = trim((string)$this->request->post('description', ''));
        $coverUrl     = trim((string)$this->request->post('cover_url', ''));
        $targetAmount = (float)$this->request->post('target_amount', 0);
        $deadline     = trim((string)$this->request->post('deadline', ''));

        if ($title === '') {
            $this->error('项目标题不能为空');
            return;
        }
        if ($personaName === '') {
            $this->error('角色名称不能为空');
            return;
        }
        if ($targetAmount <= 0) {
            $this->error('目标金额必须大于0');
            return;
        }
        $deadlineTs = strtotime(str_replace('T', ' ', $deadline));
        if ($deadline === '' || $deadlineTs === false) {
            $this->error('截止时间格式不正确');
            return;
        }

        // 处理封面：优先使用新上传的图片
        $hasUpload = !empty($_FILES['cover_file']['tmp_name'])
            && (int)$_FILES['cover_file']['error'] === UPLOAD_ERR_OK
            && (int)$_FILES['cover_file']['size'] > 0;
        if ($hasUpload) {
            try {
                $file       = $this->request->file('cover_file');
                $upload     = new \app\common\library\Upload($file);
                $upload->setTopic('crowdfunding_cover');
                $attachment = $upload->upload(null, 0, 0);
                $coverUrl   = (string)($attachment['url'] ?? $coverUrl);
            } catch (\Throwable $e) {
                $this->error('图片上传失败: ' . $e->getMessage());
                return;
            }
        }

        $project->title         = $title;
        $project->persona_name  = $personaName;
        $project->description   = $description;
        $project->cover_url     = $coverUrl;
        $project->target_amount = $targetAmount;
        $project->deadline      = date('Y-m-d H:i:s', $deadlineTs);
        $project->updated_at    = date('Y-m-d H:i:s');
        $project->save();

        $this->success('保存成功');
    }

    /**
     * 删除众筹项目（含冻结支持退款）
     */
    public function delete(): void
    {
        $id = $this->request->param('id/d', 0);
        try {
            (new CrowdfundingService())->deleteProject($id);
        } catch (\Throwable $e) {
            $this->error($e->getMessage() ?: '删除失败');
            return;
        }
        $this->success('删除成功');
    }

    private function renderPage($list, $statusMap, array $stats): string
    {
        $rows = '';
        foreach ($list->items() as $row) {
            $st    = $statusMap[$row['status']] ?? '未知';
            $stNum = (int)$row['status'];
            $nick  = htmlspecialchars($row['nickname'] ?: '未命名');
            $no    = htmlspecialchars((string)$row['user_no']);
            $title = htmlspecialchars($row['title']);
            $pname = htmlspecialchars($row['persona_name']);
            $target = number_format((float)$row['target_amount'], 0);
            $raised = number_format((float)$row['raised_amount'], 0);
            $scount = (int)$row['supporter_count'];
            $deadline = $row['deadline'] ? substr((string)$row['deadline'], 0, 10) : '-';
            $time  = $row['created_at'] ? substr((string)$row['created_at'], 0, 10) : '-';
            $tg = (float)$row['target_amount'];
            $rs = (float)$row['raised_amount'];
            $pct = $tg > 0 ? (int)min(100, round($rs / $tg * 100)) : 0;
            $cover = $row['cover_url']
                ? '<div class="thumb"><img src="' . htmlspecialchars((string)$row['cover_url']) . '" alt="" onerror="this.style.display=\'none\'"></div>'
                : '<div class="thumb thumb-empty"></div>';

            $rows .= <<<ROW
            <tr>
                <td>
                    <div class="proj-cell">
                        {$cover}
                        <div class="proj-info">
                            <div class="proj-title">{$title}</div>
                            <div class="proj-persona">{$pname}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="owner-cell">
                        <div class="owner-name">{$nick}</div>
                        <div class="owner-no">#{$no}</div>
                    </div>
                </td>
                <td>
                    <div class="prog-cell">
                        <div class="mini-progress"><div class="mini-fill" style="width:{$pct}%"></div></div>
                        <div class="mini-pct">{$pct}%</div>
                    </div>
                </td>
                <td>
                    <div class="amt-cell">
                        <div class="amt-raised">{$raised}</div>
                        <div class="amt-target">/ {$target} 钻</div>
                    </div>
                </td>
                <td class="td-center">{$scount}</td>
                <td>{$deadline}</td>
                <td><span class="badge badge-{$stNum}">{$st}</span></td>
                <td>{$time}</td>
                <td>
                    <div class="op-group">
                        <button class="op-btn" onclick="showDetail({$row['id']})" title="查看">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>查看
                        </button>
                        <button class="op-btn op-edit" onclick="showEdit({$row['id']})" title="编辑">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>编辑
                        </button>
                        <button class="op-btn op-del" onclick="doDelete({$row['id']})" title="删除">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>删除
                        </button>
                    </div>
                </td>
            </tr>
            ROW;
        }

        $pager = $list->render();

        $keywordVal = htmlspecialchars((string)$this->request->param('search', ''));
        $statusVal  = (int)$this->request->param('status', '');
        $statusAll  = $statusVal === '' || $statusVal === -1 ? 'selected' : '';
        $status0    = $statusVal === 0 ? 'selected' : '';
        $status1    = $statusVal === 1 ? 'selected' : '';
        $status2    = $statusVal === 2 ? 'selected' : '';

        $statTotal   = number_format($stats['total'], 0);
        $statActive  = number_format($stats['active'], 0);
        $statSuccess = number_format($stats['success'], 0);
        $statFailed  = number_format($stats['failed'], 0);
        $statRaised  = number_format($stats['raised'], 0);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>众筹项目管理</title>
            <style>
                *{margin:0;padding:0;box-sizing:border-box}
                body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;background:#f4f5fb;color:#1f2430;font-size:14px}
                .container{max-width:1560px;margin:0 auto;padding:28px 24px 60px}

                /* 页头 */
                .page-head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:22px}
                .page-head h1{font-size:24px;font-weight:700;color:#1a1f36}
                .page-head .sub{margin-top:6px;font-size:13px;color:#8a90a3}

                /* 统计卡片 */
                .stats-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:22px}
                .stat-card{position:relative;background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:18px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 3px rgba(30,34,60,.04);overflow:hidden}
                .stat-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--accent,#6366f1)}
                .stat-ico{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:var(--accent-soft,#eef0ff);color:var(--accent,#6366f1)}
                .stat-ico svg{width:22px;height:22px}
                .stat-val{font-size:22px;font-weight:700;line-height:1.1;color:#1a1f36}
                .stat-label{font-size:12px;color:#8a90a3;margin-top:3px}
                .sc-total{--accent:#6366f1;--accent-soft:#eef0ff}
                .sc-active{--accent:#2563eb;--accent-soft:#e8f1ff}
                .sc-success{--accent:#059669;--accent-soft:#e6f7f1}
                .sc-failed{--accent:#e11d48;--accent-soft:#fdeef2}
                .sc-raised{--accent:#a855f7;--accent-soft:#f4ecfe}

                /* 工具栏 */
                .toolbar{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;align-items:center}
                .search-box{position:relative;display:flex;align-items:center}
                .search-box svg{position:absolute;left:12px;width:16px;height:16px;color:#9aa0b0;pointer-events:none}
                .search-box input{width:320px;padding:10px 14px 10px 38px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;background:#fff;color:#1f2430;outline:none;transition:border-color .2s,box-shadow .2s}
                .search-box input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.12)}
                .toolbar select{padding:10px 14px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;background:#fff;color:#1f2430;outline:none;cursor:pointer}
                .btn{border:none;border-radius:10px;cursor:pointer;font-size:14px;font-weight:600;padding:10px 20px;transition:all .15s}
                .btn-primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;box-shadow:0 4px 12px rgba(99,102,241,.25)}
                .btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(99,102,241,.32)}
                .btn-ghost{background:#fff;color:#5a6172;border:1px solid #e2e5ee;text-decoration:none;display:inline-flex;align-items:center}
                .btn-ghost:hover{background:#f7f8fc}

                /* 表格 */
                .table-card{background:#fff;border:1px solid #eef0f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(30,34,60,.04)}
                .table-wrap{overflow-x:auto}
                table{width:100%;border-collapse:collapse;min-width:1180px}
                th{background:#fafbfe;font-weight:600;font-size:12px;color:#8a90a3;text-transform:uppercase;letter-spacing:.03em;text-align:left;padding:14px 16px;border-bottom:1px solid #eef0f6;white-space:nowrap}
                td{padding:14px 16px;border-bottom:1px solid #f2f3f8;font-size:13px;color:#3a4051;white-space:nowrap;vertical-align:middle}
                tbody tr{transition:background .12s}
                tbody tr:hover{background:#fafbfe}
                tbody tr:last-child td{border-bottom:none}
                .td-center{text-align:center}

                /* 项目单元格 */
                .proj-cell{display:flex;align-items:center;gap:12px;min-width:220px}
                .thumb{width:44px;height:44px;border-radius:10px;overflow:hidden;background:#eef0f6;flex-shrink:0}
                .thumb img{width:100%;height:100%;object-fit:cover;display:block}
                .thumb-empty{background:linear-gradient(135deg,#eef0ff,#f4ecfe)}
                .proj-info{min-width:0}
                .proj-title{font-weight:600;color:#1a1f36;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
                .proj-persona{font-size:12px;color:#8a90a3;margin-top:2px}

                /* 发起者 */
                .owner-name{font-weight:500;color:#3a4051}
                .owner-no{font-size:12px;color:#9aa0b0;margin-top:2px}

                /* 进度 */
                .prog-cell{display:flex;align-items:center;gap:8px;min-width:110px}
                .mini-progress{width:72px;height:6px;border-radius:999px;background:#eef0f6;overflow:hidden}
                .mini-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#6366f1,#a855f7)}
                .mini-pct{font-size:12px;color:#6366f1;font-weight:600}

                /* 金额 */
                .amt-raised{font-weight:700;color:#1a1f36}
                .amt-target{font-size:12px;color:#9aa0b0;margin-top:2px}

                /* 状态徽章 */
                .badge{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:999px;font-size:12px;font-weight:600}
                .badge::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor}
                .badge-0{background:#e8f1ff;color:#2563eb}
                .badge-1{background:#e6f7f1;color:#059669}
                .badge-2{background:#fdeef2;color:#e11d48}

                /* 操作 */
                .op-group{display:flex;gap:6px}
                .op-btn{display:inline-flex;align-items:center;gap:4px;padding:6px 10px;border:1px solid #e2e5ee;border-radius:8px;background:#fff;color:#5a6172;font-size:12px;font-weight:500;cursor:pointer;transition:all .15s}
                .op-btn svg{width:13px;height:13px}
                .op-btn:hover{border-color:#c9cde0;background:#f7f8fc}
                .op-edit:hover{color:#b45309;border-color:#f0d7b0;background:#fdf6ec}
                .op-del:hover{color:#dc2626;border-color:#f5c2c2;background:#fef2f2}

                /* 分页 */
                .page-bar{text-align:center;margin-top:22px}
                .page-bar ul{display:inline-flex;gap:6px;list-style:none}
                .page-bar li a,.page-bar li span{display:inline-block;padding:8px 13px;border:1px solid #e2e5ee;border-radius:8px;color:#5a6172;text-decoration:none;font-size:13px;background:#fff;transition:all .15s}
                .page-bar li a:hover{border-color:#6366f1;color:#6366f1}
                .page-bar .active span{background:#6366f1;color:#fff;border-color:#6366f1}

                /* 弹窗通用 */
                .modal-overlay{display:none;position:fixed;inset:0;background:rgba(20,22,40,.5);backdrop-filter:blur(3px);z-index:999;align-items:center;justify-content:center;padding:20px}
                .modal-overlay.active{display:flex}
                .modal{background:#fff;border-radius:16px;width:720px;max-width:100%;max-height:88vh;overflow-y:auto;box-shadow:0 24px 60px rgba(20,22,40,.25)}
                .modal-head{position:sticky;top:0;background:#fff;padding:20px 24px;border-bottom:1px solid #eef0f6;display:flex;align-items:center;justify-content:space-between;z-index:2}
                .modal-head h3{font-size:17px;font-weight:700;color:#1a1f36}
                .modal-close{width:32px;height:32px;border:none;border-radius:8px;background:#f2f3f8;color:#5a6172;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s}
                .modal-close:hover{background:#e8eaf2}
                .modal-body{padding:24px}

                /* 详情 */
                .detail-top{display:flex;gap:20px;margin-bottom:20px}
                .detail-cover{width:180px;height:180px;border-radius:14px;overflow:hidden;background:linear-gradient(135deg,#eef0ff,#f4ecfe);flex-shrink:0;display:flex;align-items:center;justify-content:center;color:#b8bdd0;font-size:12px}
                .detail-cover img{width:100%;height:100%;object-fit:cover;display:block}
                .detail-meta{flex:1;min-width:0}
                .detail-title{font-size:19px;font-weight:700;color:#1a1f36;line-height:1.35}
                .detail-persona{font-size:13px;color:#a855f7;font-weight:600;margin-top:6px}
                .detail-owner{margin-top:14px;font-size:13px;color:#5a6172}
                .detail-owner b{color:#1a1f36}
                .detail-badges{margin-top:12px;display:flex;gap:8px}
                .stat-mini-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
                .stat-mini{background:#fafbfe;border:1px solid #eef0f6;border-radius:12px;padding:14px}
                .stat-mini .v{font-size:17px;font-weight:700;color:#1a1f36}
                .stat-mini .k{font-size:12px;color:#8a90a3;margin-top:3px}
                .detail-progress{margin-bottom:20px}
                .big-progress{height:10px;border-radius:999px;background:#eef0f6;overflow:hidden;margin-top:8px}
                .big-progress .fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#6366f1,#a855f7)}
                .detail-desc{background:#fafbfe;border:1px solid #eef0f6;border-radius:12px;padding:14px 16px;font-size:13px;color:#4a5062;line-height:1.8;white-space:pre-wrap;word-break:break-word;margin-bottom:20px}
                .sec-title{font-size:13px;font-weight:700;color:#1a1f36;margin-bottom:10px}
                .pledge-table{width:100%;border-collapse:collapse;min-width:0;border:1px solid #eef0f6;border-radius:10px;overflow:hidden}
                .pledge-table th,.pledge-table td{padding:10px 14px;font-size:12px;text-align:left;border-bottom:1px solid #f2f3f8;white-space:nowrap}
                .pledge-table th{background:#fafbfe}
                .pledge-table tr:last-child td{border-bottom:none}
                .p-badge{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600}
                .p-badge-0{background:#e8f1ff;color:#2563eb}
                .p-badge-1{background:#e6f7f1;color:#059669}
                .p-badge-2{background:#fdeef2;color:#e11d48}
                .empty-tip{padding:30px;text-align:center;color:#9aa0b0;font-size:13px}
                .modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:24px;padding-top:16px;border-top:1px solid #eef0f6}

                /* 表单 */
                .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
                .form-field{margin-bottom:16px}
                .form-field.full{grid-column:1/-1}
                .form-field label{display:block;font-size:12px;font-weight:600;color:#5a6172;margin-bottom:7px}
                .form-field label .req{color:#e11d48}
                .form-field input,.form-field textarea{width:100%;padding:10px 14px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;color:#1f2430;background:#fff;outline:none;transition:border-color .2s,box-shadow .2s;box-sizing:border-box}
                .form-field input:focus,.form-field textarea:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.12)}
                .form-field textarea{resize:vertical;min-height:96px;line-height:1.6}
                .form-field .hint{font-size:11px;color:#9aa0b0;margin-top:5px}
                .cover-preview{width:100%;height:160px;border-radius:12px;border:2px dashed #d8dbea;background:#fafbfe;display:flex;align-items:center;justify-content:center;color:#b8bdd0;font-size:13px;overflow:hidden;margin-top:8px;cursor:pointer;transition:border-color .2s,background .2s;flex-direction:column;gap:6px}
                .cover-preview:hover{border-color:#6366f1;background:#f7f8ff;color:#6366f1}
                .cover-preview img{width:100%;height:100%;object-fit:cover;display:block}
                .btn-cancel{background:#fff;color:#5a6172;border:1px solid #e2e5ee}
                .btn-cancel:hover{background:#f7f8fc}

                @media (max-width:1100px){
                    .stats-grid{grid-template-columns:repeat(2,1fr)}
                }
                @media (max-width:640px){
                    .stats-grid{grid-template-columns:1fr 1fr}
                    .detail-top{flex-direction:column}
                    .detail-cover{width:100%;height:200px}
                    .form-grid{grid-template-columns:1fr}
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="page-head">
                    <div>
                        <h1>众筹项目管理</h1>
                        <p class="sub">管理角色众筹项目 · 查看进度、编辑内容、处理支持记录</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card sc-total">
                        <div class="stat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div>
                        <div><div class="stat-val">{$statTotal}</div><div class="stat-label">全部项目</div></div>
                    </div>
                    <div class="stat-card sc-active">
                        <div class="stat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                        <div><div class="stat-val">{$statActive}</div><div class="stat-label">进行中</div></div>
                    </div>
                    <div class="stat-card sc-success">
                        <div class="stat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                        <div><div class="stat-val">{$statSuccess}</div><div class="stat-label">已成功</div></div>
                    </div>
                    <div class="stat-card sc-failed">
                        <div class="stat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
                        <div><div class="stat-val">{$statFailed}</div><div class="stat-label">已失败</div></div>
                    </div>
                    <div class="stat-card sc-raised">
                        <div class="stat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
                        <div><div class="stat-val">{$statRaised}</div><div class="stat-label">累计已筹（钻）</div></div>
                    </div>
                </div>

                <form class="toolbar" method="get">
                    <div class="search-box">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input name="search" placeholder="搜索项目标题 / 角色名 / 发起者" value="{$keywordVal}">
                    </div>
                    <select name="status">
                        <option value="" {$statusAll}>全部状态</option>
                        <option value="0" {$status0}>进行中</option>
                        <option value="1" {$status1}>已成功</option>
                        <option value="2" {$status2}>已失败</option>
                    </select>
                    <button class="btn btn-primary" type="submit">查询</button>
                    <a class="btn btn-ghost" href="/admin/live.Crowdfunding/index">重置</a>
                </form>

                <div class="table-card">
                    <div class="table-wrap">
                        <table>
                            <thead><tr>
                                <th>项目</th><th>发起者</th><th>进度</th><th>目标 / 已筹</th><th>支持</th><th>截止时间</th><th>状态</th><th>创建时间</th><th>操作</th>
                            </tr></thead>
                            <tbody>{$rows}</tbody>
                        </table>
                    </div>
                </div>
                <div class="page-bar">{$pager}</div>
            </div>

            <div class="modal-overlay" id="detailModal">
                <div class="modal" id="detailBox"></div>
            </div>

            <div class="modal-overlay" id="editModal">
                <div class="modal">
                    <div class="modal-head">
                        <h3>编辑众筹项目</h3>
                        <button class="modal-close" onclick="closeEdit()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editId" />
                        <div class="form-grid">
                            <div class="form-field">
                                <label>项目标题 <span class="req">*</span></label>
                                <input type="text" id="editTitle" maxlength="100" placeholder="一句话描述角色创意" />
                            </div>
                            <div class="form-field">
                                <label>角色名称 <span class="req">*</span></label>
                                <input type="text" id="editPersona" maxlength="50" placeholder="角色名字" />
                            </div>
                            <div class="form-field full">
                                <label>详细描述</label>
                                <textarea id="editDesc" placeholder="描述角色人设、风格、创意理念…"></textarea>
                            </div>
                            <div class="form-field full">
                                <label>封面图</label>
                                <div class="cover-preview" id="coverPreview" onclick="document.getElementById('coverFile').click()"><span>点击选择图片上传</span></div>
                                <input type="file" id="coverFile" accept="image/*" style="display:none" onchange="onCoverSelected(this)" />
                                <input type="hidden" id="editCover" />
                                <div class="hint">点击上方区域选择图片，保存时自动上传</div>
                            </div>
                            <div class="form-field">
                                <label>目标金额（钻） <span class="req">*</span></label>
                                <input type="number" id="editTarget" min="1" step="1" placeholder="例如 10000" />
                            </div>
                            <div class="form-field">
                                <label>截止时间 <span class="req">*</span></label>
                                <input type="datetime-local" id="editDeadline" />
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-cancel" onclick="closeEdit()">取消</button>
                            <button class="btn btn-primary" id="editSaveBtn" onclick="saveEdit()">保存修改</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            function htmlEscape(s) {
                return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }
            function fmt(n) {
                return Number(n || 0).toLocaleString();
            }
            function statusBadge(s, text) {
                return '<span class="badge badge-' + s + '">' + htmlEscape(text) + '</span>';
            }
            function pledgeBadge(s, text) {
                return '<span class="p-badge p-badge-' + s + '">' + htmlEscape(text) + '</span>';
            }

            function showDetail(id) {
                document.getElementById('detailModal').classList.add('active');
                document.getElementById('detailBox').innerHTML = '<div class="modal-head"><h3>项目详情</h3><button class="modal-close" onclick="closeDetail()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button></div><div class="modal-body"><div class="empty-tip">加载中...</div></div>';
                fetch('/admin/live.Crowdfunding/detailJson?id=' + id)
                    .then(function(r){ return r.json() })
                    .then(function(d){
                        if (d.code !== 1) {
                            document.getElementById('detailBox').innerHTML = '<div class="modal-head"><h3>项目详情</h3><button class="modal-close" onclick="closeDetail()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button></div><div class="modal-body"><div class="empty-tip">' + htmlEscape(d.msg || '加载失败') + '</div></div>';
                            return;
                        }
                        var p = d.data;
                        var cover = p.cover_url
                            ? '<div class="detail-cover"><img src="' + htmlEscape(p.cover_url) + '" onerror="this.style.display=\'none\'"></div>'
                            : '<div class="detail-cover">无封面</div>';
                        var desc = p.description ? htmlEscape(p.description) : '暂无描述';
                        var pledges = p.pledges || [];
                        var pledgeRows = '';
                        if (pledges.length === 0) {
                            pledgeRows = '<tr><td colspan="4" class="empty-tip">暂无支持记录</td></tr>';
                        } else {
                            for (var i = 0; i < pledges.length; i++) {
                                var w = pledges[i];
                                pledgeRows += '<tr>' +
                                    '<td>' + (w.nickname ? htmlEscape(w.nickname) : '用户#' + w.id) + '</td>' +
                                    '<td>' + fmt(w.amount) + ' 钻</td>' +
                                    '<td>' + pledgeBadge(w.status, w.status_text) + '</td>' +
                                    '<td>' + htmlEscape(w.created_at || '-') + '</td>' +
                                    '</tr>';
                            }
                        }
                        var html =
                            '<div class="modal-head">' +
                                '<h3>项目详情</h3>' +
                                '<button class="modal-close" onclick="closeDetail()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>' +
                            '</div>' +
                            '<div class="modal-body">' +
                                '<div class="detail-top">' +
                                    cover +
                                    '<div class="detail-meta">' +
                                        '<div class="detail-title">' + htmlEscape(p.title) + '</div>' +
                                        '<div class="detail-persona">' + htmlEscape(p.persona_name) + '</div>' +
                                        '<div class="detail-owner">发起者：<b>' + htmlEscape(p.nickname) + '</b>（ID: ' + p.user_id + '）</div>' +
                                        '<div class="detail-badges">' + statusBadge(p.status, p.status_text) + '</div>' +
                                    '</div>' +
                                '</div>' +
                                '<div class="stat-mini-grid">' +
                                    '<div class="stat-mini"><div class="v">' + fmt(p.target_amount) + '</div><div class="k">目标金额（钻）</div></div>' +
                                    '<div class="stat-mini"><div class="v">' + fmt(p.raised_amount) + '</div><div class="k">已筹金额（钻）</div></div>' +
                                    '<div class="stat-mini"><div class="v">' + p.progress_percent + '%</div><div class="k">完成进度</div></div>' +
                                    '<div class="stat-mini"><div class="v">' + p.supporter_count + '</div><div class="k">支持人数</div></div>' +
                                '</div>' +
                                '<div class="detail-progress">' +
                                    '<div class="big-progress"><div class="fill" style="width:' + p.progress_percent + '%"></div></div>' +
                                '</div>' +
                                '<div class="sec-title">项目描述</div>' +
                                '<div class="detail-desc">' + desc + '</div>' +
                                '<div class="sec-title">支持记录（' + pledges.length + '）</div>' +
                                '<table class="pledge-table"><thead><tr><th>支持者</th><th>金额</th><th>状态</th><th>时间</th></tr></thead><tbody>' + pledgeRows + '</tbody></table>' +
                                '<div class="modal-footer">' +
                                    '<button class="btn btn-ghost" onclick="closeDetail()">关闭</button>' +
                                    '<button class="btn btn-primary" onclick="closeDetail();showEdit(' + p.id + ')">编辑</button>' +
                                '</div>' +
                            '</div>';
                        document.getElementById('detailBox').innerHTML = html;
                    })
                    .catch(function(){
                        document.getElementById('detailBox').innerHTML = '<div class="modal-head"><h3>项目详情</h3><button class="modal-close" onclick="closeDetail()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button></div><div class="modal-body"><div class="empty-tip">网络错误</div></div>';
                    });
            }
            function closeDetail() {
                document.getElementById('detailModal').classList.remove('active');
            }

            function showEdit(id) {
                fetch('/admin/live.Crowdfunding/detailJson?id=' + id)
                    .then(function(r){ return r.json() })
                    .then(function(d){
                        if (d.code !== 1) { alert('加载失败: ' + (d.msg || '')); return; }
                        var p = d.data;
                        document.getElementById('editId').value = p.id;
                        document.getElementById('editTitle').value = p.title || '';
                        document.getElementById('editPersona').value = p.persona_name || '';
                        document.getElementById('editDesc').value = p.description || '';
                        document.getElementById('editCover').value = p.cover_url || '';
                        document.getElementById('editTarget').value = p.target_amount || 0;
                        document.getElementById('editDeadline').value = toLocalInput(p.deadline);
                        document.getElementById('coverFile').value = '';
                        setCoverPreview(p.cover_url);
                        document.getElementById('editModal').classList.add('active');
                    })
                    .catch(function(){ alert('网络错误'); });
            }
            function toLocalInput(s) {
                if (!s) return '';
                var d = new Date(s);
                if (isNaN(d.getTime())) return s;
                var t = new Date(d.getTime() - d.getTimezoneOffset() * 60000);
                return t.toISOString().slice(0, 16);
            }
            function setCoverPreview(url) {
                var box = document.getElementById('coverPreview');
                if (url) {
                    box.innerHTML = '<img src="' + htmlEscape(url) + '" onerror="this.parentNode.innerHTML=\'<span>图片加载失败</span>\'">';
                } else {
                    box.innerHTML = '<span>点击选择图片上传</span>';
                }
            }
            function onCoverSelected(input) {
                if (!input.files || !input.files[0]) return;
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('coverPreview').innerHTML = '<img src="' + e.target.result + '" alt="">';
                };
                reader.readAsDataURL(input.files[0]);
            }
            function closeEdit() {
                document.getElementById('editModal').classList.remove('active');
            }
            function saveEdit() {
                var btn = document.getElementById('editSaveBtn');
                btn.disabled = true;
                btn.textContent = '保存中...';
                var fd = new FormData();
                fd.append('id', document.getElementById('editId').value);
                fd.append('title', document.getElementById('editTitle').value.trim());
                fd.append('persona_name', document.getElementById('editPersona').value.trim());
                fd.append('description', document.getElementById('editDesc').value.trim());
                fd.append('cover_url', document.getElementById('editCover').value);
                fd.append('target_amount', document.getElementById('editTarget').value);
                fd.append('deadline', document.getElementById('editDeadline').value);
                var fileInput = document.getElementById('coverFile');
                if (fileInput.files && fileInput.files[0]) {
                    fd.append('cover_file', fileInput.files[0]);
                }
                fetch('/admin/live.Crowdfunding/edit', {
                    method: 'POST',
                    body: fd
                }).then(function(r){ return r.json() }).then(function(d){
                    btn.disabled = false;
                    btn.textContent = '保存修改';
                    if (d.code === 1) { alert('保存成功'); location.reload(); }
                    else { alert(d.msg || '保存失败'); }
                }).catch(function(){ btn.disabled = false; btn.textContent = '保存修改'; alert('网络错误'); });
            }
            function doDelete(id) {
                if (!confirm('确定删除该众筹项目？进行中的支持款将自动退回。')) return;
                var body = new URLSearchParams();
                body.append('id', id);
                fetch('/admin/live.Crowdfunding/delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                }).then(function(r){ return r.json() }).then(function(d){
                    if (d.code === 1) { alert('删除成功'); location.reload(); }
                    else { alert(d.msg || '删除失败'); }
                }).catch(function(){ alert('网络错误'); });
            }

            document.getElementById('detailModal').addEventListener('click', function(e) {
                if (e.target === this) closeDetail();
            });
            document.getElementById('editModal').addEventListener('click', function(e) {
                if (e.target === this) closeEdit();
            });
            </script>
        </body>
        </html>
        HTML;
    }
}
