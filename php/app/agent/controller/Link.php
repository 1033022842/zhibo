<?php
declare(strict_types=1);

namespace app\agent\controller;

use app\agent\service\AgentView;
use app\live\service\AgentService;
use app\live\service\FbCapiService;
use app\live\service\PromoService;
use think\facade\Db;

/**
 * 代理推广链接（只能看/建自己名下的链接）
 */
final class Link extends Base
{
    public function index(): void
    {
        $this->requirePerm('link');

        if (!AgentService::ready()) {
            $this->render('link', '推广链接', '<div class="card"><h2>推广链接</h2><div class="tip">代理系统尚未初始化，请联系总部管理员执行升级脚本。</div></div>');
        }

        $agent   = $this->agent();
        $agentId = $this->agentId();
        $db      = Db::connect('live_mysql');

        $domains = AgentService::effectiveDomains($agentId, true);
        $pixels  = AgentService::effectivePixels($agentId, true);
        $canNew  = AgentService::hasPerm($agent, 'link_create');

        // 编辑中的链接（只能编辑自己的）
        $editId = (int) ($this->request->param('id') ?? 0);
        $edit   = ['id' => 0, 'name' => '', 'domain' => '', 'pixel_id' => 0, 'channel' => '', 'remark' => ''];
        if ($editId > 0) {
            $row = $db->table('lp_promo_link')->where('id', $editId)->where('agent_id', $agentId)->find();
            if ($row) {
                $edit = array_merge($edit, $row);
            }
        }
        $eId = $canNew ? (int) $edit['id'] : 0;

        $links = $db->table('lp_promo_link')->where('agent_id', $agentId)
            ->order('id', 'desc')->limit(200)->select()->toArray();

        $regMap = [];
        foreach ($db->query(
            'SELECT reg_link_name AS k, COUNT(*) AS c FROM lp_user
             WHERE reg_agent_id = ? AND reg_link_name <> \'\' GROUP BY reg_link_name',
            [$agentId]
        ) as $r) {
            $regMap[(string) $r['k']] = (int) $r['c'];
        }
        $payMap = [];
        foreach ($db->query(
            "SELECT u.reg_link_name AS k, COUNT(DISTINCT o.user_id) AS b, IFNULL(SUM(o.pay_amount),0) AS amt
             FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
             WHERE o.status = 1 AND o.biz_type <> 'vip' AND u.reg_agent_id = ? AND u.reg_link_name <> ''
             GROUP BY u.reg_link_name",
            [$agentId]
        ) as $r) {
            $payMap[(string) $r['k']] = ['b' => (int) $r['b'], 'amt' => (float) $r['amt']];
        }
        $subMap = [];
        foreach ($db->query(
            "SELECT u.reg_link_name AS k, COUNT(DISTINCT o.user_id) AS b, IFNULL(SUM(o.pay_amount),0) AS amt
             FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
             WHERE o.status = 1 AND o.biz_type = 'vip' AND u.reg_agent_id = ? AND u.reg_link_name <> ''
             GROUP BY u.reg_link_name",
            [$agentId]
        ) as $r) {
            $subMap[(string) $r['k']] = ['b' => (int) $r['b'], 'amt' => (float) $r['amt']];
        }

        $pixelLabel = [];
        foreach ($pixels as $p) {
            $pid = (int) $p['id'];
            $pf  = PromoService::PIXEL_PLATFORMS[(string) $p['platform']] ?? (string) $p['platform'];
            $pn  = trim((string) $p['name']);
            $pixelLabel[$pid] = $pf . ($pn !== '' ? ' · ' . $pn : '');
        }

        $rows = '';
        foreach ($links as $l) {
            $id     = (int) $l['id'];
            $name   = (string) $l['name'];
            $domain = (string) $l['domain'];
            $ch     = (string) $l['channel'];
            $px     = (int) $l['pixel_id'];
            $status = (int) $l['status'];
            $remark = trim((string) $l['remark']);

            $reg = $regMap[$name] ?? 0;
            $pay = $payMap[$name] ?? ['b' => 0, 'amt' => 0.0];
            $sub = $subMap[$name] ?? ['b' => 0, 'amt' => 0.0];

            $url = $domain !== '' ? AgentService::buildLink($domain, $id, $px) : '';
            $linkCell = $url === ''
                ? '<span class="muted">未设置域名</span>'
                : '<div class="lk"><code>' . AgentView::h($url) . '</code>'
                    . '<button type="button" class="btn-copy" data-link="' . AgentView::h($url) . '">复制</button></div>';

            $ops = '';
            if ($canNew) {
                $ops .= '<a href="/agent/link/index?id=' . $id . '">编辑</a>';
            }
            $ops .= $px > 0
                ? '<a href="javascript:void(0)" onclick="pxTest(' . $id . ')">测试回调</a>'
                : '<span class="muted" style="font-size:12px">未绑定像素</span>';
            if ($canNew) {
                $ops .= '<a href="/agent/link/toggle?id=' . $id . '&_csrf=' . urlencode($this->csrf()) . '">' . ($status === 1 ? '停用' : '启用') . '</a>'
                    . '<a class="danger" href="/agent/link/del?id=' . $id . '&_csrf=' . urlencode($this->csrf()) . '" onclick="return confirm(\'确认删除该链接？\')">删除</a>';
            }

            $rows .= '<tr>'
                . '<td><b>' . AgentView::h($name) . '</b>' . ($remark !== '' ? '<div class="muted" style="font-size:11.5px;margin-top:3px">' . AgentView::h($remark) . '</div>' : '') . '</td>'
                . '<td style="max-width:360px">' . $linkCell . '</td>'
                . '<td>' . AgentView::h($pixelLabel[$px] ?? '不绑定') . '</td>'
                . '<td>' . ($ch !== '' ? AgentView::h($ch) : '<span class="muted">—</span>') . '</td>'
                . '<td class="num">' . $reg . '</td>'
                . '<td class="num">' . (int) $pay['b'] . ' / ' . number_format((float) $pay['amt'], 2) . '</td>'
                . '<td class="num">' . (int) $sub['b'] . ' / ' . number_format((float) $sub['amt'], 2) . '</td>'
                . '<td>' . ($status === 1 ? '<span class="chip on">启用</span>' : '<span class="chip off">停用</span>') . '</td>'
                . '<td class="ops">' . $ops . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="9" class="empty">还没有推广链接' . ($canNew ? '，在下方新建一条' : '，请联系总部管理员') . '</td></tr>';
        }

        $form = '';
        if ($canNew) {
            $domOptions = '';
            $eDomain    = (string) $edit['domain'];
            foreach ($domains as $d) {
                $dv = (string) $d['domain'];
                $sel = $dv === $eDomain ? ' selected' : '';
                $domOptions .= '<option value="' . AgentView::h($dv) . '"' . $sel . '>' . AgentView::h($dv) . '</option>';
            }
            if ($domOptions === '') {
                $domOptions = '<option value="">（暂无可用域名）</option>';
            }
            $ePixel    = (int) $edit['pixel_id'];
            $pxOptions = '<option value="0"' . ($ePixel === 0 ? ' selected' : '') . '>不绑定渠道</option>';
            foreach ($pixels as $p) {
                $pid = (int) $p['id'];
                $sel = $pid === $ePixel ? ' selected' : '';
                $pxOptions .= '<option value="' . $pid . '"' . $sel . '>' . AgentView::h($pixelLabel[$pid] ?? ('渠道#' . $pid)) . '</option>';
            }
            if ($ePixel > 0 && !isset($pixelLabel[$ePixel])) {
                $pxOptions .= '<option value="' . $ePixel . '" selected>（已停用/不存在的像素 #' . $ePixel . '）</option>';
            }

            $nameField = $eId > 0
                ? '<div class="fld"><label>链接名称（创建后不可改名，否则历史归因会断开）</label>'
                    . '<input type="text" value="' . AgentView::h((string) $edit['name']) . '" readonly></div>'
                : '<div class="fld"><label>链接别名（必填，会加上你的代理编号前缀）</label>'
                    . '<input type="text" name="slug" maxlength="80" placeholder="如 fb-素材A" required></div>';

            $formTitle = $eId > 0 ? '编辑推广链接 #' . $eId : '新建推广链接';
            $cancelBtn = $eId > 0 ? '<a class="btn-line" href="/agent/link/index">取消编辑</a>' : '';

            $form = '<div class="card"><h2>' . $formTitle . '</h2>'
                . '<form class="frm" method="post" action="/agent/link/save">'
                . '<input type="hidden" name="_csrf" value="' . AgentView::h($this->csrf()) . '">'
                . '<input type="hidden" name="id" value="' . $eId . '">'
                . $nameField
                . '<div class="fld"><label>推广域名</label><select name="domain" required>' . $domOptions . '</select></div>'
                . '<div class="fld"><label>绑定像素（可选）</label><select name="pixel_id">' . $pxOptions . '</select></div>'
                . '<div class="fld"><label>渠道参数 ch（可选）</label><input type="text" name="channel" maxlength="64" value="' . AgentView::h((string) $edit['channel']) . '" placeholder="如 fb1"></div>'
                . '<div class="fld"><label>备注（可选）</label><input type="text" name="remark" maxlength="255" value="' . AgentView::h((string) $edit['remark']) . '" placeholder="如 投美国区"></div>'
                . '<div class="actions"><button class="btn" type="submit">' . ($eId > 0 ? '保存修改' : '创建链接') . '</button>' . $cancelBtn . '</div>'
                . '</form>'
                . '<div class="tip">链接名称会自动生成「' . AgentView::h((string) $agent['agent_no']) . '-别名」并保证全局唯一；统计按链接名称归因，改动链接名称会断开历史归属，因此创建后不支持改名。<br>'
                . ($pixels === [] ? '总部暂未给你分配像素渠道，如需绑定像素请联系总部在「推广与像素」里分配。' : '绑定像素后，短链接会带 <b>?px=像素ID</b>，从该链接进来的访客只打这一条像素；保存后可在列表点「测试回调」验证像素配置。')
                . '</div></div>';
        }

        $csrfJs = json_encode($this->csrf(), JSON_UNESCAPED_SLASHES);

        $content = <<<HTML
<div class="head">
  <div>
    <h1>推广链接</h1>
    <div class="sub">你可以在这里创建并复制自己的推广链接。访客通过链接注册后，会归入你的代理数据。</div>
  </div>
</div>
<div class="card">
  <h2>链接列表</h2>
  <div class="table-wrap"><table><thead><tr>
    <th>链接名称</th><th>推广链接</th><th>像素</th><th>ch</th>
    <th class="num">注册数</th><th class="num">充值人数/金额</th><th class="num">订阅人数/金额</th><th>状态</th><th>操作</th>
  </tr></thead><tbody>{$rows}</tbody></table></div>
</div>
{$form}
<div class="mask" id="pxTestMask" onclick="if(event.target===this){closePxTest()}">
  <div class="panel">
    <div class="panel-head"><b id="pxTestTitle">测试回调</b><button type="button" onclick="closePxTest()">×</button></div>
    <pre id="pxTestBody"></pre>
    <div class="panel-foot"><button type="button" class="btn-line" onclick="closePxTest()">关闭</button></div>
  </div>
</div>
<script>
var AGENT_CSRF = {$csrfJs};
function closePxTest() {
  document.getElementById('pxTestMask').classList.remove('open');
}
function pxTest(id) {
  var mask = document.getElementById('pxTestMask');
  var title = document.getElementById('pxTestTitle');
  var body = document.getElementById('pxTestBody');
  title.textContent = '测试回调 · 链接 #' + id;
  body.textContent = '正在向像素发送一条测试事件，请稍候…';
  mask.classList.add('open');
  fetch('/agent/link/test?id=' + id + '&_csrf=' + encodeURIComponent(AGENT_CSRF), {
    headers: { 'Accept': 'application/json' }
  }).then(function (r) { return r.json(); }).then(function (d) {
    title.textContent = '测试回调 · 链接 #' + id + (d.ok ? ' · 成功' : ' · 失败');
    var out = 'HTTP ' + (d.http || 0) + '\n\n' + (d.resp || '');
    if (d.diag) { out += '\n\n' + d.diag; }
    body.textContent = out;
  }).catch(function (e) {
    body.textContent = '请求失败：' + e;
  });
}
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') { closePxTest(); }
});
</script>
HTML;

        $this->render('link', '推广链接', $content);
    }

    public function save(): void
    {
        $this->requirePerm('link');
        $this->requirePerm('link_create');
        $this->verifyCsrf();

        $agentId = $this->agentId();
        $agent   = $this->agent();
        $db      = Db::connect('live_mysql');

        $id      = (int) ($this->request->post('id') ?? 0);
        $slug    = trim((string) ($this->request->post('slug') ?? ''));
        $domain  = PromoService::normalizeDomain((string) ($this->request->post('domain') ?? ''));
        $pixelId = (int) ($this->request->post('pixel_id') ?? 0);
        $channel = mb_substr(trim((string) ($this->request->post('channel') ?? '')), 0, 64);
        $remark  = mb_substr(trim((string) ($this->request->post('remark') ?? '')), 0, 255);

        $assignedDomains = array_map(static fn(array $d): string => (string) $d['domain'], AgentService::effectiveDomains($agentId, true));
        if ($domain === '' || !in_array($domain, $assignedDomains, true)) {
            $this->flashError('请选择总部或上级分配给你的推广域名');
        }

        if ($pixelId > 0) {
            $allowed = array_map(static fn(array $p): int => (int) $p['id'], AgentService::effectivePixels($agentId, true));
            if (!in_array($pixelId, $allowed, true)) {
                $this->flashError('该渠道未分配给你，请重新选择');
            }
        }

        // 编辑：只允许改域名 / 像素 / 渠道 / 备注，链接名称保持不变
        if ($id > 0) {
            $row = $db->table('lp_promo_link')->where('id', $id)->where('agent_id', $agentId)->find();
            if (!$row) {
                $this->flashError('推广链接不存在或不属于你');
            }
            $db->table('lp_promo_link')->where('id', $id)->where('agent_id', $agentId)->update([
                'domain'     => $domain,
                'pixel_id'   => max(0, $pixelId),
                'channel'    => $channel,
                'remark'     => $remark,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->redirect('/agent/link/index?ok=2');
        }

        $slug = trim(preg_replace('/[?&#%\s]+/u', '-', $slug) ?? $slug, '-');
        if ($slug === '') {
            $this->flashError('请填写链接别名');
        }

        $name = AgentService::linkName((string) $agent['agent_no'], $slug);
        if ($db->table('lp_promo_link')->where('name', $name)->count() > 0) {
            $this->flashError('链接名称「' . $name . '」已存在，请换一个别名');
        }

        $now = date('Y-m-d H:i:s');
        $db->table('lp_promo_link')->insert([
            'name'       => $name,
            'domain'     => $domain,
            'pixel_id'   => max(0, $pixelId),
            'channel'    => $channel,
            'remark'     => $remark,
            'sort'       => 0,
            'status'     => 1,
            'agent_id'   => $agentId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->redirect('/agent/link/index?ok=1');
    }

    /**
     * 测试回调：用链接绑定的像素发一条测试事件，返回像素平台原始响应
     * 只允许测试「自己名下链接」绑定的「分配给自己的」Facebook 像素
     */
    public function test(): void
    {
        $this->requirePerm('link');
        $this->verifyCsrfParam();

        $id = (int) ($this->request->param('id') ?? 0);
        $db = Db::connect('live_mysql');

        $link = $id > 0
            ? $db->table('lp_promo_link')->where('id', $id)->where('agent_id', $this->agentId())->find()
            : null;
        if (!$link) {
            $this->jsonOut(['ok' => false, 'http' => 0, 'resp' => '推广链接不存在或不属于你']);
        }

        $pixelId = (int) $link['pixel_id'];
        if ($pixelId <= 0) {
            $this->jsonOut(['ok' => false, 'http' => 0, 'resp' => '该链接没有绑定像素，请先点「编辑」绑定一个像素渠道']);
        }

        $allowed = array_map(static fn(array $p): int => (int) $p['id'], AgentService::effectivePixels($this->agentId(), true));
        if (!in_array($pixelId, $allowed, true)) {
            $this->jsonOut(['ok' => false, 'http' => 0, 'resp' => '该像素未分配给你的代理账号，请联系总部']);
        }

        $pixel = $db->table('lp_promo_pixel')->where('id', $pixelId)->find();
        if (!$pixel) {
            $this->jsonOut(['ok' => false, 'http' => 0, 'resp' => '像素 #' . $pixelId . ' 不存在，请联系总部']);
        }
        if ((string) $pixel['platform'] !== 'facebook') {
            $this->jsonOut(['ok' => false, 'http' => 0, 'resp' => '该像素不是 Facebook 像素，暂不支持测试回调']);
        }

        $res = FbCapiService::probe($pixel);
        $this->jsonOut([
            'ok'   => (bool) ($res['ok'] ?? false),
            'http' => (int) ($res['http'] ?? 0),
            'resp' => (string) ($res['resp'] ?? ''),
        ]);
    }

    public function toggle(): void
    {
        $this->requirePerm('link');
        $this->requirePerm('link_create');
        $this->verifyCsrfParam();
        $db = Db::connect('live_mysql');
        $id = (int) ($this->request->param('id') ?? 0);
        $row = $id > 0 ? $db->table('lp_promo_link')
            ->where('id', $id)->where('agent_id', $this->agentId())->find() : null;
        if ($row) {
            $db->table('lp_promo_link')->where('id', $id)->update([
                'status'     => (int) $row['status'] === 1 ? 0 : 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->redirect('/agent/link/index');
    }

    public function del(): void
    {
        $this->requirePerm('link');
        $this->requirePerm('link_create');
        $this->verifyCsrfParam();

        $id = (int) ($this->request->param('id') ?? 0);
        if ($id > 0) {
            Db::connect('live_mysql')->table('lp_promo_link')
                ->where('id', $id)->where('agent_id', $this->agentId())->delete();
        }
        $this->redirect('/agent/link/index');
    }

    /** 链接类操作走 GET，CSRF 放在 query 里 */
    private function verifyCsrfParam(): void
    {
        $given = (string) ($this->request->param('_csrf') ?? '');
        if ($given === '' || !hash_equals($this->csrf(), $given)) {
            $this->deny('请求已失效，请刷新页面后重试。', 419);
        }
    }

    /** 输出 JSON（供页面内 fetch 使用） */
    private function jsonOut(array $data): void
    {
        response(json_encode($data, JSON_UNESCAPED_UNICODE), 200, ['Content-Type' => 'application/json; charset=utf-8'])->send();
        exit;
    }

    private function flashError(string $message): void
    {
        $content = '<div class="card"><h2>操作失败</h2><div class="tip" style="font-size:13px;color:#d61f4a">'
            . AgentView::h($message) . '</div><div class="actions">'
            . '<a class="btn-line" href="/agent/link/index">返回推广链接</a></div></div>';
        $this->render('link', '操作失败', $content, 422);
    }
}
