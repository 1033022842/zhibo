<?php
/**
 * 前端客服渠道管理
 * 访问: /admin/live.CsChannel/index?_key=<ADMIN_FLOAT_KEY>
 *
 * 一页搞定：客服弹窗/悬浮按钮的全局设置 + 渠道增删改（Telegram / WhatsApp / Line / Messenger / 邮件 / 外链 / 嵌入）
 * 前端静态站由 js/tracking.js 读取 /api/v1/site/config 渲染，改完即时生效（缓存 60 秒）；
 * 渠道点击数记在 lp_cs_channel.clicks，列表里可直接看到哪个渠道被点得多。
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\live\service\PromoService;
use think\facade\Db;

final class CsChannel extends Backend
{
    protected array $noNeedLogin = ['index', 'globalSave', 'chanSave', 'chanToggle', 'chanDel'];

    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    private const ICON_DIR = 'uploads/cs';

    public function index(): void
    {
        $svc     = new PromoService();
        $values  = $svc->all();
        $editId  = (int) $this->request->param('id', 0);

        $v  = static fn(string $key, string $default = ''): string => htmlspecialchars((string) ($values[$key] ?? $default), ENT_QUOTES);
        $on = static fn(string $key, string $default = '1'): string => ($values[$key] ?? $default) === '1' ? ' checked' : '';

        $csOn    = $on('cs_enabled');
        $floatOn = $on('cs_float');
        $title   = $v('cs_title', 'Contact us');
        $subtitle = $v('cs_subtitle', 'Pick a channel below — we usually reply within minutes.');
        $hint    = $v('cs_hint', 'Chat with us');
        $logo    = $v('cs_logo');

        // 编辑中的渠道
        $edit = ['id' => 0, 'type' => 'telegram', 'name' => '', 'url' => '', 'icon' => '', 'sort' => 0, 'status' => 1];
        if ($editId > 0) {
            $row = Db::connect('live_mysql')->table('lp_cs_channel')->where('id', $editId)->find();
            if ($row) {
                $edit = array_merge($edit, $row);
            }
        }
        $typeOptions = '';
        foreach (PromoService::CHANNEL_TYPES as $typeKey => $typeLabel) {
            $sel = ((string) $edit['type'] === $typeKey) ? ' selected' : '';
            $typeOptions .= "<option value=\"{$typeKey}\"{$sel}>{$typeLabel}</option>";
        }
        $eId     = (int) $edit['id'];
        $eName   = htmlspecialchars((string) $edit['name'], ENT_QUOTES);
        $eUrl    = htmlspecialchars((string) $edit['url'], ENT_QUOTES);
        $eIcon   = htmlspecialchars((string) $edit['icon'], ENT_QUOTES);
        $eSort   = (int) $edit['sort'];
        $eStatus = (int) $edit['status'] === 1 ? ' checked' : '';
        $formTitle = $eId > 0 ? '编辑渠道 #' . $eId : '新增渠道';

        // 列表
        $rows  = '';
        $total = 0;
        foreach ($svc->channels(false) as $row) {
            $id     = (int) $row['id'];
            $type   = (string) $row['type'];
            $label  = PromoService::CHANNEL_TYPES[$type] ?? $type;
            $name   = htmlspecialchars((string) $row['name'], ENT_QUOTES);
            $url    = htmlspecialchars((string) $row['url'], ENT_QUOTES);
            $icon   = htmlspecialchars((string) $row['icon'], ENT_QUOTES);
            $clicks = (int) $row['clicks'];
            $sort   = (int) $row['sort'];
            $status = (int) $row['status'];
            $total += $clicks;

            $statusHtml = $status === 1
                ? '<span class="chip on">启用</span>'
                : '<span class="chip off">停用</span>';
            $toggleText = $status === 1 ? '停用' : '启用';
            $iconHtml   = $icon !== '' ? '<img src="' . $icon . '" class="mini">' : '<span class="muted">内置</span>';
            $shortUrl   = mb_strlen($url) > 46 ? mb_substr($url, 0, 46) . '…' : $url;

            $rows .= <<<ROW
            <tr>
                <td>{$id}</td>
                <td><b>{$name}</b></td>
                <td>{$label}</td>
                <td class="url" title="{$url}">{$shortUrl}</td>
                <td>{$iconHtml}</td>
                <td>{$sort}</td>
                <td>{$statusHtml}</td>
                <td><b>{$clicks}</b></td>
                <td class="ops">
                    <a href="/admin/live.CsChannel/index?id={$id}">编辑</a>
                    <a href="/admin/live.CsChannel/chanToggle?id={$id}">{$toggleText}</a>
                    <a href="/admin/live.CsChannel/chanDel?id={$id}" onclick="return confirm('确认删除该客服渠道？')" class="danger">删除</a>
                </td>
            </tr>
ROW;
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="9" class="empty">还没有客服渠道，先在上面新增一个（例如 Telegram）</td></tr>';
        }

        $logoPreview = $logo !== ''
            ? '<img src="' . $logo . '" class="logo-preview">'
            : '<span class="muted">未设置，前端用内置聊天气泡图标</span>';

        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>客服渠道</title>
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
    .field input[type=text],.field select{width:100%;padding:9px 10px;border:1px solid #ddd;border-radius:6px;font-size:14px}
    .switch{display:flex;align-items:center;gap:8px;font-size:13px;color:#333;font-weight:600;margin-bottom:10px}
    .switch input{width:16px;height:16px}
    .tip{font-size:12px;color:#999;margin-top:6px;line-height:1.6}
    .btn{padding:10px 26px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}
    .btn-primary{background:#409eff;color:#fff}
    .btn-ghost{background:#f0f2f5;color:#555}
    table{width:100%;border-collapse:collapse;font-size:13px}
    th,td{padding:10px 8px;border-bottom:1px solid #eef0f3;text-align:left;vertical-align:middle}
    th{color:#888;font-weight:600;background:#fafbfc}
    td.url{color:#666;font-size:12px;max-width:320px;word-break:break-all}
    td.ops a{color:#409eff;text-decoration:none;margin-right:10px}
    td.ops a.danger{color:#ff2d55}
    td.empty{text-align:center;color:#aaa;padding:26px}
    .chip{font-size:12px;padding:2px 9px;border-radius:999px}
    .chip.on{background:#e8f7ef;color:#00a870}
    .chip.off{background:#f2f3f5;color:#999}
    .muted{color:#bbb;font-size:12px}
    img.mini{width:20px;height:20px;border-radius:4px;object-fit:cover}
    img.logo-preview{width:44px;height:44px;border-radius:10px;object-fit:cover;border:1px solid #eee;margin-left:8px;vertical-align:middle}
    .bar{display:flex;justify-content:space-between;align-items:center}
    .note{background:#eef6ff;border:1px solid #cfe3ff;color:#2b6cb0;padding:12px 16px;border-radius:10px;font-size:13px;line-height:1.7;margin-bottom:18px}
</style>
</head>
<body>
<div class="container">

    <div class="note">
        前台在<b>所有页面右下角</b>显示客服悬浮按钮（可关），点开列出下面启用的渠道；各页面原有的「Contact Us」入口也会改成打开同一个弹窗。
        保存后前台 <b>60 秒内生效</b>。当前累计点击：<b>{$total}</b> 次。
    </div>

    <form class="card" method="post" action="/admin/live.CsChannel/globalSave" enctype="multipart/form-data">
        <h2><span class="dot"></span>客服弹窗与悬浮按钮</h2>
        <div class="grid">
            <div>
                <label class="switch"><input type="checkbox" name="cs_enabled" value="1"{$csOn}> 开启客服功能</label>
                <label class="switch"><input type="checkbox" name="cs_float" value="1"{$floatOn}> 显示右下角悬浮按钮</label>
                <div class="field">
                    <label>弹窗标题</label>
                    <input type="text" name="cs_title" value="{$title}">
                </div>
                <div class="field">
                    <label>弹窗副标题</label>
                    <input type="text" name="cs_subtitle" value="{$subtitle}">
                </div>
            </div>
            <div>
                <div class="field">
                    <label>悬浮按钮气泡文案（鼠标移上去显示）</label>
                    <input type="text" name="cs_hint" value="{$hint}">
                </div>
                <div class="field">
                    <label>客服图标 URL（48×48 左右的方形图最好）</label>
                    <input type="text" name="cs_logo" value="{$logo}" placeholder="https://… 或上传后自动填">
                    {$logoPreview}
                </div>
                <div class="field">
                    <label>或直接上传图标（png/jpg/svg/webp，≤2MB）</label>
                    <input type="file" name="logo_file" accept="image/*">
                </div>
            </div>
        </div>
        <div class="bar">
            <div class="tip">关掉「开启客服功能」后，前端不再出现任何客服入口。「Contact Us」会退回原来的邮件链接。</div>
            <button class="btn btn-primary" type="submit">保存客服设置</button>
        </div>
    </form>

    <form class="card" method="post" action="/admin/live.CsChannel/chanSave">
        <h2><span class="dot"></span>{$formTitle}</h2>
        <input type="hidden" name="id" value="{$eId}">
        <div class="grid">
            <div>
                <div class="field">
                    <label>渠道类型</label>
                    <select name="type">{$typeOptions}</select>
                    <div class="tip">邮件填邮箱地址；嵌入网页（iframe）适合 tawk.to 之类在线客服，会在弹窗里内嵌打开。</div>
                </div>
                <div class="field">
                    <label>展示名称</label>
                    <input type="text" name="name" value="{$eName}" placeholder="如 Telegram 客服 / WhatsApp 1 号">
                </div>
            </div>
            <div>
                <div class="field">
                    <label>链接</label>
                    <input type="text" name="url" value="{$eUrl}" placeholder="t.me/xxx、wa.me/8613800000000、https://…">
                    <div class="tip">Telegram 可只填 <code>@xxx</code> 或 <code>t.me/xxx</code>；WhatsApp 可只填手机号。系统会补全成 https 链接。</div>
                </div>
                <div class="field">
                    <label>自定义图标 URL（可选，留空用内置图标）</label>
                    <input type="text" name="icon" value="{$eIcon}">
                </div>
            </div>
        </div>
        <div class="grid">
            <div class="field">
                <label>排序（越小越靠前）</label>
                <input type="text" name="sort" value="{$eSort}">
            </div>
            <div class="field" style="padding-top:22px">
                <label class="switch"><input type="checkbox" name="status" value="1"{$eStatus}> 启用</label>
            </div>
        </div>
        <div class="bar">
            <a class="btn btn-ghost" href="/admin/live.CsChannel/index">清空表单</a>
            <button class="btn btn-primary" type="submit">{$formTitle}</button>
        </div>
    </form>

    <div class="card">
        <h2><span class="dot"></span>渠道列表</h2>
        <table>
            <thead><tr><th>ID</th><th>名称</th><th>类型</th><th>链接</th><th>图标</th><th>排序</th><th>状态</th><th>点击</th><th>操作</th></tr></thead>
            <tbody>{$rows}</tbody>
        </table>
    </div>
</div>
</body>
</html>
HTML;
        exit;
    }

    /** 客服弹窗/悬浮按钮全局设置（可同时上传图标） */
    public function globalSave(): void
    {
        try {
            $svc    = new PromoService();
            $fields = PromoService::CS_FIELDS;

            foreach ($fields as $key => [$type, $remark]) {
                if ($type === 'bool') {
                    $value = (string) $this->request->post($key, '') === '1' ? '1' : '0';
                } else {
                    $value = trim((string) $this->request->post($key, ''));
                }
                $svc->saveSetting($key, $value, $remark);
            }

            // 上传了图标就以文件为准
            $uploaded = $this->saveUploadedLogo();
            if ($uploaded !== null) {
                $svc->saveSetting('cs_logo', $uploaded, $fields['cs_logo'][1]);
            }
            $svc->flushCache();
            $this->backToIndex('客服设置已保存' . ($uploaded !== null ? '（图标已上传）' : ''));
        } catch (\Throwable $e) {
            $this->renderToast(false, $e->getMessage(), '/admin/live.CsChannel/index');
        }
    }

    /** 新增 / 编辑渠道 */
    public function chanSave(): void
    {
        try {
            $id     = (int) $this->request->post('id', 0);
            $type   = (string) $this->request->post('type', 'link');
            $type   = isset(PromoService::CHANNEL_TYPES[$type]) ? $type : 'link';
            $name   = trim((string) $this->request->post('name', ''));
            $url    = $this->normalizeUrl($type, (string) $this->request->post('url', ''));
            $icon   = trim((string) $this->request->post('icon', ''));
            $sort   = (int) $this->request->post('sort', 0);
            $status = (string) $this->request->post('status', '') === '1' ? 1 : 0;

            if ($name === '') {
                throw new \InvalidArgumentException('请填展示名称');
            }
            if ($url === '') {
                throw new \InvalidArgumentException($type === 'email' ? '请填邮箱地址' : '请填链接');
            }

            $db  = Db::connect('live_mysql');
            $now = date('Y-m-d H:i:s');
            $data = [
                'type' => $type, 'name' => $name, 'url' => $url, 'icon' => $icon,
                'sort' => $sort, 'status' => $status, 'updated_at' => $now,
            ];
            if ($id > 0) {
                $db->table('lp_cs_channel')->where('id', $id)->update($data);
            } else {
                $data['created_at'] = $now;
                $db->table('lp_cs_channel')->insert($data);
            }
            (new PromoService())->flushCache();
            $this->backToIndex($id > 0 ? '渠道已更新' : '渠道已新增');
        } catch (\Throwable $e) {
            $this->renderToast(false, $e->getMessage(), '/admin/live.CsChannel/index');
        }
    }

    /** 启用 / 停用 */
    public function chanToggle(): void
    {
        $id = (int) $this->request->param('id', 0);
        $db = Db::connect('live_mysql');
        $row = $id ? $db->table('lp_cs_channel')->where('id', $id)->find() : null;
        if ($row) {
            $db->table('lp_cs_channel')->where('id', $id)->update([
                'status'     => (int) $row['status'] === 1 ? 0 : 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            (new PromoService())->flushCache();
        }
        $this->backToIndex('已切换状态');
    }

    /** 删除 */
    public function chanDel(): void
    {
        $id = (int) $this->request->param('id', 0);
        if ($id > 0) {
            Db::connect('live_mysql')->table('lp_cs_channel')->where('id', $id)->delete();
            (new PromoService())->flushCache();
        }
        $this->backToIndex('已删除');
    }

    // ================= 内部 =================

    /**
     * 保存上传的客服图标 → 静态站目录 uploads/cs/，返回站点相对路径（如 /uploads/cs/xxx.png）
     * 上传到静态站目录是为了让前端能直接用这个 URL，不必跨域。
     */
    private function saveUploadedLogo(): ?string
    {
        $file = $this->request->file('logo_file');
        if (!$file) {
            return null;
        }
        $ext = strtolower($file->extension());
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true)) {
            throw new \InvalidArgumentException('图标格式只支持 png/jpg/webp/gif/svg');
        }
        if ((int) $file->getSize() > 2 * 1024 * 1024) {
            throw new \InvalidArgumentException('图标不能超过 2MB');
        }

        $siteDir = $this->siteRoot();
        $dir     = $siteDir . '/' . self::ICON_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('无法创建目录 ' . $dir);
        }
        $name = 'cs_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $file->move($dir, $name);

        return '/' . self::ICON_DIR . '/' . $name;
    }

    /** 前端静态站根目录（与 php 应用同级） */
    private function siteRoot(): string
    {
        $dir = dirname(rtrim(root_path(), '/\\')) . '/ai-girl-malaysia.com';
        if (!is_dir($dir)) {
            throw new \RuntimeException('未找到前端站点目录：' . $dir);
        }
        return $dir;
    }

    /** 链接补全：@xxx / t.me/xxx / 手机号 都能直接用 */
    private function normalizeUrl(string $type, string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if ($type === 'email') {
            return preg_replace('#^mailto:#i', '', $url) ?? $url;
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        if ($type === 'telegram') {
            return 'https://t.me/' . ltrim($url, '@/');
        }
        if ($type === 'whatsapp') {
            $digits = preg_replace('/\D/', '', $url) ?? '';
            return $digits === '' ? 'https://wa.me/' : 'https://wa.me/' . $digits;
        }
        if ($type === 'line') {
            return str_starts_with($url, '~') ? 'https://line.me/R/ti/p/' . $url : 'https://line.me/R/ti/p/~' . $url;
        }
        return 'https://' . ltrim($url, '/');
    }

    /** 操作后回列表页（带上 _key，保证首次访问的飘页鉴权不断） */
    private function backToIndex(string $msg): void
    {
        $key  = (string) $this->request->param('_key', '');
        $suffix = $key !== '' ? '?_key=' . urlencode($key) : '';
        $this->renderToast(true, $msg, '/admin/live.CsChannel/index' . $suffix, 1200);
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
