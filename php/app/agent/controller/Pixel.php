<?php
declare(strict_types=1);

namespace app\agent\controller;

use app\agent\service\AgentView;
use app\live\service\AgentService;
use app\live\service\FbCapiService;
use app\live\service\PromoService;
use think\facade\Db;

/**
 * 代理端「推广与像素」
 * - 代理可创建/编辑自己的像素渠道（与总后台「推广与像素」同一张表 lp_promo_pixel）
 * - 只能看/改 agent_id = 自己的像素；可发测试回调验证配置
 * - 推广域名只能填自己名下的域名（见「专属域名」）
 */
final class Pixel extends Base
{
    /** 单个代理最多像素数 */
    private const MAX_PIXELS = 20;

    public function index(): void
    {
        $this->requirePerm('pixel');

        $agentId = $this->agentId();
        $db      = Db::connect('live_mysql');

        $pixels = $db->table('lp_promo_pixel')->where('agent_id', $agentId)
            ->order('sort', 'asc')->order('id', 'asc')->select()->toArray();

        // 编辑中的像素（只能编辑自己的）
        $editId = (int) ($this->request->param('id') ?? 0);
        $edit   = [
            'id' => 0, 'platform' => 'facebook', 'name' => '', 'pixel_id' => '', 'events' => '',
            'domains' => '', 'code' => '', 'capi_token' => '', 'test_code' => '', 'sort' => 0, 'status' => 1,
        ];
        if ($editId > 0) {
            $row = $db->table('lp_promo_pixel')->where('id', $editId)->where('agent_id', $agentId)->find();
            if ($row) {
                $edit = array_merge($edit, $row);
            }
        }

        // 统计：按注册时记录的像素 ID（lp_user.reg_px）归因
        $regMap = [];
        foreach ($db->query(
            'SELECT reg_px AS k, COUNT(*) AS c FROM lp_user
             WHERE reg_agent_id = ? AND reg_px <> \'\' GROUP BY reg_px',
            [$agentId]
        ) as $r) {
            $regMap[(string) $r['k']] = (int) $r['c'];
        }
        $payMap = [];
        foreach ($db->query(
            "SELECT u.reg_px AS k, COUNT(DISTINCT o.user_id) AS b, IFNULL(SUM(o.pay_amount),0) AS amt
             FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
             WHERE o.status = 1 AND o.biz_type <> 'vip' AND u.reg_agent_id = ? AND u.reg_px <> ''
             GROUP BY u.reg_px",
            [$agentId]
        ) as $r) {
            $payMap[(string) $r['k']] = ['b' => (int) $r['b'], 'amt' => (float) $r['amt']];
        }
        $subMap = [];
        foreach ($db->query(
            "SELECT u.reg_px AS k, COUNT(DISTINCT o.user_id) AS b, IFNULL(SUM(o.pay_amount),0) AS amt
             FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
             WHERE o.status = 1 AND o.biz_type = 'vip' AND u.reg_agent_id = ? AND u.reg_px <> ''
             GROUP BY u.reg_px",
            [$agentId]
        ) as $r) {
            $subMap[(string) $r['k']] = ['b' => (int) $r['b'], 'amt' => (float) $r['amt']];
        }

        // 推广链接可选域名：代理自己的域名 + 平台统一域名（归因靠 ?px=，不靠域名）
        $myDomains     = AgentService::effectiveDomains($agentId, true);
        $ownDomainList = array_map(static fn(array $d): string => (string) $d['domain'], $myDomains);
        $linkDomains   = array_values(array_unique(array_merge($ownDomainList, (new PromoService())->siteDomains())));
        $fallbackDomain = (string) ($linkDomains[0] ?? '');

        $rows = '';
        foreach ($pixels as $p) {
            $id       = (int) $p['id'];
            $platform = (string) $p['platform'];
            $pfLabel  = PromoService::PIXEL_PLATFORMS[$platform] ?? $platform;
            $status   = (int) $p['status'] === 1;
            $events   = array_filter(array_map('trim', explode(',', (string) $p['events'])));
            $eventStr = $events === []
                ? '全部事件'
                : implode(' / ', array_map(static fn(string $e): string => PromoService::PIXEL_EVENTS[$e] ?? $e, $events));
            $domains  = array_values(array_filter(array_map('trim', explode(',', (string) $p['domains']))));

            $reg = $regMap[(string) $id] ?? 0;
            $pay = $payMap[(string) $id] ?? ['b' => 0, 'amt' => 0.0];
            $sub = $subMap[(string) $id] ?? ['b' => 0, 'amt' => 0.0];

            // 推广链接：优先用像素限定的域名（需仍在可用列表里），其次代理主域名
            // 归因靠 ?px=渠道ID，不依赖平台像素 ID，因此没填像素 ID 的无像素渠道也要能生成链接
            $linkDomain = '';
            foreach ($domains as $d) {
                if (in_array($d, $linkDomains, true)) {
                    $linkDomain = $d;
                    break;
                }
            }
            if ($linkDomain === '') {
                $linkDomain = $fallbackDomain;
            }
            $url = $linkDomain !== ''
                ? 'https://' . $linkDomain . '/?px=' . $id
                : '';
            $linkCell = $url === ''
                ? '<span class="muted">暂无可用域名，请先在「专属域名」添加</span>'
                : '<div class="lk"><code data-px="' . $id . '">' . AgentView::h($url) . '</code>'
                    . '<button type="button" class="btn-copy" data-link="' . AgentView::h($url) . '">复制</button></div>';

            $ops = '<a href="/agent/pixel/index?id=' . $id . '">编辑</a>';
            $ops .= $platform === 'facebook'
                ? '<a href="javascript:void(0)" onclick="pxTest(' . $id . ')">测试回调</a>'
                : '<span class="muted" style="font-size:12px">不支持测试</span>';
            $ops .= '<a href="/agent/pixel/toggle?id=' . $id . '&_csrf=' . urlencode($this->csrf()) . '">' . ($status ? '停用' : '启用') . '</a>'
                . '<a class="danger" href="/agent/pixel/del?id=' . $id . '&_csrf=' . urlencode($this->csrf()) . '" onclick="return confirm(\'确认删除该像素？\')">删除</a>';

            $rows .= '<tr>'
                . '<td><b>' . AgentView::h((string) $p['name']) . '</b>'
                    . '<div class="muted" style="font-size:11.5px;margin-top:3px">#' . $id . ' · ' . AgentView::h($pfLabel)
                    . ' · ' . AgentView::h($eventStr) . '</div>'
                    . ($domains !== [] ? '<div class="muted" style="font-size:11px;margin-top:2px">' . AgentView::h(implode('、', $domains)) . '</div>' : '')
                    . '</td>'
                . '<td style="max-width:320px">' . $linkCell . '</td>'
                . '<td class="num">' . $reg . '</td>'
                . '<td class="num">' . (int) $pay['b'] . ' / ' . number_format((float) $pay['amt'], 2) . '</td>'
                . '<td class="num">' . (int) $sub['b'] . ' / ' . number_format((float) $sub['amt'], 2) . '</td>'
                . '<td>' . ($status ? '<span class="chip on">启用</span>' : '<span class="chip off">停用</span>') . '</td>'
                . '<td class="ops">' . $ops . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="7" class="empty">还没有像素渠道，在下方新建一个</td></tr>';
        }

        // 表单数据
        $eId       = (int) $edit['id'];
        $ePlatform = (string) $edit['platform'];
        $eEvents   = array_filter(array_map('trim', explode(',', (string) $edit['events'])));
        if ($eId > 0 && $eEvents === []) {
            $eEvents = array_keys(PromoService::PIXEL_EVENTS);
        }
        if ($eId === 0) {
            $eEvents = array_keys(PromoService::PIXEL_EVENTS);
        }
        $eDomains  = array_filter(array_map('trim', explode(',', (string) $edit['domains'])));
        $eStatus   = (int) $edit['status'] === 1 ? ' checked' : '';

        $platformOptions = '';
        foreach (PromoService::PIXEL_PLATFORMS as $key => $label) {
            $platformOptions .= '<option value="' . AgentView::h($key) . '"' . ($key === $ePlatform ? ' selected' : '') . '>'
                . AgentView::h($label) . '</option>';
        }

        $eventBoxes = '';
        foreach (PromoService::PIXEL_EVENTS as $key => $label) {
            $chk = in_array($key, $eEvents, true) ? ' checked' : '';
            $eventBoxes .= '<label><input type="checkbox" name="events[]" value="' . $key . '"' . $chk . '>' . AgentView::h($label) . '</label>';
        }

        $myDomains = AgentService::effectiveDomains($agentId, true);
        $domainBoxes = '';
        foreach ($myDomains as $d) {
            $dv  = (string) $d['domain'];
            $chk = in_array($dv, $eDomains, true) ? ' checked' : '';
            $domainBoxes .= '<label><input type="checkbox" name="domains[]" value="' . AgentView::h($dv) . '"' . $chk . '>'
                . AgentView::h($dv) . '</label>';
        }
        if ($domainBoxes === '') {
            $domainBoxes = '<span class="muted" style="font-size:12.5px">不选也可以：共用平台域名时，像素只在带 <b>?px=</b> 的推广链接里触发，不会影响其他访客。</span>';
        }

        $formTitle = $eId > 0 ? '编辑像素渠道 #' . $eId : '新建像素渠道';
        $cancel    = $eId > 0 ? '<a class="btn-line" href="/agent/pixel/index">取消编辑</a>' : '';
        $submit    = $eId > 0 ? '保存修改' : '创建像素';
        $csrf      = $this->csrf();
        $csrfJs    = json_encode($csrf, JSON_UNESCAPED_SLASHES);
        $maxPixels = self::MAX_PIXELS;
        $eName     = $this->h($edit['name']);
        $ePixelId  = $this->h($edit['pixel_id']);
        $eToken    = $this->h($edit['capi_token']);
        $eTestCode = $this->h($edit['test_code']);
        $eSort     = (int) $edit['sort'];
        $eCode     = $this->h($edit['code']);

        // 汇总统计
        $pixelCount = count($pixels);
        $enabledN   = 0;
        foreach ($pixels as $p) {
            if ((int) $p['status'] === 1) {
                $enabledN++;
            }
        }
        $totalReg      = array_sum($regMap);
        $totalPayUsers = 0;
        $totalPayAmt   = 0.0;
        foreach ($payMap as $v) {
            $totalPayUsers += (int) $v['b'];
            $totalPayAmt   += (float) $v['amt'];
        }
        $totalPayAmtFmt = number_format($totalPayAmt, 2);

        $linkOptions = '<option value="">按各像素域名（默认）</option>';
        foreach ($linkDomains as $d) {
            $linkOptions .= '<option value="' . AgentView::h($d) . '">' . AgentView::h($d) . '</option>';
        }
        $domainWarning = $linkDomains === []
            ? '<div class="alert err">还没有可用的推广域名，推广链接无法生成。请先到 <a href="/agent/domain/index">专属域名</a> 添加一个域名（域名需先完成解析并配好 HTTPS）。</div>'
            : '';

        $content = <<<HTML
<div class="head">
  <div>
    <h1>推广与像素</h1>
    <div class="sub">配置自己的像素渠道并复制推广链接投放。访客从 <b>?px=</b> 链接进来后，注册与充值会按该像素实时归因到下面的统计。</div>
  </div>
</div>
{$domainWarning}
<div class="stats">
  <div class="stat"><div><div class="k">像素渠道</div><div class="v">{$pixelCount}</div><div class="d">启用中 {$enabledN} 个</div></div></div>
  <div class="stat"><div><div class="k">注册数</div><div class="v">{$totalReg}</div><div class="d">按像素归因</div></div></div>
  <div class="stat"><div><div class="k">充值人数</div><div class="v">{$totalPayUsers}</div><div class="d">已通过订单</div></div></div>
  <div class="stat"><div><div class="k">充值金额 (USD)</div><div class="v">{$totalPayAmtFmt}</div><div class="d">不含订阅</div></div></div>
</div>
<div class="card">
  <h2>{$formTitle}</h2>
  <form class="frm" method="post" action="/agent/pixel/save">
    <input type="hidden" name="_csrf" value="{$csrf}">
    <input type="hidden" name="id" value="{$eId}">
    <div class="fld"><label>渠道平台</label><select name="platform">{$platformOptions}</select></div>
    <div class="fld"><label>渠道名称（必填）</label><input type="text" name="name" maxlength="64" value="{$eName}" placeholder="如 FB-主像素" required></div>
    <div class="fld"><label>像素 ID</label><input type="text" name="pixel_id" maxlength="64" value="{$ePixelId}" placeholder="如 1234567890123456"></div>
    <div class="fld"><label>服务端回传令牌（CAPI Token）</label><input type="text" name="capi_token" maxlength="255" value="{$eToken}" placeholder="Events Manager 生成的令牌"></div>
    <div class="fld"><label>测试码（test_event_code）</label><input type="text" name="test_code" maxlength="64" value="{$eTestCode}" placeholder="如 TEST88641"></div>
    <div class="fld"><label>排序（小的在前）</label><input type="number" name="sort" value="{$eSort}"></div>
    <div class="fld" style="grid-column:1/-1"><label>像素代码（没有像素 ID 时填前端代码，可留空）</label>
      <textarea name="code" rows="4" placeholder="粘贴前端像素代码">{$eCode}</textarea></div>
    <div class="fld" style="grid-column:1/-1"><label>上报事件</label><div class="chk-row">{$eventBoxes}</div></div>
    <div class="fld" style="grid-column:1/-1"><label>像素生效域名（可选，仅你自己名下的域名）</label><div class="chk-row">{$domainBoxes}</div></div>
    <div class="actions">
      <button class="btn" type="submit">{$submit}</button>{$cancel}
      <label class="switch"><input type="checkbox" name="status" value="1"{$eStatus}> 启用</label>
    </div>
  </form>
  <div class="tip">「像素 ID + 回传令牌」必须来自同一个像素，否则测试回调会返回 400。最多可创建 {$maxPixels} 个像素渠道。</div>
</div>
<div class="card">
  <h2>像素列表<span class="muted" style="font-weight:400;font-size:12px"> · 统计按注册时的 ?px= 归因</span></h2>
  <div class="toolbar" style="margin-bottom:12px">
    <span class="muted" style="font-size:12.5px">推广链接域名</span>
    <select id="pxLinkDomain">{$linkOptions}</select>
    <span class="muted" style="font-size:12px">选择后，下面所有链接会切换成该域名；选「按各像素域名」恢复各自配置</span>
  </div>
  <div class="table-wrap"><table><thead><tr>
    <th>渠道</th><th>推广链接</th><th class="num">注册数</th>
    <th class="num">充值人数/金额</th><th class="num">订阅人数/金额</th><th>状态</th><th>操作</th>
  </tr></thead><tbody>{$rows}</tbody></table></div>
</div>
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
  title.textContent = '测试回调 · 像素 #' + id;
  body.textContent = '正在向像素发送一条测试事件，请稍候…';
  mask.classList.add('open');
  fetch('/agent/pixel/test?id=' + id + '&_csrf=' + encodeURIComponent(AGENT_CSRF), {
    headers: { 'Accept': 'application/json' }
  }).then(function (r) { return r.json(); }).then(function (d) {
    title.textContent = '测试回调 · 像素 #' + id + (d.ok ? ' · 成功' : ' · 失败');
    body.textContent = 'HTTP ' + (d.http || 0) + '\n\n' + (d.resp || '');
  }).catch(function (e) {
    body.textContent = '请求失败：' + e;
  });
}
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') { closePxTest(); }
});
(function () {
  var sel = document.getElementById('pxLinkDomain');
  if (!sel) return;
  var codes = document.querySelectorAll('code[data-px]');
  for (var i = 0; i < codes.length; i++) {
    codes[i].setAttribute('data-orig', codes[i].textContent);
  }
  sel.addEventListener('change', function () {
    var host = sel.value || '';
    for (var j = 0; j < codes.length; j++) {
      var code = codes[j];
      var url = host
        ? ('https://' + host + '/?px=' + code.getAttribute('data-px'))
        : (code.getAttribute('data-orig') || '');
      code.textContent = url;
      var btn = code.parentNode.querySelector('.btn-copy');
      if (btn) { btn.setAttribute('data-link', url); }
    }
  });
})();
</script>
HTML;

        $this->render('pixel', '推广与像素', $content);
    }

    /** 新建 / 编辑自己的像素 */
    public function save(): void
    {
        $this->requirePerm('pixel');
        $this->verifyCsrf();

        $agentId = $this->agentId();
        $db      = Db::connect('live_mysql');

        $id        = (int) ($this->request->post('id') ?? 0);
        $platform  = (string) ($this->request->post('platform') ?? 'facebook');
        $platform  = isset(PromoService::PIXEL_PLATFORMS[$platform]) ? $platform : 'other';
        $name      = mb_substr(trim((string) ($this->request->post('name') ?? '')), 0, 64);
        $pixelId   = preg_replace('/[\s\'"<>]/', '', trim((string) ($this->request->post('pixel_id') ?? ''))) ?? '';
        $code      = trim((string) ($this->request->post('code') ?? ''));
        $capiToken = mb_substr(trim((string) ($this->request->post('capi_token') ?? '')), 0, 255);
        $testCode  = preg_replace('/[\s\'"<>]/', '', trim((string) ($this->request->post('test_code') ?? ''))) ?? '';
        $sort      = (int) ($this->request->post('sort') ?? 0);
        $status    = (string) ($this->request->post('status') ?? '') === '1' ? 1 : 0;

        if ($name === '') {
            $this->flashError('渠道名称不能为空');
        }
        if (!in_array($platform, PromoService::NO_PIXEL_PLATFORMS, true) && $pixelId === '' && $code === '') {
            $this->flashError('该平台需要填写「像素 ID」或「像素代码」');
        }

        // 推广域名只能选自己（含继承）的域名
        $myDomains = array_map(
            static fn(array $d): string => (string) $d['domain'],
            AgentService::effectiveDomains($agentId, true)
        );
        $domainsIn = $this->request->post('domains', []);
        $domains   = [];
        foreach ((array) $domainsIn as $d) {
            $d = PromoService::normalizeDomain((string) $d);
            if ($d === '') {
                continue;
            }
            if (!in_array($d, $myDomains, true)) {
                $this->flashError('推广域名「' . $d . '」不属于你，请先在「专属域名」添加');
            }
            $domains[] = $d;
        }
        $domainsStr = implode(',', array_values(array_unique($domains)));

        $events = (array) $this->request->post('events', []);
        $events = array_values(array_intersect(
            array_map('strval', $events),
            array_keys(PromoService::PIXEL_EVENTS)
        ));
        $eventsStr = $events === [] ? implode(',', array_keys(PromoService::PIXEL_EVENTS)) : implode(',', $events);

        $now  = date('Y-m-d H:i:s');
        $data = [
            'platform'   => $platform,
            'name'       => $name,
            'pixel_id'   => $pixelId,
            'events'     => $eventsStr,
            'domains'    => $domainsStr,
            'code'       => $code,
            'capi_token' => $capiToken,
            'test_code'  => $testCode,
            'sort'       => $sort,
            'status'     => $status,
            'updated_at' => $now,
        ];

        if ($id > 0) {
            $row = $db->table('lp_promo_pixel')->where('id', $id)->where('agent_id', $agentId)->find();
            if (!$row) {
                $this->flashError('像素不存在或不属于你');
            }
            $db->table('lp_promo_pixel')->where('id', $id)->where('agent_id', $agentId)->update($data);
        } else {
            if ($db->table('lp_promo_pixel')->where('agent_id', $agentId)->count() >= self::MAX_PIXELS) {
                $this->flashError('最多只能创建 ' . self::MAX_PIXELS . ' 个像素渠道');
            }
            $data['agent_id']   = $agentId;
            $data['created_at'] = $now;
            $db->table('lp_promo_pixel')->insert($data);
        }

        (new PromoService())->flushCache();
        $this->redirect('/agent/pixel/index');
    }

    /** 测试回调：用自己的像素发一条测试事件 */
    public function test(): void
    {
        $this->requirePerm('pixel');
        $this->verifyCsrfParam();

        $id  = (int) ($this->request->param('id') ?? 0);
        $db  = Db::connect('live_mysql');
        $row = $id > 0
            ? $db->table('lp_promo_pixel')->where('id', $id)->where('agent_id', $this->agentId())->find()
            : null;
        if (!$row) {
            $this->jsonOut(['ok' => false, 'http' => 0, 'resp' => '像素不存在或不属于你']);
        }
        if ((string) $row['platform'] !== 'facebook') {
            $this->jsonOut(['ok' => false, 'http' => 0, 'resp' => '该渠道不是 Facebook 像素，暂不支持测试回调']);
        }

        $res = FbCapiService::probe($row);
        $this->jsonOut([
            'ok'   => (bool) ($res['ok'] ?? false),
            'http' => (int) ($res['http'] ?? 0),
            'resp' => (string) ($res['resp'] ?? ''),
        ]);
    }

    public function toggle(): void
    {
        $this->requirePerm('pixel');
        $this->verifyCsrfParam();

        $id  = (int) ($this->request->param('id') ?? 0);
        $db  = Db::connect('live_mysql');
        $row = $id > 0
            ? $db->table('lp_promo_pixel')->where('id', $id)->where('agent_id', $this->agentId())->find()
            : null;
        if ($row) {
            $db->table('lp_promo_pixel')->where('id', $id)->update([
                'status'     => (int) $row['status'] === 1 ? 0 : 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            (new PromoService())->flushCache();
        }
        $this->redirect('/agent/pixel/index');
    }

    public function del(): void
    {
        $this->requirePerm('pixel');
        $this->verifyCsrfParam();

        $id = (int) ($this->request->param('id') ?? 0);
        if ($id > 0) {
            Db::connect('live_mysql')->table('lp_promo_pixel')
                ->where('id', $id)->where('agent_id', $this->agentId())->delete();
            (new PromoService())->flushCache();
        }
        $this->redirect('/agent/pixel/index');
    }

    private function h(mixed $v): string
    {
        return AgentView::h($v);
    }

    /** 像素类操作走 GET，CSRF 放在 query 里 */
    private function verifyCsrfParam(): void
    {
        $given = (string) ($this->request->param('_csrf') ?? '');
        if ($given === '' || !hash_equals($this->csrf(), $given)) {
            $this->deny('请求已失效，请刷新页面后重试。', 419);
        }
    }

    private function jsonOut(array $data): void
    {
        response(json_encode($data, JSON_UNESCAPED_UNICODE), 200, ['Content-Type' => 'application/json; charset=utf-8'])->send();
        exit;
    }

    private function flashError(string $message): void
    {
        $content = '<div class="card"><h2>操作失败</h2><div class="tip" style="font-size:13px;color:#d61f4a">'
            . AgentView::h($message) . '</div><div class="actions">'
            . '<a class="btn-line" href="/agent/pixel/index">返回推广与像素</a></div></div>';
        $this->render('pixel', '操作失败', $content, 422);
    }
}
