<?php
/**
 * 充值渠道管理
 * 访问: /admin/live.RechargeChannel/index
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\live\service\RechargeService;
use think\facade\Db;
use app\admin\model\live\RechargeChannel as ChannelModel;

final class RechargeChannel extends Backend
{
    protected object $model;

    // 绕过登录检查（iframe 模式下 cookie 不共享）
    protected array $noNeedLogin = ['index', 'editChannel', 'vipPlanSave', 'vipPlanToggle', 'editVip', 'editPackage', 'packageSave', 'packageToggle'];

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
        $packages = Db::connect('live_mysql')->table('lp_recharge_package')->order('sort', 'asc')->select()->toArray();
        // 法币通道（单国本币收款）也在这个页面上维护，不单开菜单
        $fiatList = Db::connect('live_mysql')->table('lp_fiat_channel')->order('sort', 'asc')->order('id', 'asc')->select()->toArray();
        $html = $this->renderPage($list, $vipPlans, $packages, $fiatList);
        response($html)->send();
        exit;
    }

    /**
     * 充值套餐编辑表单 + 保存入口（AI 女友端 Tokens 页）
     */
    public function editPackage(): void
    {
        $id  = (int) $this->request->param('id', 0);
        $row = $id ? Db::connect('live_mysql')->table('lp_recharge_package')->where('id', $id)->find() : null;

        $r      = $row ?: ['id' => '', 'tokens' => '100', 'price' => '9.99', 'bonus_percent' => '0', 'sort' => '0', 'status' => 1];
        $pId    = (int) $r['id'];
        $pToken = htmlspecialchars((string) $r['tokens'], ENT_QUOTES);
        $pPrice = htmlspecialchars((string) $r['price'], ENT_QUOTES);
        $pBonus = htmlspecialchars((string) $r['bonus_percent'], ENT_QUOTES);
        $pSort  = (int) $r['sort'];
        $hdr    = $pId ? '编辑充值套餐' : '新增充值套餐';

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
            <form method="post" action="/admin/live.RechargeChannel/packageSave">
                <input type="hidden" name="id" value="{$pId}" />
                <div class="field"><label>点数（到账代币数）</label><input type="number" name="tokens" value="{$pToken}" min="1" required /></div>
                <div class="field"><label>售价 USD</label><input type="number" step="0.01" name="price" value="{$pPrice}" min="0.01" required /></div>
                <div class="field"><label>赠送比例（仅展示，如 10 = +10% bonus，填 0 不展示）</label><input type="number" name="bonus_percent" value="{$pBonus}" min="0" /></div>
                <div class="field"><label>排序</label><input type="number" name="sort" value="{$pSort}" /></div>
                <button class="btn btn-primary" type="submit">保存</button>
                <a class="btn btn-cancel" href="/admin/live.RechargeChannel/index">取消</a>
            </form>
        </div></body></html>
        HTML;
        exit;
    }

    /**
     * 充值套餐保存（新增/编辑）
     */
    public function packageSave(): void
    {
        $id   = (int) $this->request->post('id', 0);
        $data = [
            'tokens'        => (int) $this->request->post('tokens', 0),
            'price'         => (float) $this->request->post('price', 0),
            'bonus_percent' => (int) $this->request->post('bonus_percent', 0),
            'sort'          => (int) $this->request->post('sort', 0),
        ];
        if ($data['tokens'] <= 0 || $data['price'] <= 0) {
            $this->renderError('点数与售价必须大于0');
            return;
        }
        try {
            if ($id) {
                Db::connect('live_mysql')->table('lp_recharge_package')->where('id', $id)->update($data);
            } else {
                $data['status'] = 1;
                Db::connect('live_mysql')->table('lp_recharge_package')->insert($data);
            }
            $this->renderSuccess('套餐已保存');
        } catch (\Exception $e) {
            $this->renderError('保存失败: ' . $e->getMessage());
        }
    }

    /**
     * 充值套餐上架/下架切换
     */
    public function packageToggle(): void
    {
        $id  = (int) $this->request->param('id', 0);
        $row = Db::connect('live_mysql')->table('lp_recharge_package')->where('id', $id)->find();
        if (!$row) {
            $this->renderError('套餐不存在');
            return;
        }
        Db::connect('live_mysql')->table('lp_recharge_package')
            ->where('id', $id)
            ->update(['status' => ((int) $row['status'] === 1) ? 0 : 1]);
        $this->renderSuccess('状态已切换');
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
                    'memo'         => trim((string) $this->request->post('memo', '')),
                    'diamond_rate' => $this->request->post('diamond_rate/f', 100),
                    'min_amount'   => $this->request->post('min_amount/f', 10),
                    'sort'         => $this->request->post('sort/d', 0),
                    'status'       => $this->request->post('status/d', 0),
                    'confirm_mode' => $this->request->post('confirm_mode', 'auto') === 'manual' ? 'manual' : 'auto',
                    'api_key'      => (string) $this->request->post('api_key', ''),
                ];
                // 服务端校验
                if (empty(trim($data['name']))) {
                    throw new \InvalidArgumentException('渠道名称不能为空');
                }
                if ($data['diamond_rate'] <= 0) {
                    throw new \InvalidArgumentException('汇率必须大于0');
                }
                // 币种白名单：不在白名单里的 type 一律按「其他」落库（币种代号取渠道名）
                if ($data['type'] !== 'other' && !isset(RechargeService::COINS[$data['type']])) {
                    throw new \InvalidArgumentException('币种类型不在支持列表内');
                }
                // 链上收款渠道：启用时必须填收款地址，否则前端拿不到地址会直接不展示该渠道
                $addr = trim((string) $data['address']);
                if ((int) $data['status'] === 1 && $addr === '') {
                    throw new \InvalidArgumentException('链上渠道启用前必须填写收款地址，否则前端不会展示');
                }
                if ($addr !== '') {
                    $addrErr = RechargeService::addressError((string) $data['type'], $addr);
                    if ($addrErr !== '') {
                        throw new \InvalidArgumentException($addrErr);
                    }
                }
                // XRP / TON 这类「必须带备注」的链：不填备注用户转币就认不出来
                $needMemo = ['xrp', 'ton'];
                if ((int) $data['status'] === 1 && in_array($data['type'], $needMemo, true) && trim((string) $data['memo']) === '') {
                    throw new \InvalidArgumentException(strtoupper((string) $data['type']) . ' 必须填转账备注（Destination Tag / Comment），否则用户转币无法归属');
                }
                // 非稳定币要按后台汇率折算应付金额，没配汇率就别启用（否则用户下单会报错）
                $spec = RechargeService::specOf($data);
                $rate = $this->request->post('coin_usd_rate/f', 0);
                if ((int) $data['status'] === 1 && !$spec['pegged'] && $rate <= 0 && $data['type'] !== 'other') {
                    throw new \InvalidArgumentException('非稳定币必须填写「1 ' . $spec['unit'] . ' = ? USD」才能启用');
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

                // 非稳定币：记录该币种兑 USD 价格（链上充值按该价格折算应付金额）
                $ethRate = $this->request->post('coin_usd_rate/f', 0);
                if (!$spec['pegged'] && $data['type'] !== 'other' && $ethRate > 0) {
                    $cfgKey = $data['type'] . '_usd_rate';
                    $cfg = Db::connect('live_mysql')->table('lp_platform_config')->where('key', $cfgKey)->find();
                    if ($cfg) {
                        Db::connect('live_mysql')->table('lp_platform_config')
                            ->where('key', $cfgKey)
                            ->update(['value' => (string) $ethRate, 'updated_at' => date('Y-m-d H:i:s')]);
                    } else {
                        Db::connect('live_mysql')->table('lp_platform_config')->insert([
                            'key'        => $cfgKey,
                            'value'      => (string) $ethRate,
                            'remark'     => $spec['unit'] . ' 兑 USD 价格（' . $spec['unit'] . ' 充值换算用）',
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
                $this->renderSuccess('保存成功');
            } catch (\Exception $e) {
                $this->renderError('保存失败: ' . $e->getMessage());
            }
            return;
        }

        $r = $row ? $row->toArray() : ['id'=>'','name'=>'','type'=>'usdt_trc20','qr_code_url'=>'','address'=>'','diamond_rate'=>'100','min_amount'=>'10','sort'=>'0','status'=>'1','confirm_mode'=>'auto','api_key'=>''];
        $checked = ($r['status'] == 1) ? 'checked' : '';
        $hdrTitle = $r['id'] ? '编辑渠道' : '新增渠道';
        // 币种下拉：按分组列出后台支持的币种（与 RechargeService::COINS 同源，新增币种只改那一处）
        $typeGroups = [
            '稳定币（1:1 美元）' => ['usdt_trc20','usdt_erc20','usdt_bep20','usdt_polygon','usdt_sol','usdc_erc20','usdc_sol','dai_erc20','tusd_erc20'],
            '主流币（按汇率折算）' => ['btc','ltc','bch','doge'],
            '公链原生币（按汇率折算）' => ['eth','trx','bnb','sol','ton','xrp'],
        ];
        $typeOptions = '';
        foreach ($typeGroups as $groupName => $types) {
            $typeOptions .= '<optgroup label="' . $groupName . '">';
            foreach ($types as $t) {
                $spec = RechargeService::COINS[$t];
                $sel  = ((string) $r['type'] === $t) ? ' selected' : '';
                $suffix = $spec['scan'] ? '（支持自动扫链）' : '';
                $typeOptions .= '<option value="' . $t . '"' . $sel . '>' . htmlspecialchars($spec['label'], ENT_QUOTES) . $suffix . '</option>';
            }
            $typeOptions .= '</optgroup>';
        }
        $typeOptions .= '<option value="other"' . (((string) $r['type'] === 'other') ? ' selected' : '') . '>其他（币种代号取渠道名，1:1 美元）</option>';
        $selAuto = (($r['confirm_mode'] ?? 'auto') == 'auto') ? 'selected' : '';
        $selManual = (($r['confirm_mode'] ?? 'auto') == 'manual') ? 'selected' : '';
        $apiKey = htmlspecialchars((string) ($r['api_key'] ?? ''), ENT_QUOTES);
        // 该币种兑 USD 价格（非稳定币才有），键名 <type>_usd_rate
        $curType = (string) $r['type'];
        $curSpec = RechargeService::specOf($r);
        $coinRateCfg = (!$curSpec['pegged'] && $curType !== 'other')
            ? (string) Db::connect('live_mysql')->table('lp_platform_config')->where('key', $curType . '_usd_rate')->value('value')
            : '';
        $ethRate = htmlspecialchars($coinRateCfg, ENT_QUOTES);
        $rateLabel = '1 ' . $curSpec['unit'] . ' = ? USD（仅非稳定币生效，链上应付金额 = USD ÷ 此价格）';
        $name = htmlspecialchars((string)$r['name'], ENT_QUOTES);
        // 二维码预览：拼接完整URL确保在iframe中也能正常显示
        $qrRaw = (string)$r['qr_code_url'];
        $qrUrl = '';
        if ($qrRaw) {
            $qrUrl = (str_starts_with($qrRaw, 'http')) ? $qrRaw : rtrim($this->request->domain(), '/') . '/' . ltrim($qrRaw, '/');
        }
        $qr = htmlspecialchars($qrUrl, ENT_QUOTES);
        $addr = htmlspecialchars((string)$r['address'], ENT_QUOTES);
        $memo = htmlspecialchars((string)($r['memo'] ?? ''), ENT_QUOTES);
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
                <div class="field"><label>类型（币种 / 链）</label><select name="type">{$typeOptions}</select></div>
                <div class="field"><label>收款二维码图片</label><input type="file" name="qr_image" accept="image/*" />{$qrPreview}</div>
                <div class="field"><label>收款地址</label><input name="address" value="{$addr}" placeholder="按所选币种填对应链的收款地址" /></div>
                <div class="field"><label>转账备注 / Memo / Tag（XRP、TON 必填，其它币种留空）</label><input name="memo" value="{$memo}" placeholder="如 XRP Destination Tag、TON Comment" /></div>
                <div class="field"><label>链上确认方式</label><select name="confirm_mode"><option value="auto" {$selAuto}>自动（扫链到账，仅 USDT-TRC20 / ETH 支持）</option><option value="manual" {$selManual}>人工审核（后台订单列表核对后放行）</option></select></div>
                <div class="field"><label>链上扫描 API Key（可选，自动确认时建议填）</label><input name="api_key" value="{$apiKey}" placeholder="TronGrid API Key / Etherscan API Key" /></div>
                <div class="field"><label>{$rateLabel}</label><input type="number" step="0.01" name="coin_usd_rate" value="{$ethRate}" placeholder="非稳定币必填，如 BTC=65000" /></div>
                <div class="field"><label>汇率（1USD=N代币）</label><input type="number" step="0.01" name="diamond_rate" value="{$rate}" required /></div>
                <div class="field"><label>最低充值金额（USD）</label><input type="number" step="0.01" name="min_amount" value="{$min}" required /></div>
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

        $r = $row ? $row : ['id' => '', 'name' => '', 'months' => '1', 'usd_price' => '', 'diamond_price' => '', 'daily_diamond' => '', 'sort' => '0',
            'level' => 'premium', 'benefits' => '', 'chat_daily_limit' => '0', 'image_daily_limit' => '3', 'discount_percent' => '10', 'custom_video' => '1'];
        $vName = htmlspecialchars((string)$r['name'], ENT_QUOTES);
        $vMonths = (int)$r['months'];
        $vUsd = htmlspecialchars((string)$r['usd_price'], ENT_QUOTES);
        $vDia = htmlspecialchars((string)$r['diamond_price'], ENT_QUOTES);
        $vDaily = htmlspecialchars((string)$r['daily_diamond'], ENT_QUOTES);
        $vSort = (int)$r['sort'];
        $vId = (int)$r['id'];
        // 权益：库里存 JSON 数组，表单按「一行一条」编辑
        $benefitList = json_decode((string)($r['benefits'] ?? ''), true);
        $vBenefits = htmlspecialchars(implode("\n", is_array($benefitList) ? $benefitList : []), ENT_QUOTES);
        $vLevel = htmlspecialchars((string)($r['level'] ?? 'premium'), ENT_QUOTES);
        $vChat = (int)($r['chat_daily_limit'] ?? 0);
        $vImage = (int)($r['image_daily_limit'] ?? 0);
        $vDiscount = (int)($r['discount_percent'] ?? 0);
        $vCustomVideo = (int)($r['custom_video'] ?? 1);
        $selPremium = $vLevel === 'premium' ? ' selected' : '';
        $selPlus = $vLevel === 'premium_plus' ? ' selected' : '';
        $selCustomVideoOff = $vCustomVideo === 1 ? '' : ' selected';
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
            .field input,.field select,.field textarea{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px}
            .tip{font-size:12px;color:#999;margin-top:4px}
            .btn{padding:10px 24px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
            .btn-primary{background:#409eff;color:#fff}
            .btn-cancel{background:#eee;color:#666;margin-left:8px;text-decoration:none;display:inline-block}
        </style></head>
        <body><div class="container">
            <h2>{$hdr}</h2>
            <form method="post" action="/admin/live.RechargeChannel/vipPlanSave">
                <input type="hidden" name="id" value="{$vId}" />
                <div class="field"><label>会员层级</label>
                    <select name="level">
                        <option value="premium"{$selPremium}>Premium（基础）</option>
                        <option value="premium_plus"{$selPlus}>Premium Plus（高级）</option>
                    </select>
                    <div class="tip">前端按层级切换展示；同层级内选择 1/3/12 个月时长</div>
                </div>
                <div class="field"><label>套餐名称</label><input name="name" value="{$vName}" required /></div>
                <div class="field"><label>月数</label><input type="number" name="months" value="{$vMonths}" min="1" required /></div>
                <div class="field"><label>USDT 参考价（页面展示用）</label><input type="number" step="0.01" name="usd_price" value="{$vUsd}" required /></div>
                <div class="field"><label>代币支付价（购买实际扣代币）</label><input type="number" step="0.01" name="diamond_price" value="{$vDia}" required /></div>
                <div class="field"><label>每日领取代币</label><input type="number" step="0.01" name="daily_diamond" value="{$vDaily}" required /></div>
                <div class="field"><label>权益列表（每行一条，展示在订阅页）</label><textarea name="benefits" rows="6">{$vBenefits}</textarea></div>
                <div class="field"><label>每日聊天条数（0=不限）</label><input type="number" name="chat_daily_limit" value="{$vChat}" /></div>
                <div class="field"><label>每日图片/视频生成次数</label><input type="number" name="image_daily_limit" value="{$vImage}" /></div>
                <div class="field"><label>内容/商城折扣百分比（10=9折，20=8折）</label><input type="number" name="discount_percent" value="{$vDiscount}" min="0" max="90" /></div>
                <div class="field"><label>可用定制视频/图片生成</label>
                    <select name="custom_video"><option value="1">可用</option><option value="0"{$selCustomVideoOff}>不可用</option></select>
                </div>
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
        $level = (string) $this->request->post('level', 'premium');
        // 权益文案：表单每行一条，落库为 JSON 数组
        $benefitRaw = str_replace(["\r\n", "\r"], "\n", (string) $this->request->post('benefits', ''));
        $benefits   = array_values(array_filter(array_map('trim', explode("\n", $benefitRaw)), static fn($v) => $v !== ''));

        $data = [
            'name'              => trim((string) $this->request->post('name', '')),
            'level'             => $level === 'premium_plus' ? 'premium_plus' : 'premium',
            'months'            => max(1, (int) $this->request->post('months', 1)),
            'usd_price'         => (float) $this->request->post('usd_price', 0),
            'diamond_price'     => (float) $this->request->post('diamond_price', 0),
            'daily_diamond'     => (float) $this->request->post('daily_diamond', 0),
            'benefits'          => json_encode($benefits, JSON_UNESCAPED_UNICODE),
            'chat_daily_limit'  => max(0, (int) $this->request->post('chat_daily_limit', 0)),
            'image_daily_limit' => max(0, (int) $this->request->post('image_daily_limit', 0)),
            'discount_percent'  => max(0, min(90, (int) $this->request->post('discount_percent', 0))),
            'custom_video'      => (int) $this->request->post('custom_video', 1) === 1 ? 1 : 0,
            'sort'              => (int) $this->request->post('sort', 0),
        ];
        if ($data['name'] === '' || $data['diamond_price'] <= 0) {
            $this->renderError('套餐名必填且代币价必须大于0');
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

    private function renderPage($list, array $vipPlans = [], array $packages = [], array $fiatList = []): string
    {
        // ===== 法币通道（单国本币收款，lp_fiat_channel）=====
        $fiatRows = '';
        foreach ($fiatList as $r) {
            $fid = (int) $r['id'];
            $fst = ((int) $r['status'] === 1)
                ? '<span style="color:#00d4aa">启用</span>'
                : '<span style="color:#ff2d55">已下架</span>';
            $fc  = htmlspecialchars((string) $r['currency'], ENT_QUOTES);
            $fs  = htmlspecialchars((string) $r['currency_symbol'], ENT_QUOTES);
            $fgw = ((string) $r['gateway'] === 'starpay') ? 'star-pay' : '人工审核';

            // 拿 $15 举例把折算结果写出来，运营填错汇率能一眼看出来
            $sample = '—';
            if ((float) $r['usd_rate'] > 0) {
                $dec = max(0, min(8, (int) $r['decimals']));
                $amt = number_format(round(15 * (float) $r['usd_rate'], $dec), $dec, '.', '');
                $sample = htmlspecialchars(($fs !== '' ? $fs : $fc) . ' ' . $amt, ENT_QUOTES)
                    . '<span style="color:#999"> / $15</span>';
            }

            $fiatRows .= "<tr><td>{$fid}</td>"
                . '<td>' . htmlspecialchars((string) $r['name'], ENT_QUOTES) . '</td>'
                . "<td>{$fgw}</td><td>{$fc}</td>"
                . '<td>' . htmlspecialchars((string) $r['usd_rate'], ENT_QUOTES) . "</td>"
                . "<td>{$sample}</td>"
                . '<td>' . (int) $r['decimals'] . '</td>'
                . '<td>' . htmlspecialchars((string) $r['min_amount'], ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars((string) $r['diamond_rate'], ENT_QUOTES) . '</td>'
                . '<td>' . (int) $r['sort'] . "</td><td>{$fst}</td>"
                . "<td><a class='btn-edit' href='/admin/live.FiatChannel/edit?id={$fid}'>编辑</a> "
                . "<a class='btn-edit' href='/admin/live.FiatChannel/toggle?id={$fid}' onclick=\"return confirm('切换启用状态?')\">切换</a></td></tr>";
        }
        if ($fiatRows === '') {
            $fiatRows = '<tr><td colspan="11" style="text-align:center;color:#999">还没有法币通道，点上方「+ 新增法币通道」添加</td></tr>';
        }

        $pkgRows = '';
        foreach ($packages as $p) {
            $pid   = (int) $p['id'];
            $pst   = ((int) $p['status'] === 1) ? '<span style="color:#00a870">上架</span>' : '<span style="color:#999">下架</span>';
            $bonus = ((int) $p['bonus_percent'] > 0) ? '+' . (int) $p['bonus_percent'] . '%' : '-';
            $pkgRows .= "<tr><td>{$pid}</td><td>" . (int) $p['tokens'] . "</td><td>" . htmlspecialchars((string) $p['price']) . "</td><td>{$bonus}</td><td>" . (int) $p['sort'] . "</td><td>{$pst}</td>"
                . "<td><a class='btn-edit' href='/admin/live.RechargeChannel/editPackage?id={$pid}'>编辑</a> "
                . "<a class='btn-edit' href='/admin/live.RechargeChannel/packageToggle?id={$pid}' onclick=\"return confirm('切换上下架?')\">切换</a></td></tr>";
        }
        if ($pkgRows === '') {
            $pkgRows = '<tr><td colspan="7" style="text-align:center;color:#999">暂无套餐</td></tr>';
        }

        $vipRows = '';
        foreach ($vipPlans as $v) {
            $v['usd_price'] = $v['usd_price'] ?? '0.00';
            $vid = (int)$v['id'];
            $vst = ((int)$v['status'] === 1) ? '<span style="color:#00a870">启用</span>' : '<span style="color:#999">禁用</span>';
            $vlevel = ((string)($v['level'] ?? 'premium') === 'premium_plus') ? 'Premium Plus' : 'Premium';
            $vipRows .= "<tr><td>{$vid}</td><td>" . htmlspecialchars((string)$v['name']) . "</td><td>{$vlevel}</td><td>{$v['months']}</td><td>{$v['usd_price']}</td><td>{$v['diamond_price']}</td><td>{$v['daily_diamond']}</td><td>{$v['sort']}</td><td>{$vst}</td>"
                . "<td><a class='btn-edit' href='/admin/live.RechargeChannel/editVip?id={$vid}'>编辑</a> "
                . "<a class='btn-edit' href='/admin/live.RechargeChannel/vipPlanToggle?id={$vid}' onclick=\"return confirm('切换启用状态?')\">切换</a></td></tr>";
        }
        if ($vipRows === '') {
            $vipRows = '<tr><td colspan="10" style="text-align:center;color:#999">暂无套餐</td></tr>';
        }

        $rows = '';
        foreach ($list->items() as $r) {
            $st = ($r['status'] == 1) ? '<span style="color:#00d4aa">启用</span>' : '<span style="color:#ff2d55">禁用</span>';
            $qr = $r['qr_code_url'] ? '有' : '无';
            $rid = (int)$r['id'];
            $rname = htmlspecialchars((string)$r['name'], ENT_QUOTES);
            $rtype = htmlspecialchars(RechargeService::COINS[(string)$r['type']]['label'] ?? (string)$r['type'], ENT_QUOTES);
            // 实际生效的确认方式：没扫链实现的币种即使后台选了「自动」也按人工审核跑
            $rmode = RechargeService::effectiveConfirmMode($r) === 'auto' ? '<span style="color:#00a870">自动扫链</span>' : '<span style="color:#e6a23c">人工审核</span>';
            $raddr = htmlspecialchars((string)$r['address'], ENT_QUOTES);
            $rrate = htmlspecialchars((string)$r['diamond_rate'], ENT_QUOTES);
            $rmin  = htmlspecialchars((string)$r['min_amount'], ENT_QUOTES);
            $rsort = htmlspecialchars((string)$r['sort'], ENT_QUOTES);
            $rows .= "<tr><td>{$rid}</td><td>{$rname}</td><td>{$rtype}</td><td>{$rmode}</td><td>{$qr}</td><td>{$raddr}</td><td>{$rrate}</td><td>{$rmin}</td><td>{$rsort}</td><td>{$st}</td><td><a href='/admin/live.RechargeChannel/editChannel?id={$rid}' class='btn-edit'>编辑</a></td></tr>";
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
            <table><thead><tr><th>ID</th><th>名称</th><th>类型</th><th>确认方式</th><th>二维码</th><th>地址</th><th>汇率</th><th>最低(USD)</th><th>排序</th><th>状态</th><th>操作</th></tr></thead><tbody>{$rows}</tbody></table>
            <div class="pager">{$pager}</div>

            <h1 style="margin-top:32px">法币通道（单国本币收款）</h1>
            <div style="color:#888;font-size:13px;margin-bottom:12px;line-height:1.7">
                用户在收银台看到的是本币金额：套餐 $15 × 汇率 = 本币实付。汇率在下单那一刻锁定写进订单，之后改汇率不影响已生成的订单。
            </div>
            <div class="toolbar"><a class="btn-add" href="/admin/live.FiatChannel/edit">+ 新增法币通道</a></div>
            <table><thead><tr><th>ID</th><th>名称</th><th>对接方式</th><th>币种</th><th>汇率(1 USD=?)</th><th>折算示例</th><th>小数位</th><th>最低(本币)</th><th>代币/USD</th><th>排序</th><th>状态</th><th>操作</th></tr></thead><tbody>{$fiatRows}</tbody></table>

            <h1 style="margin-top:32px">VIP 会员套餐</h1>
            <div class="toolbar"><a class="btn-add" href="/admin/live.RechargeChannel/editVip">+ 新增套餐</a></div>
            <table><thead><tr><th>ID</th><th>名称</th><th>层级</th><th>月数</th><th>USDT价格</th><th>代币价格</th><th>每日领取(代币)</th><th>排序</th><th>状态</th><th>操作</th></tr></thead><tbody>{$vipRows}</tbody></table>

            <h1 style="margin-top:32px">充值套餐（AI 女友端 Tokens 页）</h1>
            <div class="toolbar"><a class="btn-add" href="/admin/live.RechargeChannel/editPackage">+ 新增套餐</a></div>
            <table><thead><tr><th>ID</th><th>点数</th><th>售价(USD)</th><th>赠送</th><th>排序</th><th>状态</th><th>操作</th></tr></thead><tbody>{$pkgRows}</tbody></table>
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
