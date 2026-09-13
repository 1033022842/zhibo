<?php
/**
 * 首页推荐角色管理 - 独立页面（无需前端编译）
 * 访问: /admin/live.HomeCharacter/index
 */

declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\admin\model\live\HomeCharacter as CharacterModel;

final class HomeCharacter extends Backend
{
    protected object $model;

    protected array $noNeedLogin = ['index', 'detailJson', 'add', 'edit', 'delete'];

    // 飘页访问守卫：URL ?_key= 首次校验后走 Cookie（密钥 .env ADMIN_FLOAT_KEY）
    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new CharacterModel();
    }

    /**
     * 角色列表页
     */
    public function index(): void
    {
        $keyword = trim((string)$this->request->param('search', ''));
        $status  = $this->request->param('status', '');

        $query = $this->model;
        if ($keyword !== '') {
            $query = $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->whereOr('tagline', 'like', "%{$keyword}%")
                  ->whereOr('tags', 'like', "%{$keyword}%");
            });
        }
        if ($status !== '') {
            $query = $query->where('status', (int)$status);
        }

        $list = $query->order('weigh', 'desc')->order('id', 'desc')->paginate(15);

        $stats = [
            'total'    => (int)$this->model->count(),
            'enabled'  => (int)$this->model->where('status', 1)->count(),
            'disabled' => (int)$this->model->where('status', 0)->count(),
            'adult'    => (int)$this->model->where('is_adult', 1)->count(),
        ];

        $html = $this->renderPage($list, $stats);
        response($html)->send();
        exit;
    }

    /**
     * 角色详情 JSON
     */
    public function detailJson(): void
    {
        $id = $this->request->param('id/d', 0);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error('角色不存在');
            return;
        }
        $this->success('', ['row' => $row->toArray()]);
    }

    /**
     * 新增角色
     */
    public function add(): void
    {
        if (!$this->request->isPost()) {
            $this->error('参数错误');
            return;
        }

        $data = $this->buildData();
        if ($data === null) {
            return;
        }

        try {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = $data['created_at'];
            $this->model->save($data);
        } catch (\Throwable $e) {
            $this->error($e->getMessage() ?: '新增失败');
            return;
        }

        $this->success('新增成功');
    }

    /**
     * 编辑角色
     */
    public function edit(): void
    {
        if (!$this->request->isPost()) {
            $this->error('参数错误');
            return;
        }

        $id = $this->request->post('id/d', 0);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error('角色不存在');
            return;
        }

        $data = $this->buildData((string)$row->cover_url);
        if ($data === null) {
            return;
        }

        try {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $row->save($data);
        } catch (\Throwable $e) {
            $this->error($e->getMessage() ?: '保存失败');
            return;
        }

        $this->success('保存成功');
    }

    /**
     * 删除角色
     */
    public function delete(): void
    {
        $id = $this->request->param('id/d', 0);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error('角色不存在');
            return;
        }
        try {
            $row->delete();
        } catch (\Throwable $e) {
            $this->error($e->getMessage() ?: '删除失败');
            return;
        }
        $this->success('删除成功');
    }

    /**
     * 组装并校验表单数据，校验失败时直接输出错误并返回 null
     */
    private function buildData(string $existingCover = ''): ?array
    {
        $name    = trim((string)$this->request->post('name', ''));
        $age     = trim((string)$this->request->post('age', ''));
        $tagline = trim((string)$this->request->post('tagline', ''));
        $desc    = trim((string)$this->request->post('description', ''));
        $tags    = $this->normalizeList((string)$this->request->post('tags', ''));
        $link    = trim((string)$this->request->post('link_url', ''));
        $cover   = trim((string)$this->request->post('cover_url', $existingCover));

        if ($name === '') {
            $this->error('角色名不能为空');
            return null;
        }
        if ($tagline === '') {
            $this->error('一句话简介不能为空');
            return null;
        }
        if ($link !== '' && !preg_match('/^(https?:)?\/\//i', $link) && !str_starts_with($link, './')) {
            $this->error('跳转链接需以 http(s):// 或 ./ 开头');
            return null;
        }

        // 处理封面：优先使用新上传的图片
        $hasUpload = !empty($_FILES['cover_file']['tmp_name'])
            && (int)$_FILES['cover_file']['error'] === UPLOAD_ERR_OK
            && (int)$_FILES['cover_file']['size'] > 0;
        if ($hasUpload) {
            try {
                $file       = $this->request->file('cover_file');
                $upload     = new \app\common\library\Upload($file);
                $upload->setTopic('home_character');
                $attachment = $upload->upload(null, 0, 0);
                $cover      = (string)($attachment['url'] ?? $cover);
            } catch (\Throwable $e) {
                $this->error('图片上传失败: ' . $e->getMessage());
                return null;
            }
        }

        return [
            'name'        => $name,
            'age'         => $age,
            'tagline'     => $tagline,
            'description' => $desc,
            'cover_url'   => $cover,
            'tags'        => $tags,
            'link_url'    => $link,
            'is_adult'    => (int)$this->request->post('is_adult/d', 0) === 1 ? 1 : 0,
            'weigh'       => (int)$this->request->post('weigh/d', 0),
            'status'      => (int)$this->request->post('status/d', 1) === 1 ? 1 : 0,
        ];
    }

    /**
     * 逗号分隔字符串 -> 去重、去空后的字符串
     */
    private function normalizeList(string $value): string
    {
        $value = trim(str_replace('，', ',', $value));
        if ($value === '') {
            return '';
        }
        $parts = array_filter(array_map('trim', explode(',', $value)), static fn($s) => $s !== '');
        return implode(',', array_unique($parts));
    }

    private function renderPage($list, array $stats): string
    {
        $rows = '';
        foreach ($list->items() as $row) {
            $id      = (int)$row['id'];
            $name    = htmlspecialchars((string)$row['name']);
            $age     = htmlspecialchars((string)$row['age']);
            $tagline = htmlspecialchars((string)$row['tagline']);
            $weigh   = (int)$row['weigh'];
            $adult   = (int)$row['is_adult'];
            $status  = (int)$row['status'];
            $time    = $row['updated_at'] ? substr((string)$row['updated_at'], 0, 16) : '-';

            $tags = array_filter(array_map('trim', explode(',', (string)$row['tags'])), static fn($s) => $s !== '');
            $tagsHtml = $tags
                ? implode('', array_map(static fn($t) => '<span class="tag-chip">' . htmlspecialchars($t) . '</span>', $tags))
                : '<span class="muted">-</span>';

            $cover = $row['cover_url']
                ? '<div class="thumb"><img src="' . htmlspecialchars((string)$row['cover_url']) . '" alt="" onerror="this.style.display=\'none\'"></div>'
                : '<div class="thumb thumb-empty"></div>';

            $statusHtml = $status === 1
                ? '<span class="badge badge-1">启用</span>'
                : '<span class="badge badge-0">禁用</span>';
            $adultHtml = $adult === 1
                ? '<span class="adult-chip">18+</span>'
                : '<span class="muted">否</span>';

            $rows .= <<<ROW
            <tr>
                <td>{$id}</td>
                <td>
                    <div class="proj-cell">
                        {$cover}
                        <div class="proj-info">
                            <div class="proj-title">{$name}</div>
                            <div class="proj-persona">{$age}</div>
                        </div>
                    </div>
                </td>
                <td><div class="cell-clamp">{$tagline}</div></td>
                <td><div class="tag-list">{$tagsHtml}</div></td>
                <td class="td-center">{$adultHtml}</td>
                <td class="td-center">{$weigh}</td>
                <td class="td-center">{$statusHtml}</td>
                <td>{$time}</td>
                <td>
                    <div class="op-group">
                        <button class="op-btn op-edit" onclick="showEdit({$id})" title="编辑">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>编辑
                        </button>
                        <button class="op-btn op-del" onclick="doDelete({$id})" title="删除">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>删除
                        </button>
                    </div>
                </td>
            </tr>
            ROW;
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="9"><div class="empty-tip">暂无推荐角色，点击右上角「新增角色」添加</div></td></tr>';
        }

        $pager = $list->render();

        $keywordVal = htmlspecialchars((string)$this->request->param('search', ''));
        $statusVal  = $this->request->param('status', '');
        $statusAll  = $statusVal === '' ? 'selected' : '';
        $status1    = (string)$statusVal === '1' ? 'selected' : '';
        $status0    = (string)$statusVal === '0' ? 'selected' : '';

        $statTotal    = number_format($stats['total'], 0);
        $statEnabled  = number_format($stats['enabled'], 0);
        $statDisabled = number_format($stats['disabled'], 0);
        $statAdult    = number_format($stats['adult'], 0);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>首页推荐角色</title>
            <style>
                *{margin:0;padding:0;box-sizing:border-box}
                body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;background:#f4f5fb;color:#1f2430;font-size:14px}
                .container{max-width:1460px;margin:0 auto;padding:28px 24px 60px}

                .page-head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:22px}
                .page-head h1{font-size:24px;font-weight:700;color:#1a1f36}
                .page-head .sub{margin-top:6px;font-size:13px;color:#8a90a3}

                .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:22px}
                .stat-card{position:relative;background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:18px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 3px rgba(30,34,60,.04);overflow:hidden}
                .stat-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--accent,#6366f1)}
                .stat-ico{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:var(--accent-soft,#eef0ff);color:var(--accent,#6366f1)}
                .stat-ico svg{width:22px;height:22px}
                .stat-val{font-size:22px;font-weight:700;line-height:1.1;color:#1a1f36}
                .stat-label{font-size:12px;color:#8a90a3;margin-top:3px}
                .sc-total{--accent:#6366f1;--accent-soft:#eef0ff}
                .sc-enabled{--accent:#059669;--accent-soft:#e6f7f1}
                .sc-disabled{--accent:#64748b;--accent-soft:#eef2f7}
                .sc-adult{--accent:#e11d48;--accent-soft:#fdeef2}

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
                .spacer{margin-left:auto}

                .table-card{background:#fff;border:1px solid #eef0f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(30,34,60,.04)}
                .table-wrap{overflow-x:auto}
                table{width:100%;border-collapse:collapse;min-width:1080px}
                th{background:#fafbfe;font-weight:600;font-size:12px;color:#8a90a3;text-transform:uppercase;letter-spacing:.03em;text-align:left;padding:14px 16px;border-bottom:1px solid #eef0f6;white-space:nowrap}
                td{padding:14px 16px;border-bottom:1px solid #f2f3f8;font-size:13px;color:#3a4051;vertical-align:middle}
                tbody tr{transition:background .12s}
                tbody tr:hover{background:#fafbfe}
                tbody tr:last-child td{border-bottom:none}
                .td-center{text-align:center}
                .muted{color:#9aa0b0}

                .proj-cell{display:flex;align-items:center;gap:12px;min-width:200px}
                .thumb{width:48px;height:48px;border-radius:10px;overflow:hidden;background:#eef0f6;flex-shrink:0}
                .thumb img{width:100%;height:100%;object-fit:cover;display:block}
                .thumb-empty{background:linear-gradient(135deg,#eef0ff,#f4ecfe)}
                .proj-info{min-width:0}
                .proj-title{font-weight:600;color:#1a1f36;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
                .proj-persona{font-size:12px;color:#8a90a3;margin-top:2px}

                .cell-clamp{max-width:340px;color:#5a6172;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;white-space:normal}

                .tag-list{display:flex;flex-wrap:wrap;gap:5px}
                .tag-chip{padding:3px 9px;border-radius:999px;background:#f4ecfe;border:1px solid #e4d4fb;color:#7c3aed;font-size:12px;white-space:nowrap}
                .adult-chip{padding:3px 10px;border-radius:999px;background:#fdeef2;border:1px solid #f7c9d6;color:#e11d48;font-size:12px;font-weight:600}

                .badge{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:999px;font-size:12px;font-weight:600}
                .badge::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor}
                .badge-0{background:#eef2f7;color:#64748b}
                .badge-1{background:#e6f7f1;color:#059669}

                .op-group{display:flex;gap:6px}
                .op-btn{display:inline-flex;align-items:center;gap:4px;padding:6px 10px;border:1px solid #e2e5ee;border-radius:8px;background:#fff;color:#5a6172;font-size:12px;font-weight:500;cursor:pointer;transition:all .15s;white-space:nowrap}
                .op-btn svg{width:13px;height:13px}
                .op-btn:hover{border-color:#c9cde0;background:#f7f8fc}
                .op-edit:hover{color:#b45309;border-color:#f0d7b0;background:#fdf6ec}
                .op-del:hover{color:#dc2626;border-color:#f5c2c2;background:#fef2f2}

                .page-bar{text-align:center;margin-top:22px}
                .page-bar ul{display:inline-flex;gap:6px;list-style:none}
                .page-bar li a,.page-bar li span{display:inline-block;padding:8px 13px;border:1px solid #e2e5ee;border-radius:8px;color:#5a6172;text-decoration:none;font-size:13px;background:#fff;transition:all .15s}
                .page-bar li a:hover{border-color:#6366f1;color:#6366f1}
                .page-bar .active span{background:#6366f1;color:#fff;border-color:#6366f1}
                .empty-tip{padding:30px;text-align:center;color:#9aa0b0;font-size:13px}

                .modal-overlay{display:none;position:fixed;inset:0;background:rgba(20,22,40,.5);backdrop-filter:blur(3px);z-index:999;align-items:center;justify-content:center;padding:20px}
                .modal-overlay.active{display:flex}
                .modal{background:#fff;border-radius:16px;width:680px;max-width:100%;max-height:88vh;overflow-y:auto;box-shadow:0 24px 60px rgba(20,22,40,.25)}
                .modal-head{position:sticky;top:0;background:#fff;padding:20px 24px;border-bottom:1px solid #eef0f6;display:flex;align-items:center;justify-content:space-between;z-index:2}
                .modal-head h3{font-size:17px;font-weight:700;color:#1a1f36}
                .modal-close{width:32px;height:32px;border:none;border-radius:8px;background:#f2f3f8;color:#5a6172;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s}
                .modal-close:hover{background:#e8eaf2}
                .modal-body{padding:24px}
                .modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:24px;padding-top:16px;border-top:1px solid #eef0f6}

                .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
                .form-field{margin-bottom:4px}
                .form-field.full{grid-column:1/-1}
                .form-field label{display:block;font-size:12px;font-weight:600;color:#5a6172;margin-bottom:7px}
                .form-field label .req{color:#e11d48}
                .form-field input,.form-field textarea,.form-field select{width:100%;padding:10px 14px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;color:#1f2430;background:#fff;outline:none;transition:border-color .2s,box-shadow .2s;box-sizing:border-box}
                .form-field input:focus,.form-field textarea:focus,.form-field select:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.12)}
                .form-field textarea{resize:vertical;min-height:90px;line-height:1.6}
                .form-field .hint{font-size:11px;color:#9aa0b0;margin-top:5px}
                .cover-preview{width:100%;height:150px;border-radius:12px;border:2px dashed #d8dbea;background:#fafbfe;display:flex;align-items:center;justify-content:center;color:#b8bdd0;font-size:13px;overflow:hidden;margin-top:8px;cursor:pointer;transition:border-color .2s,background .2s}
                .cover-preview:hover{border-color:#6366f1;background:#f7f8ff;color:#6366f1}
                .cover-preview img{width:100%;height:100%;object-fit:cover;display:block}
                .btn-cancel{background:#fff;color:#5a6172;border:1px solid #e2e5ee}
                .btn-cancel:hover{background:#f7f8fc}

                @media (max-width:900px){
                    .stats-grid{grid-template-columns:repeat(2,1fr)}
                    .form-grid{grid-template-columns:1fr}
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="page-head">
                    <div>
                        <h1>首页推荐角色</h1>
                        <p class="sub">AI 女友端首页（Home.html）Hero 轮播与推荐卡片的数据来源</p>
                    </div>
                    <button class="btn btn-primary" onclick="showAdd()">+ 新增角色</button>
                </div>

                <div class="stats-grid">
                    <div class="stat-card sc-total">
                        <div class="stat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                        <div><div class="stat-val">{$statTotal}</div><div class="stat-label">全部角色</div></div>
                    </div>
                    <div class="stat-card sc-enabled">
                        <div class="stat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                        <div><div class="stat-val">{$statEnabled}</div><div class="stat-label">已启用</div></div>
                    </div>
                    <div class="stat-card sc-disabled">
                        <div class="stat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg></div>
                        <div><div class="stat-val">{$statDisabled}</div><div class="stat-label">已禁用</div></div>
                    </div>
                    <div class="stat-card sc-adult">
                        <div class="stat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                        <div><div class="stat-val">{$statAdult}</div><div class="stat-label">18+ 内容</div></div>
                    </div>
                </div>

                <form class="toolbar" method="get">
                    <div class="search-box">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input name="search" placeholder="搜索角色名 / 简介 / 标签" value="{$keywordVal}">
                    </div>
                    <select name="status">
                        <option value="" {$statusAll}>全部状态</option>
                        <option value="1" {$status1}>启用</option>
                        <option value="0" {$status0}>禁用</option>
                    </select>
                    <button class="btn btn-primary" type="submit">查询</button>
                    <a class="btn btn-ghost" href="/admin/live.HomeCharacter/index">重置</a>
                </form>

                <div class="table-card">
                    <div class="table-wrap">
                        <table>
                            <thead><tr>
                                <th>ID</th><th>角色</th><th>简介</th><th>标签</th><th>18+</th><th>权重</th><th>状态</th><th>更新时间</th><th>操作</th>
                            </tr></thead>
                            <tbody>{$rows}</tbody>
                        </table>
                    </div>
                </div>
                <div class="page-bar">{$pager}</div>
            </div>

            <div class="modal-overlay" id="formModal">
                <div class="modal">
                    <div class="modal-head">
                        <h3 id="formTitle">新增角色</h3>
                        <button class="modal-close" onclick="closeForm()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="fId" />
                        <div class="form-grid">
                            <div class="form-field">
                                <label>角色名 <span class="req">*</span></label>
                                <input type="text" id="fName" maxlength="60" placeholder="例如 Bonnie" />
                            </div>
                            <div class="form-field">
                                <label>年龄</label>
                                <input type="text" id="fAge" maxlength="20" placeholder="例如 22" />
                            </div>
                            <div class="form-field full">
                                <label>一句话简介 <span class="req">*</span></label>
                                <input type="text" id="fTagline" maxlength="160" placeholder="卡片上展示的一句话，例如 Sweet talker who remembers every word" />
                            </div>
                            <div class="form-field full">
                                <label>详细描述</label>
                                <textarea id="fDesc" placeholder="角色背景、性格、相处方式…"></textarea>
                            </div>
                            <div class="form-field full">
                                <label>标签</label>
                                <input type="text" id="fTags" placeholder="逗号分隔，例如：Sweet,Voice,Girlfriend" />
                            </div>
                            <div class="form-field full">
                                <label>跳转链接</label>
                                <input type="text" id="fLink" placeholder="例如 ./Girls.html 或 https://..." />
                                <div class="hint">留空则卡片点击跳转角色聊天入口</div>
                            </div>
                            <div class="form-field">
                                <label>权重（越大越靠前）</label>
                                <input type="number" id="fWeigh" step="1" value="0" />
                            </div>
                            <div class="form-field">
                                <label>是否 18+ 内容</label>
                                <select id="fAdult">
                                    <option value="0">否 · 全年龄</option>
                                    <option value="1">是 · 18+</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>状态</label>
                                <select id="fStatus">
                                    <option value="1">启用</option>
                                    <option value="0">禁用</option>
                                </select>
                            </div>
                            <div class="form-field full">
                                <label>封面图</label>
                                <div class="cover-preview" id="coverPreview" onclick="document.getElementById('coverFile').click()"><span>点击选择图片上传</span></div>
                                <input type="file" id="coverFile" accept="image/*" style="display:none" onchange="onCoverSelected(this)" />
                                <input type="hidden" id="fCover" />
                                <div class="hint">点击上方区域选择图片，保存时自动上传</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-cancel" onclick="closeForm()">取消</button>
                            <button class="btn btn-primary" id="saveBtn" onclick="saveForm()">保存</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            function htmlEscape(s) {
                return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
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
            function resetForm() {
                document.getElementById('fId').value = '';
                document.getElementById('fName').value = '';
                document.getElementById('fAge').value = '';
                document.getElementById('fTagline').value = '';
                document.getElementById('fDesc').value = '';
                document.getElementById('fTags').value = '';
                document.getElementById('fLink').value = '';
                document.getElementById('fWeigh').value = '0';
                document.getElementById('fAdult').value = '0';
                document.getElementById('fStatus').value = '1';
                document.getElementById('fCover').value = '';
                document.getElementById('coverFile').value = '';
                setCoverPreview('');
            }
            function showAdd() {
                resetForm();
                document.getElementById('formTitle').textContent = '新增角色';
                document.getElementById('formModal').classList.add('active');
            }
            function showEdit(id) {
                fetch('/admin/live.HomeCharacter/detailJson?id=' + id)
                    .then(function(r){ return r.json() })
                    .then(function(d){
                        if (d.code !== 1) { alert('加载失败: ' + (d.msg || '')); return; }
                        var p = d.data.row;
                        resetForm();
                        document.getElementById('fId').value = p.id;
                        document.getElementById('fName').value = p.name || '';
                        document.getElementById('fAge').value = p.age || '';
                        document.getElementById('fTagline').value = p.tagline || '';
                        document.getElementById('fDesc').value = p.description || '';
                        document.getElementById('fTags').value = p.tags || '';
                        document.getElementById('fLink').value = p.link_url || '';
                        document.getElementById('fWeigh').value = p.weigh || 0;
                        document.getElementById('fAdult').value = String(p.is_adult || 0);
                        document.getElementById('fStatus').value = String(p.status);
                        document.getElementById('fCover').value = p.cover_url || '';
                        setCoverPreview(p.cover_url);
                        document.getElementById('formTitle').textContent = '编辑角色';
                        document.getElementById('formModal').classList.add('active');
                    })
                    .catch(function(){ alert('网络错误'); });
            }
            function closeForm() {
                document.getElementById('formModal').classList.remove('active');
            }
            function saveForm() {
                var name = document.getElementById('fName').value.trim();
                var tagline = document.getElementById('fTagline').value.trim();
                if (!name) { alert('角色名不能为空'); return; }
                if (!tagline) { alert('一句话简介不能为空'); return; }

                var btn = document.getElementById('saveBtn');
                btn.disabled = true;
                btn.textContent = '保存中...';

                var id = document.getElementById('fId').value;
                var fd = new FormData();
                fd.append('id', id);
                fd.append('name', name);
                fd.append('age', document.getElementById('fAge').value.trim());
                fd.append('tagline', tagline);
                fd.append('description', document.getElementById('fDesc').value.trim());
                fd.append('tags', document.getElementById('fTags').value.trim());
                fd.append('link_url', document.getElementById('fLink').value.trim());
                fd.append('weigh', document.getElementById('fWeigh').value || 0);
                fd.append('is_adult', document.getElementById('fAdult').value);
                fd.append('status', document.getElementById('fStatus').value);
                fd.append('cover_url', document.getElementById('fCover').value);
                var fileInput = document.getElementById('coverFile');
                if (fileInput.files && fileInput.files[0]) {
                    fd.append('cover_file', fileInput.files[0]);
                }
                var url = id ? '/admin/live.HomeCharacter/edit' : '/admin/live.HomeCharacter/add';
                fetch(url, { method: 'POST', body: fd })
                    .then(function(r){ return r.json() })
                    .then(function(d){
                        btn.disabled = false;
                        btn.textContent = '保存';
                        if (d.code === 1) { alert(d.msg || '保存成功'); location.reload(); }
                        else { alert(d.msg || '保存失败'); }
                    })
                    .catch(function(){ btn.disabled = false; btn.textContent = '保存'; alert('网络错误'); });
            }
            function doDelete(id) {
                if (!confirm('确定删除该推荐角色？')) return;
                var body = new URLSearchParams();
                body.append('id', id);
                fetch('/admin/live.HomeCharacter/delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                }).then(function(r){ return r.json() }).then(function(d){
                    if (d.code === 1) { alert('删除成功'); location.reload(); }
                    else { alert(d.msg || '删除失败'); }
                }).catch(function(){ alert('网络错误'); });
            }
            document.getElementById('formModal').addEventListener('click', function(e) {
                if (e.target === this) closeForm();
            });
            </script>
        </body>
        </html>
        HTML;
    }
}
