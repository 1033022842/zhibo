<?php
/**
 * 推广与像素配置
 * 访问: /admin/live.PromoSetting/index?_key=<ADMIN_FLOAT_KEY>
 *
 * 一条像素 = 一个广告后台账号，可无限添加。每条可单独设置：
 *   推广渠道（Facebook / TikTok / Google / Snapchat / Kwai / Bing / Pinterest / 其他）
 *   像素ID、事件（完成注册 / 购物·充值 / 订阅）、推广域名、像素代码、测试回调编码
 *
 * 前端静态站每个页面引用的 js/tracking.js 会拉 /api/v1/site/config 自动注入，改完即时生效（缓存 60 秒）。
 * 「测试回调编码」填广告后台 Events Manager 里的测试代码，配合「测试事件」面板即可验证注册 / 充值有没有回传。
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\live\service\PromoService;
use think\facade\Db;

final class PromoSetting extends Backend
{
    protected array $noNeedLogin = ['index', 'evSave', 'domainSave', 'pxSave', 'pxToggle', 'pxDel', 'preview'];

    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    public function index(): void
    {
        $svc    = new PromoService();
        $values = $svc->all();
        $editId = (int) $this->request->param('id', 0);

        $on = static fn(string $key, string $default = '1'): string => ($values[$key] ?? $default) === '1' ? ' checked' : '';

        $evReg = $on('promo_ev_register');
        $evPay = $on('promo_ev_recharge');
        $evSub = $on('promo_ev_subscribe');

        // ---------- 编辑中的像素 ----------
        $edit = [
            'id' => 0, 'platform' => 'facebook', 'name' => '', 'pixel_id' => '',
            'events' => 'register,recharge,subscribe', 'domains' => '', 'code' => '',
            'test_code' => '', 'sort' => 0, 'status' => 1,
        ];
        if ($editId > 0) {
            $row = Db::connect('live_mysql')->table('lp_promo_pixel')->where('id', $editId)->find();
            if ($row) {
                $edit = array_merge($edit, $row);
            }
        }
        $eId = (int) $edit['id'];
        $eEvents = array_map('trim', explode(',', (string) $edit['events']));

        $platformOptions = '';
        foreach (PromoService::PIXEL_PLATFORMS as $key => $label) {
            $sel = ((string) $edit['platform'] === $key) ? ' selected' : '';
            $platformOptions .= "<option value=\"{$key}\"{$sel}>{$label}</option>";
        }
        $eventBoxes = '';
        foreach (PromoService::PIXEL_EVENTS as $key => $label) {
            $checked = in_array($key, $eEvents, true) ? ' checked' : '';
            $eventBoxes .= "<label class=\"switch\"><input type=\"checkbox\" name=\"events[]\" value=\"{$key}\"{$checked}> {$label}</label>";
        }

        // ---------- 前端域名（推广域名下拉的数据源）----------
        $domains      = $svc->siteDomains();
        $domainListV  = htmlspecialchars(implode("\n", $domains), ENT_QUOTES);
        $domainCount  = count($domains);
        $eDomainList  = array_values(array_filter(array_map('trim', explode(',', (string) $edit['domains']))));
        $eDomainStr   = implode(',', $eDomainList);
        $isKnownOne   = count($eDomainList) === 1 && in_array($eDomainList[0], $domains, true);
        $useCustom    = $eDomainStr !== '' && !$isKnownOne;

        $domainOptions = '<option value=""' . ($eDomainStr === '' ? ' selected' : '') . '>全部域名（不限）</option>';
        foreach ($domains as $d) {
            $dEsc = htmlspecialchars($d, ENT_QUOTES);
            $domainOptions .= '<option value="' . $dEsc . '"' . ($eDomainStr === $d ? ' selected' : '') . '>' . $dEsc . '</option>';
        }
        $domainOptions .= '<option value="__custom__"' . ($useCustom ? ' selected' : '') . '>自定义 / 多个域名…</option>';

        $eName   = htmlspecialchars((string) $edit['name'], ENT_QUOTES);
        $ePixel  = htmlspecialchars((string) $edit['pixel_id'], ENT_QUOTES);
        $eDomain = htmlspecialchars((string) $edit['domains'], ENT_QUOTES);
        $customStyle = $useCustom ? '' : ' style="display:none"';
        $eCode   = htmlspecialchars((string) $edit['code'], ENT_QUOTES);
        $eTest   = htmlspecialchars((string) $edit['test_code'], ENT_QUOTES);
        $eSort   = (int) $edit['sort'];
        $eStatus = (int) $edit['status'] === 1 ? ' checked' : '';
        $formTitle = $eId > 0 ? '编辑像素 #' . $eId : '新增像素';

        // ---------- 像素列表 ----------
        $rows = '';
        $enabled = 0;
        foreach ($svc->pixels(false) as $row) {
            $id       = (int) $row['id'];
            $platform = (string) $row['platform'];
            $label    = PromoService::PIXEL_PLATFORMS[$platform] ?? $platform;
            $name     = htmlspecialchars((string) $row['name'], ENT_QUOTES);
            $pixelId  = htmlspecialchars((string) $row['pixel_id'], ENT_QUOTES);
            $domains  = htmlspecialchars((string) $row['domains'], ENT_QUOTES);
            $testCode = htmlspecialchars((string) $row['test_code'], ENT_QUOTES);
            $status   = (int) $row['status'];
            if ($status === 1) {
                $enabled++;
            }

            $evLabels = [];
            foreach (array_map('trim', explode(',', (string) $row['events'])) as $ek) {
                if (isset(PromoService::PIXEL_EVENTS[$ek])) {
                    $evLabels[] = PromoService::PIXEL_EVENTS[$ek];
                }
            }
            $evHtml = $evLabels === []
                ? '<span class="muted">全部</span>'
                : htmlspecialchars(implode(' / ', $evLabels), ENT_QUOTES);

            $domHtml  = $domains === '' ? '<span class="muted">全部域名</span>' : htmlspecialchars($domains, ENT_QUOTES);
            $codeHtml = trim((string) $row['code']) !== '' ? '<span class="chip on">有</span>' : '<span class="muted">无</span>';
            $testHtml = $testCode !== '' ? '<span class="chip test">' . $testCode . '</span>' : '<span class="muted">—</span>';
            $statusHtml = $status === 1 ? '<span class="chip on">启用</span>' : '<span class="chip off">停用</span>';
            $toggleText = $status === 1 ? '停用' : '启用';

            $rows .= <<<ROW
            <tr>
                <td>{$id}</td>
                <td><b>{$label}</b></td>
                <td>{$name}</td>
                <td class="mono">{$pixelId}</td>
                <td>{$evHtml}</td>
                <td class="url">{$domHtml}</td>
                <td>{$codeHtml}</td>
                <td>{$testHtml}</td>
                <td>{$statusHtml}</td>
                <td class="ops">
                    <a href="/admin/live.PromoSetting/index?id={$id}">编辑</a>
                    <a href="/admin/live.PromoSetting/pxToggle?id={$id}">{$toggleText}</a>
                    <a href="/admin/live.PromoSetting/pxDel?id={$id}" onclick="return confirm('确认删除该像素？')" class="danger">删除</a>
                </td>
            </tr>
ROW;
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="10" class="empty">还没有像素，先在下面「新增像素」加一条（Facebook / TikTok 都支持无限多条）</td></tr>';
        }

        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>推广与像素</title>
<style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:-apple-system,sans-serif;background:#f5f7fa;color:#333;padding:20px;font-size:14px}
    .container{max-width:1180px;margin:0 auto}
    .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:18px}
    h2{font-size:17px;margin-bottom:14px;display:flex;align-items:center;gap:8px}
    h2 .dot{width:6px;height:18px;background:#409eff;border-radius:3px}
    .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}
    .field{margin-bottom:12px}
    .field label{display:block;font-size:13px;color:#666;margin-bottom:4px}
    .field input[type=text],.field select,.field textarea{width:100%;padding:9px 10px;border:1px solid #ddd;border-radius:6px;font-size:14px;font-family:inherit}
    .field select + input[type=text]{margin-top:8px}
    .field textarea{min-height:74px;resize:vertical}
    .field textarea.code{min-height:120px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px}
    .switch{display:inline-flex;align-items:center;gap:8px;font-size:13px;color:#333;font-weight:600;margin-right:20px}
    .switch input{width:16px;height:16px}
    .tip{font-size:12px;color:#999;margin-top:6px;line-height:1.6}
    .btn{padding:10px 26px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}
    .btn-primary{background:#409eff;color:#fff}
    .btn-ghost{background:#f0f2f5;color:#555}
    table{width:100%;border-collapse:collapse;font-size:13px}
    th,td{padding:10px 8px;border-bottom:1px solid #eef0f3;text-align:left;vertical-align:middle}
    th{color:#888;font-weight:600;background:#fafbfc}
    td.url,td.mono{color:#666;font-size:12px;max-width:220px;word-break:break-all}
    td.ops a{color:#409eff;text-decoration:none;margin-right:10px}
    td.ops a.danger{color:#ff2d55}
    td.empty{text-align:center;color:#aaa;padding:26px}
    .chip{font-size:12px;padding:2px 9px;border-radius:999px}
    .chip.on{background:#e8f7ef;color:#00a870}
    .chip.off{background:#f2f3f5;color:#999}
    .chip.test{background:#fff4e5;color:#d48806}
    .muted{color:#bbb;font-size:12px}
    .bar{display:flex;justify-content:space-between;align-items:center;gap:12px}
    .note{background:#eef6ff;border:1px solid #cfe3ff;color:#2b6cb0;padding:12px 16px;border-radius:10px;font-size:13px;line-height:1.8;margin-bottom:18px}
    code{background:#f2f3f5;padding:1px 5px;border-radius:4px}
</style>
</head>
<body>
<div class="container">

    <div class="note">
        当前启用中的像素：<b>{$enabled}</b> 条。像素支持<b>无限添加</b>——同一条推广渠道可以有多个像素（例如 FB 主号 + FB 备用号），
        每条像素可以<b>单独选择要接收的事件</b>、<b>限定只在某个推广域名上生效</b>。保存后前台 <b>60 秒内自动生效</b>（无需重新发版）。<br>
        要验证事件是否真的回传到广告后台：Ads Manager / Events Manager 里取「测试代码」填到下面的<b>测试回调编码</b>，
        然后在广告后台打开「测试事件」面板，用无痕窗口走一遍注册或充值，事件会实时出现在面板里。
    </div>

    <form class="card" method="post" action="/admin/live.PromoSetting/evSave">
        <h2><span class="dot"></span>转化事件总开关</h2>
        <div>
            <label class="switch"><input type="checkbox" name="promo_ev_register" value="1"{$evReg}> 完成注册 CompleteRegistration</label>
            <label class="switch"><input type="checkbox" name="promo_ev_recharge" value="1"{$evPay}> 购物 / 充值 Purchase</label>
            <label class="switch"><input type="checkbox" name="promo_ev_subscribe" value="1"{$evSub}> 订阅 Subscribe</label>
        </div>
        <div class="tip">这里是总闸：关掉后<b>所有</b>像素都不再上报该事件（各像素自己的事件选择仍然有效）。页面浏览不受影响。</div>
        <div class="bar" style="margin-top:14px">
            <div class="tip">购物 / 订阅事件会带上金额与币种（USD）。</div>
            <button class="btn btn-primary" type="submit">保存总开关</button>
        </div>
    </form>

    <form class="card" method="post" action="/admin/live.PromoSetting/domainSave">
        <h2><span class="dot"></span>前端推广域名</h2>
        <div class="field">
            <label>下面这些域名会出现在「推广域名」下拉里，一行一个（当前 {$domainCount} 个）</label>
            <textarea name="promo_site_domains" style="min-height:110px" placeholder="sugus.it.com&#10;promo-01.sugus.ai">{$domainListV}</textarea>
            <div class="tip">
                前台每有一个新域名打开站点，会自动收录进这个列表（去重，最多 30 条），所以通常不用手填；
                你也可以在这里手动增删。<b>在这里删掉的域名不会再被自动收录回来</b>，想恢复就重新加回去。
                像素里选中的域名 = 该像素只在这个域名上生效。
            </div>
        </div>
        <div class="bar">
            <div class="tip">下拉里没有你想要的域名时，在像素表单里选「自定义 / 多个域名…」手动填即可。</div>
            <button class="btn btn-primary" type="submit">保存域名列表</button>
        </div>
    </form>

    <form class="card" method="post" action="/admin/live.PromoSetting/pxSave">
        <h2><span class="dot"></span>{$formTitle}</h2>
        <input type="hidden" name="id" value="{$eId}">
        <div class="grid">
            <div>
                <div class="field">
                    <label>推广渠道</label>
                    <select name="platform">{$platformOptions}</select>
                    <div class="tip">Facebook / TikTok / Google 会加载对应的官方脚本；Snapchat、Kwai 等请把官方像素代码粘到右下「像素代码」。</div>
                </div>
                <div class="field">
                    <label>备注名（仅后台可见，方便区分多条像素）</label>
                    <input type="text" name="name" value="{$eName}" placeholder="如 FB-马来1号">
                </div>
                <div class="field">
                    <label>像素ID</label>
                    <input type="text" name="pixel_id" value="{$ePixel}" placeholder="FB: 123456789012345 / TikTok: C1A2B3... / GA4: G-XXXX / Ads: AW-XXXX / GTM: GTM-XXXX">
                    <div class="tip">Google 渠道按前缀自动识别：<code>G-</code> GA4、<code>AW-</code> Google Ads、<code>GTM-</code> 容器。</div>
                </div>
                <div class="field">
                    <label>测试回调编码（Test Event Code，可选）</label>
                    <input type="text" name="test_code" value="{$eTest}" placeholder="如 TEST12345">
                    <div class="tip">填了之后，这条像素上报的转化事件会带上测试标记，可直接在广告后台「测试事件」面板里看到，用来验证注册 / 充值是否真的回传。</div>
                </div>
            </div>
            <div>
                <div class="field">
                    <label>事件选择（这条像素要接收哪些事件）</label>
                    {$eventBoxes}
                    <div class="tip">全不勾 = 接收全部事件。</div>
                </div>
                <div class="field">
                    <label>推广域名（选「全部域名」= 不限域名，全站生效）</label>
                    <select name="domains" id="pxDomains">{$domainOptions}</select>
                    <input type="text" name="domains_custom" id="pxDomainsCustom" value="{$eDomain}"
                           placeholder="多个用逗号分隔，支持 *.sugus.ai"{$customStyle}>
                    <div class="tip">下拉里的域名就是我们前端在跑的域名（前台访问时自动收录，也可在下面「前端推广域名」里手动维护）。选某条域名 = 这条像素只在该域名上生效，方便多条域名分别投不同广告账号。</div>
                </div>
                <div class="field">
                    <label>排序（越小越靠前）</label>
                    <input type="text" name="sort" value="{$eSort}">
                </div>
                <div class="field">
                    <label class="switch"><input type="checkbox" name="status" value="1"{$eStatus}> 启用这条像素</label>
                </div>
            </div>
        </div>
        <div class="field">
            <label>像素代码（可选，原样注入页面 &lt;head&gt;）</label>
            <textarea class="code" name="code" placeholder="&lt;script&gt; ... &lt;/script&gt;">{$eCode}</textarea>
            <div class="tip">用于 Snapchat / Kwai / Bing 等未内置的像素，或给已有像素补充自定义脚本。已内置的 Facebook / TikTok / Google 无需在此粘贴代码。</div>
        </div>
        <div class="bar">
            <a class="btn btn-ghost" href="/admin/live.PromoSetting/index">清空表单</a>
            <button class="btn btn-primary" type="submit">{$formTitle}</button>
        </div>
    </form>

    <div class="card">
        <div class="bar" style="margin-bottom:12px">
            <h2 style="margin:0"><span class="dot"></span>像素列表</h2>
            <div class="tip" id="livePreview">当前生效：加载中…</div>
        </div>
        <table>
            <thead><tr><th>ID</th><th>推广渠道</th><th>备注</th><th>像素ID</th><th>事件</th><th>推广域名</th><th>像素代码</th><th>测试编码</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>{$rows}</tbody>
        </table>
    </div>
</div>

<script>
// 「推广域名」下拉：选到「自定义 / 多个域名…」时才显示手动输入框
(function () {
    var sel = document.getElementById('pxDomains');
    var custom = document.getElementById('pxDomainsCustom');
    if (!sel || !custom) return;
    sel.addEventListener('change', function () {
        var isCustom = sel.value === '__custom__';
        custom.style.display = isCustom ? 'block' : 'none';
        if (!isCustom) custom.value = '';
    });
})();

fetch('/api/v1/site/config').then(function(r){return r.json()}).then(function(d){
    var box = document.getElementById('livePreview');
    if (!d || d.code !== '00000') { box.textContent = '读取失败'; return; }
    var p = d.data.pixels || {};
    var items = p.items || [];
    var ev = p.events || {};
    var on = [];
    if (ev.register) on.push('注册');
    if (ev.recharge) on.push('购物');
    if (ev.subscribe) on.push('订阅');
    box.textContent = '前台正在加载 ' + items.length + ' 条像素 · 总开关：' + (on.length ? on.join('/') : '全关');
}).catch(function(){ document.getElementById('livePreview').textContent = '读取失败'; });
</script>
</body>
</html>
HTML;
        exit;
    }

    /** 转化事件总开关 */
    public function evSave(): void
    {
        try {
            $svc = new PromoService();
            foreach (PromoService::EVENT_FIELDS as $key => [$type, $remark]) {
                $value = (string) $this->request->post($key, '') === '1' ? '1' : '0';
                $svc->saveSetting($key, $value, $remark);
            }
            $svc->flushCache();
            $this->backToIndex('转化事件总开关已保存');
        } catch (\Throwable $e) {
            $this->renderToast(false, $e->getMessage(), '/admin/live.PromoSetting/index');
        }
    }

    /** 前端推广域名列表（像素「推广域名」下拉的数据源） */
    public function domainSave(): void
    {
        try {
            $raw  = (string) $this->request->post('promo_site_domains', '');
            $list = [];
            foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $d) {
                $d = PromoService::normalizeDomain($d);
                if ($d !== '' && !in_array($d, $list, true)) {
                    $list[] = $d;
                }
            }
            $saved = (new PromoService())->saveDomains($list);
            $this->backToIndex('前端域名列表已保存（共 ' . count($saved) . ' 个）');
        } catch (\Throwable $e) {
            $this->renderToast(false, $e->getMessage(), '/admin/live.PromoSetting/index');
        }
    }

    /** 新增 / 编辑像素 */
    public function pxSave(): void
    {
        try {
            $id       = (int) $this->request->post('id', 0);
            $platform = (string) $this->request->post('platform', 'facebook');
            $platform = isset(PromoService::PIXEL_PLATFORMS[$platform]) ? $platform : 'other';
            $name     = trim((string) $this->request->post('name', ''));
            $pixelId  = $this->cleanId((string) $this->request->post('pixel_id', ''));
            $code     = trim((string) $this->request->post('code', ''));
            $testCode = $this->cleanId((string) $this->request->post('test_code', ''));
            // 推广域名：下拉选具体域名，或选「自定义 / 多个域名…」走手动输入
            $rawDomain = (string) $this->request->post('domains', '');
            if ($rawDomain === '__custom__') {
                $rawDomain = (string) $this->request->post('domains_custom', '');
            }
            $domains = $this->cleanDomains($rawDomain);
            $sort     = (int) $this->request->post('sort', 0);
            $status   = (string) $this->request->post('status', '') === '1' ? 1 : 0;

            $events = $this->request->param('events/a', []);
            $events = array_values(array_intersect(
                array_map('strval', is_array($events) ? $events : []),
                array_keys(PromoService::PIXEL_EVENTS)
            ));
            // 全不勾 = 接收全部事件
            $eventsStr = $events === [] ? implode(',', array_keys(PromoService::PIXEL_EVENTS)) : implode(',', $events);

            if ($pixelId === '' && $code === '') {
                throw new \InvalidArgumentException('像素ID 和 像素代码 至少要填一个');
            }

            $db   = Db::connect('live_mysql');
            $now  = date('Y-m-d H:i:s');
            $data = [
                'platform' => $platform, 'name' => $name, 'pixel_id' => $pixelId,
                'events' => $eventsStr, 'domains' => $domains, 'code' => $code,
                'test_code' => $testCode, 'sort' => $sort, 'status' => $status, 'updated_at' => $now,
            ];
            if ($id > 0) {
                $db->table('lp_promo_pixel')->where('id', $id)->update($data);
            } else {
                $data['created_at'] = $now;
                $db->table('lp_promo_pixel')->insert($data);
            }
            (new PromoService())->flushCache();
            $this->backToIndex($id > 0 ? '像素已更新' : '像素已新增');
        } catch (\Throwable $e) {
            $this->renderToast(false, $e->getMessage(), '/admin/live.PromoSetting/index');
        }
    }

    /** 启用 / 停用 */
    public function pxToggle(): void
    {
        $id  = (int) $this->request->param('id', 0);
        $db  = Db::connect('live_mysql');
        $row = $id ? $db->table('lp_promo_pixel')->where('id', $id)->find() : null;
        if ($row) {
            $db->table('lp_promo_pixel')->where('id', $id)->update([
                'status'     => (int) $row['status'] === 1 ? 0 : 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            (new PromoService())->flushCache();
        }
        $this->backToIndex('已切换状态');
    }

    /** 删除 */
    public function pxDel(): void
    {
        $id = (int) $this->request->param('id', 0);
        if ($id > 0) {
            Db::connect('live_mysql')->table('lp_promo_pixel')->where('id', $id)->delete();
            (new PromoService())->flushCache();
        }
        $this->backToIndex('已删除');
    }

    /** 当前生效的公开配置（自检用） */
    public function preview(): void
    {
        echo json_encode(['code' => 1, 'msg' => '', 'data' => (new PromoService())->publicConfig()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ================= 内部 =================

    /** 去掉粘贴时常见的杂质（空格、引号、尖括号） */
    private function cleanId(string $raw): string
    {
        return preg_replace('/[\s\'"<>]/', '', trim($raw)) ?? '';
    }

    /** 域名规范化：一行/逗号/分号分隔 → 逗号分隔，去掉协议与路径 */
    private function cleanDomains(string $raw): string
    {
        $parts = preg_split('/[\s,;]+/', $raw) ?: [];
        $out   = [];
        foreach ($parts as $p) {
            $p = strtolower(trim($p));
            $p = preg_replace('#^https?://#i', '', $p) ?? $p;
            $p = preg_replace('#[/:].*$#', '', $p) ?? $p;
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return implode(',', array_unique($out));
    }

    /** 操作后回列表页（带上 _key，保证首次访问的飘页鉴权不断） */
    private function backToIndex(string $msg): void
    {
        $key    = (string) $this->request->param('_key', '');
        $suffix = $key !== '' ? '?_key=' . urlencode($key) : '';
        $this->renderToast(true, $msg, '/admin/live.PromoSetting/index' . $suffix, 1200);
    }

    private function renderToast(bool $success, string $msg, string $listUrl, int $delay = 5000): void
    {
        $escMsg   = htmlspecialchars($msg, ENT_QUOTES);
        $redirect = $success ? "<script>setTimeout(function(){location.href='{$listUrl}'},{$delay})</script>" : '';
        $color    = $success ? '#00a870' : '#ff2d55';
        $icon     = $success
            ? '<polyline points="20 6 9 17 4 12"/>'
            : '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>';
        echo <<<HTML
        <!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>操作结果</title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:-apple-system,sans-serif;background:#f5f7fa;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
            .toast-box{background:#fff;padding:36px 44px;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.1);text-align:center;max-width:560px}
            .toast-icon{width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;background:rgba(0,0,0,.04)}
            .toast-icon svg{width:28px;height:28px;color:{$color}}
            .toast-msg{font-size:14px;color:#333;margin-bottom:20px;line-height:1.8;text-align:left;word-break:break-all}
            a{color:#409eff;text-decoration:none;font-size:13px}
        </style></head><body>
        <div class="toast-box">
            <div class="toast-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">{$icon}</svg></div>
            <div class="toast-msg">{$escMsg}</div>
            <a href="{$listUrl}">返回列表</a>
            {$redirect}
        </div></body></html>
        HTML;
        exit;
    }
}
