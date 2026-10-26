<?php
/**
 * 站点首页设置
 * 访问: /admin/live.SiteHome/index?_key=<ADMIN_FLOAT_KEY>
 *
 * 作用：决定访客访问站点根路径 `/` 时落到哪个页面。
 *   - 默认：保持原落地页 index.html（不跳转）
 *   - 指定：302 到所选页面（如 Home.html / Image.html / Shorts.html）
 *
 * 生效链路：
 *   1) 配置写 lp_platform_config（key: site_home_page），供 /api/live/siteConfig 读取
 *   2) 同时写站点根目录 .state/home.json，供根目录 index.php 零依赖读取后做 302
 *   3) nginx 需把站点 index 设为 `index index.php index.html;` 并放行 `location = /index.php`
 *
 * 只影响 `/`，直接访问 /index.html 仍是原落地页（老链接、投放统计不断）。
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use think\facade\Db;

final class SiteHome extends Backend
{
    protected array $noNeedLogin = ['index', 'save'];

    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    private const CONFIG_KEY = 'site_home_page';

    /** 未设置时的默认首页（保持线上现状：/ → /ai/Home.html） */
    private const DEFAULT_PAGE = 'Home.html';

    private function db()
    {
        return Db::connect('live_mysql');
    }

    private function siteDir(): string
    {
        $dir = (string) env('site.html_dir', '');
        return rtrim($dir !== '' ? $dir : '/www/wwwroot/douyin/ai-girl-malaysia.com', '/');
    }

    private function stateFile(): string
    {
        return $this->siteDir() . '/.state/home.json';
    }

    /**
     * 扫描站点可选的 HTML 页面，顺带取 <title> 方便运营辨认
     *
     * @return array<string,string> 文件名 => 展示名
     */
    private function pages(): array
    {
        $dir = $this->siteDir();
        $out = [];
        foreach (glob($dir . '/*.html') ?: [] as $path) {
            $name = basename($path);
            if (preg_match('/\.bak|\.old|\.copy/i', $name)) {
                continue;
            }
            $title = '';
            $fh = @fopen($path, 'r');
            if ($fh) {
                $head = (string) fread($fh, 4096);
                fclose($fh);
                if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $head, $m)) {
                    $title = trim(preg_replace('/\s+/', ' ', strip_tags($m[1])));
                }
            }
            $out[$name] = $title !== '' ? ($name . ' — ' . $title) : $name;
        }
        ksort($out);
        return $out;
    }

    private function current(): string
    {
        $val = (string) $this->db()->table('lp_platform_config')
            ->where('key', self::CONFIG_KEY)->value('value');
        return trim($val);
    }

    public function index(): void
    {
        $pages = $this->pages();
        $current = $this->current();
        if ($current !== '' && !isset($pages[$current])) {
            $current = ''; // 页面已被删除 → 视为默认
        }

        $options = '<option value=""' . ($current === '' ? ' selected' : '') . '>默认：' . self::DEFAULT_PAGE . '（保持当前线上首页）</option>';
        foreach ($pages as $name => $label) {
            $sel = ($name === $current) ? ' selected' : '';
            $options .= '<option value="' . htmlspecialchars($name, ENT_QUOTES) . '"' . $sel . '>'
                . htmlspecialchars($label, ENT_QUOTES) . '</option>';
        }
        $count = count($pages);
        $dir = htmlspecialchars($this->siteDir(), ENT_QUOTES);
        $preview = $current !== '' ? ('<a class="link" href="/' . htmlspecialchars($current, ENT_QUOTES) . '" target="_blank">预览当前首页 →</a>') : '';
        $stateOk = is_dir($this->siteDir() . '/.state') ? '已就绪' : '首次保存时自动创建';
        $stateClass = is_dir($this->siteDir() . '/.state') ? 'ok' : 'warn';

        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>站点首页设置</title>
<style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f5f7fa;color:#333;padding:20px;font-size:14px}
    .container{max-width:760px;margin:0 auto}
    .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:18px}
    h2{font-size:17px;margin-bottom:4px;display:flex;align-items:center;gap:8px}
    h2 .dot{width:6px;height:18px;background:#409eff;border-radius:3px}
    .sub{font-size:12px;color:#999;margin:0 0 16px 14px;line-height:1.7}
    .field label{display:block;font-size:12.5px;color:#666;font-weight:600;margin-bottom:6px}
    select{width:100%;height:38px;padding:0 10px;border:1px solid #dcdfe6;border-radius:8px;font-size:13px;background:#fff;outline:none}
    select:focus{border-color:#409eff}
    .actions{display:flex;align-items:center;gap:12px;margin-top:6px}
    .btn{padding:10px 26px;border:none;border-radius:8px;cursor:pointer;background:#409eff;color:#fff;font-weight:700;font-size:14px}
    .btn.ghost{background:#fff;color:#606266;border:1px solid #dcdfe6;font-weight:600}
    .btn:disabled{opacity:.6;cursor:not-allowed}
    .state{font-size:12.5px;color:#909399}
    .tip{font-size:12px;color:#909399;line-height:1.9;margin-top:14px;background:#fafbfc;border:1px solid #eef0f3;border-radius:8px;padding:10px 12px}
    .tip b{color:#606266}
    .link{color:#409eff;text-decoration:none;font-size:12.5px}
    .badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11.5px;margin-left:6px}
    .badge.ok{background:#e8f8f1;color:#0a8f5f}
    .badge.warn{background:#fdf3e7;color:#b26a00}
    .toast{position:fixed;top:18px;right:18px;padding:10px 18px;border-radius:8px;font-size:13px;color:#fff;display:none;z-index:9;max-width:60vw}
    .toast.ok{background:#00a870}
    .toast.err{background:#e34d59}
</style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2><span class="dot"></span>站点首页</h2>
        <p class="sub">决定访客打开站点根路径 <code>/</code> 时看到的页面。共扫描到 {$count} 个可选页面。</p>
        <div class="field">
            <label>首页指向</label>
            <select id="homePage">{$options}</select>
        </div>
        <div class="actions">
            <button class="btn" id="saveBtn" type="button">保存</button>
            <button class="btn ghost" id="resetBtn" type="button">恢复默认</button>
            {$preview}
        </div>
        <div class="tip">
            <b>生效范围：</b>只影响 <code>/</code> 和 <code>/ai/</code>（访客从域名根进来时）。直接访问 <code>/X.html</code> 的链接不受影响。<br>
            <b>跳转方式：</b>302 临时跳转，随时可改回默认，不影响搜索收录里的旧落地页。<br>
            <b>站点目录：</b>{$dir}　<span class="badge {$stateClass}">状态文件 {$stateOk}</span>
        </div>
    </div>
</div>
<div class="toast" id="toast"></div>
<script>
(function () {
    var q = location.search || '';
    var toast = document.getElementById('toast');
    var sel = document.getElementById('homePage');
    var saveBtn = document.getElementById('saveBtn');
    var resetBtn = document.getElementById('resetBtn');

    function flash(ok, msg) {
        toast.className = 'toast ' + (ok ? 'ok' : 'err');
        toast.textContent = msg;
        toast.style.display = 'block';
        setTimeout(function () { toast.style.display = 'none'; }, 2600);
    }

    function save(page) {
        var fd = new FormData();
        fd.append('home_page', page);
        saveBtn.disabled = true;
        resetBtn.disabled = true;
        fetch('/admin/live.SiteHome/save' + q, { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d && d.code === 1) {
                    flash(true, d.msg || '已保存');
                    setTimeout(function () { location.reload(); }, 700);
                } else {
                    flash(false, (d && d.msg) || '保存失败');
                }
            })
            .catch(function () { flash(false, '网络错误，请重试') })
            .finally(function () { saveBtn.disabled = false; resetBtn.disabled = false; });
    }

    saveBtn.addEventListener('click', function () { save(sel.value); });
    resetBtn.addEventListener('click', function () { save(''); });
})();
</script>
</body>
</html>
HTML;
        exit;
    }

    public function save(): void
    {
        $page = trim((string) $this->request->post('home_page', ''));
        $pages = $this->pages();
        if ($page !== '' && !isset($pages[$page])) {
            echo json_encode(['code' => 0, 'msg' => '页面不存在：' . $page], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 1) 写数据库（前台接口用）
        $db = $this->db();
        $now = date('Y-m-d H:i:s');
        $row = $db->table('lp_platform_config')->where('key', self::CONFIG_KEY)->find();
        if ($row) {
            $db->table('lp_platform_config')->where('key', self::CONFIG_KEY)
                ->update(['value' => $page, 'updated_at' => $now]);
        } else {
            $db->table('lp_platform_config')->insert([
                'key'        => self::CONFIG_KEY,
                'value'      => $page,
                'remark'     => '站点首页：空=默认 index.html，其它=302 到该页面',
                'updated_at' => $now,
            ]);
        }

        // 2) 写状态文件（根目录 index.php 零依赖读取；静态目录 www-data 不可写时给出明确报错）
        $dir = $this->siteDir() . '/.state';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            echo json_encode(['code' => 0, 'msg' => '状态目录创建失败：' . $dir . '（需 chown www-data）'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $payload = json_encode([
            'page'       => $page,
            'updated_at' => $now,
        ], JSON_UNESCAPED_UNICODE);
        if (@file_put_contents($dir . '/home.json', $payload) === false) {
            echo json_encode(['code' => 0, 'msg' => '状态文件写入失败：' . $dir . '/home.json'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'code' => 1,
            'msg'  => $page === '' ? ('已恢复默认（' . self::DEFAULT_PAGE . '）') : ('已保存，首页 → /' . $page),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
