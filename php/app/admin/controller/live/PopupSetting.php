<?php
/**
 * 登录后弹窗设置
 * 访问: /admin/live.PopupSetting/index?_key=<ADMIN_FLOAT_KEY>
 *
 * 控制两个登录后弹窗：
 *   1) 创作指南（Creator Earning Guide，js/creator-guide.js）
 *   2) 18+ 进场弹窗（js/promo-popup.js）
 *
 * 每个弹窗可配置：开关、延迟秒数、弹出频率、间隔小时。
 * 配置存 lp_platform_config，前台通过 GET /api/live/popupConfig 读取。
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use think\facade\Db;

final class PopupSetting extends Backend
{
    protected array $noNeedLogin = ['index', 'save'];

    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    private const FIELDS = [
        'popup_guide_enabled'  => ['default' => '1',       'remark' => '创作指南弹窗:1开 0关'],
        'popup_guide_delay'    => ['default' => '1',       'remark' => '创作指南弹窗延迟秒数(0-60)'],
        'popup_guide_freq'     => ['default' => 'login',   'remark' => '创作指南弹窗频率:login/session/interval/always/once'],
        'popup_guide_interval' => ['default' => '24',      'remark' => '创作指南弹窗间隔小时(1-720)'],
        'popup_promo_enabled'  => ['default' => '1',       'remark' => '18+进场弹窗:1开 0关'],
        'popup_promo_delay'    => ['default' => '1',       'remark' => '18+进场弹窗延迟秒数(0-60)'],
        'popup_promo_freq'     => ['default' => 'session', 'remark' => '18+进场弹窗频率:login/session/interval/always/once'],
        'popup_promo_interval' => ['default' => '24',      'remark' => '18+进场弹窗间隔小时(1-720)'],
    ];

    private const FREQS = ['login', 'session', 'interval', 'always', 'once'];

    private function db()
    {
        return Db::connect('live_mysql');
    }

    public function index(): void
    {
        $values = [];
        foreach (self::FIELDS as $key => $def) {
            $val = (string) $this->db()->table('lp_platform_config')->where('key', $key)->value('value');
            $values[$key] = $val !== '' ? $val : $def['default'];
        }

        $sel = static function (array $options, string $current): string {
            $html = '';
            foreach ($options as $value => $label) {
                $checked = ((string) $value === $current) ? ' selected' : '';
                $html .= '<option value="' . htmlspecialchars((string) $value, ENT_QUOTES) . '"' . $checked . '>'
                    . htmlspecialchars($label, ENT_QUOTES) . '</option>';
            }
            return $html;
        };
        $enabledOptions = ['1' => '开启', '0' => '关闭'];
        $freqOptions = [
            'login'    => '每次登录后弹一次',
            'session'  => '每个浏览器会话弹一次',
            'interval' => '按间隔时间弹',
            'always'   => '每次打开页面都弹',
            'once'     => '只弹一次（之后不再弹）',
        ];

        $gEnabled = $sel($enabledOptions, $values['popup_guide_enabled']);
        $gFreq    = $sel($freqOptions, $values['popup_guide_freq']);
        $pEnabled = $sel($enabledOptions, $values['popup_promo_enabled']);
        $pFreq    = $sel($freqOptions, $values['popup_promo_freq']);
        $gDelay   = (int) $values['popup_guide_delay'];
        $gInter   = (int) $values['popup_guide_interval'];
        $pDelay   = (int) $values['popup_promo_delay'];
        $pInter   = (int) $values['popup_promo_interval'];

        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>登录后弹窗设置</title>
<style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f5f7fa;color:#333;padding:20px;font-size:14px}
    .container{max-width:760px;margin:0 auto}
    .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:18px}
    h2{font-size:17px;margin-bottom:4px;display:flex;align-items:center;gap:8px}
    h2 .dot{width:6px;height:18px;background:#409eff;border-radius:3px}
    .sub{font-size:12px;color:#999;margin:0 0 16px 14px;line-height:1.6}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px 18px}
    .field label{display:block;font-size:12.5px;color:#666;font-weight:600;margin-bottom:6px}
    select,input[type=number]{width:100%;height:38px;padding:0 10px;border:1px solid #dcdfe6;border-radius:8px;font-size:13px;background:#fff;outline:none}
    select:focus,input[type=number]:focus{border-color:#409eff}
    .tip{font-size:12px;color:#999;line-height:1.8;margin-top:14px;background:#fafbfc;border:1px solid #eef0f3;border-radius:8px;padding:10px 12px}
    .tip b{color:#606266}
    .actions{display:flex;align-items:center;gap:12px}
    .btn{padding:10px 26px;border:none;border-radius:8px;cursor:pointer;background:#409eff;color:#fff;font-weight:700;font-size:14px}
    .btn:disabled{opacity:.6;cursor:not-allowed}
    .state{font-size:12.5px;color:#909399}
    .toast{position:fixed;top:18px;right:18px;padding:10px 18px;border-radius:8px;font-size:13px;color:#fff;display:none;z-index:9}
    .toast.ok{background:#00a870}
    .toast.err{background:#e34d59}
    .freq-hint{font-size:11.5px;color:#a8abb2;margin-top:5px}
</style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2><span class="dot"></span>创作指南弹窗（Creator Earning Guide）</h2>
        <p class="sub">登录后自动弹出的创作收益引导。用户点过「Don't show again」后仍会永久关闭，除非清浏览器数据。</p>
        <div class="grid">
            <div class="field">
                <label>开关</label>
                <select id="g_enabled">{$gEnabled}</select>
            </div>
            <div class="field">
                <label>延迟弹出（秒）</label>
                <input type="number" id="g_delay" min="0" max="60" value="{$gDelay}">
            </div>
            <div class="field">
                <label>弹出频率</label>
                <select id="g_freq">{$gFreq}</select>
                <div class="freq-hint">「按间隔时间弹」需配合下方小时数</div>
            </div>
            <div class="field">
                <label>间隔（小时）</label>
                <input type="number" id="g_interval" min="1" max="720" value="{$gInter}">
            </div>
        </div>
    </div>

    <div class="card">
        <h2><span class="dot" style="background:#e65073"></span>18+ 进场弹窗（Promo）</h2>
        <p class="sub">登录用户进入页面时弹出的换脸推广弹窗，点击跳转 face_swap.html。</p>
        <div class="grid">
            <div class="field">
                <label>开关</label>
                <select id="p_enabled">{$pEnabled}</select>
            </div>
            <div class="field">
                <label>延迟弹出（秒）</label>
                <input type="number" id="p_delay" min="0" max="60" value="{$pDelay}">
            </div>
            <div class="field">
                <label>弹出频率</label>
                <select id="p_freq">{$pFreq}</select>
                <div class="freq-hint">「按间隔时间弹」需配合下方小时数</div>
            </div>
            <div class="field">
                <label>间隔（小时）</label>
                <input type="number" id="p_interval" min="1" max="720" value="{$pInter}">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="actions">
            <button class="btn" id="saveBtn" type="button">保存设置</button>
            <span class="state" id="stateText">修改后立即生效，前端下次加载页面时读取</span>
        </div>
        <div class="tip">
            <b>频率说明：</b>每次登录后弹一次（同一登录只弹一次） / 每个浏览器会话弹一次 /
            按间隔时间弹（每 N 小时最多一次） / 每次打开页面都弹 / 只弹一次（本机永久不再弹）。<br>
            <b>提示：</b>用户关闭弹窗只影响当前会话；「只弹一次」和创作指南的「Don't show again」会写入浏览器本地存储。
        </div>
    </div>
</div>
<div class="toast" id="toast"></div>
<script>
(function () {
    var q = location.search || '';
    var toast = document.getElementById('toast');
    var btn = document.getElementById('saveBtn');

    function flash(ok, msg) {
        toast.className = 'toast ' + (ok ? 'ok' : 'err');
        toast.textContent = msg;
        toast.style.display = 'block';
        setTimeout(function () { toast.style.display = 'none'; }, 2200);
    }

    function num(id) {
        return String(parseInt(document.getElementById(id).value, 10) || 0);
    }

    btn.addEventListener('click', function () {
        var fd = new FormData();
        fd.append('popup_guide_enabled', document.getElementById('g_enabled').value);
        fd.append('popup_guide_delay', num('g_delay'));
        fd.append('popup_guide_freq', document.getElementById('g_freq').value);
        fd.append('popup_guide_interval', num('g_interval'));
        fd.append('popup_promo_enabled', document.getElementById('p_enabled').value);
        fd.append('popup_promo_delay', num('p_delay'));
        fd.append('popup_promo_freq', document.getElementById('p_freq').value);
        fd.append('popup_promo_interval', num('p_interval'));

        btn.disabled = true;
        fetch('/admin/live.PopupSetting/save' + q, { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d && d.code === 1) {
                    flash(true, d.msg || '已保存');
                    document.getElementById('stateText').textContent = '已保存 · ' + new Date().toLocaleTimeString();
                } else {
                    flash(false, (d && d.msg) || '保存失败');
                }
            })
            .catch(function () { flash(false, '网络错误，请重试') })
            .finally(function () { btn.disabled = false; });
    });
})();
</script>
</body>
</html>
HTML;
        exit;
    }

    public function save(): void
    {
        $validFreqs = self::FREQS;
        $updates = [];

        $updates['popup_guide_enabled'] = (string) $this->request->post('popup_guide_enabled', '0') === '1' ? '1' : '0';
        $updates['popup_guide_delay'] = (string) max(0, min(60, (int) $this->request->post('popup_guide_delay', 1)));
        $guideFreq = (string) $this->request->post('popup_guide_freq', 'login');
        $updates['popup_guide_freq'] = in_array($guideFreq, $validFreqs, true) ? $guideFreq : 'login';
        $updates['popup_guide_interval'] = (string) max(1, min(720, (int) $this->request->post('popup_guide_interval', 24)));

        $updates['popup_promo_enabled'] = (string) $this->request->post('popup_promo_enabled', '0') === '1' ? '1' : '0';
        $updates['popup_promo_delay'] = (string) max(0, min(60, (int) $this->request->post('popup_promo_delay', 1)));
        $promoFreq = (string) $this->request->post('popup_promo_freq', 'session');
        $updates['popup_promo_freq'] = in_array($promoFreq, $validFreqs, true) ? $promoFreq : 'session';
        $updates['popup_promo_interval'] = (string) max(1, min(720, (int) $this->request->post('popup_promo_interval', 24)));

        $db = $this->db();
        $now = date('Y-m-d H:i:s');
        foreach ($updates as $key => $value) {
            $remark = (string) (self::FIELDS[$key]['remark'] ?? '');
            $row = $db->table('lp_platform_config')->where('key', $key)->find();
            if ($row) {
                $db->table('lp_platform_config')->where('key', $key)->update([
                    'value'      => $value,
                    'updated_at' => $now,
                ]);
            } else {
                $db->table('lp_platform_config')->insert([
                    'key'        => $key,
                    'value'      => $value,
                    'remark'     => $remark,
                    'updated_at' => $now,
                ]);
            }
        }

        echo json_encode(['code' => 1, 'msg' => '已保存，前端下次加载页面生效'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
