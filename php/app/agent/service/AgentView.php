<?php
declare(strict_types=1);

namespace app\agent\service;

use app\live\service\AgentService;

/**
 * 代理门户页面骨架（服务端渲染，避免额外前端构建）
 */
final class AgentView
{
    public static function h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /** 带侧栏的代理后台页面 */
    public static function page(array $agent, string $active, string $title, string $content, string $csrf): string
    {
        $name    = self::h($agent['name'] ?? '代理');
        $no      = self::h($agent['agent_no'] ?? '');
        $initial = self::h(mb_substr((string) ($agent['name'] ?? 'A'), 0, 1));
        $title   = self::h($title);

        $items = [
            'overview' => ['/agent/index/index', '数据概览', AgentService::hasPerm($agent, 'overview')],
            'link'     => ['/agent/link/index', '推广链接', AgentService::hasPerm($agent, 'link')],
            'member'   => ['/agent/member/index', '会员明细', AgentService::hasPerm($agent, 'member')],
            'domain'   => ['/agent/domain/index', '专属域名', AgentService::hasPerm($agent, 'domain')],
            'account'  => ['/agent/account/index', '账号设置', true],
        ];
        $nav = '';
        foreach ($items as $key => [$url, $label, $visible]) {
            if (!$visible) {
                continue;
            }
            $cls = $key === $active ? 'nav-item is-active' : 'nav-item';
            $nav .= '<a class="' . $cls . '" href="' . $url . '">' . self::h($label) . '</a>';
        }

        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title} · 代理中心</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;background:#f5f6fa;color:#1f2430;font-size:14px}
a{color:inherit}
.layout{display:flex;min-height:100vh}
.side{width:212px;flex:0 0 212px;background:#111827;color:#cbd5e1;display:flex;flex-direction:column;padding:22px 14px}
.brand{display:flex;align-items:center;gap:10px;padding:0 8px 20px;border-bottom:1px solid rgba(255,255,255,.08)}
.brand .logo{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:15px}
.brand .t{font-size:15px;font-weight:700;color:#fff}
.brand .s{font-size:11px;color:#8b93a7;margin-top:2px}
.nav{margin-top:18px;display:flex;flex-direction:column;gap:4px}
.nav-item{padding:10px 12px;border-radius:9px;color:#cbd5e1;text-decoration:none;font-size:13.5px;transition:.15s}
.nav-item:hover{background:rgba(255,255,255,.07);color:#fff}
.nav-item.is-active{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;font-weight:600}
.side-bottom{margin-top:auto;padding-top:18px;border-top:1px solid rgba(255,255,255,.08)}
.who{display:flex;align-items:center;gap:9px;padding:0 6px 12px}
.avatar{width:30px;height:30px;border-radius:50%;background:#374151;color:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700}
.who .n{font-size:13px;color:#fff;font-weight:600;line-height:1.2}
.who .i{font-size:11px;color:#8b93a7;margin-top:2px}
.logout-btn{width:100%;height:34px;border:1px solid rgba(255,255,255,.14);background:transparent;color:#cbd5e1;border-radius:8px;cursor:pointer;font-size:12.5px}
.logout-btn:hover{background:rgba(255,255,255,.07);color:#fff}
.main{flex:1;min-width:0;padding:26px 30px 60px}
.head{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;margin-bottom:20px}
.head h1{font-size:22px;font-weight:700;color:#161b2c}
.head .sub{font-size:12.5px;color:#8a90a3;margin-top:5px;line-height:1.7}
.card{background:#fff;border:1px solid #eceef5;border-radius:14px;padding:20px;margin-bottom:18px;box-shadow:0 1px 3px rgba(30,34,60,.04)}
.card h2{font-size:15px;font-weight:700;margin-bottom:14px;color:#1a1f36}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px}
.stat{background:#fff;border:1px solid #eceef5;border-radius:14px;padding:16px;box-shadow:0 1px 3px rgba(30,34,60,.04)}
.stat .k{font-size:12px;color:#8a90a3}
.stat .v{font-size:22px;font-weight:700;color:#1a1f36;margin-top:6px;font-variant-numeric:tabular-nums}
.stat .d{font-size:11.5px;color:#9aa0b0;margin-top:4px}
table{width:100%;border-collapse:collapse}
th{background:#fafbfe;font-weight:600;font-size:12px;color:#8a90a3;text-align:left;padding:11px 12px;border-bottom:1px solid #eef0f6;white-space:nowrap}
td{padding:11px 12px;border-bottom:1px solid #f2f3f8;font-size:13px;color:#3a4051;vertical-align:middle}
tr:last-child td{border-bottom:none}
td.num,th.num{text-align:right;font-variant-numeric:tabular-nums}
.empty{text-align:center;color:#9aa0b0;padding:26px 0}
.muted{color:#9aa0b0}
.chip{display:inline-block;font-size:12px;padding:2px 9px;border-radius:999px;white-space:nowrap}
.chip.on{background:#e8f7ef;color:#00a870}
.chip.off{background:#f2f3f5;color:#999}
.frm{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.fld{display:flex;flex-direction:column;gap:6px}
.fld label{font-size:12px;color:#8a90a3}
.fld input,.fld select{height:38px;padding:0 12px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;color:#1f2430;background:#fff;outline:none;width:100%}
.fld input:focus,.fld select:focus{border-color:#6366f1}
.actions{grid-column:1/-1;display:flex;gap:10px;align-items:center;margin-top:2px}
.btn{height:38px;padding:0 20px;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff}
.btn-line{height:34px;line-height:32px;padding:0 13px;border:1px solid #dfe2ec;border-radius:9px;background:#fff;color:#4b5165;font-size:12.5px;cursor:pointer;text-decoration:none;display:inline-block}
.btn-line:hover{border-color:#6366f1;color:#6366f1}
.btn-danger{color:#ff2d55;border-color:#ffd6df}
.btn-danger:hover{border-color:#ff2d55;color:#ff2d55}
.lk{display:flex;align-items:center;gap:8px}
.lk code{flex:1;min-width:0;background:#f7f8fc;border:1px solid #eef0f6;border-radius:8px;padding:6px 9px;font-family:Consolas,Menlo,monospace;font-size:11.5px;color:#4a5069;word-break:break-all}
.btn-copy{flex:0 0 auto;padding:5px 10px;border:1px solid #6366f1;border-radius:8px;background:#fff;color:#6366f1;font-size:12px;cursor:pointer}
.btn-copy.copied{border-color:#00a870;color:#00a870;background:#e8f7ef}
.ops a{color:#6366f1;text-decoration:none;margin-right:10px;white-space:nowrap;font-size:12.5px}
.ops a.danger{color:#ff2d55}
.pager{display:flex;align-items:center;gap:10px;margin-top:16px;font-size:12.5px;color:#8a90a3}
.pager a{color:#6366f1;text-decoration:none}
.tip{font-size:12px;color:#9aa0b0;line-height:1.8;margin-top:12px}
.toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px}
.toolbar input{height:34px;padding:0 12px;border:1px solid #e2e5ee;border-radius:9px;outline:none;min-width:200px}
.toolbar input:focus{border-color:#6366f1}
.alert{padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:14px;line-height:1.6}
.alert.err{background:#fff1f4;color:#d61f4a;border:1px solid #ffd6df}
.alert.ok{background:#e8f7ef;color:#00875a;border:1px solid #c7ebd8}
@media(max-width:900px){
  .layout{flex-direction:column}
  .side{width:100%;flex:none;padding:16px}
  .nav{flex-direction:row;flex-wrap:wrap}
  .side-bottom{margin-top:14px}
  .main{padding:18px 14px 50px}
  .stats{grid-template-columns:repeat(2,1fr)}
  .frm{grid-template-columns:1fr}
}
</style>
</head>
<body>
<div class="layout">
  <aside class="side">
    <div class="brand">
      <div class="logo">{$initial}</div>
      <div><div class="t">代理中心</div><div class="s">{$no}</div></div>
    </div>
    <nav class="nav">{$nav}</nav>
    <div class="side-bottom">
      <div class="who">
        <div class="avatar">{$initial}</div>
        <div><div class="n">{$name}</div><div class="i">代理账号</div></div>
      </div>
      <form method="post" action="/agent/auth/logout">
        <input type="hidden" name="_csrf" value="{$csrf}">
        <button type="submit" class="logout-btn">退出登录</button>
      </form>
    </div>
  </aside>
  <main class="main">{$content}</main>
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
    }

    /** 登录页（独立布局） */
    public static function login(string $error = '', string $username = '', string $notice = '', string $redirect = ''): string
    {
        $errBox = $error !== '' ? '<div class="alert err">' . self::h($error) . '</div>' : '';
        $okBox  = $notice !== '' ? '<div class="alert ok">' . self::h($notice) . '</div>' : '';
        $user   = self::h($username);
        $redir  = self::h($redirect);
        $year   = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>代理登录</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;background:linear-gradient(135deg,#eef0ff 0%,#f7f8fc 45%,#fdf2ff 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;color:#1f2430}
.box{width:100%;max-width:400px;background:#fff;border:1px solid #eceef5;border-radius:18px;padding:34px 32px;box-shadow:0 18px 50px rgba(60,70,120,.12)}
.logo{width:46px;height:46px;border-radius:13px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;font-weight:700;margin-bottom:16px}
h1{font-size:21px;font-weight:700}
.sub{font-size:12.5px;color:#8a90a3;margin:7px 0 20px;line-height:1.7}
.alert{padding:10px 13px;border-radius:10px;font-size:13px;margin-bottom:14px;line-height:1.6}
.alert.err{background:#fff1f4;color:#d61f4a;border:1px solid #ffd6df}
.alert.ok{background:#e8f7ef;color:#00875a;border:1px solid #c7ebd8}
.fld{display:flex;flex-direction:column;gap:7px;margin-bottom:14px}
.fld label{font-size:12.5px;color:#6b7080}
.fld input{height:42px;padding:0 13px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;outline:none;background:#fbfbfe}
.fld input:focus{border-color:#6366f1;background:#fff}
.btn{width:100%;height:42px;border:none;border-radius:10px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;font-size:14.5px;font-weight:600;cursor:pointer;margin-top:6px}
.btn:hover{filter:brightness(1.03)}
.foot{margin-top:18px;text-align:center;font-size:11.5px;color:#a0a5b3}
</style>
</head>
<body>
<div class="box">
  <div class="logo">A</div>
  <h1>代理中心</h1>
  <div class="sub">请使用总部分配的代理账号登录。账号仅能查看自己名下的数据。</div>
  {$errBox}
  {$okBox}
  <form method="post" action="/agent/auth/login">
    <input type="hidden" name="redirect" value="{$redir}">
    <div class="fld"><label>代理账号</label><input type="text" name="username" value="{$user}" maxlength="64" autocomplete="username" required></div>
    <div class="fld"><label>登录密码</label><input type="password" name="password" maxlength="64" autocomplete="current-password" required></div>
    <button type="submit" class="btn">登录</button>
  </form>
  <div class="foot">© {$year} 代理中心</div>
</div>
</body>
</html>
HTML;
    }
}
