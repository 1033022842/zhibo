<?php
/**
 * 充值渠道管理
 * 访问: /admin/live.RechargeChannel/index
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use think\facade\Db;
use app\admin\model\live\RechargeChannel as ChannelModel;

final class RechargeChannel extends Backend
{
    protected object $model;

    // 绕过登录检查（iframe 模式下 cookie 不共享）
    protected array $noNeedLogin = ['index', 'editChannel', 'vipPlanSave', 'vipPlanToggle', 'editVip'];

    // 飘页访问守卫：URL ?_key= 首次校验后走 Cookie（密钥 .env ADMIN_FLOAT_KEY）
    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new ChannelModel();
    }

    public function index(): void
    {
        $list = $this->model->order('sort', 'asc')->paginate(20);
        $vipPlans = Db::connect('live_mysql')->table('lp_vip_plan')->order('sort', 'asc')->select()->toArray();
        $html = $this->renderPage($list, $vipPlans);
        response($html)->send();
        exit;
    }

    public function editChannel(): void
    {
        $id = $this->request->param('id/d', 0);
        $row = $id ? $this->model->find($id) : null;

        if ($this->request->isPost()) {
            try {
                // 只提取数据库表字段，避免文件字段等混入
                $data = [
                    'name'         => $this->request->post('name', ''),
                    'type'         => $this->request->post('type', 'usdt_trc20'),
                    'address'      => $this->request->post('address', ''),
                    'diamond_rate' => $this->request->post('diamond_rate/f', 100),
                    'min_amount'   => $this->request->post('min_amount/f', 10),
                    'sort'         => $this->request->post('sort/d', 0),
                    'status'       => $this->request->post('status/d', 0),
                ];
                // 服务端校验
                if (empty(trim($data['name']))) {
                    throw new \InvalidArgumentException('渠道名称不能为空');
                }
                if ($data['diamond_rate'] <= 0) {
                    throw new \InvalidArgumentException('汇率必须大于0');
                }
                // 处理二维码图片上传（仅当确有文件被上传时才处理）
                $hasUpload = !empty($_FILES['qr_image']['tmp_name'])
                    && $_FILES['qr_image']['error'] === UPLOAD_ERR_OK
                    && $_FILES['qr_image']['size'] > 0;
                if ($hasUpload) {
                    try {
                        $file = $this->request->file('qr_image');
                        $upload = new \app\common\library\Upload($file);
                        $upload->setTopic('recharge_qr');
                        $attachment = $upload->upload(null, 0, 0);
                        $data['qr_code_url'] = $attachment['url'];
                    } catch (\Exception $e) {
                        throw new \InvalidArgumentException('图片上传失败: ' . $e->getMessage());
                    }
                }
                if ($id) {
                    $row->save($data);
                } else {
                    $this->model->save($data);
                }
                $this->renderSuccess('保存成功');
            } catch (\Exception $e) {
                $this->renderError('保存失败: ' . $e->getMessage());
            }
            return;
        }

        $r = $row ? $row->toArray() : ['id'=>'','name'=>'','type'=>'usdt_trc20','qr_code_url'=>'','address'=>'','diamond_rate'=>'100','min_amount'=>'10','sort'=>'0','status'=>'1'];
        $checked = ($r['status'] == 1) ? 'checked' : '';
        $hdrTitle = $r['id'] ? '编辑渠道' : '新增渠道';
        $selUsdt = ($r['type'] == 'usdt_trc20') ? 'selected' : '';
        $selOther = ($r['type'] == 'other') ? 'selected' : '';
        $name = htmlspecialchars((string)$r['name'], ENT_QUOTES);
        // 二维码预览：拼接完整URL确保在iframe中也能正常显示
        $qrRaw = (string)$r['qr_code_url'];
        $qrUrl = '';
        if ($qrRaw) {
            $qrUrl = (str_starts_with($qrRaw, 'http')) ? $qrRaw : rtrim($this->request->domain(), '/') . '/' . ltrim($qrRaw, '/');
        }
        $qr = htmlspecialchars($qrUrl, ENT_QUOTES);
        $addr = htmlspecialchars((string)$r['address'], ENT_QUOTES);
        $rate = htmlspecialchars((string)$r['diamond_rate'], ENT_QUOTES);
        $min = htmlspecialchars((string)$r['min_amount'], ENT_QUOTES);
        $sort = htmlspecialchars((string)$r['sort'], ENT_QUOTES);

        // 二维码预览
        $qrPreview = '';
        if ($qr) {
            $qrPreview = "<div style='margin-top:8px'><img src='{$qr}' style='max-width:200px;max-height:200px;border-radius:8px;border:1px solid #ddd' /></div>";
        }

        echo <<<HTML
        <!DOCTYPE html><html lang="zh-CN">
        <head><meta charset="UTF-8"><title>编辑渠道</title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:-apple-system,sans-serif;background:#f5f7fa;color:#333;padding:20px}
            .container{max-width:600px;margin:0 auto;background:#fff;border-radius:12px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
            h2{margin-bottom:20px}
            .field{margin-bottom:16px}
            .field label{display:block;font-size:13px;color:#666;margin-bottom:4px}
            .field input,.field select{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px}
            .field input[type=file]{padding:8px}
            .btn{padding:10px 24px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
            .btn-primary{background:#409eff;color:#fff}
            .btn-cancel{background:#eee;color:#666;margin-left:8px}
            .toggle-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
            .toggle-label{font-size:13px;color:#666}
            .toggle{position:relative;display:inline-block;width:44px;height:24px}
            .toggle input{opacity:0;width:0;height:0}
            .toggle .slider{position:absolute;cursor:pointer;inset:0;background:#ccc;border-radius:24px;transition:.25s}
            .toggle .slider:before{content:'';position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.25s}
            .toggle input:checked+.slider{background:#00d4aa}
            .toggle input:checked+.slider:before{transform:translateX(20px)}
        </style></head>
        <body>
        <div class="container">
            <h2>{$hdrTitle}</h2>
            <form method="post" enctype="multipart/form-data">
                <div class="field"><label>名称</label><input name="name" value="{$name}" required /></div>
                <div class="field"><label>类型</label><select name="type"><option value="usdt_trc20" {$selUsdt}>USDT-TRC20</option><option value="other" {$selOther}>其他</option></select></div>
                <div class="field"><label>收款二维码图片</label><input type="file" name="qr_image" accept="image/*" />{$qrPreview}</div>
                <div class="field"><label>收款地址</label><input name="address" value="{$addr}" placeholder="USDT地址 / 银行账号" /></div>
                <div class="field"><label>汇率（1USDT=N钻石）</label><input type="number" step="0.01" name="diamond_rate" value="{$rate}" required /></div>
                <div class="field"><label>最低充值金额</label><input type="number" step="0.01" name="min_amount" value="{$min}" required /></div>
                <div class="field"><label>排序</label><input type="number" name="sort" value="{$sort}" /></div>
                <div class="toggle-row">
                    <span class="toggle-label">启用状态</span>
                    <label class="toggle">
                        <input type="checkbox" name="status" value="1" {$checked} />
                        <span class="slider"></span>
                    </label>
                </div>
                <button class="btn btn-primary" type="submit">保存</button>
                <a class="btn btn-cancel" href="/admin/live.RechargeChannel/index">取消</a>
            </form>
        </div></body></html>
        HTML;
        exit;
    }

    /**
     * VIP 套餐编辑表单 + 保存入口
     */
    public function editVip(): void
    {
        $id = (int) $this->request->param('id', 0);
        $row = $id ? Db::connect('live_mysql')->table('lp_vip_plan')->where('id', $id)->find() : null;

        $r = $row ? $row : ['id' => '', 'name' => '', 'months' => '1', 'usd_price' => '', 'diamond_price' => '', 'daily_diamond' => '', 'sort' => '0'];
        $vName = htmlspecialchars((string)$r['name'], ENT_QUOTES);
        $vMonths = (int)$r['months'];
        $vUsd = htmlspecialchars((string)$r['usd_price'], ENT_QUOTES);
        $vDia = htmlspecialchars((string)$r['diamond_price'], ENT_QUOTES);
        $vDaily = htmlspecialchars((string)$r['daily_diamond'], ENT_QUOTES);
        $vSort = (int)$r['sort'];
        $vId = (int)$r['id'];
        $hdr = $vId ? '编辑套餐' : '新增套餐';

        echo <<<HTML
        <!DOCTYPE html><html lang="zh-CN">
        <head><meta charset="UTF-8"><title>{$hdr}</title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:-apple-system,sans-serif;background:#f5f7fa;color:#333;padding:20px}
            .container{max-width:600px;margin:0 auto;background:#fff;border-radius:12px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
            h2{margin-bottom:20px}
            .field{margin-bottom:16px}
            .field label{display:block;font-size:13px;color:#666;margin-bottom:4px}
            .field input{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px}
            .btn{padding:10px 24px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
            .btn-primary{background:#409eff;color:#fff}
            .btn-cancel{background:#eee;color:#666;margin-left:8px;text-decoration:none;display:inline-block}
        </style></head>
        <body><div class="container">
            <h2>{$hdr}</h2>
            <form method="post" action="/admin/live.RechargeChannel/vipPlanSave">
                <input type="hidden" name="id" value="{$vId}" />
                <div class="field"><label>套餐名称</label><input name="name" value="{$vName}" required /></div>
                <div class="field"><label>月数</label><input type="number" name="months" value="{$vMonths}" min="1" required /></div>
                <div class="field"><label>USDT 参考价（页面展示用）</label><input type="number" step="0.01" name="usd_price" value="{$vUsd}" required /></div>
                <div class="field"><label>钻石支付价（购买实际扣钻）</label><input type="number" step="0.01" name="diamond_price" value="{$vDia}" required /></div>
                <div class="field"><label>每日领取钻石</label><input type="number" step="0.01" name="daily_diamond" value="{$vDaily}" required /></div>
                <div class="field"><label>排序</label><input type="number" name="sort" value="{$vSort}" /></div>
                <button class="btn btn-primary" type="submit">保存</button>
                <a class="btn btn-cancel" href="/admin/live.RechargeChannel/index">取消</a>
            </form>
        </div></body></html>
        HTML;
        exit;
    }

    /**
     * VIP 套餐保存（新增/编辑）
     */
    public function vipPlanSave(): void
    {
        $id   = (int) $this->request->post('id', 0);
        $data = [
            'name'          => trim((string) $this->request->post('name', '')),
            'months'        => max(1, (int) $this->request->post('months', 1)),
            'usd_price'    => (float) $this->request->post('usd_price', 0),
            'diamond_price' => (float) $this->request->post('diamond_price', 0),
            'daily_diamond' => (float) $this->request->post('daily_diamond', 0),
            'sort'          => (int) $this->request->post('sort', 0),
        ];
        if ($data['name'] === '' || $data['diamond_price'] <= 0) {
            $this->renderError('套餐名必填且钻石价必须大于0');
            return;
        }
        try {
            if ($id) {
                Db::connect('live_mysql')->table('lp_vip_plan')->where('id', $id)->update($data);
            } else {
                $data['status'] = 1;
                Db::connect('live_mysql')->table('lp_vip_plan')->insert($data);
            }
            $this->renderSuccess('套餐已保存');
        } catch (\Exception $e) {
            $this->renderError('保存失败: ' . $e->getMessage());
        }
    }

    /**
     * VIP 套餐启用/禁用切换
     */
    public function vipPlanToggle(): void
    {
        $id = (int) $this->request->param('id', 0);
        $row = Db::connect('live_mysql')->table('lp_vip_plan')->where('id', $id)->find();
        if (!$row) {
            $this->renderError('套餐不存在');
            return;
        }
        Db::connect('live_mysql')->table('lp_vip_plan')
            ->where('id', $id)
            ->update(['status' => ((int)$row['status'] === 1) ? 0 : 1]);
        $this->renderSuccess('状态已切换');
    }

    private function renderPage($list, array $vipPlans = []): string
    {
        $vipRows = '';
        foreach ($vipPlans as $v) {
            $v['usd_price'] = $v['usd_price'] ?? '0.00';
            $vid = (int)$v['id'];
            $vst = ((int)$v['status'] === 1) ? '<span style="color:#00a870">启用</span>' : '<span style="color:#999">禁用</span>';
            $vipRows .= "<tr><td>{$vid}</td><td>" . htmlspecialchars((string)$v['name']) . "</td><td>{$v['months']}</td><td>{$v['usd_price']}</td><td>{$v['diamond_price']}</td><td>{$v['daily_diamond']}</td><td>{$v['sort']}</td><td>{$vst}</td>"
                . "<td><a class='btn-edit' href='/admin/live.RechargeChannel/editVip?id={$vid}'>编辑</a> "
                . "<a class='btn-edit' href='/admin/live.RechargeChannel/vipPlanToggle?id={$vid}' onclick=\"return confirm('切换启用状态?')\">切换</a></td></tr>";
        }
        if ($vipRows === '') {
            $vipRows = '<tr><td colspan="9" style="text-align:center;color:#999">暂无套餐</td></tr>';
        }

        $rows = '';
        foreach ($list->items() as $r) {
            $st = ($r['status'] == 1) ? '<span style="color:#00d4aa">启用</span>' : '<span style="color:#ff2d55">禁用</span>';
            $qr = $r['qr_code_url'] ? '有' : '无';
            $rid = (int)$r['id'];
            $rname = htmlspecialchars((string)$r['name'], ENT_QUOTES);
            $rtype = htmlspecialchars((string)$r['type'], ENT_QUOTES);
            $raddr = htmlspecialchars((string)$r['address'], ENT_QUOTES);
            $rrate = htmlspecialchars((string)$r['diamond_rate'], ENT_QUOTES);
            $rmin  = htmlspecialchars((string)$r['min_amount'], ENT_QUOTES);
            $rsort = htmlspecialchars((string)$r['sort'], ENT_QUOTES);
            $rows .= "<tr><td>{$rid}</td><td>{$rname}</td><td>{$rtype}</td><td>{$qr}</td><td>{$raddr}</td><td>{$rrate}</td><td>{$rmin}</td><td>{$rsort}</td><td>{$st}</td><td><a href='/admin/live.RechargeChannel/editChannel?id={$rid}' class='btn-edit'>编辑</a></td></tr>";
        }
        $pager = $list->render();
        return <<<HTML
        <!DOCTYPE html><html lang="zh-CN">
        <head><meta charset="UTF-8"><title>充值渠道管理</title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:-apple-system,sans-serif;background:#f5f7fa;color:#333;font-size:14px}
            .container{max-width:1400px;margin:0 auto;padding:20px}
            h1{margin-bottom:16px}
            .toolbar{margin-bottom:12px}
            table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06)}th,td{padding:10px 14px;text-align:left;border-bottom:1px solid #eee}th{background:#f8f9fc;font-weight:600;font-size:13px}
            .btn-add{display:inline-block;padding:8px 18px;background:#409eff;color:#fff;border-radius:6px;text-decoration:none;font-size:14px}
            .btn-edit{color:#409eff;text-decoration:none}
            .pager{margin-top:16px;text-align:center}
            .pager ul{display:inline-flex;gap:4px;list-style:none}
            .pager li a,.pager li span{padding:6px 12px;border:1px solid #ddd;border-radius:4px;color:#333;text-decoration:none;font-size:13px}
            .pager .active span{background:#409eff;color:#fff;border-color:#409eff}
        </style></head><body>
        <div class="container">
            <h1>充值渠道 & VIP 会员套餐</h1>
            <div class="toolbar"><a class="btn-add" href="/admin/live.RechargeChannel/editChannel">+ 新增渠道</a></div>
            <table><thead><tr><th>ID</th><th>名称</th><th>类型</th><th>二维码</th><th>地址</th><th>汇率</th><th>最低(USDT)</th><th>排序</th><th>状态</th><th>操作</th></tr></thead><tbody>{$rows}</tbody></table>
            <div class="pager">{$pager}</div>

            <h1 style="margin-top:32px">VIP 会员套餐</h1>
            <div class="toolbar"><a class="btn-add" href="/admin/live.RechargeChannel/editVip">+ 新增套餐</a></div>
            <table><thead><tr><th>ID</th><th>名称</th><th>月数</th><th>USDT价格</th><th>钻石价格</th><th>每日领取(钻)</th><th>排序</th><th>状态</th><th>操作</th></tr></thead><tbody>{$vipRows}</tbody></table>
        </div></body></html>
        HTML;
    }

    /**
     * 输出成功提示页面并跳转回列表
     */
    private function renderSuccess(string $msg): void
    {
        $listUrl = '/admin/live.RechargeChannel/index';
        $escMsg = htmlspecialchars($msg, ENT_QUOTES);
        echo <<<HTML
        <!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>操作成功</title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:-apple-system,sans-serif;background:#f5f7fa;display:flex;align-items:center;justify-content:center;min-height:100vh}
            .toast-box{background:#fff;padding:40px 48px;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.1);text-align:center}
            .toast-icon{width:56px;height:56px;border-radius:50%;background:rgba(0,212,170,.12);display:flex;align-items:center;justify-content:center;margin:0 auto 16px}
            .toast-icon svg{width:28px;height:28px;color:#00d4aa}
            .toast-msg{font-size:16px;color:#333;margin-bottom:20px}
            .toast-redirect{font-size:13px;color:#999}
        </style></head><body>
        <div class="toast-box">
            <div class="toast-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div class="toast-msg">{$escMsg}</div>
            <div class="toast-redirect">即将跳转回列表...</div>
        </div>
        <script>setTimeout(function(){location.href='{$listUrl}'},800)</script>
        </body></html>
        HTML;
        exit;
    }

    /**
     * 输出失败提示页面并返回
     */
    private function renderError(string $msg): void
    {
        $escMsg = htmlspecialchars($msg, ENT_QUOTES);
        echo <<<HTML
        <!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>操作失败</title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:-apple-system,sans-serif;background:#f5f7fa;display:flex;align-items:center;justify-content:center;min-height:100vh}
            .toast-box{background:#fff;padding:40px 48px;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.1);text-align:center}
            .toast-icon{width:56px;height:56px;border-radius:50%;background:rgba(255,45,85,.12);display:flex;align-items:center;justify-content:center;margin:0 auto 16px}
            .toast-icon svg{width:28px;height:28px;color:#ff2d55}
            .toast-msg{font-size:16px;color:#333;margin-bottom:20px}
            .toast-back{font-size:13px}
            .toast-back a{color:#409eff;text-decoration:none}
        </style></head><body>
        <div class="toast-box">
            <div class="toast-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
            <div class="toast-msg">{$escMsg}</div>
            <div class="toast-back"><a href="javascript:history.back()">返回上页</a></div>
        </div>
        </body></html>
        HTML;
        exit;
    }
}
