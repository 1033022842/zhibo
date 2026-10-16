<?php
/**
 * 谷歌验证码（2FA）绑定/解绑
 * 访问: /admin/live.TotpSetup/index （后台菜单 iframe）
 *
 * 纯本地实现：密钥在服务端生成，二维码用同源 JS 在页面内渲染，
 * otpauth URI 不经过任何第三方（不调用任何在线二维码服务）。
 *
 * 身份识别：后台 iframe 直接打开时 URL 不带 token，故页面 JS 从同源
 * localStorage(adminInfo).token 读取 batoken，随每个 POST 提交，服务端用
 * Token::get() 反查 user_id（仅接受 type=admin）。
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\admin\model\Admin;
use app\common\controller\Backend;
use app\common\library\Totp;
use think\facade\Db;

final class TotpSetup extends Backend
{
    protected array $noNeedLogin = ['index'];

    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    private const CFG_TOTP_ON = 'admin_totp_enabled';

    private function db()
    {
        return Db::connect('live_mysql');
    }

    private function cfg(string $key): string
    {
        return trim((string) $this->db()->table('lp_platform_config')->where('key', $key)->value('value'));
    }

    private function setCfg(string $key, string $value): void
    {
        $db  = $this->db();
        $row = $db->table('lp_platform_config')->where('key', $key)->find();
        if ($row) {
            $db->table('lp_platform_config')->where('key', $key)->update(['value' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            $db->table('lp_platform_config')->insert(['key' => $key, 'value' => $value, 'remark' => '', 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    /**
     * 依据请求里的 batoken 反查当前登录管理员
     * （Backend::initialize 已用 get_auth_token() 解析 batoken 并初始化 $this->auth）
     */
    private function currentAdmin(): ?Admin
    {
        try {
            if (!$this->auth->isLogin()) {
                return null;
            }
            $id = (int) ($this->auth->getInfo()['id'] ?? 0);
        } catch (\Throwable $e) {
            return null;
        }
        return $id > 0 ? (Admin::find($id) ?: null) : null;
    }

    private function json(array $payload): void
    {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function index(): void
    {
        if ($this->request->isPost()) {
            $admin = $this->currentAdmin();
            if (!$admin) {
                $this->json(['code' => 0, 'msg' => '登录态失效，请重新登录后台后再操作']);
            }
            $act = (string) $this->request->post('act', '');

            // ---- 查询当前状态 ----
            if ($act === 'state') {
                $bound  = (int) ($admin->totp_enabled ?? 0) === 1 && trim((string) $admin->totp_secret) !== '';
                $recArr = json_decode((string) $admin->totp_recovery, true);
                $this->json([
                    'code'          => 1,
                    'bound'         => $bound,
                    'global_on'     => $this->cfg(self::CFG_TOTP_ON) === '1',
                    'recovery_left' => is_array($recArr) ? count($recArr) : 0,
                    'username'      => (string) $admin->username,
                ]);
            }

            // ---- 生成新密钥（尚未启用，等验证码确认）----
            if ($act === 'start') {
                $secret = Totp::generateSecret();
                $uri    = Totp::provisioningUri($secret, (string) $admin->username, 'Sugus Admin');
                $this->json(['code' => 1, 'secret' => $secret, 'uri' => $uri]);
            }

            // ---- 确认并启用（校验 6 位码）----
            if ($act === 'confirm') {
                $secret = strtoupper(preg_replace('/\s+/', '', (string) $this->request->post('secret', '')) ?? '');
                $code   = preg_replace('/\D/', '', (string) $this->request->post('code', '')) ?? '';
                if ($secret === '' || !preg_match('/^[A-Z2-7]{16,64}$/', $secret)) {
                    $this->json(['code' => 0, 'msg' => '密钥无效，请重新生成']);
                }
                if (strlen($code) !== 6) {
                    $this->json(['code' => 0, 'msg' => '请输入 6 位动态验证码']);
                }
                if (!Totp::verify($secret, $code)) {
                    $this->json(['code' => 0, 'msg' => '验证码不正确（请确认手机时间准确后重试）']);
                }
                $plain  = Totp::generateRecoveryCodes(8);
                $hashed = array_map(static fn ($c) => hash('sha256', $c), $plain);

                $admin->totp_secret   = $secret;
                $admin->totp_enabled  = 1;
                $admin->totp_recovery = json_encode($hashed);
                $admin->save();
                // 绑定时同步打开全局开关（否则绑定不生效）
                $this->setCfg(self::CFG_TOTP_ON, '1');

                $this->json(['code' => 1, 'msg' => 'ok', 'recovery' => $plain]);
            }

            // ---- 重新生成恢复码（需有效动态码）----
            if ($act === 'regenerate') {
                if (!$this->verifyOwn($admin, (string) $this->request->post('code', ''))) {
                    $this->json(['code' => 0, 'msg' => '动态验证码不正确']);
                }
                $plain  = Totp::generateRecoveryCodes(8);
                $hashed = array_map(static fn ($c) => hash('sha256', $c), $plain);
                $admin->totp_recovery = json_encode($hashed);
                $admin->save();
                $this->json(['code' => 1, 'msg' => 'ok', 'recovery' => $plain]);
            }

            // ---- 关闭 2FA（需有效动态码或恢复码）----
            if ($act === 'disable') {
                if (!$this->verifyOwn($admin, (string) $this->request->post('code', ''))) {
                    $this->json(['code' => 0, 'msg' => '验证码不正确，请输入当前动态码或一次性恢复码']);
                }
                $admin->totp_secret   = '';
                $admin->totp_enabled  = 0;
                $admin->totp_recovery = '[]';
                $admin->save();
                $this->json(['code' => 1, 'msg' => 'ok']);
            }

            // ---- 单独切换全局开关 ----
            if ($act === 'global') {
                $on = (string) $this->request->post('value', '0') === '1' ? '1' : '0';
                $this->setCfg(self::CFG_TOTP_ON, $on);
                $this->json(['code' => 1, 'msg' => 'ok']);
            }

            $this->json(['code' => 0, 'msg' => 'bad act']);
        }

        $this->render();
    }

    /**
     * 校验该账号的动态码或一次性恢复码
     */
    private function verifyOwn(Admin $admin, string $input): bool
    {
        $code = preg_replace('/\D/', '', $input) ?? '';
        $secret = trim((string) $admin->totp_secret);
        if ($secret !== '' && strlen($code) === 6 && Totp::verify($secret, $code)) {
            return true;
        }
        // 恢复码
        $rc = strtoupper(trim($input));
        if (preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $rc)) {
            $hashes = json_decode((string) $admin->totp_recovery, true);
            if (is_array($hashes)) {
                $h    = hash('sha256', $rc);
                $left = [];
                $hit  = false;
                foreach ($hashes as $item) {
                    if (!$hit && is_string($item) && hash_equals($item, $h)) {
                        $hit = true;
                        continue;
                    }
                    $left[] = $item;
                }
                if ($hit) {
                    $admin->totp_recovery = json_encode(array_values($left));
                    $admin->save();
                    return true;
                }
            }
        }
        return false;
    }

    private function render(): void
    {
        $key       = (string) $this->request->param('_key', '');
        $hiddenKey = '<input type="hidden" id="_key" value="' . htmlspecialchars($key, ENT_QUOTES) . '">';

        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>谷歌验证码（2FA）</title>
<script src="/static/js/qrcode-generator.js"></script>
<style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:-apple-system,sans-serif;background:#f5f7fa;color:#333;padding:20px;font-size:14px}
    .container{max-width:760px;margin:0 auto}
    .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:18px}
    h2{font-size:17px;margin-bottom:14px;display:flex;align-items:center;gap:8px}
    h2 .dot{width:6px;height:18px;background:#409eff;border-radius:3px}
    .btn{padding:8px 16px;border:none;border-radius:8px;cursor:pointer;background:#409eff;color:#fff;font-weight:600;font-size:13px}
    .btn.ghost{background:#eef4ff;color:#409eff}
    .btn.danger{background:#fdeaea;color:#e34d59}
    .btn:disabled{opacity:.5;cursor:not-allowed}
    .warn{background:#fff7e6;border:1px solid #ffe1a8;color:#a8761b;border-radius:8px;padding:12px 14px;font-size:12.5px;line-height:1.7;margin-bottom:16px}
    .ok{background:#eefaf3;border:1px solid #b7e6cd;color:#0a8f5c;border-radius:8px;padding:12px 14px;font-size:12.5px;line-height:1.7;margin-bottom:16px}
    .badge{display:inline-block;padding:3px 9px;border-radius:5px;font-size:12px;font-weight:700}
    .badge.on{background:#eefaf3;color:#0a8f5c}
    .badge.off{background:#f2f3f5;color:#999}
    .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px}
    .qr{text-align:center;margin:16px 0}
    .qr svg{width:210px;height:210px;background:#fff;padding:8px;border:1px solid #eee;border-radius:8px}
    .secret{font-family:monospace;font-size:15px;letter-spacing:1px;background:#f2f3f5;padding:9px 12px;border-radius:7px;display:inline-block;word-break:break-all;cursor:pointer}
    .codes{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;font-family:monospace;font-size:15px;margin:12px 0}
    .codes span{background:#f2f3f5;padding:8px 12px;border-radius:7px;letter-spacing:1px;text-align:center}
    input[type=text]{padding:8px 10px;border:1px solid #ddd;border-radius:7px;font-size:14px;width:180px;letter-spacing:2px;font-family:monospace}
    label.lb{font-weight:600;display:block;margin-bottom:6px;font-size:13px;color:#555}
    .muted{color:#999;font-size:12px;line-height:1.7;margin-top:10px}
    #toast{position:fixed;top:18px;right:18px;padding:9px 18px;border-radius:8px;font-size:13px;display:none;z-index:9;color:#fff}
    .steps{color:#666;font-size:13px;line-height:2}
    code{background:#f2f3f5;padding:1px 6px;border-radius:4px;font-family:monospace}
</style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2><span class="dot"></span>谷歌验证码（两步验证 2FA）</h2>
        <div id="loading" class="muted">加载中…</div>
        <div id="body" style="display:none"></div>
    </div>
</div>
<div id="toast"></div>
__HIDDEN_KEY__
<script>
(function () {
    var qrcode = window.qrcode;
    var key = (document.getElementById('_key') || {}).value || '';

    function token() {
        try {
            var raw = localStorage.getItem('adminInfo');
            if (!raw) return '';
            var o = JSON.parse(raw);
            return (o && o.token) ? o.token : '';
        } catch (e) { return ''; }
    }
    function post(act, data) {
        var fd = new FormData();
        fd.append('act', act);
        fd.append('batoken', token());
        for (var k in data) fd.append(k, data[k]);
        var url = '/admin/live.TotpSetup/index' + (key ? ('?_key=' + encodeURIComponent(key)) : '');
        return fetch(url, { method: 'POST', body: fd }).then(function (r) { return r.json() });
    }
    function toast(msg, ok) {
        var el = document.getElementById('toast');
        el.textContent = msg;
        el.style.background = ok === false ? '#e34d59' : '#00a870';
        el.style.display = 'block';
        setTimeout(function () { el.style.display = 'none'; }, 2600);
    }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }); }

    var state = { bound: false, global_on: false, recovery_left: 0, username: '' };

    function refresh() {
        return post('state').then(function (d) {
            if (d.code !== 1) {
                document.getElementById('loading').textContent = d.msg || '无法识别登录状态，请在后台面板内打开本页';
                return;
            }
            state = d;
            paint();
        }).catch(function () {
            document.getElementById('loading').textContent = '网络错误';
        });
    }

    function paint() {
        document.getElementById('loading').style.display = 'none';
        var b = document.getElementById('body');
        b.style.display = 'block';

        var boundBadge = state.bound ? '<span class="badge on">已绑定</span>' : '<span class="badge off">未绑定</span>';
        var globalBadge = state.global_on ? '<span class="badge on">已开启</span>' : '<span class="badge off">已关闭</span>';

        var html = '';
        html += '<div class="row" style="margin-top:0"><b>账号：</b>' + esc(state.username) + '　<b>状态：</b>' + boundBadge + '　<b>全局开关：</b>' + globalBadge + '</div>';

        if (!state.bound) {
            html += '<div class="warn">绑定后，该账号登录时需要输入手机验证器上的 6 位动态码。请妥善保存下面的一次性恢复码，手机丢失时可凭恢复码登录。</div>';
            html += '<div class="steps"><b>操作步骤：</b><br>1）手机安装验证器 App（Google Authenticator / Microsoft Authenticator / 1Password 等）<br>2）点击「生成密钥」，用 App 扫描下方二维码或手动输入密钥<br>3）输入 App 显示的 6 位动态码，点「确认启用」</div>';
            html += '<div class="row"><button class="btn" id="btnStart">生成密钥</button></div>';
            html += '<div id="setup"></div>';
        } else {
            html += '<div class="ok">该账号已启用两步验证。剩余可用一次性恢复码：<b>' + state.recovery_left + '</b> 个。</div>';
            if (!state.global_on) {
                html += '<div class="warn">⚠️ 全局开关当前为「关闭」，绑定不会生效。登录时不会要求动态码。可点击下方按钮开启。</div>';
            }
            html += '<label class="lb">输入当前动态码（6 位）或一次性恢复码，以执行敏感操作：</label><div class="row" style="margin-top:0"><input type="text" id="veriCode" placeholder="6 位动态码"></div>';
            html += '<div class="row">';
            html += '<button class="btn ghost" id="btnRegen">重新生成恢复码</button>';
            if (!state.global_on) {
                html += '<button class="btn" id="btnGlobalOn">开启全局开关</button>';
            } else {
                html += '<button class="btn ghost" id="btnGlobalOff">关闭全局开关</button>';
            }
            html += '<button class="btn danger" id="btnDisable">关闭本账号 2FA</button>';
            html += '</div>';
            html += '<div class="muted">关闭全局开关后，所有账号登录都不再要求动态码（但绑定信息保留）。</div>';
        }
        b.innerHTML = html;

        var startBtn = document.getElementById('btnStart');
        if (startBtn) startBtn.addEventListener('click', doStart);

        var regen = document.getElementById('btnRegen');
        if (regen) regen.addEventListener('click', doRegen);
        var dis = document.getElementById('btnDisable');
        if (dis) dis.addEventListener('click', doDisable);
        var gon = document.getElementById('btnGlobalOn');
        if (gon) gon.addEventListener('click', function () { doGlobal('1', '已开启全局开关，登录将要求动态码'); });
        var goff = document.getElementById('btnGlobalOff');
        if (goff) goff.addEventListener('click', function () { doGlobal('0', '已关闭全局开关'); });
    }

    var pendingSecret = '';

    function doStart() {
        post('start').then(function (d) {
            if (d.code !== 1) { toast(d.msg, false); return; }
            pendingSecret = d.secret;
            var box = document.getElementById('setup');
            var svg = '';
            try {
                var qr = qrcode(0, 'M');
                qr.addData(d.uri);
                qr.make();
                svg = qr.createSvgTag(5, 2);
            } catch (e) { svg = '<div class="muted">二维码生成失败，请手动输入下方密钥</div>'; }
            box.innerHTML =
                '<div class="qr">' + svg + '</div>' +
                '<div style="text-align:center"><span class="secret" id="secretTxt" title="点击复制">' + esc(d.secret) + '</span></div>' +
                '<div class="muted" style="text-align:center">二维码无法扫描时，可在验证器里手动输入上面的密钥（点击可复制）。</div>' +
                '<div class="row" style="justify-content:center">' +
                '<input type="text" id="codeInput" maxlength="6" placeholder="6 位动态码">' +
                '<button class="btn" id="btnConfirm">确认启用</button>' +
                '</div>' +
                '<div id="recoveryBox"></div>';
            var st = document.getElementById('secretTxt');
            if (st) st.addEventListener('click', function () {
                try { navigator.clipboard.writeText(d.secret); toast('密钥已复制'); } catch (e) {}
            });
            var ci = document.getElementById('codeInput');
            if (ci) ci.addEventListener('keyup', function (e) { if (e.key === 'Enter') doConfirm(); });
            document.getElementById('btnConfirm').addEventListener('click', doConfirm);
        }).catch(function () { toast('网络错误', false); });
    }

    function doConfirm() {
        var code = (document.getElementById('codeInput') || {}).value || '';
        var btn = document.getElementById('btnConfirm');
        if (btn) btn.disabled = true;
        post('confirm', { secret: pendingSecret, code: code }).then(function (d) {
            if (btn) btn.disabled = false;
            if (d.code !== 1) { toast(d.msg, false); return; }
            showRecovery(d.recovery);
            toast('已启用两步验证');
        }).catch(function () { if (btn) btn.disabled = false; toast('网络错误', false); });
    }

    function doRegen() {
        var code = (document.getElementById('veriCode') || {}).value || '';
        post('regenerate', { code: code }).then(function (d) {
            if (d.code !== 1) { toast(d.msg, false); return; }
            showRecovery(d.recovery);
            toast('恢复码已重新生成');
        }).catch(function () { toast('网络错误', false); });
    }

    function doDisable() {
        var code = (document.getElementById('veriCode') || {}).value || '';
        if (!confirm('确定关闭本账号的两步验证？关闭后登录只需密码。')) return;
        post('disable', { code: code }).then(function (d) {
            if (d.code !== 1) { toast(d.msg, false); return; }
            toast('已关闭两步验证');
            refresh();
        }).catch(function () { toast('网络错误', false); });
    }

    function doGlobal(val, msg) {
        post('global', { value: val }).then(function (d) {
            if (d.code !== 1) { toast(d.msg, false); return; }
            toast(msg);
            refresh();
        }).catch(function () { toast('网络错误', false); });
    }

    function showRecovery(codes) {
        var box = document.getElementById('recoveryBox') || document.getElementById('body');
        var html = '<div class="warn" style="margin-top:16px">🔑 <b>一次性恢复码（请立即抄写保存，关闭后不再显示）</b><br>手机丢失/更换时，可用其中一个恢复码代替动态码登录；每个恢复码仅能用一次。</div>';
        html += '<div class="codes">';
        (codes || []).forEach(function (c) { html += '<span>' + esc(c) + '</span>'; });
        html += '</div>';
        html += '<div class="row"><button class="btn ghost" id="btnCopyRc">复制全部</button><button class="btn ghost" id="btnRcDone">我已保存</button></div>';
        box.innerHTML = html;
        document.getElementById('btnCopyRc').addEventListener('click', function () {
            try { navigator.clipboard.writeText((codes || []).join('\n')); toast('已复制'); } catch (e) {}
        });
        document.getElementById('btnRcDone').addEventListener('click', function () { refresh(); });
    }

    refresh();
})();
</script>
</body>
</html>
HTML;
        echo str_replace('__HIDDEN_KEY__', $hiddenKey, $html);
        exit;
    }
}
