<?php
declare(strict_types=1);

namespace app\agent\service;

use app\live\service\AgentService;

/**
 * 代理门户页面骨架（浅色后台风格，与总后台保持一致）
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

        $icons = [
            'overview' => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.6"/><rect x="14" y="3" width="7" height="7" rx="1.6"/><rect x="3" y="14" width="7" height="7" rx="1.6"/><rect x="14" y="14" width="7" height="7" rx="1.6"/></svg>',
            'link'     => '<svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.07 0l1.42-1.42a5 5 0 0 0-7.07-7.07L10 5.93"/><path d="M14 11a5 5 0 0 0-7.07 0L5.51 12.42a5 5 0 0 0 7.07 7.07L14 18.07"/></svg>',
            'pixel'    => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v3"/><path d="M12 19v3"/><path d="M2 12h3"/><path d="M19 12h3"/></svg>',
            'member'   => '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'order'    => '<svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg>',
            'withdraw' => '<svg viewBox="0 0 24 24"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>',
            'cs'       => '<svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-8.4 8.5 8.5 8.5 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.38 8.38 0 0 1 8.4-8.5h.6a8.5 8.5 0 0 1 7 7z"/></svg>',
            'sub_agent' => '<svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg>',
            'domain'   => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20 15.3 15.3 0 0 1 0-20z"/></svg>',
            'account'  => '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
        ];

        $items = [
            'overview' => ['/agent/index/index', '数据概览', AgentService::hasPerm($agent, 'overview')],
            'pixel'    => ['/agent/pixel/index', '推广与像素', AgentService::hasPerm($agent, 'pixel')],
            'member'   => ['/agent/member/index', '会员明细', AgentService::hasPerm($agent, 'member')],
            'order'    => ['/agent/order/index', '充值订单', AgentService::hasPerm($agent, 'order')],
            'withdraw' => ['/agent/withdraw/index', '提现记录', AgentService::hasPerm($agent, 'withdraw')],
            'cs'       => ['/agent/cs/index', '独立客服', AgentService::hasPerm($agent, 'cs')],
            'sub_agent' => ['/agent/subagent/index', '子代理', AgentService::hasPerm($agent, 'sub_agent')],
            'account'  => ['/agent/account/index', '账号设置', true],
        ];
        $nav = '';
        foreach ($items as $key => [$url, $label, $visible]) {
            if (!$visible) {
                continue;
            }
            $cls = $key === $active ? 'nav-item is-active' : 'nav-item';
            $nav .= '<a class="' . $cls . '" href="' . $url . '">' . ($icons[$key] ?? '') . '<span>' . self::h($label) . '</span></a>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title} · 代理中心</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--primary:#6366f1;--primary2:#8b5cf6;--line:#eceef5;--bg:#f5f6fa;--text:#1f2430;--muted:#8a90a3}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;background:var(--bg);color:var(--text);font-size:14px;-webkit-font-smoothing:antialiased}
a{color:inherit}
.shell{display:flex;min-height:100vh}
.side{width:224px;flex:0 0 224px;background:#fff;border-right:1px solid var(--line);display:flex;flex-direction:column;padding:20px 14px;position:sticky;top:0;height:100vh}
.brand{display:flex;align-items:center;gap:10px;padding:0 8px 18px;border-bottom:1px solid #f1f2f7}
.brand .logo{width:36px;height:36px;flex:0 0 36px;border-radius:11px;background:linear-gradient(135deg,var(--primary),var(--primary2));display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:15px;box-shadow:0 5px 14px rgba(99,102,241,.24)}
.brand .t{font-size:15px;font-weight:700;color:#161b2c;line-height:1.2}
.brand .s{font-size:11px;color:#9aa0b0;margin-top:3px;font-family:Consolas,Menlo,monospace}
.nav-label{font-size:10.5px;color:#a0a5b3;letter-spacing:.08em;margin:18px 10px 8px}
.nav{display:flex;flex-direction:column;gap:4px}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:#5b6072;text-decoration:none;font-size:13.5px;transition:background .15s,color .15s}
.nav-item svg{width:17px;height:17px;flex:0 0 17px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;opacity:.9}
.nav-item:hover{background:#f5f6fa;color:#1f2430}
.nav-item.is-active{background:#eef0ff;color:#4f46e5;font-weight:600}
.side-foot{margin-top:auto;padding-top:16px;border-top:1px solid #f1f2f7}
.who{display:flex;align-items:center;gap:9px;padding:0 6px 12px}
.avatar{width:32px;height:32px;flex:0 0 32px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary2));color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700}
.who .n{font-size:13px;color:#1f2430;font-weight:600;line-height:1.2;max-width:135px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.who .i{font-size:11px;color:#9aa0b0;margin-top:2px}
.logout-btn{width:100%;height:34px;border:1px solid #e2e5ee;background:#fff;color:#6b7080;border-radius:9px;cursor:pointer;font-size:12.5px;transition:.15s}
.logout-btn:hover{border-color:#ffd6df;background:#fff7f9;color:#d61f4a}
.main{flex:1;min-width:0}
.topbar{height:58px;display:flex;align-items:center;justify-content:space-between;gap:14px;padding:0 30px;border-bottom:1px solid var(--line);background:rgba(245,246,250,.86);backdrop-filter:blur(8px);position:sticky;top:0;z-index:5}
.crumb{font-size:12.5px;color:#9aa0b0}
.crumb b{color:#3a4051;font-weight:600}
.crumb .sep{margin:0 7px;color:#c6cad4}
.badge{font-size:11.5px;line-height:24px;padding:0 10px;border-radius:999px;background:#eef0ff;color:#4f46e5;font-family:Consolas,Menlo,monospace}
.content{padding:24px 30px 56px;max-width:1320px;margin:0 auto}
.head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:20px}
.head h1{font-size:24px;font-weight:700;letter-spacing:-.02em;color:#161b2c}
.head .sub{margin-top:7px;font-size:13px;color:var(--muted);line-height:1.7}
.card{background:#fff;border:1px solid var(--line);border-radius:15px;padding:20px;margin-bottom:18px;box-shadow:0 1px 2px rgba(30,34,60,.035);overflow:hidden}
.card h2{font-size:15.5px;font-weight:700;margin-bottom:14px;color:#1a1f36}
.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}
.stat{display:flex;gap:13px;align-items:flex-start;background:#fff;border:1px solid var(--line);border-radius:15px;padding:17px 18px;box-shadow:0 1px 2px rgba(30,34,60,.035)}
.stat .ico{width:38px;height:38px;flex:0 0 38px;border-radius:11px;display:flex;align-items:center;justify-content:center}
.stat .ico svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.ico.indigo{background:#eef0ff;color:#6366f1}
.ico.emerald{background:#e8f7ef;color:#00a870}
.ico.amber{background:#fff6e6;color:#d68a00}
.ico.violet{background:#f3edff;color:#8b5cf6}
.stat .k{font-size:12px;color:var(--muted)}
.stat .v{font-size:22px;font-weight:700;letter-spacing:-.02em;margin-top:4px;font-variant-numeric:tabular-nums}
.stat .d{font-size:11.5px;color:#9aa0b0;margin-top:3px}
.seg{display:inline-flex;background:#eef0f5;border-radius:9px;padding:3px;gap:2px}
.seg-item{height:30px;line-height:30px;padding:0 13px;border-radius:7px;font-size:12.5px;color:#6b7080;text-decoration:none;transition:.15s}
.seg-item:hover{color:#1f2430}
.seg-item.is-active{background:#fff;color:#4f46e5;font-weight:600;box-shadow:0 1px 3px rgba(30,34,60,.12)}
table{width:100%;border-collapse:collapse}
th{background:#fafbfe;font-weight:600;font-size:12px;color:var(--muted);text-align:left;padding:11px 12px;border-bottom:1px solid #eef0f6;white-space:nowrap}
td{padding:11px 12px;border-bottom:1px solid #f2f3f8;font-size:13px;color:#3a4051;vertical-align:middle}
tbody tr:hover{background:#fbfbff}
tr:last-child td{border-bottom:none}
td.num,th.num{text-align:right;font-variant-numeric:tabular-nums}
.empty{text-align:center;color:#9aa0b0;padding:28px 0}
.muted{color:#9aa0b0}
.chip{display:inline-block;font-size:12px;padding:2px 9px;border-radius:999px;white-space:nowrap}
.chip.on{background:#e8f7ef;color:#00a870}
.chip.off{background:#f2f3f5;color:#999}
.chip.warn{background:#fff7e6;color:#d48806}
.chip.danger{background:#fff1f4;color:#d61f4a}
.mono{font-family:Consolas,Menlo,monospace;font-size:12px;color:#4a5069;word-break:break-all}
td.nowrap,th.nowrap{white-space:nowrap}
.mini-chip{display:inline-block;font-size:11px;line-height:20px;padding:0 6px;border-radius:6px;background:#f1f3f9;color:#5b6072;margin:0 3px 0 0;white-space:nowrap}
.mini-chip.more{background:#eef0ff;color:#4f46e5}
.who-cell .sub .sep{margin:0 6px;color:#c9ccd8}
.chips{display:flex;flex-wrap:nowrap;max-width:230px}
.perm-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.perm{display:flex;gap:9px;align-items:flex-start;border:1px solid #eceef5;border-radius:11px;padding:11px 12px;background:#fcfcff;cursor:pointer}
.perm input{margin-top:3px;accent-color:#6366f1;flex:0 0 auto}
.perm.is-fixed{background:#f7f8fc;border-style:dashed;cursor:default}
.perm.is-fixed input{opacity:.55}
.perm b{display:block;font-size:13px;font-weight:600;color:#1f2430}
.perm small{display:block;font-size:11.5px;color:#8a90a3;margin-top:3px;line-height:1.6}
.switch{display:inline-flex;align-items:center;gap:7px;font-size:12.5px;color:#5b6072;cursor:pointer}
.switch input{accent-color:#6366f1}
.head .btn{display:inline-flex;align-items:center;height:36px;padding:0 16px;text-decoration:none;font-size:13px;flex:0 0 auto}
.card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:14px}
.card-head h2{margin-bottom:0}
.card-head .hint{font-size:12px;color:#9aa0b0;margin-top:5px;line-height:1.6}
.who-cell{display:flex;align-items:center;gap:10px}
.avatar-sm{width:32px;height:32px;flex:0 0 32px;border-radius:9px;background:linear-gradient(135deg,#eef0ff,#f3edff);color:#4f46e5;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px}
.who-cell .nm{font-size:13px;font-weight:600;color:#1f2430;line-height:1.3}
.who-cell .sub{font-size:11px;color:#9aa0b0;margin-top:3px;font-family:Consolas,Menlo,monospace;white-space:nowrap}
.tbl-subs td{padding:9px 12px}
.tbl-subs .avatar-sm{width:28px;height:28px;flex:0 0 28px;border-radius:8px;font-size:12px}
.tbl-subs .ops{white-space:nowrap}
.lv{display:inline-block;margin:0 4px;padding:0 5px;height:16px;line-height:16px;border-radius:4px;background:#eef0ff;color:#6366f1;font-size:10px;font-family:inherit}
.blank{padding:34px 16px;text-align:center}
.blank-ico{width:46px;height:46px;margin:0 auto 12px;border-radius:14px;background:#f3f4fb;display:flex;align-items:center;justify-content:center;color:#a5aabc}
.blank-ico svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.blank-t{font-size:14px;font-weight:600;color:#3a4051}
.blank-d{font-size:12.5px;color:#9aa0b0;margin:6px 0 14px}
.divider{height:1px;background:#f1f2f8;margin:20px 0}
.section-head{display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:12px}
.section-head h3{font-size:13.5px;font-weight:700;color:#1f2430}
.section-head .hint{font-size:11.5px;color:#9aa0b0}
.res-row{display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:1px dashed #e3e6f1;border-radius:10px;background:#fafbff;margin-bottom:8px}
.res-row .k{flex:0 0 64px;font-size:12px;color:#8a90a3;line-height:22px}
.res-row .v{display:flex;flex-wrap:wrap;gap:6px}
.tag{font-size:12px;line-height:22px;padding:0 9px;border-radius:7px;background:#eef0ff;color:#4f46e5;white-space:nowrap}
.tag.alt{background:#f3edff;color:#7c3aed}
.form-actions{display:flex;align-items:center;gap:10px;margin-top:20px;padding-top:16px;border-top:1px solid #f1f2f8}
.form-actions .switch{margin-left:auto}
.frm{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.fld{display:flex;flex-direction:column;gap:6px}
.fld label{font-size:12px;color:var(--muted)}
.fld input,.fld select{height:38px;padding:0 12px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;color:var(--text);background:#fff;outline:none;width:100%;transition:border-color .15s,box-shadow .15s}
.fld textarea{padding:9px 12px;border:1px solid #e2e5ee;border-radius:10px;font-size:13px;color:var(--text);background:#fff;outline:none;width:100%;font-family:Consolas,Menlo,monospace;resize:vertical;transition:border-color .15s,box-shadow .15s}
.fld textarea:focus{border-color:#a5b4fc;box-shadow:0 0 0 3px rgba(99,102,241,.12)}
.chk-row{display:flex;flex-wrap:wrap;gap:8px}
.chk-row label{display:inline-flex;align-items:center;gap:6px;border:1px solid #e6e8f2;border-radius:9px;padding:7px 11px;font-size:12.5px;color:#3a4051;background:#fbfbff;cursor:pointer}
.chk-row input{accent-color:#6366f1;width:15px;height:15px;margin:0}
.fld input:focus,.fld select:focus{border-color:#a5b4fc;box-shadow:0 0 0 3px rgba(99,102,241,.12)}
.actions{grid-column:1/-1;display:flex;gap:10px;align-items:center;margin-top:2px}
.btn{height:38px;padding:0 20px;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;background:linear-gradient(135deg,var(--primary),var(--primary2));color:#fff;box-shadow:0 4px 12px rgba(99,102,241,.22)}
.btn:hover{filter:brightness(1.04)}
.btn-line{height:34px;line-height:32px;padding:0 13px;border:1px solid #dfe2ec;border-radius:9px;background:#fff;color:#4b5165;font-size:12.5px;cursor:pointer;text-decoration:none;display:inline-block}
.btn-line:hover{border-color:#c7cbf7;color:#4f46e5}
.btn-danger{color:#ff2d55;border-color:#ffd6df}
.btn-danger:hover{border-color:#ff2d55;color:#ff2d55}
.lk{display:flex;align-items:center;gap:8px}
.lk code{flex:1;min-width:0;background:#f7f8fc;border:1px solid #eef0f6;border-radius:8px;padding:6px 9px;font-family:Consolas,Menlo,monospace;font-size:11.5px;color:#4a5069;word-break:break-all}
.btn-copy{flex:0 0 auto;padding:5px 10px;border:1px solid #c7cbf7;border-radius:8px;background:#fff;color:#4f46e5;font-size:12px;cursor:pointer}
.btn-copy:hover{background:#eef0ff}
.btn-copy.copied{border-color:#00a870;color:#00a870;background:#e8f7ef}
.ops a{color:var(--primary);text-decoration:none;margin-right:10px;white-space:nowrap;font-size:12.5px}
.ops a.danger{color:#ff2d55}
.ops a:hover{text-decoration:underline}
.pager{display:flex;align-items:center;gap:10px;margin-top:16px;font-size:12.5px;color:var(--muted)}
.pager a{color:var(--primary);text-decoration:none}
.tip{font-size:12px;color:#9aa0b0;line-height:1.8;margin-top:12px}
.toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px}
.toolbar input{height:34px;padding:0 12px;border:1px solid #e2e5ee;border-radius:9px;outline:none;min-width:200px}
.toolbar select{height:34px;padding:0 10px;border:1px solid #e2e5ee;border-radius:9px;outline:none;background:#fff;color:#3a4051;font-size:13px}
.toolbar input[type=date]{min-width:0;width:150px;color:#3a4051}
.toolbar input:focus{border-color:#a5b4fc;box-shadow:0 0 0 3px rgba(99,102,241,.12)}
.alert{padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:14px;line-height:1.6}
.alert.err{background:#fff1f4;color:#d61f4a;border:1px solid #ffd6df}
.alert.ok{background:#e8f7ef;color:#00875a;border:1px solid #c7ebd8}
.mask{display:none;position:fixed;inset:0;z-index:1200;background:rgba(17,20,35,.42);padding:20px}
.mask.open{display:flex;align-items:center;justify-content:center}
.panel{width:min(720px,94vw);max-height:86vh;background:#fff;border-radius:14px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(30,34,60,.24)}
.panel-head{display:flex;align-items:center;justify-content:space-between;padding:13px 16px;border-bottom:1px solid #eef0f6}
.panel-head b{font-size:14px;color:#1f2430}
.panel-head button{border:none;background:none;font-size:21px;line-height:1;color:#9aa0b0;cursor:pointer}
.panel pre{margin:0;padding:16px;overflow:auto;white-space:pre-wrap;word-break:break-all;font-size:12px;line-height:1.75;color:#3a4051;font-family:Consolas,Menlo,monospace}
.panel-foot{padding:11px 16px;border-top:1px solid #eef0f6;display:flex;justify-content:flex-end;gap:8px}
.bar{height:6px;border-radius:999px;background:#f0f2f7;overflow:hidden;margin-top:6px}
.bar>i{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,var(--primary),var(--primary2))}
@media(max-width:1024px){
  .side{width:78px;flex:0 0 78px;padding:16px 8px}
  .brand{padding:0 6px 14px;justify-content:center}
  .brand .t,.brand .s,.nav-label,.nav-item span,.who .n,.who .i{display:none}
  .nav-item{justify-content:center;padding:11px 0}
  .who{justify-content:center;padding:0 0 10px}
  .topbar{padding:0 18px}
  .content{padding:20px 18px 48px}
  .stats{grid-template-columns:repeat(2,minmax(0,1fr))}
  .frm{grid-template-columns:1fr}
  .perm-grid{grid-template-columns:1fr}
}
@media(max-width:720px){
  .shell{display:block}
  .side{width:100%;height:auto;position:static;flex-direction:row;align-items:center;padding:9px 12px;border-right:0;border-bottom:1px solid var(--line);overflow-x:auto}
  .brand{display:none}
  .nav{flex-direction:row;gap:4px;margin:0}
  .nav-item{justify-content:flex-start;padding:8px 12px;white-space:nowrap}
  .nav-item span{display:inline}
  .side-foot{margin:0 0 0 auto;border:0;padding:0}
  .side-foot .who{display:none}
  .logout-btn{width:auto;padding:0 12px}
  .topbar{height:50px;padding:0 14px}
  .content{padding:16px 14px 44px}
  .head{flex-direction:column;align-items:flex-start}
  .card-head{flex-direction:column}
  .res-row{flex-direction:column;gap:6px}
  .stats{grid-template-columns:1fr}
  .card{padding:15px}
}
</style>
</head>
<body>
<div class="shell">
  <aside class="side">
    <div class="brand">
      <div class="logo">{$initial}</div>
      <div><div class="t">代理中心</div><div class="s">{$no}</div></div>
    </div>
    <div class="nav-label">工作台</div>
    <nav class="nav">{$nav}</nav>
    <div class="side-foot">
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
  <main class="main">
    <div class="topbar">
      <div class="crumb">代理中心<span class="sep">/</span><b>{$title}</b></div>
      <div class="badge">{$no}</div>
    </div>
    <div class="content">{$content}</div>
  </main>
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
<title>代理登录 · 代理中心</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;color:#1f2430;background:#f5f6fa;background-image:radial-gradient(900px 420px at 12% -8%,rgba(99,102,241,.16),transparent 62%),radial-gradient(760px 420px at 92% 108%,rgba(139,92,246,.13),transparent 60%)}
.box{width:100%;max-width:410px;background:#fff;border:1px solid #eceef5;border-radius:20px;padding:34px 32px 28px;box-shadow:0 24px 60px rgba(50,60,110,.12)}
.brand{display:flex;align-items:center;gap:11px;margin-bottom:24px}
.logo{width:44px;height:44px;flex:0 0 44px;border-radius:13px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:19px;font-weight:700;box-shadow:0 8px 20px rgba(99,102,241,.26)}
.brand .t{font-size:15px;font-weight:700;color:#161b2c;line-height:1.2}
.brand .s{font-size:11.5px;color:#9aa0b0;margin-top:3px}
h1{font-size:22px;font-weight:700;letter-spacing:-.02em}
.sub{font-size:12.5px;color:#8a90a3;margin:8px 0 22px;line-height:1.75}
.alert{padding:10px 13px;border-radius:10px;font-size:13px;margin-bottom:14px;line-height:1.6}
.alert.err{background:#fff1f4;color:#d61f4a;border:1px solid #ffd6df}
.alert.ok{background:#e8f7ef;color:#00875a;border:1px solid #c7ebd8}
.fld{display:flex;flex-direction:column;gap:7px;margin-bottom:14px}
.fld label{font-size:12.5px;color:#6b7080}
.input-wrap{position:relative}
.input-wrap svg{position:absolute;left:13px;top:50%;transform:translateY(-50%);width:17px;height:17px;fill:none;stroke:#a0a5b3;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.input-wrap input{width:100%;height:44px;padding:0 13px 0 40px;border:1px solid #e2e5ee;border-radius:11px;font-size:14px;outline:none;background:#fbfbfe;transition:border-color .15s,box-shadow .15s,background .15s}
.input-wrap input:focus{border-color:#a5b4fc;background:#fff;box-shadow:0 0 0 3px rgba(99,102,241,.12)}
.btn{width:100%;height:44px;border:none;border-radius:11px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;font-size:14.5px;font-weight:600;cursor:pointer;margin-top:6px;box-shadow:0 8px 20px rgba(99,102,241,.24);transition:.15s}
.btn:hover{filter:brightness(1.04);transform:translateY(-1px)}
.foot{margin-top:18px;text-align:center;font-size:11.5px;color:#a0a5b3}
@media(max-width:480px){.box{padding:28px 22px 24px;border-radius:16px}}
</style>
</head>
<body>
<div class="box">
  <div class="brand">
    <div class="logo">A</div>
    <div><div class="t">代理中心</div><div class="s">Agent Portal</div></div>
  </div>
  <h1>登录代理后台</h1>
  <div class="sub">使用总部发放的代理账号登录。账号仅能查看自己名下的数据。</div>
  {$errBox}
  {$okBox}
  <form method="post" action="/agent/auth/login">
    <input type="hidden" name="redirect" value="{$redir}">
    <div class="fld"><label>代理账号</label>
      <div class="input-wrap">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
        <input type="text" name="username" value="{$user}" maxlength="64" autocomplete="username" placeholder="请输入代理账号" required>
      </div>
    </div>
    <div class="fld"><label>登录密码</label>
      <div class="input-wrap">
        <svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
        <input type="password" name="password" maxlength="64" autocomplete="current-password" placeholder="请输入登录密码" required>
      </div>
    </div>
    <button type="submit" class="btn">登录</button>
  </form>
  <div class="foot">© {$year} 代理中心</div>
</div>
</body>
</html>
HTML;
    }
}
