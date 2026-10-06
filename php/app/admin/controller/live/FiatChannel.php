<?php
/**
 * 法币支付通道管理（单国本币收款）
 * 访问: /admin/live.FiatChannel/index
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\admin\model\live\FiatChannel as FiatChannelModel;
use think\facade\Db;

final class FiatChannel extends Backend
{
    protected object $model;

    // 绕过登录检查（iframe 模式下 cookie 不共享）
    protected array $noNeedLogin = ['index', 'edit', 'save', 'toggle'];

    // 飘页访问守卫：URL ?_key= 首次校验后走 Cookie（密钥 .env ADMIN_FLOAT_KEY）
    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    /** 已对接的网关 code（填其它值下单会直接报「尚未对接」） */
    private const GATEWAYS = [
        'manual'  => '人工审核（暂不接网关，订单进「充值审核」等运营核对）',
        'starpay' => 'star-pay（金额传本币，币种走 extra.fiat_currency）',
    ];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new FiatChannelModel();
    }

    public function index(): void
    {
        $list = $this->model->order('sort', 'asc')->order('id', 'asc')->paginate(20);
        response($this->renderPage($list))->send();
        exit;
    }

    /**
     * 新增 / 编辑表单
     */
    public function edit(): void
    {
        $id  = (int) $this->request->param('id', 0);
        $row = $id ? Db::connect('live_mysql')->table('lp_fiat_channel')->where('id', $id)->find() : null;

        $r = $row ?: [
            'id' => '', 'name' => '', 'gateway' => 'manual', 'gateway_ref' => '',
            'currency' => '', 'currency_symbol' => '', 'usd_rate' => '',
            'decimals' => 2, 'min_amount' => 0, 'diamond_rate' => 100,
            'pay_instructions' => '', 'sort' => 0, 'status' => 1,
        ];

        $fId   = (int) $r['id'];
        $hdr   = $fId ? '编辑法币通道' : '新增法币通道';
        $cur   = (string) $r['gateway'];

        $gwOptions = '';
        foreach (self::GATEWAYS as $code => $label) {
            $sel = $cur === $code ? ' selected' : '';
            $gwOptions .= '<option value="' . $code . '"' . $sel . '>' . htmlspecialchars($label, ENT_QUOTES) . '</option>';
        }

        $v = static fn($key): string => htmlspecialchars((string) $r[$key], ENT_QUOTES);

        echo <<<HTML
        <!DOCTYPE html><html lang="zh-CN">
        <head><meta charset="UTF-8"><title>{$hdr}</title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:-apple-system,sans-serif;background:#f5f7fa;color:#333;padding:20px}
            .container{max-width:680px;margin:0 auto;background:#fff;border-radius:12px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
            h2{margin-bottom:20px}
            .field{margin-bottom:16px}
            .field label{display:block;font-size:13px;color:#666;margin-bottom:4px}
            .field input,.field select,.field textarea{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px}
            .tip{font-size:12px;color:#999;margin-top:4px;line-height:1.6}
            .btn{padding:10px 24px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
            .btn-primary{background:#409eff;color:#fff}
            .btn-cancel{background:#eee;color:#666;margin-left:8px;text-decoration:none;display:inline-block}
        </style></head>
        <body><div class="container">
            <h2>{$hdr}</h2>
            <form method="post" action="/admin/live.FiatChannel/save">
                <input type="hidden" name="id" value="{$fId}" />
                <div class="field"><label>前台展示名</label><input name="name" value="{$v('name')}" placeholder="如 Malaysia · FPX" required /></div>
                <div class="field"><label>对接方式</label>
                    <select name="gateway">{$gwOptions}</select>
                    <div class="tip">选「人工审核」时不需要网关，用户下单后订单进「充值审核」等你核对收款；选 star-pay 才走网关收银台。</div>
                </div>
                <div class="field"><label>网关标识（starpay 填 product 产品码，人工通道留空）</label>
                    <input name="gateway_ref" value="{$v('gateway_ref')}" placeholder="留空则用 .env 里的默认 product" />
                </div>
                <div class="field"><label>结算币种代码</label><input name="currency" value="{$v('currency')}" placeholder="如 MYR / VND / IDR" required /></div>
                <div class="field"><label>币种符号（金额前缀，前台展示用）</label><input name="currency_symbol" value="{$v('currency_symbol')}" placeholder="如 RM，留空则显示币种代码" /></div>
                <div class="field"><label>汇率：1 USD = ? 本币</label>
                    <input type="number" step="0.000001" name="usd_rate" value="{$v('usd_rate')}" placeholder="如 4.7020" required />
                    <div class="tip">下单时按这个汇率把套餐 USD 价折算成本币，并锁定写进订单；之后改汇率不影响已生成的订单。</div>
                </div>
                <div class="field"><label>本币金额小数位</label><input type="number" name="decimals" value="{$v('decimals')}" min="0" max="8" />
                    <div class="tip">多数币种填 2；VND、IDR 这类没有小数的填 0。</div>
                </div>
                <div class="field"><label>最低支付金额（本币）</label><input type="number" step="0.01" name="min_amount" value="{$v('min_amount')}" min="0" />
                    <div class="tip">填 0 表示不限制。低于该金额的下单会被拒绝。</div>
                </div>
                <div class="field"><label>到账代币单价（每 1 USD 给多少代币）</label><input type="number" step="0.01" name="diamond_rate" value="{$v('diamond_rate')}" min="0" />
                    <div class="tip">只影响「充值代币」的单子；会员订阅单不代币入账，按套餐开通。</div>
                </div>
                <div class="field"><label>付款说明（人工通道展示给用户，如收款账号 / 操作步骤）</label><textarea name="pay_instructions" rows="4">{$v('pay_instructions')}</textarea></div>
                <div class="field"><label>排序</label><input type="number" name="sort" value="{$v('sort')}" /></div>
                <button class="btn btn-primary" type="submit">保存</button>
                <a class="btn btn-cancel" href="/admin/live.FiatChannel/index">取消</a>
            </form>
        </div></body></html>
        HTML;
        exit;
    }

    /**
     * 保存（新增 / 编辑）
     */
    public function save(): void
    {
        $id   = (int) $this->request->post('id', 0);
        $data = [
            'name'            => trim((string) $this->request->post('name', '')),
            'gateway'         => (string) $this->request->post('gateway', 'manual'),
            'gateway_ref'     => trim((string) $this->request->post('gateway_ref', '')),
            'currency'        => strtoupper(trim((string) $this->request->post('currency', ''))),
            'currency_symbol' => trim((string) $this->request->post('currency_symbol', '')),
            'usd_rate'        => (float) $this->request->post('usd_rate', 0),
            'decimals'        => max(0, min(8, (int) $this->request->post('decimals', 2))),
            'min_amount'      => max(0, (float) $this->request->post('min_amount', 0)),
            'diamond_rate'    => max(0, (float) $this->request->post('diamond_rate', 0)),
            'pay_instructions' => trim((string) $this->request->post('pay_instructions', '')),
            'sort'            => (int) $this->request->post('sort', 0),
        ];

        if ($data['name'] === '') {
            $this->renderError('前台展示名必填');
            return;
        }
        if (!isset(self::GATEWAYS[$data['gateway']])) {
            $this->renderError('对接方式不合法');
            return;
        }
        if ($data['currency'] === '') {
            $this->renderError('结算币种代码必填，如 MYR');
            return;
        }
        if ($data['usd_rate'] <= 0) {
            $this->renderError('汇率必须大于 0，否则用户下单时无法折算金额');
            return;
        }

        try {
            if ($id) {
                Db::connect('live_mysql')->table('lp_fiat_channel')->where('id', $id)->update($data);
            } else {
                $data['status'] = 1;
                Db::connect('live_mysql')->table('lp_fiat_channel')->insert($data);
            }
            $this->renderSuccess('法币通道已保存');
        } catch (\Exception $e) {
            $this->renderError('保存失败: ' . $e->getMessage());
        }
    }

    /**
     * 启用 / 下架切换
     */
    public function toggle(): void
    {
        $id  = (int) $this->request->param('id', 0);
        $row = Db::connect('live_mysql')->table('lp_fiat_channel')->where('id', $id)->find();
        if (!$row) {
            $this->renderError('通道不存在');
            return;
        }
        Db::connect('live_mysql')->table('lp_fiat_channel')
            ->where('id', $id)
            ->update(['status' => ((int) $row['status'] === 1) ? 0 : 1]);
        $this->renderSuccess('状态已切换');
    }

    private function renderPage($list): string
    {
        $rows = '';
        foreach ($list->items() as $r) {
            $rid  = (int) $r['id'];
            $st   = ((int) $r['status'] === 1)
                ? '<span style="color:#00d4aa">启用</span>'
                : '<span style="color:#ff2d55">已下架</span>';
            $gw   = htmlspecialchars((string) (self::GATEWAYS[(string) $r['gateway']] ?? ('未对接：' . $r['gateway'])), ENT_QUOTES);
            $cur  = htmlspecialchars((string) $r['currency'], ENT_QUOTES);
            $sym  = htmlspecialchars((string) $r['currency_symbol'], ENT_QUOTES);

            // 用 $15 举例把折算结果写出来，运营填错汇率能一眼看出来
            $sample = '—';
            if ((float) $r['usd_rate'] > 0) {
                $dec = max(0, min(8, (int) $r['decimals']));
                $amt = number_format(round(15 * (float) $r['usd_rate'], $dec), $dec, '.', '');
                $sample = htmlspecialchars((($sym !== '' ? $sym : $cur) . ' ' . $amt), ENT_QUOTES)
                    . '<span style="color:#999"> / $15</span>';
            }

            $rows .= "<tr><td>{$rid}</td>"
                . '<td>' . htmlspecialchars((string) $r['name'], ENT_QUOTES) . '</td>'
                . "<td>{$gw}</td><td>{$cur}</td>"
                . '<td>' . htmlspecialchars((string) $r['usd_rate'], ENT_QUOTES) . "</td>"
                . "<td>{$sample}</td>"
                . '<td>' . (int) $r['decimals'] . '</td>'
                . '<td>' . htmlspecialchars((string) $r['min_amount'], ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars((string) $r['diamond_rate'], ENT_QUOTES) . '</td>'
                . '<td>' . (int) $r['sort'] . "</td><td>{$st}</td>"
                . "<td><a class='btn-edit' href='/admin/live.FiatChannel/edit?id={$rid}'>编辑</a> "
                . "<a class='btn-edit' href='/admin/live.FiatChannel/toggle?id={$rid}' onclick=\"return confirm('切换启用状态?')\">切换</a></td></tr>";
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="11" style="text-align:center;color:#999">还没有法币通道，点上方「+ 新增通道」添加</td></tr>';
        }

        $pager = $list->render();

        return <<<HTML
        <!DOCTYPE html><html lang="zh-CN">
        <head><meta charset="UTF-8"><title>法币通道管理</title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:-apple-system,sans-serif;background:#f5f7fa;color:#333;font-size:14px}
            .container{max-width:1400px;margin:0 auto;padding:20px}
            h1{margin-bottom:8px}
            .sub{color:#888;font-size:13px;margin-bottom:16px;line-height:1.7}
            .toolbar{margin-bottom:12px}
            table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06)}
            th,td{padding:10px 14px;text-align:left;border-bottom:1px solid #eee}th{background:#f8f9fc;font-weight:600;font-size:13px}
            .btn-add{display:inline-block;padding:8px 18px;background:#409eff;color:#fff;border-radius:6px;text-decoration:none;font-size:14px}
            .btn-edit{color:#409eff;text-decoration:none}
            .pager{margin-top:16px;text-align:center}
            .pager ul{display:inline-flex;gap:4px;list-style:none}
            .pager li a,.pager li span{padding:6px 12px;border:1px solid #ddd;border-radius:4px;color:#333;text-decoration:none;font-size:13px}
            .pager .active span{background:#409eff;color:#fff;border-color:#409eff}
        </style></head><body>
        <div class="container">
            <h1>法币通道（单国本币收款）</h1>
            <div class="sub">用户在收银台看到的是本币金额：套餐 $15 × 汇率 4.70 = RM 70.50。汇率在下单时锁定并写进订单，改汇率不影响已生成的订单。</div>
            <div class="toolbar"><a class="btn-add" href="/admin/live.FiatChannel/edit">+ 新增通道</a></div>
            <table><thead><tr><th>ID</th><th>名称</th><th>对接方式</th><th>币种</th><th>汇率(1 USD=?)</th><th>折算示例</th><th>小数位</th><th>最低(本币)</th><th>代币/USD</th><th>排序</th><th>状态</th><th>操作</th></tr></thead><tbody>{$rows}</tbody></table>
            <div class="pager">{$pager}</div>
        </div></body></html>
        HTML;
    }

    private function renderSuccess(string $msg): void
    {
        $listUrl = '/admin/live.FiatChannel/index';
        $escMsg  = htmlspecialchars($msg, ENT_QUOTES);
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
