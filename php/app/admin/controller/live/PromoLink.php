<?php
/**
 * 推广链接（可命名，按链接名称归因）
 * 访问: /admin/live.PromoLink/index?_key=<ADMIN_FLOAT_KEY>
 *
 * 一条推广链接 = 链接名称 + 推广域名 + 绑定像素 + 渠道。
 * 生成的链接形如：https://域名/?px=像素ID&name=链接名称&ch=渠道
 * 客户从这个链接进来并注册后，lp_user.reg_link_name 会记录「链接名称」，
 * 后台「会员列表」与「推广数据（按推广链接名称维度）」即可区分是哪条链接带来的客户。
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\live\service\PromoService;
use think\facade\Db;

final class PromoLink extends Backend
{
    protected array $noNeedLogin = ['index', 'save', 'toggle', 'del'];

    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    public function index(): void
    {
        $db = Db::connect('live_mysql');

        $svc     = new PromoService();
        $domains = $svc->siteDomains();
        $pixels  = $svc->pixels(true); // 只列启用中的像素，避免生成到已停用像素

        // ---------- 编辑中的链接 ----------
        $editId = (int) $this->request->param('id', 0);
        $edit   = ['id' => 0, 'name' => '', 'domain' => '', 'pixel_id' => 0, 'channel' => '', 'remark' => '', 'sort' => 0, 'status' => 1];
        if ($editId > 0) {
            $row = $db->table('lp_promo_link')->where('id', $editId)->find();
            if ($row) {
                $edit = array_merge($edit, $row);
            }
        }

        // ---------- 列表 ----------
        $links = $db->table('lp_promo_link')->order('sort', 'asc')->order('id', 'desc')->select()->toArray();

        // 按「链接名称」聚合：注册数 / 充值人数·金额 / 订阅人数·金额
        $regMap = [];
        foreach ($db->query("SELECT reg_link_name AS k, COUNT(*) AS c FROM lp_user WHERE reg_link_name <> '' GROUP BY reg_link_name") as $r) {
            $regMap[(string) $r['k']] = (int) $r['c'];
        }
        $payMap = [];
        foreach ($db->query("SELECT u.reg_link_name AS k, COUNT(DISTINCT o.user_id) AS b, IFNULL(SUM(o.pay_amount),0) AS amt
                FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
                WHERE o.status = 1 AND o.biz_type <> 'vip' AND u.reg_link_name <> ''
                GROUP BY u.reg_link_name") as $r) {
            $payMap[(string) $r['k']] = ['b' => (int) $r['b'], 'amt' => (float) $r['amt']];
        }
        $subMap = [];
        foreach ($db->query("SELECT u.reg_link_name AS k, COUNT(DISTINCT o.user_id) AS b, IFNULL(SUM(o.pay_amount),0) AS amt
                FROM lp_recharge_order o JOIN lp_user u ON u.id = o.user_id
                WHERE o.status = 1 AND o.biz_type = 'vip' AND u.reg_link_name <> ''
                GROUP BY u.reg_link_name") as $r) {
            $subMap[(string) $r['k']] = ['b' => (int) $r['b'], 'amt' => (float) $r['amt']];
        }

        // 像素：id => 展示名
        $pixelLabel = [];
        foreach ($pixels as $p) {
            $pid = (int) $p['id'];
            $pf  = PromoService::PIXEL_PLATFORMS[(string) $p['platform']] ?? (string) $p['platform'];
            $pn  = trim((string) $p['name']);
            $pixelLabel[$pid] = $pf . ($pn !== '' ? ' · ' . $pn : '') . '（#' . $pid . '）';
        }

        $rows = '';
        $sumReg = 0;
        $sumPay = 0.0;
        $enabledN = 0;
        foreach ($links as $l) {
            $lid    = (int) $l['id'];
            $name   = (string) $l['name'];
            $domain = (string) $l['domain'];
            $ch     = (string) $l['channel'];
            $px     = (int) $l['pixel_id'];
            $remark = (string) $l['remark'];
            $status = (int) $l['status'];

            if ($status === 1) {
                $enabledN++;
            }
            $reg = $regMap[$name] ?? 0;
            $pay = $payMap[$name] ?? ['b' => 0, 'amt' => 0.0];
            $sub = $subMap[$name] ?? ['b' => 0, 'amt' => 0.0];
            $sumReg += $reg;
            $sumPay += (float) $pay['amt'];

            $url    = $domain !== '' ? $this->buildLink($domain, $px, $name, $ch) : '';
            $urlEsc = htmlspecialchars($url, ENT_QUOTES);
            $linkCell = $url === ''
                ? '<span class="muted">未设置域名</span>'
                : '<div class="lk"><code>' . $urlEsc . '</code>'
                    . '<button type="button" class="btn-copy" data-link="' . $urlEsc . '">复制</button></div>';

            $nameEsc   = htmlspecialchars($name, ENT_QUOTES);
            $domainEsc = $domain !== '' ? htmlspecialchars($domain, ENT_QUOTES) : '<span class="muted">—</span>';
            $chEsc     = $ch !== '' ? htmlspecialchars($ch, ENT_QUOTES) : '<span class="muted">—</span>';
            $pxEsc     = isset($pixelLabel[$px]) ? htmlspecialchars($pixelLabel[$px], ENT_QUOTES) : '<span class="muted">不绑定</span>';
            $remarkEsc = $remark !== '' ? htmlspecialchars($remark, ENT_QUOTES) : '';
            $statusHtml = $status === 1 ? '<span class="chip on">启用</span>' : '<span class="chip off">停用</span>';
            $toggleText = $status === 1 ? '停用' : '启用';

            $rows .= '<tr>'
                . '<td>' . $lid . '</td>'
                . '<td><b>' . $nameEsc . '</b>' . ($remarkEsc !== '' ? '<div class="rmk">' . $remarkEsc . '</div>' : '') . '</td>'
                . '<td class="url">' . $linkCell . '</td>'
                . '<td>' . $domainEsc . '</td>'
                . '<td>' . $pxEsc . '</td>'
                . '<td>' . $chEsc . '</td>'
                . '<td class="num">' . $reg . '</td>'
                . '<td class="num">' . (int) $pay['b'] . ' / ' . number_format((float) $pay['amt'], 2) . '</td>'
                . '<td class="num">' . (int) $sub['b'] . ' / ' . number_format((float) $sub['amt'], 2) . '</td>'
                . '<td>' . $statusHtml . '</td>'
                . '<td class="ops">'
                    . '<a href="/admin/live.PromoLink/index?id=' . $lid . $this->keyQuery() . '">编辑</a>'
                    . '<a href="/admin/live.PromoLink/toggle?id=' . $lid . $this->keyQuery() . '">' . $toggleText . '</a>'
                    . '<a href="/admin/live.PromoLink/del?id=' . $lid . $this->keyQuery() . '" onclick="return confirm(\'确认删除该推广链接？\')" class="danger">删除</a>'
                . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="11" class="empty">还没有推广链接，先在下面「新建推广链接」加一条</td></tr>';
        }

        // ---------- 表单数据 ----------
        $eId       = (int) $edit['id'];
        $eName     = htmlspecialchars((string) $edit['name'], ENT_QUOTES);
        $eDomain   = htmlspecialchars((string) $edit['domain'], ENT_QUOTES);
        $ePixel    = (int) $edit['pixel_id'];
        $eChannel  = htmlspecialchars((string) $edit['channel'], ENT_QUOTES);
        $eRemark   = htmlspecialchars((string) $edit['remark'], ENT_QUOTES);
        $eSort     = (int) $edit['sort'];
        $eStatus   = (int) $edit['status'] === 1 ? ' checked' : '';

        $domDataList = '';
        foreach ($domains as $d) {
            $domDataList .= '<option value="' . htmlspecialchars($d, ENT_QUOTES) . '"></option>';
        }

        $pxOptions = '<option value="0"' . ($ePixel === 0 ? ' selected' : '') . '>不绑定（按域名规则自动匹配）</option>';
        foreach ($pixels as $p) {
            $pid = (int) $p['id'];
            $sel = $pid === $ePixel ? ' selected' : '';
            $pxOptions .= '<option value="' . $pid . '"' . $sel . '>' . htmlspecialchars($pixelLabel[$pid], ENT_QUOTES) . '</option>';
        }
        if ($ePixel > 0 && !isset($pixelLabel[$ePixel])) {
            $pxOptions .= '<option value="' . $ePixel . '" selected>（已停用/不存在的像素 #' . $ePixel . '）</option>';
        }

        $formTitle = $eId > 0 ? '编辑推广链接 #' . $eId : '新建推广链接';
        $cancelBtn = $eId > 0 ? '<a class="btn-ghost" href="/admin/live.PromoLink/index' . $this->keyQuery() . '">取消编辑</a>' : '';

        $totalN   = count($links);
        $sumPayFmt = number_format($sumPay, 2);
        $sumRegFmt = number_format($sumReg);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>推广链接</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;background:#f4f5fb;color:#1f2430;font-size:14px}
.container{max-width:1240px;margin:0 auto;padding:28px 24px 60px}
h1{font-size:23px;font-weight:700;color:#1a1f36}
.sub{margin-top:6px;font-size:13px;color:#8a90a3;line-height:1.7}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:22px 0}
.stat{background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:16px;box-shadow:0 1px 3px rgba(30,34,60,.04)}
.stat .v{font-size:20px;font-weight:700;color:#1a1f36}
.stat .k{font-size:12px;color:#8a90a3;margin-top:3px}
.card{background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:20px;margin-bottom:18px;box-shadow:0 1px 3px rgba(30,34,60,.04)}
.card h2{font-size:15px;font-weight:700;margin-bottom:14px;color:#1a1f36}
.frm{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.fld{display:flex;flex-direction:column;gap:6px}
.fld label{font-size:12px;color:#8a90a3}
.fld input,.fld select{height:38px;padding:0 12px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;color:#1f2430;background:#fff;outline:none;width:100%}
.fld input:focus,.fld select:focus{border-color:#6366f1}
.fld.full{grid-column:1/-1}
.actions{grid-column:1/-1;display:flex;gap:10px;align-items:center}
.btn{height:38px;padding:0 20px;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff}
.btn-ghost{height:38px;line-height:36px;padding:0 16px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;color:#5b6072;text-decoration:none;background:#fff}
table{width:100%;border-collapse:collapse}
th{background:#fafbfe;font-weight:600;font-size:12px;color:#8a90a3;text-align:left;padding:12px 14px;border-bottom:1px solid #eef0f6;white-space:nowrap}
td{padding:12px 14px;border-bottom:1px solid #f2f3f8;font-size:13px;color:#3a4051;vertical-align:top}
td.num,th.num{text-align:right;font-variant-numeric:tabular-nums}
tr:last-child td{border-bottom:none}
.empty{text-align:center;color:#9aa0b0;padding:26px 0}
.muted{color:#9aa0b0}
.rmk{font-size:12px;color:#9aa0b0;margin-top:3px}
.chip{font-size:12px;padding:2px 9px;border-radius:999px;white-space:nowrap}
.chip.on{background:#e8f7ef;color:#00a870}
.chip.off{background:#f2f3f5;color:#999}
td.url{max-width:340px}
.lk{display:flex;align-items:center;gap:8px}
.lk code{flex:1;min-width:0;background:#f7f8fc;border:1px solid #eef0f6;border-radius:8px;padding:6px 10px;font-family:Consolas,Menlo,monospace;font-size:12px;color:#4a5069;word-break:break-all}
.btn-copy{flex:0 0 auto;padding:5px 12px;border:1px solid #6366f1;border-radius:8px;background:#fff;color:#6366f1;font-size:12px;cursor:pointer}
.btn-copy:hover{background:#eef0ff}
.btn-copy.copied{border-color:#00a870;color:#00a870;background:#e8f7ef}
td.ops a{color:#6366f1;text-decoration:none;margin-right:12px;white-space:nowrap}
td.ops a.danger{color:#ff2d55}
.tip{font-size:12px;color:#9aa0b0;margin-top:12px;line-height:1.8}
</style>
</head>
<body>
<div class="container">
    <h1>推广链接</h1>
    <div class="sub">每条推广链接有独立「名称」，生成 <b>?px=&amp;name=&amp;ch=</b> 三个参数。
        客户从链接进来注册后，后台「会员列表」与「推广数据（推广链接名称维度）」即可区分是<b>哪条链接</b>带来的客户。</div>

    <div class="stats">
        <div class="stat"><div class="v">{$totalN}</div><div class="k">推广链接总数</div></div>
        <div class="stat"><div class="v">{$enabledN}</div><div class="k">启用中</div></div>
        <div class="stat"><div class="v">{$sumRegFmt}</div><div class="k">按链接归因的注册数</div></div>
        <div class="stat"><div class="v">{$sumPayFmt}</div><div class="k">按链接归因的充值金额(USD)</div></div>
    </div>

    <div class="card">
        <h2>{$formTitle}</h2>
        <form class="frm" method="post" action="/admin/live.PromoLink/save">
            <input type="hidden" name="id" value="{$eId}">
            <div class="fld">
                <label>链接名称（必填，唯一）</label>
                <input type="text" name="name" value="{$eName}" placeholder="如 FB-素材A" maxlength="128" required>
            </div>
            <div class="fld">
                <label>推广域名</label>
                <input type="text" name="domain" value="{$eDomain}" list="domList" placeholder="如 sugus.live" maxlength="120">
                <datalist id="domList">{$domDataList}</datalist>
            </div>
            <div class="fld">
                <label>绑定像素（可选）</label>
                <select name="pixel_id">{$pxOptions}</select>
            </div>
            <div class="fld">
                <label>渠道 ch（可选）</label>
                <input type="text" name="channel" value="{$eChannel}" placeholder="如 fb1" maxlength="64">
            </div>
            <div class="fld">
                <label>备注（可选，仅自己看）</label>
                <input type="text" name="remark" value="{$eRemark}" placeholder="如 投美国区、素材A" maxlength="255">
            </div>
            <div class="fld">
                <label>排序（小的在前）</label>
                <input type="number" name="sort" value="{$eSort}">
            </div>
            <div class="actions">
                <button class="btn" type="submit">保存</button>
                {$cancelBtn}
                <label class="muted" style="margin-left:auto"><input type="checkbox" name="status" value="1"{$eStatus}> 启用</label>
            </div>
        </form>
        <div class="tip">绑定像素后生成 <b>?px=像素ID</b>：从该链接进来的访客只打这一条像素（不受域名规则影响），跳转其它页面也保持绑定。<br>
            不绑定像素也可以，只靠 <b>?name=</b> 做来源区分（像素按「推广与像素」页的域名规则匹配）。</div>
    </div>

    <div class="card">
        <h2>链接列表<span class="muted" style="font-weight:400"> · 注册/充值按「链接名称」归因（首触）</span></h2>
        <table>
            <thead><tr>
                <th>ID</th><th>链接名称</th><th>推广链接</th><th>域名</th><th>像素</th><th>渠道</th>
                <th class="num">注册数</th><th class="num">充值人数/金额</th><th class="num">订阅人数/金额</th>
                <th>状态</th><th>操作</th>
            </tr></thead>
            <tbody>{$rows}</tbody>
        </table>
    </div>
</div>
<script>
(function () {
    function fallback(text, done) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
        done();
    }
    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('.btn-copy') : null;
        if (!btn) return;
        var link = btn.getAttribute('data-link') || '';
        if (!link) return;
        var done = function () {
            var old = btn.textContent;
            btn.textContent = '已复制';
            btn.classList.add('copied');
            setTimeout(function () { btn.textContent = old; btn.classList.remove('copied'); }, 1500);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(link).then(done, function () { fallback(link, done); });
        } else {
            fallback(link, done);
        }
    });
})();
</script>
</body>
</html>
HTML;

        response($html)->send();
        exit;
    }

    /** 新增 / 编辑 */
    public function save(): void
    {
        try {
            $id     = (int) $this->request->post('id', 0);
            $name   = mb_substr(trim((string) $this->request->post('name', '')), 0, 128);
            $domain = PromoService::normalizeDomain((string) $this->request->post('domain', ''));
            $pixel  = (int) $this->request->post('pixel_id', 0);
            $ch     = mb_substr(trim((string) $this->request->post('channel', '')), 0, 64);
            $remark = mb_substr(trim((string) $this->request->post('remark', '')), 0, 255);
            $sort   = (int) $this->request->post('sort', 0);
            $status = (string) $this->request->post('status', '') === '1' ? 1 : 0;

            if ($name === '') {
                throw new \InvalidArgumentException('链接名称必填');
            }

            $db = Db::connect('live_mysql');
            // 名称唯一
            $dup = $db->table('lp_promo_link')->where('name', $name)->where('id', '<>', $id)->find();
            if ($dup) {
                throw new \InvalidArgumentException('链接名称「' . $name . '」已存在，请换一个');
            }

            $now  = date('Y-m-d H:i:s');
            $data = [
                'name' => $name, 'domain' => $domain, 'pixel_id' => max(0, $pixel),
                'channel' => $ch, 'remark' => $remark, 'sort' => $sort,
                'status' => $status, 'updated_at' => $now,
            ];
            if ($id > 0) {
                $db->table('lp_promo_link')->where('id', $id)->update($data);
            } else {
                $data['created_at'] = $now;
                $db->table('lp_promo_link')->insert($data);
            }
            $this->backToIndex($id > 0 ? '推广链接已更新' : '推广链接已新建');
        } catch (\Throwable $e) {
            $this->renderToast(false, $e->getMessage(), '/admin/live.PromoLink/index');
        }
    }

    /** 启用 / 停用 */
    public function toggle(): void
    {
        $id  = (int) $this->request->param('id', 0);
        $db  = Db::connect('live_mysql');
        $row = $id > 0 ? $db->table('lp_promo_link')->where('id', $id)->find() : null;
        if ($row) {
            $db->table('lp_promo_link')->where('id', $id)->update([
                'status'     => (int) $row['status'] === 1 ? 0 : 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->backToIndex('已切换状态');
    }

    /** 删除 */
    public function del(): void
    {
        $id = (int) $this->request->param('id', 0);
        if ($id > 0) {
            Db::connect('live_mysql')->table('lp_promo_link')->where('id', $id)->delete();
        }
        $this->backToIndex('已删除');
    }

    // ================= 内部 =================

    /** 组装推广链接：https://域名/?px=像素ID&name=名称&ch=渠道 */
    private function buildLink(string $domain, int $pixelId, string $name, string $channel): string
    {
        $qs = [];
        if ($pixelId > 0) {
            $qs['px'] = (string) $pixelId;
        }
        if ($name !== '') {
            $qs['name'] = $name;
        }
        if ($channel !== '') {
            $qs['ch'] = $channel;
        }
        return 'https://' . $domain . '/' . ($qs !== [] ? '?' . http_build_query($qs) : '');
    }

    /** 保留当前 _key，便于首次访问的飘页鉴权不断 */
    private function keyQuery(): string
    {
        $key = (string) $this->request->param('_key', '');
        return $key !== '' ? '?_key=' . urlencode($key) : '';
    }

    private function backToIndex(string $msg): void
    {
        $this->renderToast(true, $msg, '/admin/live.PromoLink/index' . $this->keyQuery(), 1200);
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
            body{font-family:-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;background:#f5f7fa}
            .box{background:#fff;border-radius:12px;padding:34px 40px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.08)}
            .box svg{width:52px;height:52px;fill:none;stroke:{$color};stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round}
            .box p{margin-top:16px;font-size:15px;color:#333}
        </style></head>
        <body><div class="box"><svg viewBox="0 0 24 24">{$icon}</svg><p>{$escMsg}</p></div>{$redirect}</body></html>
HTML;
        exit;
    }
}
