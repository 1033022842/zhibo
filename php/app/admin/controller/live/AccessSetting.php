<?php
/**
 * 访问控制（后台 IP 白名单）
 * 访问: /admin/live.AccessSetting/index?_key=<ADMIN_FLOAT_KEY>
 *
 * 应用层拦截：总开关开启后，非白名单 IP 访问 /admin 全部 403。
 * 防锁死：默认关闭；「一键加入当前IP」；{?_ipkey=<ADMIN_FLOAT_KEY>} 应急旁路。
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use think\facade\Db;

final class AccessSetting extends Backend
{
    protected array $noNeedLogin = ['index', 'save', 'toggle', 'del', 'addCurrent'];

    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    private function db()
    {
        return Db::connect('live_mysql');
    }

    public function index(): void
    {
        $db = $this->db();

        $enabled = (string) $db->table('lp_platform_config')
            ->where('key', 'admin_ip_whitelist_enabled')->value('value');
        $enabled = $enabled === '1';

        // POST：保存开关 / 增删
        if ($this->request->isPost()) {
            $act = (string) $this->request->post('act', '');

            if ($act === 'toggle') {
                $on = (string) $this->request->post('value', '0') === '1' ? '1' : '0';
                // 打开总闸时，若白名单为空则拒绝（避免直接锁死所有外部 IP）
                if ($on === '1' && (int) $db->table('lp_admin_ip_whitelist')->count() === 0) {
                    echo json_encode(['code' => 0, 'msg' => '白名单为空，请先添加至少一个 IP 再开启（建议先点「加入当前IP」）'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $this->saveConfig('admin_ip_whitelist_enabled', $on);
                echo json_encode(['code' => 1, 'msg' => 'ok'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($act === 'add') {
                $ip     = trim((string) $this->request->post('ip', ''));
                $remark = mb_substr(trim((string) $this->request->post('remark', '')), 0, 120);
                if ($ip === '') {
                    echo json_encode(['code' => 0, 'msg' => 'IP 不能为空'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                if (!preg_match('#^[0-9a-fA-F:.]+(/\d{1,3})?$#', $ip)) {
                    echo json_encode(['code' => 0, 'msg' => '格式不正确（单个IP如 1.2.3.4 或网段 1.2.3.0/24）'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $exists = $db->table('lp_admin_ip_whitelist')->where('ip', $ip)->find();
                if ($exists) {
                    echo json_encode(['code' => 0, 'msg' => '该 IP 已在白名单中'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $db->table('lp_admin_ip_whitelist')->insert(['ip' => $ip, 'remark' => $remark, 'created_at' => date('Y-m-d H:i:s')]);
                echo json_encode(['code' => 1, 'msg' => 'ok'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($act === 'del') {
                $id = (int) $this->request->post('id', 0);
                if ($id > 0) {
                    $db->table('lp_admin_ip_whitelist')->where('id', $id)->delete();
                }
                echo json_encode(['code' => 1, 'msg' => 'ok'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode(['code' => 0, 'msg' => 'bad act'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $rows     = $db->table('lp_admin_ip_whitelist')->order('id', 'desc')->select()->toArray();
        $curIp    = (string) $this->request->ip();
        $curInList = false;
        $tr = '';
        foreach ($rows as $r) {
            $id     = (int) $r['id'];
            $ip     = htmlspecialchars((string) $r['ip'], ENT_QUOTES);
            $remark = htmlspecialchars((string) $r['remark'], ENT_QUOTES);
            $time   = htmlspecialchars((string) $r['created_at'], ENT_QUOTES);
            if ((string) $r['ip'] === $curIp) { $curInList = true; }
            $tr .= "<tr data-id=\"{$id}\"><td>{$ip}</td><td>{$remark}</td><td style=\"color:#999\">{$time}</td>"
                . "<td><button class=\"btn-mini del\" data-id=\"{$id}\" style=\"background:#fdeaea;color:#e34d59\">删除</button></td></tr>";
        }
        $emptyRow = $rows ? '' : '<tr><td colspan="4" style="text-align:center;color:#999;padding:24px">暂无白名单 IP</td></tr>';
        $curIpHtml = htmlspecialchars($curIp, ENT_QUOTES);
        $key = (string) $this->request->param('_key', '');
        $hiddenKey = $key !== '' ? '<input type="hidden" id="_key" value="' . htmlspecialchars($key, ENT_QUOTES) . '">' : '<input type="hidden" id="_key" value="">';
        $onChecked = $enabled ? 'checked' : '';
        $stateText = $enabled
            ? '<span style="color:#00a870;font-weight:700">已开启</span>'
            : '<span style="color:#999;font-weight:700">已关闭（默认）</span>';

        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>访问控制 · IP 白名单</title>
<style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:-apple-system,sans-serif;background:#f5f7fa;color:#333;padding:20px;font-size:14px}
    .container{max-width:860px;margin:0 auto}
    .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:18px}
    h2{font-size:17px;margin-bottom:14px;display:flex;align-items:center;gap:8px}
    h2 .dot{width:6px;height:18px;background:#409eff;border-radius:3px}
    table{width:100%;border-collapse:collapse;font-size:13px}
    th,td{padding:9px 8px;border-bottom:1px solid #eef0f3;text-align:left}
    th{color:#888;font-weight:600;background:#fafbfc}
    input[type=text]{padding:7px 9px;border:1px solid #ddd;border-radius:6px;font-size:13px;width:230px}
    .btn{padding:8px 16px;border:none;border-radius:8px;cursor:pointer;background:#409eff;color:#fff;font-weight:600;font-size:13px}
    .btn.ghost{background:#eef4ff;color:#409eff}
    .btn.danger{background:#eef4ff;color:#e34d59}
    .btn-mini{padding:4px 12px;font-size:12px;border:none;border-radius:6px;cursor:pointer;font-weight:600}
    .tip{font-size:12px;color:#999;margin-top:12px;line-height:1.7}
    .warn{background:#fff7e6;border:1px solid #ffe1a8;color:#a8761b;border-radius:8px;padding:12px 14px;font-size:12.5px;line-height:1.7;margin-bottom:16px}
    .switch{display:inline-flex;align-items:center;gap:10px;font-weight:600}
    .ip-cur{background:#eefaf3;color:#0a8f5c;padding:3px 8px;border-radius:5px;font-family:monospace}
    .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .ok-toast{position:fixed;top:18px;right:18px;background:#00a870;color:#fff;padding:9px 18px;border-radius:8px;font-size:13px;display:none;z-index:9}
    .err-toast{position:fixed;top:18px;right:18px;background:#e34d59;color:#fff;padding:9px 18px;border-radius:8px;font-size:13px;display:none;z-index:9}
</style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2><span class="dot"></span>后台 IP 白名单</h2>
        <div class="warn">
            ⚠️ 开启后，<b>不在白名单内的 IP 将完全无法访问后台</b>（包含登录页与所有接口）。<br>
            保险机制：①默认关闭；②开启前必须先加至少一个 IP；③把当前 IP 加入可用「一键加入当前 IP」；
            ④万一被锁，可在后台地址后加 <code>?_ipkey=&lt;浮动密钥&gt;</code> 应急进入。</div>
        <div class="row" style="margin-bottom:6px">
            <span class="switch">总开关：<label style="cursor:pointer"><input type="checkbox" id="sw" {$onChecked} style="transform:scale(1.3)"> </label> {$stateText}</span>
        </div>
        <div class="row">
            <span>当前你的访问 IP：<span class="ip-cur">{$curIpHtml}</span></span>
            <button class="btn ghost" id="addCur">一键加入当前 IP</button>
        </div>
    </div>

    <div class="card">
        <h2><span class="dot"></span>白名单列表（支持单个 IP 与 CIDR 网段）</h2>
        <table>
            <thead><tr><th>IP / 网段</th><th>备注</th><th>添加时间</th><th>操作</th></tr></thead>
            <tbody>
                {$tr}
                {$emptyRow}
            </tbody>
        </table>
        <div class="row" style="margin-top:14px">
            <input type="text" id="newIp" placeholder="如 1.2.3.4 或 10.0.0.0/8">
            <input type="text" id="newRemark" placeholder="备注（可选）" style="width:180px">
            <button class="btn" id="addBtn">添加</button>
        </div>
        <div class="tip">白名单为空时无法开启总开关。加 CDN/换出口 IP 后记得同步更新，否则会把自己关在门外。</div>
    </div>
</div>
<div class="ok-toast" id="toastOk">已保存</div>
<div class="err-toast" id="toastErr">失败</div>
{$hiddenKey}
<script>
(function () {
    var key = (document.getElementById('_key') || {}).value || '';
    function q(extra) { return key ? ('?_key=' + encodeURIComponent(key) + (extra ? '&' + extra : '')) : (extra ? '?' + extra : ''); }
    function flash(ok, msg) {
        var el = document.getElementById(ok ? 'toastOk' : 'toastErr');
        el.textContent = msg || (ok ? '已保存' : '失败');
        el.style.display = 'block'; setTimeout(function () { el.style.display = 'none' }, 1800);
    }
    function post(act, data) {
        var fd = new FormData(); fd.append('act', act);
        for (var k in data) fd.append(k, data[k]);
        return fetch('/admin/live.AccessSetting/index' + q(), { method: 'POST', body: fd })
            .then(function (r) { return r.json() });
    }
    document.getElementById('sw').addEventListener('change', function () {
        var on = this.checked ? '1' : '0';
        post('toggle', { value: on }).then(function (d) {
            if (d.code === 1) { flash(true, on === '1' ? '已开启白名单' : '已关闭白名单'); setTimeout(function(){location.reload()}, 700) }
            else { flash(false, d.msg); document.getElementById('sw').checked = !this.checked; }
        }.bind(this)).catch(function () { flash(false, '网络错误') });
    });
    document.getElementById('addCur').addEventListener('click', function () {
        post('add', { ip: '{$curIp}', remark: '本机当前IP(一键加入)' }).then(function (d) {
            if (d.code === 1) { flash(true, '已加入当前 IP'); setTimeout(function(){location.reload()}, 700) }
            else { flash(false, d.msg) }
        }).catch(function () { flash(false, '网络错误') });
    });
    document.getElementById('addBtn').addEventListener('click', function () {
        var ip = document.getElementById('newIp').value.trim();
        var rk = document.getElementById('newRemark').value.trim();
        if (!ip) { flash(false, '请填写 IP'); return }
        post('add', { ip: ip, remark: rk }).then(function (d) {
            if (d.code === 1) { flash(true, '已添加'); setTimeout(function(){location.reload()}, 700) }
            else { flash(false, d.msg) }
        }).catch(function () { flash(false, '网络错误') });
    });
    document.querySelectorAll('.del').forEach(function (b) {
        b.addEventListener('click', function () {
            if (!confirm('确定删除该白名单 IP？')) return;
            post('del', { id: b.getAttribute('data-id') }).then(function (d) {
                if (d.code === 1) { flash(true, '已删除'); setTimeout(function(){location.reload()}, 600) }
                else { flash(false, d.msg) }
            }).catch(function () { flash(false, '网络错误') });
        });
    });
})();
</script>
</body>
</html>
HTML;
        exit;
    }

    private function saveConfig(string $key, string $value): void
    {
        $db = $this->db();
        $row = $db->table('lp_platform_config')->where('key', $key)->find();
        if ($row) {
            $db->table('lp_platform_config')->where('key', $key)->update(['value' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            $db->table('lp_platform_config')->insert(['key' => $key, 'value' => $value, 'remark' => '', 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }
}
