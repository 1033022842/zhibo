<?php
declare(strict_types=1);

namespace app\live\service;

/**
 * 总后台代理页面公共骨架（与现有推广/会员后台保持同一套浅色紫调）
 */
final class AgentAdminView
{
    public static function h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function page(string $title, string $subtitle, string $content, string $actions = ''): string
    {
        $titleEsc = self::h($title);
        $subEsc   = self::h($subtitle);

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$titleEsc}</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--primary:#6366f1;--primary2:#8b5cf6;--line:#eceef5;--bg:#f4f5fb;--text:#1f2430;--muted:#8a90a3}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;background:var(--bg);color:var(--text);font-size:14px;-webkit-font-smoothing:antialiased}
.container{max-width:1360px;margin:0 auto;padding:28px 28px 64px}
.page-head{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;margin-bottom:20px}
.page-head h1{font-size:24px;font-weight:700;letter-spacing:-.02em;color:#161b2c}
.page-head .sub{margin-top:7px;font-size:13px;color:var(--muted);line-height:1.7}
.head-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.card{background:#fff;border:1px solid var(--line);border-radius:15px;padding:22px;margin-bottom:18px;box-shadow:0 1px 2px rgba(30,34,60,.035)}
.card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:16px}
.card-head h2{font-size:15.5px;font-weight:700;color:#1a1f36}
.card-head .hint{font-size:12px;color:#9aa0b0;margin-top:5px;line-height:1.6}
.card h3{font-size:13.5px;font-weight:700;color:#2a3042}
.divider{height:1px;background:#f0f2f7;margin:20px 0}
.section-head{display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:12px}
.section-head .hint{font-size:12px;color:#9aa0b0;line-height:1.6}
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
.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;border-radius:10px}
.data-table{width:100%;border-collapse:collapse;min-width:1080px}
.data-table th{background:#fafbfe;font-weight:600;font-size:12px;color:var(--muted);text-align:left;padding:11px 12px;border-bottom:1px solid #eef0f6;white-space:nowrap}
.data-table td{padding:12px;border-bottom:1px solid #f2f3f8;font-size:13px;color:#3a4051;vertical-align:middle}
.data-table tbody tr:hover{background:#fbfbff}
.data-table tr:last-child td{border-bottom:none}
.data-table td.num,.data-table th.num{text-align:right;font-variant-numeric:tabular-nums}
.agent-name{font-weight:600;color:#1f2430}
.agent-no{font-size:11.5px;color:#9aa0b0;margin-top:3px;font-family:Consolas,Menlo,monospace}
.tag{display:inline-block;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle;font-size:11.5px;line-height:20px;padding:0 8px;border-radius:6px;background:#eef0ff;color:#4f46e5;margin:2px 4px 2px 0}
.tag.alt{background:#f3f4f8;color:#5b6072}
.tag.more{background:#f7f8fc;color:#9aa0b0}
.chip{display:inline-block;font-size:12px;padding:2px 9px;border-radius:999px;white-space:nowrap}
.chip.on{background:#e8f7ef;color:#00a870}
.chip.off{background:#f2f3f5;color:#999}
.mini-chips{display:flex;flex-wrap:wrap;gap:4px;max-width:230px}
.mini-chip{font-size:11px;line-height:18px;padding:0 6px;border-radius:5px;background:#f5f6fa;color:#6b7080;white-space:nowrap}
.ops a{color:var(--primary);text-decoration:none;margin-right:12px;white-space:nowrap;font-size:12.5px}
.ops a.danger{color:#ff2d55}
.ops a:hover{text-decoration:underline}
.frm{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px 16px}
.fld{display:flex;flex-direction:column;gap:6px}
.fld label{font-size:12px;color:var(--muted)}
.fld input,.fld textarea{padding:9px 12px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;color:var(--text);background:#fff;outline:none;width:100%;font-family:inherit;transition:border-color .15s,box-shadow .15s}
.fld input{height:38px}
.fld input:focus,.fld textarea:focus{border-color:#a5b4fc;box-shadow:0 0 0 3px rgba(99,102,241,.12)}
.fld.full{grid-column:1/-1}
.perm-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.perm{display:flex;gap:10px;align-items:flex-start;padding:12px 13px;border:1px solid #e8eaf2;border-radius:11px;background:#fff;cursor:pointer;transition:.15s}
.perm:hover{border-color:#c7cbf7;background:#fbfbff}
.perm input{margin-top:3px;accent-color:var(--primary);flex:0 0 auto}
.perm b{display:block;font-size:13px;font-weight:600;color:#2a3042}
.perm small{display:block;font-size:11.5px;color:#9aa0b0;line-height:1.55;margin-top:3px}
.perm.is-disabled{background:#fafbfe;border-color:#f0f1f6;cursor:not-allowed;color:#b4b9c6}
.perm.is-disabled b{color:#b4b9c6}
.perm-grid.compact{grid-template-columns:repeat(2,minmax(0,1fr))}
.two-col{display:grid;grid-template-columns:1.1fr 1fr;gap:22px}
.field-block h3{margin-bottom:9px}
.field-block .hint{font-size:11.5px;color:#9aa0b0;line-height:1.6;margin-top:7px}
.form-actions{position:sticky;bottom:0;display:flex;align-items:center;gap:10px;padding:16px 0 2px;margin-top:6px;background:linear-gradient(180deg,rgba(255,255,255,0),#fff 38%)}
.btn{height:38px;padding:0 20px;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;background:linear-gradient(135deg,var(--primary),var(--primary2));color:#fff;box-shadow:0 4px 12px rgba(99,102,241,.22)}
.btn:hover{filter:brightness(1.04)}
.btn-line{height:38px;line-height:36px;padding:0 16px;border:1px solid #e2e5ee;border-radius:10px;font-size:13.5px;color:#5b6072;text-decoration:none;background:#fff;display:inline-block;cursor:pointer}
.btn-line:hover{border-color:#c7cbf7;color:#4f46e5}
.switch{display:inline-flex;align-items:center;gap:8px;font-size:13px;color:#5b6072;cursor:pointer;margin-left:auto}
.switch input{accent-color:var(--primary);width:16px;height:16px}
.toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.toolbar input[type=date]{height:36px;padding:0 11px;border:1px solid #e2e5ee;border-radius:9px;outline:none;color:#3a4051;background:#fff}
.toolbar input[type=text]{height:36px;padding:0 11px;border:1px solid #e2e5ee;border-radius:9px;outline:none;color:#3a4051;background:#fff;min-width:200px}
.toolbar input:focus{border-color:#a5b4fc;box-shadow:0 0 0 3px rgba(99,102,241,.12)}
.toolbar .muted{font-size:12px}
.bar{height:6px;border-radius:999px;background:#f0f2f7;overflow:hidden;min-width:70px;margin-top:6px}
.bar>i{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#6366f1,#8b5cf6)}
.alert{padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;line-height:1.6}
.alert.ok{background:#e8f7ef;color:#00875a;border:1px solid #c7ebd8}
.alert.err{background:#fff1f4;color:#d61f4a;border:1px solid #ffd6df}
.empty{text-align:center;color:#9aa0b0;padding:30px 0}
.muted{color:#9aa0b0}
.mono{font-family:Consolas,Menlo,monospace}
.tip{font-size:13px;color:#6b7080;line-height:1.8}
.pager{display:flex;align-items:center;gap:12px;margin-top:16px;font-size:12.5px;color:var(--muted)}
.pager a{color:var(--primary);text-decoration:none}
code{background:#f7f8fc;border:1px solid #eef0f6;border-radius:6px;padding:2px 6px;font-family:Consolas,Menlo,monospace;font-size:12.5px}
@media(max-width:1100px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.frm{grid-template-columns:repeat(2,minmax(0,1fr))}.perm-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.two-col{grid-template-columns:1fr}}
@media(max-width:720px){.container{padding:18px 14px 50px}.page-head{flex-direction:column;align-items:flex-start}.stats{grid-template-columns:1fr}.frm{grid-template-columns:1fr}.perm-grid{grid-template-columns:1fr}.perm-grid.compact{grid-template-columns:1fr}.card{padding:16px}}
</style>
</head>
<body>
<div class="container">
  <div class="page-head">
    <div><h1>{$titleEsc}</h1><div class="sub">{$subEsc}</div></div>
    <div class="head-actions">{$actions}</div>
  </div>
  {$content}
</div>
</body>
</html>
HTML;
    }
}
