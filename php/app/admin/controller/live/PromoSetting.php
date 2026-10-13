<?php
/**
 * 推广与像素配置
 * 访问: /admin/live.PromoSetting/index?_key=<ADMIN_FLOAT_KEY>
 *
 * 按参考后台改成「渠道统计 / 渠道配置」双 Tab 结构：
 * - 渠道统计：按站点展示各广告渠道配置数量
 * - 渠道配置：像素列表 + 弹窗式新增/编辑 + 直接复制推广链接
 * - 单独的「推广链接」页不再作为主操作入口
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\live\service\PromoService;
use think\facade\Db;

final class PromoSetting extends Backend
{
    protected array $noNeedLogin = ['index', 'evSave', 'domainSave', 'pxSave', 'pxToggle', 'pxDel', 'preview', 'pxTest'];

    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    public function index(): void
    {
        $svc     = new PromoService();
        $values  = $svc->all();
        $pixels  = $svc->pixels(false);
        $tab     = (string) $this->request->param('tab', 'config');
        $tab     = in_array($tab, ['stats', 'config'], true) ? $tab : 'config';
        $editId  = (int) $this->request->param('id', 0);
        $showNew = (string) $this->request->param('show', '') === 'new';

        $key           = (string) $this->request->param('_key', '');
        $hiddenKey     = $key !== '' ? '<input type="hidden" name="_key" value="' . htmlspecialchars($key, ENT_QUOTES) . '">' : '';
        $currentTabInp = '<input type="hidden" name="tab" value="config">';
        $siteName      = htmlspecialchars((string) get_sys_config('site_name'), ENT_QUOTES);
        $siteName      = $siteName !== '' ? $siteName : '默认站点';
        $siteId        = 1;

        $on = static fn(string $keyName, string $default = '1'): string => ($values[$keyName] ?? $default) === '1' ? ' checked' : '';
        $evReg = $on('promo_ev_register');
        $evPay = $on('promo_ev_recharge');
        $evSub = $on('promo_ev_subscribe');

        $edit = [
            'id' => 0,
            'platform' => 'facebook',
            'name' => '',
            'pixel_id' => '',
            'events' => 'register,recharge,subscribe',
            'domains' => '',
            'code' => '',
            'capi_token' => '',
            'test_code' => '',
            'sort' => 0,
            'status' => 1,
        ];
        if ($editId > 0) {
            $row = Db::connect('live_mysql')->table('lp_promo_pixel')->where('id', $editId)->find();
            if ($row) {
                $edit = array_merge($edit, $row);
            }
        }

        $eId     = (int) $edit['id'];
        $eEvents = array_map('trim', explode(',', (string) $edit['events']));
        $showForm = $eId > 0 || $showNew;

        $platformOptions = '';
        foreach (PromoService::PIXEL_PLATFORMS as $keyName => $label) {
            $selected = ((string) $edit['platform'] === $keyName) ? ' selected' : '';
            $platformOptions .= "<option value=\"{$keyName}\"{$selected}>{$label}</option>";
        }

        $eventBoxes = '';
        foreach (PromoService::PIXEL_EVENTS as $keyName => $label) {
            $checked = in_array($keyName, $eEvents, true) ? ' checked' : '';
            $eventBoxes .= "<label class=\"event-item\"><input type=\"checkbox\" name=\"events[]\" value=\"{$keyName}\"{$checked}> <span>{$label}</span></label>";
        }

        $domains     = $svc->siteDomains();
        $domainCount = count($domains);
        $domainListV = htmlspecialchars(implode("\n", $domains), ENT_QUOTES);
        $eDomainList = array_values(array_filter(array_map('trim', explode(',', (string) $edit['domains']))));
        $eDomainStr  = implode(',', $eDomainList);
        $isKnownOne  = count($eDomainList) === 1 && in_array($eDomainList[0], $domains, true);
        $useCustom   = $eDomainStr !== '' && !$isKnownOne;

        $domainOptions = '<option value=""' . ($eDomainStr === '' ? ' selected' : '') . '>全部域名（不限）</option>';
        foreach ($domains as $d) {
            $dEsc = htmlspecialchars($d, ENT_QUOTES);
            $selected = $eDomainStr === $d ? ' selected' : '';
            $domainOptions .= '<option value="' . $dEsc . '"' . $selected . '>' . $dEsc . '</option>';
        }
        $domainOptions .= '<option value="__custom__"' . ($useCustom ? ' selected' : '') . '>自定义 / 多个域名…</option>';

        $eName        = htmlspecialchars((string) $edit['name'], ENT_QUOTES);
        $ePixel       = htmlspecialchars((string) $edit['pixel_id'], ENT_QUOTES);
        $eDomain      = htmlspecialchars((string) $edit['domains'], ENT_QUOTES);
        $eCode        = htmlspecialchars((string) $edit['code'], ENT_QUOTES);
        $eToken       = htmlspecialchars((string) ($edit['capi_token'] ?? ''), ENT_QUOTES);
        $eTest        = htmlspecialchars((string) $edit['test_code'], ENT_QUOTES);
        $eSort        = (int) $edit['sort'];
        $eStatus      = (int) $edit['status'] === 1 ? ' checked' : '';
        $customStyle  = $useCustom ? '' : ' style="display:none"';
        $formTitle    = $eId > 0 ? '编辑渠道' : '新增渠道';
        $formBtnText  = $eId > 0 ? '保存修改' : '保存';

        $pixelTotal   = count($pixels);
        $pixelEnabled = count(array_filter($pixels, static fn(array $row): bool => (int) ($row['status'] ?? 0) === 1));
        $pixelDisabled = $pixelTotal - $pixelEnabled;
        $copyableTotal = 0;

        $statBuckets = [
            'facebook' => 0,
            'kwai' => 0,
            'bigo' => 0,
            'tiktok' => 0,
            'google' => 0,
            'okspin' => 0,
            'adjust' => 0,
            'mgskyads' => 0,
            'threebeauties' => 0,
            'traxpix' => 0,
            'geemee' => 0,
            'smartads' => 0,
        ];
        foreach ($pixels as $row) {
            if ((int) ($row['status'] ?? 0) !== 1) {
                continue;
            }
            $bucket = $this->detectStatBucket((string) ($row['platform'] ?? ''), (string) ($row['name'] ?? ''));
            if (isset($statBuckets[$bucket])) {
                $statBuckets[$bucket]++;
            }
        }

        $statsRow = '<tr>'
            . '<td>' . $siteId . '</td>'
            . '<td>' . $siteName . '</td>'
            . '<td>' . $statBuckets['facebook'] . '</td>'
            . '<td>' . $statBuckets['kwai'] . '</td>'
            . '<td>' . $statBuckets['bigo'] . '</td>'
            . '<td>' . $statBuckets['tiktok'] . '</td>'
            . '<td>' . $statBuckets['google'] . '</td>'
            . '<td>' . $statBuckets['okspin'] . '</td>'
            . '<td>' . $statBuckets['adjust'] . '</td>'
            . '<td>' . $statBuckets['mgskyads'] . '</td>'
            . '<td>' . $statBuckets['threebeauties'] . '</td>'
            . '<td>' . $statBuckets['traxpix'] . '</td>'
            . '<td>' . $statBuckets['geemee'] . '</td>'
            . '<td>' . $statBuckets['smartads'] . '</td>'
            . '</tr>';

        $pixelRows = '';
        foreach ($pixels as $row) {
            $id       = (int) $row['id'];
            $platform = (string) $row['platform'];
            $label    = PromoService::PIXEL_PLATFORMS[$platform] ?? $platform;
            $nameRaw  = (string) $row['name'];
            $name     = htmlspecialchars($nameRaw, ENT_QUOTES);
            $pixelId  = htmlspecialchars((string) $row['pixel_id'], ENT_QUOTES);
            $domainsV = trim((string) $row['domains']) !== '' ? htmlspecialchars((string) $row['domains'], ENT_QUOTES) : '<span class="muted">全部域名</span>';
            $tokenV   = trim((string) ($row['capi_token'] ?? '')) !== '' ? '<span class="flag flag-on">已配置</span>' : '<span class="muted">未配置</span>';
            $testV    = trim((string) ($row['test_code'] ?? '')) !== '' ? htmlspecialchars((string) $row['test_code'], ENT_QUOTES) : '<span class="muted">—</span>';
            $status   = (int) $row['status'];
            $events   = [];
            foreach (array_map('trim', explode(',', (string) $row['events'])) as $eventKey) {
                if (isset(PromoService::PIXEL_EVENTS[$eventKey])) {
                    $events[] = PromoService::PIXEL_EVENTS[$eventKey];
                }
            }
            $eventsV    = $events === [] ? '<span class="muted">全部</span>' : htmlspecialchars(implode(' / ', $events), ENT_QUOTES);
            $statusHtml = $status === 1 ? '<span class="flag flag-on">启用</span>' : '<span class="flag flag-off">停用</span>';
            $toggleText = $status === 1 ? '停用' : '启用';
            $linkName      = trim($nameRaw) !== '' ? trim($nameRaw) : ('channel-' . $id);
            $copyPromoBtn  = '';
            $copyPromoHint = '';
            if ($status === 1) {
                foreach ($this->resolvePromoDomains((string) ($row['domains'] ?? ''), $domains) as $promoDomain) {
                    if (strpos($promoDomain, '*') !== false) {
                        continue;
                    }
                    // 普通推广 / 其他不绑定像素：链接只带渠道ID（最短写法），注册时后台按 ID 反解出渠道名称
                    $promoUrl = in_array($platform, ['general', 'other'], true)
                        ? $this->buildPromoLink($promoDomain, (string) $id, '', '')
                        : $this->buildPromoLink($promoDomain, (string) $id, $linkName, $platform);
                    $promoUrlEsc = htmlspecialchars($promoUrl, ENT_QUOTES);
                    $promoDomainEsc = htmlspecialchars($promoDomain, ENT_QUOTES);
                    $copyPromoBtn = '<button type="button" class="link-copy-btn" data-link="' . $promoUrlEsc . '" title="复制 ' . $promoDomainEsc . ' 的推广链接" style="cursor:pointer" onmousedown="this.style.transform=\'translateY(1px) scale(.97)\';this.style.boxShadow=\'0 1px 3px rgba(64,158,255,.22)\';" onmouseup="this.style.transform=\'\';this.style.boxShadow=\'\';" onmouseleave="this.style.transform=\'\';this.style.boxShadow=\'\';" onclick="return onCopyPromoLink(this)">复制链接</button>';
                    $copyPromoHint = '<span class="op-hint">默认域名：' . $promoDomainEsc . '</span>';
                    $copyableTotal++;
                    break;
                }
            }

            $pixelRows .= '<tr>'
                . '<td>' . $id . '</td>'
                . '<td><div class="channel-name">' . ($name !== '' ? $name : '未命名渠道') . '</div><div class="channel-sub">' . htmlspecialchars($label, ENT_QUOTES) . '</div></td>'
                . '<td>' . htmlspecialchars($label, ENT_QUOTES) . '</td>'
                . '<td class="td-wrap">' . $domainsV . '</td>'
                . '<td class="td-wrap mono">' . ($pixelId !== '' ? $pixelId : '<span class="muted">未填写</span>') . '</td>'
                . '<td>' . $eventsV . '</td>'
                . '<td>' . $tokenV . '</td>'
                . '<td>' . $testV . '</td>'
                . '<td>' . $statusHtml . '</td>'
                . '<td class="td-ops">'
                    . '<a href="' . $this->buildIndexUrl(['tab' => 'config', 'id' => $id]) . '">编辑</a>'
                    . '<a href="javascript:void(0)" onclick="testPx(' . $id . ')">测试</a>'
                    . '<a href="/admin/live.PromoSetting/pxToggle?id=' . $id . $this->buildActionQuery(['tab' => 'config']) . '">' . $toggleText . '</a>'
                    . '<a href="/admin/live.PromoSetting/pxDel?id=' . $id . $this->buildActionQuery(['tab' => 'config']) . '" class="danger" onclick="return confirm(\'确认删除该渠道？\')">删除</a>'
                    . $copyPromoBtn
                    . $copyPromoHint
                . '</td>'
                . '</tr>';
        }
        if ($pixelRows === '') {
            $pixelRows = '<tr><td colspan="10" class="empty">还没有渠道配置，点右上角「新增」先添加一条</td></tr>';
        }

        $statsTabClass  = $tab === 'stats' ? 'tab-item is-active' : 'tab-item';
        $configTabClass = $tab === 'config' ? 'tab-item is-active' : 'tab-item';
        $statsTabUrl    = $this->buildIndexUrl(['tab' => 'stats']);
        $configTabUrl   = $this->buildIndexUrl(['tab' => 'config']);
        $newUrl         = $this->buildIndexUrl(['tab' => 'config', 'show' => 'new']);
        $closeUrl       = $this->buildIndexUrl(['tab' => 'config']);
        $closeUrlJs     = json_encode($closeUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $statsPanelStyle  = $tab === 'stats' ? '' : ' style="display:none"';
        $configPanelStyle = $tab === 'config' ? '' : ' style="display:none"';
        $dialogClass      = $showForm ? 'dialog-mask is-open' : 'dialog-mask';

        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>广告渠道配置</title>
<style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f5f7fb;color:#303133;font-size:14px;padding:16px}
    a{text-decoration:none}
    a,button,.btn,.tab-item,.link-copy-btn,.dialog-close,.summary-item{cursor:pointer}
    a:active,button:active,.btn:active,.tab-item:active,.link-copy-btn:active{transform:translateY(1px)}
    .btn:hover{border-color:#c6d4e6;color:#409eff}
    .btn.btn-primary:hover{background:#66b1ff;border-color:#66b1ff;color:#fff}
    button{font-family:inherit}
    .page{max-width:1320px;margin:0 auto}
    .summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:14px}
    .summary-item{background:#fff;border:1px solid #ebeef5;border-radius:4px;padding:14px 16px}
    .summary-item .num{font-size:24px;line-height:1;color:#303133;font-weight:600}
    .summary-item .txt{margin-top:8px;color:#909399;font-size:13px}
    .panel{background:#fff;border:1px solid #ebeef5;border-radius:4px;overflow:hidden}
    .panel-top{display:flex;align-items:center;justify-content:space-between;padding:16px 18px 12px;border-bottom:1px solid #ebeef5}
    .panel-title{font-size:18px;font-weight:600;color:#303133}
    .panel-desc{margin-top:6px;font-size:12px;color:#909399}
    .actions{display:flex;align-items:center;gap:10px}
    .btn{display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 14px;border-radius:4px;border:1px solid #dcdfe6;background:#fff;color:#606266;font-size:13px;cursor:pointer}
    .btn.btn-primary{background:#409eff;border-color:#409eff;color:#fff}
    .btn.btn-plain{background:#ecf5ff;border-color:#b3d8ff;color:#409eff}
    .tabs{display:flex;gap:28px;padding:0 18px;border-bottom:1px solid #ebeef5}
    .tab-item{position:relative;height:46px;line-height:46px;color:#606266;font-size:14px}
    .tab-item.is-active{color:#409eff}
    .tab-item.is-active::after{content:"";position:absolute;left:0;right:0;bottom:0;height:2px;background:#409eff}
    .panel-body{padding:18px}
    .notice{display:flex;align-items:flex-start;gap:8px;padding:12px 14px;margin-bottom:14px;background:#f4faff;border:1px solid #d9ecff;border-radius:4px;color:#606266;font-size:13px;line-height:1.8}
    .notice strong{color:#409eff}
    .sub-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px}
    .sub-title{font-size:14px;font-weight:600;color:#303133}
    .sub-tip{font-size:12px;color:#909399}
    table{width:100%;border-collapse:collapse}
    th,td{padding:12px 10px;border:1px solid #ebeef5;text-align:left;vertical-align:middle;font-size:13px}
    th{background:#f5f7fa;color:#606266;font-weight:600;white-space:nowrap}
    td{color:#303133}
    .td-wrap{word-break:break-all}
    .mono{font-family:Consolas,Monaco,monospace}
    .td-ops a{margin-right:12px;color:#409eff}
    .td-ops a.danger{color:#f56c6c}
    .muted{color:#c0c4cc}
    .empty{text-align:center;color:#909399;padding:28px 0}
    .flag{display:inline-flex;align-items:center;height:22px;padding:0 10px;border-radius:11px;font-size:12px}
    .flag-on{background:#f0f9eb;color:#67c23a}
    .flag-off{background:#f4f4f5;color:#909399}
    .channel-name{font-weight:600;color:#303133}
    .channel-sub{margin-top:4px;font-size:12px;color:#909399}
    .mini-stat{display:flex;gap:12px;flex-wrap:wrap;margin-top:14px}
    .mini-stat .item{padding:8px 12px;background:#fafafa;border:1px solid #ebeef5;border-radius:4px;font-size:12px;color:#606266}
    .mini-stat .item b{color:#303133}
    .split-grid{display:grid;grid-template-columns:1.15fr .85fr;gap:16px;margin-top:14px}
    .card-box{border:1px solid #ebeef5;border-radius:4px;padding:16px}
    .card-box h3{font-size:14px;font-weight:600;color:#303133;margin-bottom:12px}
    .small-form .line{margin-bottom:12px}
    .small-form label{display:block;margin-bottom:6px;font-size:12px;color:#606266}
    .small-form textarea,.small-form input[type=text]{width:100%;padding:8px 10px;border:1px solid #dcdfe6;border-radius:4px;font-size:13px;outline:none}
    .small-form textarea{min-height:96px;resize:vertical}
    .tip{margin-top:6px;font-size:12px;color:#909399;line-height:1.7}
    .td-ops{white-space:nowrap}
    .td-ops .link-copy-btn{margin-left:0}
    .op-hint{display:block;margin-top:6px;font-size:12px;color:#909399;white-space:normal}
    .link-copy-btn{height:26px;padding:0 10px;border:1px solid #b3d8ff;border-radius:4px;background:#ecf5ff;color:#409eff;font-size:12px;cursor:pointer !important;flex:0 0 auto;transition:all .18s ease;user-select:none}
    .link-copy-btn:hover{background:#d9ecff;border-color:#95c8ff;box-shadow:0 2px 8px rgba(64,158,255,.18)}
    .link-copy-btn:active{transform:translateY(1px) scale(.97);box-shadow:0 1px 3px rgba(64,158,255,.22)}
    .link-copy-btn.is-done{background:#f0f9eb;border-color:#c2e7b0;color:#67c23a}
    .copy-toast{position:fixed;left:50%;top:22px;z-index:1400;transform:translate(-50%,-12px);padding:10px 16px;border-radius:8px;background:rgba(0,0,0,.82);color:#fff;font-size:13px;line-height:1.2;opacity:0;pointer-events:none;transition:all .22s ease;box-shadow:0 8px 24px rgba(0,0,0,.22)}
    .copy-toast.show{opacity:1;transform:translate(-50%,0)}
    .dialog-mask{display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.38)}
    .dialog-mask.is-open{display:flex;align-items:flex-start;justify-content:center;padding:24px 16px;overflow:auto}
    .dialog{width:min(880px,100%);max-height:calc(100vh - 48px);display:flex;flex-direction:column;background:#fff;border-radius:4px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.18)}
    .dialog form{display:flex;flex-direction:column;min-height:0;flex:1 1 auto;overflow:hidden}
    .dialog-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #ebeef5;flex:0 0 auto}
    .dialog-head h2{font-size:18px;font-weight:600;color:#303133}
    .dialog-close{width:28px;height:28px;border:none;background:#fff;color:#909399;font-size:22px;line-height:1;cursor:pointer}
    .dialog-body{padding:20px;overflow:auto;flex:1 1 auto}
    .dialog-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 16px}
    .form-item label{display:block;margin-bottom:6px;font-size:13px;color:#606266}
    .form-item input[type=text],.form-item input[type=number],.form-item select,.form-item textarea{width:100%;padding:9px 10px;border:1px solid #dcdfe6;border-radius:4px;font-size:13px;outline:none}
    .form-item textarea{min-height:88px;resize:vertical}
    .form-item textarea.code{min-height:118px;font-family:Consolas,Monaco,monospace}
    .form-item.is-full{grid-column:1 / -1}
    .hint{margin-top:6px;font-size:12px;color:#909399;line-height:1.7}
    .radio-row,.status-row,.event-row{display:flex;align-items:center;flex-wrap:wrap;gap:12px}
    .radio-item,.event-item,.status-item{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#606266}
    .radio-item input,.event-item input,.status-item input{width:14px;height:14px}
    .dialog-foot{display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:14px 20px;border-top:1px solid #ebeef5;flex:0 0 auto;background:#fff}
    .dialog-foot .btn{min-width:88px}
    .test-mask{display:none;position:fixed;inset:0;z-index:1200;background:rgba(0,0,0,.35)}
    .test-mask.open{display:flex;align-items:center;justify-content:center}
    .test-panel{width:min(700px,94vw);background:#fff;border-radius:4px;overflow:hidden}
    .test-head{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #ebeef5}
    .test-head b{font-size:15px}
    .test-head button{width:26px;height:26px;border:none;background:#fff;font-size:20px;color:#909399;cursor:pointer}
    .test-panel pre{padding:16px;max-height:68vh;overflow:auto;white-space:pre-wrap;word-break:break-all;font-size:12px;line-height:1.75}
    .pt-ok{color:#67c23a}
    .pt-bad{color:#f56c6c}
    @media (max-width: 980px){
        .summary{grid-template-columns:repeat(2,minmax(0,1fr))}
        .split-grid,.dialog-grid{grid-template-columns:1fr}
        .panel-top,.sub-actions{align-items:flex-start}
        .actions{flex-wrap:wrap;justify-content:flex-end}
        .dialog-mask.is-open{padding:12px}
        .dialog{max-height:calc(100vh - 24px)}
        .dialog-body{padding:16px}
        .dialog-head,.dialog-foot{padding-left:16px;padding-right:16px}
    }
</style>
</head>
<body>
<div class="page">
    <div class="summary">
        <div class="summary-item"><div class="num">{$pixelTotal}</div><div class="txt">渠道配置总数</div></div>
        <div class="summary-item"><div class="num">{$pixelEnabled}</div><div class="txt">启用中的渠道</div></div>
        <div class="summary-item"><div class="num">{$copyableTotal}</div><div class="txt">可复制推广链接</div></div>
        <div class="summary-item"><div class="num">{$domainCount}</div><div class="txt">已收录推广域名</div></div>
    </div>

    <div class="panel">
        <div class="panel-top">
            <div>
                <div class="panel-title">广告渠道配置</div>
                <div class="panel-desc">把像素配置、推广链接复制和统计报表收敛到同一套后台结构里，操作路径按你给的参考后台改成双 Tab 形式。</div>
            </div>
            <div class="actions">
                <a class="btn btn-primary" href="{$newUrl}">新增</a>
            </div>
        </div>

        <div class="tabs">
            <a class="{$statsTabClass}" href="{$statsTabUrl}">渠道统计</a>
            <a class="{$configTabClass}" href="{$configTabUrl}">渠道配置</a>
        </div>

        <div class="panel-body">
            <div class="notice">
                <div>•</div>
                <div>
                    当前这版直接在 <strong>渠道配置列表</strong> 里生成并复制推广链接，来源归因仍沿用现有
                    <strong>reg_link_name / reg_channel / reg_px</strong> 字段，不再单独维护「推广链接」页。
                </div>
            </div>

            <div{$statsPanelStyle}>
                <div class="sub-actions">
                    <div>
                        <div class="sub-title">渠道统计</div>
                    <div class="sub-tip">按当前站点统计已启用的广告渠道配置数量，表头结构对齐参考后台。</div>
                    </div>
                    <div class="sub-tip">站点：{$siteName}</div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>站点ID</th>
                            <th>站点名称</th>
                            <th>Facebook广告</th>
                            <th>Kwai广告</th>
                            <th>Bigo广告</th>
                            <th>Tiktok广告</th>
                            <th>Google广告</th>
                            <th>Okspin广告</th>
                            <th>Adjust广告</th>
                            <th>MgskyAds广告</th>
                            <th>3beesuties广告</th>
                            <th>TraxPix广告</th>
                            <th>GeeMee广告</th>
                            <th>SmartAds广告</th>
                        </tr>
                    </thead>
                    <tbody>{$statsRow}</tbody>
                </table>
                <div class="mini-stat">
                    <div class="item">启用渠道：<b>{$pixelEnabled}</b></div>
                    <div class="item">停用渠道：<b>{$pixelDisabled}</b></div>
                    <div class="item">可复制链接：<b>{$copyableTotal}</b></div>
                    <div class="item">推广域名：<b>{$domainCount}</b></div>
                </div>
            </div>

            <div{$configPanelStyle}>
                <div class="sub-actions">
                    <div>
                        <div class="sub-title">渠道配置</div>
                        <div class="sub-tip">列表展示当前像素渠道，并可按域名直接复制投放链接；右侧保留全局事件和域名维护。</div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width:72px">ID</th>
                            <th style="min-width:180px">渠道名称</th>
                            <th style="min-width:140px">渠道类型</th>
                            <th style="min-width:160px">推广域名</th>
                            <th style="min-width:190px">像素(Pixel Id)</th>
                            <th style="min-width:140px">上报事件</th>
                            <th style="width:110px">令牌(Token)</th>
                            <th style="width:120px">测试码</th>
                            <th style="width:92px">状态</th>
                            <th style="min-width:200px">操作</th>
                        </tr>
                    </thead>
                    <tbody>{$pixelRows}</tbody>
                </table>

                <div class="split-grid">
                    <div class="card-box">
                        <h3>转化事件总开关</h3>
                        <form class="small-form" method="post" action="/admin/live.PromoSetting/evSave">
                            {$hiddenKey}
                            {$currentTabInp}
                            <div class="line status-row">
                                <label class="status-item"><input type="checkbox" name="promo_ev_register" value="1"{$evReg}> 完成注册</label>
                                <label class="status-item"><input type="checkbox" name="promo_ev_recharge" value="1"{$evPay}> 购物 / 充值</label>
                                <label class="status-item"><input type="checkbox" name="promo_ev_subscribe" value="1"{$evSub}> 订阅</label>
                            </div>
                            <div class="tip">这里是总闸，关掉后所有渠道都不再上报该事件；浏览事件不受影响。</div>
                            <div style="margin-top:12px"><button class="btn btn-primary" type="submit">保存总开关</button></div>
                        </form>
                    </div>

                    <div class="card-box">
                        <h3>前端推广域名</h3>
                        <form class="small-form" method="post" action="/admin/live.PromoSetting/domainSave">
                            {$hiddenKey}
                            {$currentTabInp}
                            <div class="line">
                                <label>一行一个（当前 {$domainCount} 个）</label>
                                <textarea name="promo_site_domains" placeholder="promo.example.com&#10;lp.example.net">{$domainListV}</textarea>
                            </div>
                            <div class="tip">前台访问时会自动收录域名；你也可以手动增删。像素里选中的域名表示这条渠道只在该域名上生效。</div>
                            <div style="margin-top:12px"><button class="btn btn-primary" type="submit">保存域名列表</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="promoDialog" class="{$dialogClass}">
    <div class="dialog">
        <div class="dialog-head">
            <h2>{$formTitle}</h2>
            <a href="{$closeUrl}" class="dialog-close" onclick="return closePromoDialog(event)" aria-label="关闭">×</a>
        </div>
        <form method="post" action="/admin/live.PromoSetting/pxSave">
            {$hiddenKey}
            {$currentTabInp}
            <input type="hidden" name="id" value="{$eId}">
            <div class="dialog-body">
                <div class="dialog-grid">
                    <div class="form-item">
                        <label><span style="color:#f56c6c">*</span> 渠道名称</label>
                        <input type="text" name="name" value="{$eName}" placeholder="请输入渠道名称，如 FB-马来1号">
                    </div>
                    <div class="form-item">
                        <label>渠道指向</label>
                        <div class="radio-row">
                            <label class="radio-item"><input type="radio" checked disabled> 主站名</label>
                            <label class="radio-item"><input type="radio" checked> H5页面</label>
                            <label class="radio-item"><input type="radio" disabled> 短信营销</label>
                        </div>
                        <div class="hint">当前投放链路走现有 H5 主站，先按参考后台保留这个结构。</div>
                    </div>
                    <div class="form-item">
                        <label><span style="color:#f56c6c">*</span> 推广域名</label>
                        <select name="domains" id="pxDomains">{$domainOptions}</select>
                        <input type="text" name="domains_custom" id="pxDomainsCustom" value="{$eDomain}" placeholder="多个域名用逗号分隔，支持 *.example.com"{$customStyle}>
                        <div class="hint">选具体域名表示该渠道只在该域名生效；不选表示全站生效。</div>
                    </div>
                    <div class="form-item">
                        <label><span style="color:#f56c6c">*</span> 渠道类型</label>
                        <select name="platform" id="pxPlatform">{$platformOptions}</select>
                        <div class="hint px-general-hint" style="display:none">「普通推广 / 其他」不接像素，只需填「渠道名称 + 推广域名」即可保存，之后可直接复制推广链接（适合短信、群发等渠道）。</div>
                    </div>
                    <div class="form-item px-only">
                        <label>像素(Pixel Id)</label>
                        <input type="text" name="pixel_id" value="{$ePixel}" placeholder="例如 1115897220944131 / G-XXXX / AW-XXXX">
                    </div>
                    <div class="form-item px-only">
                        <label>令牌(Token)</label>
                        <input type="text" name="capi_token" value="{$eToken}" placeholder="EAA...">
                        <div class="hint">填写后注册 / 充值 / 订阅会由服务端直连回传，并与浏览器端自动去重。</div>
                    </div>
                    <div class="form-item px-only">
                        <label>测试码(test_event_code)</label>
                        <input type="text" name="test_code" value="{$eTest}" placeholder="例如 TEST88641">
                    </div>
                    <div class="form-item">
                        <label>排序</label>
                        <input type="number" name="sort" value="{$eSort}" placeholder="越小越靠前">
                    </div>
                    <div class="form-item is-full px-only">
                        <label>事件选择</label>
                        <div class="event-row">{$eventBoxes}</div>
                        <div class="hint">全不勾表示接收全部事件。</div>
                    </div>
                    <div class="form-item is-full px-only">
                        <label>像素代码（可选，原样注入页面 head）</label>
                        <textarea class="code" name="code" placeholder="&lt;script&gt;...&lt;/script&gt;">{$eCode}</textarea>
                        <div class="hint">用于 Snapchat / Kwai / Bing 等未内置平台，或给已有像素补充自定义脚本。</div>
                    </div>
                    <div class="form-item is-full">
                        <label>状态</label>
                        <div class="status-row">
                            <label class="status-item"><input type="checkbox" name="status" value="1"{$eStatus}> 启用该渠道</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="dialog-foot">
                <a class="btn" href="{$closeUrl}" onclick="return closePromoDialog(event)">取消</a>
                <button class="btn btn-primary" type="submit">{$formBtnText}</button>
            </div>
        </form>
    </div>
</div>

<div id="pxTestMask" class="test-mask">
    <div class="test-panel">
        <div class="test-head">
            <b id="pxTestTitle">测试回传</b>
            <button type="button" onclick="closePxTest()">×</button>
        </div>
        <pre id="pxTestBody">请求中…</pre>
    </div>
</div>
<input type="text" id="promoCopyProxy" value="" readonly style="position:fixed;left:-9999px;top:-9999px;opacity:0;pointer-events:none">
<div id="copyToast" class="copy-toast">复制成功</div>

<script>
var promoCloseUrl = {$closeUrlJs};
var copyToastTimer = null;

function closePromoDialog(event){
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    var dialog = document.getElementById('promoDialog');
    if (dialog) {
        dialog.classList.remove('is-open');
        dialog.style.display = 'none';
    }
    if (window.location.href !== promoCloseUrl) {
        window.setTimeout(function () {
            window.location.replace(promoCloseUrl);
        }, 10);
    }
    return false;
}

function copyText(text) {
    var proxy = document.getElementById('promoCopyProxy');
    if (!proxy) {
        window.prompt('请手动复制下面链接', text);
        return false;
    }
    proxy.value = text;
    proxy.removeAttribute('readonly');
    proxy.focus();
    proxy.select();
    if (proxy.setSelectionRange) {
        proxy.setSelectionRange(0, proxy.value.length);
    }
    proxy.setAttribute('readonly', 'readonly');
    try {
        if (document.execCommand('copy')) {
            return true;
        }
    } catch (err) {}
    window.prompt('请手动复制下面链接', text);
    return false;
}

function showCopyToast(text) {
    var toast = document.getElementById('copyToast');
    if (!toast) {
        return;
    }
    toast.textContent = text || '复制成功';
    toast.classList.add('show');
    if (copyToastTimer) {
        window.clearTimeout(copyToastTimer);
    }
    copyToastTimer = window.setTimeout(function () {
        toast.classList.remove('show');
    }, 1600);
}

function onCopyPromoLink(btn) {
    var text = btn ? (btn.getAttribute('data-link') || '') : '';
    if (!text) return false;
    var oldText = btn.textContent;
    if (copyText(text)) {
        btn.textContent = '已复制';
        btn.classList.add('is-done');
        showCopyToast('复制成功');
        window.alert('复制成功');
        window.setTimeout(function () {
            btn.textContent = oldText;
            btn.classList.remove('is-done');
        }, 1200);
        window.setTimeout(function () {
            try { window.getSelection().removeAllRanges(); } catch (err) {}
        }, 50);
    }
    return false;
}

function bindPromoCopyButtons() {
    var list = document.querySelectorAll('.link-copy-btn');
    for (var i = 0; i < list.length; i++) {
        list[i].onclick = function () {
            return onCopyPromoLink(this);
        };
    }
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        var dialog = document.getElementById('promoDialog');
        if (dialog && dialog.classList.contains('is-open')) {
            closePromoDialog();
        }
    }
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindPromoCopyButtons);
} else {
    bindPromoCopyButtons();
}

function testPx(id) {
    var mask = document.getElementById('pxTestMask');
    var body = document.getElementById('pxTestBody');
    document.getElementById('pxTestTitle').textContent = '测试回传 · 渠道 #' + id;
    body.className = '';
    body.textContent = '请求 Facebook 中…';
    mask.classList.add('open');
    fetch('/admin/live.PromoSetting/pxTest?id=' + id)
        .then(function (r) { return r.json(); })
        .then(function (d) {
            var extra = d.diag ? '\\n\\n—— 自诊断 ——\\n' + d.diag : '';
            body.textContent = (d.ok ? '✅ 回传成功\\n\\n' : '❌ 回传失败\\n\\n') + 'HTTP ' + d.http + '\\n' + d.resp + extra;
            body.className = d.ok ? 'pt-ok' : 'pt-bad';
        })
        .catch(function (e) {
            body.textContent = '请求失败：' + e;
            body.className = 'pt-bad';
        });
}

function closePxTest() {
    document.getElementById('pxTestMask').classList.remove('open');
}

document.addEventListener('click', function (e) {
    if (e.target && e.target.id === 'pxTestMask') {
        closePxTest();
    }
    if (e.target && e.target.id === 'promoDialog') {
        closePromoDialog();
    }
});

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

/* 渠道类型 = 普通推广 / 其他 时，隐藏像素相关字段（不需要媒体像素也能保存并复制链接） */
(function () {
    var sel = document.getElementById('pxPlatform');
    if (!sel) return;
    function sync() {
        var noPixel = sel.value === 'general' || sel.value === 'other';
        var list = document.querySelectorAll('.px-only');
        for (var i = 0; i < list.length; i++) {
            list[i].style.display = noPixel ? 'none' : '';
        }
        var hint = document.querySelector('.px-general-hint');
        if (hint) hint.style.display = noPixel ? 'block' : 'none';
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>
</body>
</html>
HTML;
        exit;
    }

    /** 测试回传：用该像素的 像素ID + 回传令牌 发一条测试事件，返回 FB 原始响应 */
    public function pxTest(): void
    {
        $id  = (int) $this->request->param('id', 0);
        $row = $id > 0 ? Db::connect('live_mysql')->table('lp_promo_pixel')->where('id', $id)->find() : null;
        $res = $row
            ? \app\live\service\FbCapiService::probe($row)
            : ['ok' => false, 'http' => 0, 'resp' => '像素不存在'];
        response(json_encode($res, JSON_UNESCAPED_UNICODE), 200, ['Content-Type' => 'application/json; charset=utf-8'])->send();
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
            $this->renderToast(false, $e->getMessage(), $this->buildIndexUrl(['tab' => 'config']));
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
            $this->renderToast(false, $e->getMessage(), $this->buildIndexUrl(['tab' => 'config']));
        }
    }

    /** 新增 / 编辑像素 */
    public function pxSave(): void
    {
        try {
            $id        = (int) $this->request->post('id', 0);
            $platform  = (string) $this->request->post('platform', 'facebook');
            $platform  = isset(PromoService::PIXEL_PLATFORMS[$platform]) ? $platform : 'other';
            $name      = trim((string) $this->request->post('name', ''));
            $pixelId   = $this->cleanId((string) $this->request->post('pixel_id', ''));
            $code      = trim((string) $this->request->post('code', ''));
            $capiToken = mb_substr(trim((string) $this->request->post('capi_token', '')), 0, 255);
            $testCode  = $this->cleanId((string) $this->request->post('test_code', ''));
            $rawDomain = (string) $this->request->post('domains', '');
            if ($rawDomain === '__custom__') {
                $rawDomain = (string) $this->request->post('domains_custom', '');
            }
            $domains = $this->cleanDomains($rawDomain);
            $sort    = (int) $this->request->post('sort', 0);
            $status  = (string) $this->request->post('status', '') === '1' ? 1 : 0;

            $events = $this->request->param('events/a', []);
            $events = array_values(array_intersect(
                array_map('strval', is_array($events) ? $events : []),
                array_keys(PromoService::PIXEL_EVENTS)
            ));
            $eventsStr = $events === [] ? implode(',', array_keys(PromoService::PIXEL_EVENTS)) : implode(',', $events);

            if ($name === '') {
                throw new \InvalidArgumentException('渠道名称不能为空');
            }
            // 普通推广（短信 / 群发）与「其他」不接像素，只要有名称 + 域名即可保存
            if (!in_array($platform, ['general', 'other'], true) && $pixelId === '' && $code === '') {
                throw new \InvalidArgumentException('像素ID 和 像素代码 至少要填一个');
            }

            $db   = Db::connect('live_mysql');
            $now  = date('Y-m-d H:i:s');
            $data = [
                'platform' => $platform,
                'name' => $name,
                'pixel_id' => $pixelId,
                'events' => $eventsStr,
                'domains' => $domains,
                'code' => $code,
                'capi_token' => $capiToken,
                'test_code' => $testCode,
                'sort' => $sort,
                'status' => $status,
                'updated_at' => $now,
            ];
            if ($id > 0) {
                $db->table('lp_promo_pixel')->where('id', $id)->update($data);
            } else {
                $data['created_at'] = $now;
                $db->table('lp_promo_pixel')->insert($data);
            }

            (new PromoService())->flushCache();
            $this->backToIndex($id > 0 ? '渠道已更新' : '渠道已新增');
        } catch (\Throwable $e) {
            $this->renderToast(false, $e->getMessage(), $this->buildIndexUrl(['tab' => 'config']));
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

    private function detectStatBucket(string $platform, string $name): string
    {
        $needle = strtolower($platform . ' ' . $name);
        // 普通推广不属于任何媒体广告，不计入统计表的广告列
        if ($platform === 'general') {
            return 'general';
        }
        if ($platform === 'facebook' || preg_match('/facebook|meta|instagram|fb/', $needle) === 1) {
            return 'facebook';
        }
        if ($platform === 'kwai' || preg_match('/kwai|kuaishou/', $needle) === 1) {
            return 'kwai';
        }
        if ($platform === 'tiktok' || preg_match('/tiktok|tt/', $needle) === 1) {
            return 'tiktok';
        }
        if ($platform === 'google' || preg_match('/google|ga4|gtm|ads/', $needle) === 1) {
            return 'google';
        }
        if (preg_match('/bigo/', $needle) === 1) {
            return 'bigo';
        }
        if (preg_match('/okspin/', $needle) === 1) {
            return 'okspin';
        }
        if (preg_match('/adjust/', $needle) === 1) {
            return 'adjust';
        }
        if (preg_match('/mgsky/', $needle) === 1) {
            return 'mgskyads';
        }
        if (preg_match('/3bees|3beauties/', $needle) === 1) {
            return 'threebeauties';
        }
        if (preg_match('/traxpix/', $needle) === 1) {
            return 'traxpix';
        }
        if (preg_match('/geemee/', $needle) === 1) {
            return 'geemee';
        }
        return 'smartads';
    }

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

    private function resolvePromoDomains(string $raw, array $siteDomains): array
    {
        $cleaned = $this->cleanDomains($raw);
        if ($cleaned !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $cleaned))));
        }
        return array_values(array_filter(array_map('trim', $siteDomains)));
    }

    private function buildPromoLink(string $domain, string $px, string $name, string $channel): string
    {
        $qs = [];
        if ($px !== '') {
            $qs['px'] = $px;
        }
        if ($name !== '') {
            $qs['name'] = $name;
        }
        if ($channel !== '') {
            $qs['ch'] = $channel;
        }
        return 'https://' . $domain . '/' . ($qs !== [] ? '?' . http_build_query($qs) : '');
    }

    private function buildIndexUrl(array $params = []): string
    {
        $query = [];
        $key = (string) $this->request->param('_key', '');
        if ($key !== '') {
            $query['_key'] = $key;
        }
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null) {
                continue;
            }
            $query[$k] = $v;
        }
        return '/admin/live.PromoSetting/index' . ($query !== [] ? '?' . http_build_query($query) : '');
    }

    private function buildActionQuery(array $params = []): string
    {
        $query = [];
        $key = (string) $this->request->param('_key', '');
        if ($key !== '') {
            $query['_key'] = $key;
        }
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null) {
                continue;
            }
            $query[$k] = $v;
        }
        return $query !== [] ? '&' . http_build_query($query) : '';
    }

    /** 操作后回列表页 */
    private function backToIndex(string $msg): void
    {
        $tab = (string) $this->request->param('tab', 'config');
        $tab = in_array($tab, ['stats', 'config'], true) ? $tab : 'config';
        $this->renderToast(true, $msg, $this->buildIndexUrl(['tab' => $tab]), 1200);
    }

    private function renderToast(bool $success, string $msg, string $listUrl, int $delay = 5000): void
    {
        $escMsg   = htmlspecialchars($msg, ENT_QUOTES);
        $redirect = $success ? "<script>setTimeout(function(){location.href='{$listUrl}'},{$delay})</script>" : '';
        $color    = $success ? '#67c23a' : '#f56c6c';
        $icon     = $success
            ? '<polyline points="20 6 9 17 4 12"/>'
            : '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>';
        echo <<<HTML
        <!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>操作结果</title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:-apple-system,sans-serif;background:#f5f7fa;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
            .toast-box{background:#fff;padding:36px 44px;border-radius:14px;box-shadow:0 8px 28px rgba(0,0,0,.1);text-align:center;max-width:520px}
            .toast-icon{width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;background:rgba(0,0,0,.04)}
            .toast-icon svg{width:28px;height:28px;color:{$color}}
            .toast-msg{font-size:14px;color:#333;margin-bottom:20px;line-height:1.8}
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
