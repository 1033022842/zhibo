<?php
/**
 * 创作审核 - 独立页面（无需前端编译）
 * 访问: /admin/live.Creator/index
 */

declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\admin\model\live\CreatorSubmission as CreatorModel;

final class Creator extends Backend
{
    protected object $model;

    protected array $noNeedLogin = ['index', 'approve', 'reject', 'detailJson'];

    // 飘页访问守卫：URL ?_key= 首次校验后走 Cookie（密钥 .env ADMIN_FLOAT_KEY）
    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    private const STATUS_MAP = [0 => '待审核', 1 => '已上线', 2 => '已驳回'];

    private const PUBLISH_MAP = ['live' => '直播角色', 'chat' => '普通对话角色', 'drama' => '短剧'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new CreatorModel();
    }

    /**
     * 创作提交列表页
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
                $q->where('c.persona_name', 'like', "%{$keyword}%")
                  ->whereOr('u.nickname', 'like', "%{$keyword}%");
            });
        }
        if ($status !== '') {
            $query->where('c.status', (int)$status);
        }

        $list = $query->order('c.id', 'desc')->paginate(15);

        $stats = [
            'total'    => (int)$this->model->count(),
            'pending'  => (int)$this->model->where('status', 0)->count(),
            'online'   => (int)$this->model->where('status', 1)->count(),
            'rejected' => (int)$this->model->where('status', 2)->count(),
        ];

        $html = $this->renderPage($list, $stats);
        response($html)->send();
        exit;
    }

    /**
     * 审核通过 (AJAX)
     */
    public function approve(): void
    {
        $id = $this->request->param('id/d', 0);
        $item = $this->model->find($id);
        if (!$item) { $this->error('记录不存在'); return; }
        if ((int)$item->status !== 0) { $this->error('不在待审核状态'); return; }

        $item->status      = 1;
        $item->reviewed_at = date('Y-m-d H:i:s');
        $item->updated_at  = date('Y-m-d H:i:s');
        $item->save();
        $this->success('审核通过');
    }

    /**
     * 审核驳回 (AJAX)
     */
    public function reject(): void
    {
        $id = $this->request->param('id/d', 0);
        $reason = trim((string)$this->request->param('reason', ''));
        if ($reason === '') { $this->error('请填写驳回原因'); return; }

        $item = $this->model->find($id);
        if (!$item) { $this->error('记录不存在'); return; }
        if ((int)$item->status !== 0) { $this->error('不在待审核状态'); return; }

        $item->status        = 2;
        $item->reject_reason = $reason;
        $item->reviewed_at   = date('Y-m-d H:i:s');
        $item->updated_at    = date('Y-m-d H:i:s');
        $item->save();
        $this->success('已驳回');
    }

    /**
     * 创作详情 JSON（给弹窗用）
     */
    public function detailJson(): void
    {
        $id = $this->request->param('id/d', 0);
        $item = $this->model->find($id);
        if (!$item) {
            $this->error('记录不存在');
            return;
        }

        $user = \think\facade\Db::connect('live_mysql')
            ->table('lp_user')
            ->where('id', (int)$item->user_id)
            ->field('nickname, user_no')
            ->find();

        $materials = json_decode((string)$item->materials, true);
        if (!is_array($materials)) {
            $materials = [];
        }

        $this->success('', [
            'id'             => (int)$item->id,
            'user_id'        => (int)$item->user_id,
            'nickname'       => $user['nickname'] ?? '',
            'user_no'        => $user['user_no'] ?? '',
            'persona_name'   => (string)$item->persona_name,
            'description'    => (string)$item->description,
            'tags'           => self::splitList((string)$item->tags),
            'style'          => (string)$item->style,
            'gender'         => (string)$item->gender,
            'age_range'      => (string)$item->age_range,
            'language'       => (string)$item->language,
            'personality'    => self::splitList((string)$item->personality),
            'voice_style'    => (string)$item->voice_style,
            'publish_type'   => (string)$item->publish_type,
            'publish_text'   => self::PUBLISH_MAP[$item->publish_type] ?? (string)$item->publish_type,
            'is_adult'       => (int)$item->is_adult,
            'highlights'     => (string)$item->highlights,
            'reference_url'  => (string)$item->reference_url,
            'cover_url'      => (string)$item->cover_url,
            'materials'      => $materials,
            'status'         => (int)$item->status,
            'status_text'    => self::STATUS_MAP[$item->status] ?? '未知',
            'reject_reason'  => (string)$item->reject_reason,
            'created_at'     => (string)$item->created_at,
            'reviewed_at'    => (string)$item->reviewed_at,
        ]);
    }

    /**
     * 逗号分隔字符串 -> 数组
     */
    private static function splitList(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn($s) => $s !== ''));
    }

    private function renderPage($list, array $stats): string
    {
        $statusMap  = self::STATUS_MAP;
        $publishMap = self::PUBLISH_MAP;

        $rows = '';
        foreach ($list->items() as $row) {
            $st     = $statusMap[$row['status']] ?? '未知';
            $stNum  = (int)$row['status'];
            $name   = htmlspecialchars($row['nickname'] ?: '未命名');
            $no     = htmlspecialchars((string)($row['user_no'] ?? ''));
            $pname  = htmlspecialchars((string)$row['persona_name']);
            $ptype  = htmlspecialchars($publishMap[$row['publish_type']] ?? (string)$row['publish_type']);
            $adult  = (int)$row['is_adult'] === 1 ? '<span class="adult-chip">18+</span>' : '<span class="na">否</span>';
            $time   = $row['created_at'] ? substr((string)$row['created_at'], 0, 16) : '-';
            $cover  = $row['cover_url']
                ? '<div class="thumb"><img src="' . htmlspecialchars((string)$row['cover_url']) . '" alt="" onerror="this.style.display=\'none\'"></div>'
                : '<div class="thumb thumb-empty"></div>';

            $actions = '';
            if ($stNum === 0) {
                $actions = <<<ACT
                <button class="op-btn op-ok" onclick="doApprove({$row['id']})">通过</button>
                <button class="op-btn op-no" onclick="showReject({$row['id']})">驳回</button>
                ACT;
            }

            $rows .= <<<ROW
            <tr>
                <td>
                    <div class="proj-cell">
                        {$cover}
                        <div class="proj-info"><div class="proj-title">{$pname}</div></div>
                    </div>
                </td>
                <td>
                    <div class="owner-name">{$name}</div>
                    <div class="owner-no">#{$no}</div>
                </td>
                <td>{$ptype}</td>
                <td class="td-center">{$adult}</td>
                <td><span class="badge badge-{$stNum}">{$st}</span></td>
                <td>{$time}</td>
                <td>
                    <div class="op-group">
                        <button class="op-btn" onclick="showDetail({$row['id']})">查看</button>
                        {$actions}
                    </div>
                </td>
            </tr>
            ROW;
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7" class="empty-tip">暂无创作提交</td></tr>';
        }

        $pager = $list->render();

        $keywordVal = htmlspecialchars((string)$this->request->param('search', ''));
        $statusVal  = $this->request->param('status', '');
        $statusAll  = $statusVal === '' ? 'selected' : '';
        $status0    = (string)$statusVal === '0' ? 'selected' : '';
        $status1    = (string)$statusVal === '1' ? 'selected' : '';
        $status2    = (string)$statusVal === '2' ? 'selected' : '';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>创作审核</title>
            <style>
                *{margin:0;padding:0;box-sizing:border-box}
                body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;background:#f4f5fb;color:#1f2430;font-size:14px}
                .container{max-width:1320px;margin:0 auto;padding:28px 24px 60px}
                .page-head{margin-bottom:22px}
                .page-head h1{font-size:24px;font-weight:700;color:#1a1f36}
                .page-head .sub{margin-top:6px;font-size:13px;color:#8a90a3}
                .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:22px}
                .stat-card{background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:18px;box-shadow:0 1px 3px rgba(30,34,60,.04)}
                .stat-val{font-size:22px;font-weight:700;color:#1a1f36}
                .stat-label{font-size:12px;color:#8a90a3;margin-top:3px}
                .toolbar{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;align-items:center}
                .toolbar input{width:320px;padding:10px 14px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;background:#fff;color:#1f2430;outline:none}
                .toolbar input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.12)}
                .toolbar select{padding:10px 14px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;background:#fff;color:#1f2430;outline:none;cursor:pointer}
                .btn{border:none;border-radius:10px;cursor:pointer;font-size:14px;font-weight:600;padding:10px 20px}
                .btn-primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff}
                .btn-ghost{background:#fff;color:#5a6172;border:1px solid #e2e5ee;text-decoration:none;display:inline-flex;align-items:center}
                .table-card{background:#fff;border:1px solid #eef0f6;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(30,34,60,.04)}
                .table-wrap{overflow-x:auto}
                table{width:100%;border-collapse:collapse;min-width:900px}
                th{background:#fafbfe;font-weight:600;font-size:12px;color:#8a90a3;text-transform:uppercase;letter-spacing:.03em;text-align:left;padding:14px 16px;border-bottom:1px solid #eef0f6;white-space:nowrap}
                td{padding:14px 16px;border-bottom:1px solid #f2f3f8;font-size:13px;color:#3a4051;white-space:nowrap;vertical-align:middle}
                tbody tr:hover{background:#fafbfe}
                tbody tr:last-child td{border-bottom:none}
                .td-center{text-align:center}
                .proj-cell{display:flex;align-items:center;gap:12px}
                .thumb{width:44px;height:44px;border-radius:10px;overflow:hidden;background:#eef0f6;flex-shrink:0}
                .thumb img{width:100%;height:100%;object-fit:cover;display:block}
                .thumb-empty{background:linear-gradient(135deg,#eef0ff,#f4ecfe)}
                .proj-title{font-weight:600;color:#1a1f36;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
                .owner-name{font-weight:500;color:#3a4051}
                .owner-no{font-size:12px;color:#9aa0b0;margin-top:2px}
                .badge{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:999px;font-size:12px;font-weight:600}
                .badge::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor}
                .badge-0{background:#fdf6ec;color:#b45309}
                .badge-1{background:#e6f7f1;color:#059669}
                .badge-2{background:#fdeef2;color:#e11d48}
                .adult-chip{padding:2px 9px;border-radius:999px;background:#fdeef2;color:#e11d48;font-size:11px;font-weight:700}
                .na{color:#c0c4d0}
                .op-group{display:flex;gap:6px}
                .op-btn{display:inline-flex;align-items:center;padding:6px 12px;border:1px solid #e2e5ee;border-radius:8px;background:#fff;color:#5a6172;font-size:12px;font-weight:500;cursor:pointer}
                .op-btn:hover{background:#f7f8fc}
                .op-ok{color:#059669;border-color:#bfe6d6}
                .op-ok:hover{background:#e6f7f1}
                .op-no{color:#e11d48;border-color:#f5c2c2}
                .op-no:hover{background:#fef2f2}
                .empty-tip{text-align:center;color:#9aa0b0;padding:40px 0}
                .page-bar{text-align:center;margin-top:22px}
                .page-bar ul{display:inline-flex;gap:6px;list-style:none}
                .page-bar li a,.page-bar li span{display:inline-block;padding:8px 13px;border:1px solid #e2e5ee;border-radius:8px;color:#5a6172;text-decoration:none;font-size:13px;background:#fff}
                .page-bar .active span{background:#6366f1;color:#fff;border-color:#6366f1}
                .modal-overlay{display:none;position:fixed;inset:0;background:rgba(20,22,40,.5);backdrop-filter:blur(3px);z-index:999;align-items:center;justify-content:center;padding:20px}
                .modal-overlay.active{display:flex}
                .modal{background:#fff;border-radius:16px;width:720px;max-width:100%;max-height:88vh;overflow-y:auto;box-shadow:0 24px 60px rgba(20,22,40,.25)}
                .modal-head{position:sticky;top:0;background:#fff;padding:20px 24px;border-bottom:1px solid #eef0f6;display:flex;align-items:center;justify-content:space-between;z-index:2}
                .modal-head h3{font-size:17px;font-weight:700;color:#1a1f36}
                .modal-close{width:32px;height:32px;border:none;border-radius:8px;background:#f2f3f8;color:#5a6172;cursor:pointer}
                .modal-body{padding:24px}
                .detail-top{display:flex;gap:20px;margin-bottom:20px}
                .detail-cover{width:160px;height:160px;border-radius:14px;overflow:hidden;background:linear-gradient(135deg,#eef0ff,#f4ecfe);flex-shrink:0;display:flex;align-items:center;justify-content:center;color:#b8bdd0;font-size:12px}
                .detail-cover img{width:100%;height:100%;object-fit:cover;display:block}
                .detail-title{font-size:19px;font-weight:700;color:#1a1f36}
                .detail-owner{margin-top:12px;font-size:13px;color:#5a6172}
                .detail-owner b{color:#1a1f36}
                .kv-list{display:grid;grid-template-columns:repeat(2,1fr);gap:10px 18px;margin:18px 0}
                .kv-item{font-size:13px;color:#3a4051;line-height:1.6}
                .kv-item .k{color:#8a90a3;margin-right:6px}
                .tag-chip{display:inline-block;padding:3px 10px;border-radius:999px;background:#f4ecfe;border:1px solid #e4d4fb;color:#7c3aed;font-size:12px;margin:0 4px 4px 0}
                .sec-title{font-size:13px;font-weight:700;color:#1a1f36;margin:16px 0 8px}
                .detail-desc{background:#fafbfe;border:1px solid #eef0f6;border-radius:12px;padding:14px 16px;font-size:13px;color:#4a5062;line-height:1.8;white-space:pre-wrap;word-break:break-word}
                .mat-list{display:flex;flex-wrap:wrap;gap:10px}
                .mat-item{display:flex;flex-direction:column;gap:6px;width:150px;border:1px solid #eef0f6;border-radius:10px;padding:8px;text-decoration:none;color:#3a4051;font-size:12px}
                .mat-item img{width:100%;height:82px;object-fit:cover;border-radius:6px;background:#f2f3f8}
                .mat-item .nm{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
                .modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:24px;padding-top:16px;border-top:1px solid #eef0f6}
                .btn-sm{padding:9px 18px;font-size:13px}
                .btn-danger{background:#e11d48;color:#fff}
                .btn-success{background:#059669;color:#fff}
                .reject-box textarea{width:100%;min-height:90px;padding:10px 14px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;font-family:inherit;resize:vertical;outline:none;box-sizing:border-box}
                @media (max-width:900px){ .stats-grid{grid-template-columns:repeat(2,1fr)} .detail-top{flex-direction:column} .kv-list{grid-template-columns:1fr} }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="page-head">
                    <h1>创作审核</h1>
                    <p class="sub">审核用户独立完成的角色创作 · 通过后角色上线并可产生收益（分成与直播一致，当前 5:5）</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-val">{$stats['total']}</div><div class="stat-label">全部提交</div></div>
                    <div class="stat-card"><div class="stat-val">{$stats['pending']}</div><div class="stat-label">待审核</div></div>
                    <div class="stat-card"><div class="stat-val">{$stats['online']}</div><div class="stat-label">已上线</div></div>
                    <div class="stat-card"><div class="stat-val">{$stats['rejected']}</div><div class="stat-label">已驳回</div></div>
                </div>

                <form class="toolbar" method="get">
                    <input name="search" placeholder="搜索角色名 / 提交用户" value="{$keywordVal}">
                    <select name="status">
                        <option value="" {$statusAll}>全部状态</option>
                        <option value="0" {$status0}>待审核</option>
                        <option value="1" {$status1}>已上线</option>
                        <option value="2" {$status2}>已驳回</option>
                    </select>
                    <button class="btn btn-primary" type="submit">查询</button>
                    <a class="btn btn-ghost" href="/admin/live.Creator/index">重置</a>
                </form>

                <div class="table-card">
                    <div class="table-wrap">
                        <table>
                            <thead><tr>
                                <th>角色</th><th>提交用户</th><th>发布类型</th><th>18+</th><th>状态</th><th>提交时间</th><th>操作</th>
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

            <script>
            var PUBLISH_TEXT = {live:'直播角色',chat:'普通对话角色',drama:'短剧'};
            var STATUS_TEXT = {0:'待审核',1:'已上线',2:'已驳回'};
            var STYLE_TEXT = {realistic:'写实',anime:'二次元','3d':'3D',cyberpunk:'赛博朋克',chinese:'古风',korean:'韩系',western:'欧美'};
            var GENDER_TEXT = {female:'女性',male:'男性',other:'其他'};
            var LANG_TEXT = {'zh-CN':'中文','en-US':'英文','ja-JP':'日文','ms-MY':'马来语',multi:'多语言'};
            var VOICE_TEXT = {sweet:'甜美',mature:'御姐',magnetic:'磁性',loli:'萝莉',cold:'冷艳',gentle:'温柔',none:'不涉及语音'};
            function esc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
            function lbl(map,v){return v ? (map[v]||v) : '';}
            function kv(k,v){return '<div class="kv-item"><span class="k">'+k+'：</span>'+v+'</div>';}

            function showDetail(id){
                document.getElementById('detailModal').classList.add('active');
                document.getElementById('detailBox').innerHTML = '<div class="modal-head"><h3>创作详情</h3><button class="modal-close" onclick="closeDetail()">✕</button></div><div class="modal-body"><div class="empty-tip">加载中...</div></div>';
                fetch('/admin/live.Creator/detailJson?id=' + id).then(function(r){return r.json()}).then(function(d){
                    if (d.code !== 1) {
                        document.getElementById('detailBox').innerHTML = '<div class="modal-head"><h3>创作详情</h3><button class="modal-close" onclick="closeDetail()">✕</button></div><div class="modal-body"><div class="empty-tip">'+esc(d.msg||'加载失败')+'</div></div>';
                        return;
                    }
                    var p = d.data;
                    var cover = p.cover_url ? '<div class="detail-cover"><img src="'+esc(p.cover_url)+'" onerror="this.style.display=\'none\'"></div>' : '<div class="detail-cover">无封面</div>';
                    var tags = p.tags || [];
                    var mats = p.materials || [];
                    var info = '';
                    if (tags.length) info += kv('标签', tags.map(function(t){return '<span class="tag-chip">'+esc(t)+'</span>';}).join(''));
                    if (p.style) info += kv('风格', esc(lbl(STYLE_TEXT,p.style)));
                    if (p.gender) info += kv('性别', esc(lbl(GENDER_TEXT,p.gender)));
                    if (p.age_range) info += kv('年龄段', esc(p.age_range));
                    if (p.language) info += kv('语言', esc(lbl(LANG_TEXT,p.language)));
                    var pers = p.personality || [];
                    if (pers.length) info += kv('性格特点', esc(pers.join('、')));
                    if (p.voice_style) info += kv('语音风格', esc(lbl(VOICE_TEXT,p.voice_style)));
                    info += kv('发布类型', esc(p.publish_text || ''));
                    info += kv('18+ 内容', p.is_adult ? '<span class="adult-chip">18+</span>' : '否');
                    if (p.reference_url) info += kv('参考链接', '<a href="'+esc(p.reference_url)+'" target="_blank" style="color:#6366f1">'+esc(p.reference_url)+'</a>');
                    if (p.reject_reason) info += kv('驳回原因', '<span style="color:#e11d48">'+esc(p.reject_reason)+'</span>');

                    var matHtml = '';
                    if (mats.length) {
                        for (var i=0;i<mats.length;i++){
                            var m = mats[i] || {};
                            var isImg = /\.(png|jpe?g|gif|webp)$/i.test(m.url||'');
                            matHtml += '<a class="mat-item" href="'+esc(m.url)+'" target="_blank">' +
                                (isImg ? '<img src="'+esc(m.url)+'" onerror="this.style.visibility=\'hidden\'">' : '<div style="height:82px;display:flex;align-items:center;justify-content:center;background:#f2f3f8;border-radius:6px">附件</div>') +
                                '<span class="nm">'+esc(m.name || m.url || '')+'</span></a>';
                        }
                    } else {
                        matHtml = '<div class="empty-tip">无资料</div>';
                    }

                    var footer = '<button class="btn btn-ghost" onclick="closeDetail()">关闭</button>';
                    if (p.status === 0) {
                        footer += '<button class="btn btn-danger btn-sm" onclick="showReject('+p.id+')">驳回</button>';
                        footer += '<button class="btn btn-success btn-sm" onclick="doApprove('+p.id+',1)">通过</button>';
                    }

                    var html =
                        '<div class="modal-head"><h3>创作详情</h3><button class="modal-close" onclick="closeDetail()">✕</button></div>' +
                        '<div class="modal-body">' +
                            '<div class="detail-top">' + cover +
                                '<div><div class="detail-title">'+esc(p.persona_name)+'</div>' +
                                '<div class="detail-owner">提交用户：<b>'+esc(p.nickname)+'</b>（#'+esc(p.user_no)+'）</div>' +
                                '<div class="detail-owner">提交时间：'+esc(p.created_at||'-')+(p.reviewed_at?'<br>审核时间：'+esc(p.reviewed_at):'')+'</div>' +
                                '<div class="detail-owner" style="margin-top:8px"><span class="badge badge-'+p.status+'">'+esc(p.status_text||'')+'</span></div></div>' +
                            '</div>' +
                            (info ? '<div class="kv-list">'+info+'</div>' : '') +
                            (p.highlights ? '<div class="sec-title">项目亮点</div><div class="detail-desc">'+esc(p.highlights)+'</div>' : '') +
                            '<div class="sec-title">角色描述</div><div class="detail-desc">'+(p.description?esc(p.description):'暂无描述')+'</div>' +
                            '<div class="sec-title">角色资料（'+mats.length+'）</div><div class="mat-list">'+matHtml+'</div>' +
                            '<div class="modal-footer">'+footer+'</div>' +
                        '</div>';
                    document.getElementById('detailBox').innerHTML = html;
                }).catch(function(){
                    document.getElementById('detailBox').innerHTML = '<div class="modal-head"><h3>创作详情</h3><button class="modal-close" onclick="closeDetail()">✕</button></div><div class="modal-body"><div class="empty-tip">网络错误</div></div>';
                });
            }
            function closeDetail(){ document.getElementById('detailModal').classList.remove('active'); }

            function doApprove(id, closeAfter){
                if (!confirm('确定通过该创作？通过后角色上线。')) return;
                fetch('/admin/live.Creator/approve?id=' + id).then(function(r){return r.json()}).then(function(d){
                    if (d.code === 1) { alert('审核通过'); location.reload(); }
                    else { alert(d.msg || '操作失败'); }
                }).catch(function(){ alert('网络错误'); });
            }

            function showReject(id){
                var reason = prompt('请输入驳回原因：', '');
                if (reason === null) return;
                reason = reason.trim();
                if (!reason) { alert('请填写驳回原因'); return; }
                fetch('/admin/live.Creator/reject?id=' + id + '&reason=' + encodeURIComponent(reason)).then(function(r){return r.json()}).then(function(d){
                    if (d.code === 1) { alert('已驳回'); location.reload(); }
                    else { alert(d.msg || '操作失败'); }
                }).catch(function(){ alert('网络错误'); });
            }

            document.getElementById('detailModal').addEventListener('click', function(e){ if (e.target === this) closeDetail(); });
            </script>
        </body>
        </html>
        HTML;
    }
}
