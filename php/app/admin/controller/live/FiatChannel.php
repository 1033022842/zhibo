<?php
/**
 * 法币支付通道管理（单国本币收款）
 * 访问: /admin/live.FiatChannel/index
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use think\facade\Db;

/**
 * 法币支付通道（单国本币收款）的表单与保存
 *
 * 列表已并到「充值渠道」页面（live.RechargeChannel/index）里，这里只留
 * 新增 / 编辑 / 启停三个入口。
 */
final class FiatChannel extends Backend
{
    // 绕过登录检查（iframe 模式下 cookie 不共享）
    protected array $noNeedLogin = ['edit', 'save', 'toggle'];

    // 飘页访问守卫：URL ?_key= 首次校验后走 Cookie（密钥 .env ADMIN_FLOAT_KEY）
    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    /** 已对接的网关 code（填其它值下单会直接报「尚未对接」） */
    private const GATEWAYS = [
        'manual'  => '人工审核（暂不接网关，订单进「充值审核」等运营核对）',
        'starpay' => 'star-pay（金额传本币，币种走 extra.fiat_currency）',
        'mkpay'   => 'MKPay（巴西 BRL，金额传分；收银台内自选 Pix/GGPay/ApplePay 等）',
    ];

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
            'auto_rate' => 0,
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

        // 自动汇率选中态（heredoc 里只能放简单变量，先算好）
        $autoOn      = ((int) ($r['auto_rate'] ?? 0)) === 1;
        $selAutoRate = $autoOn ? ' selected' : '';
        $selKeepRate = $autoOn ? '' : ' selected';

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
                    <div class="tip">选「人工审核」时不需要网关，用户下单后订单进「充值审核」等你核对收款；选 star-pay / mkpay 才走网关收银台。</div>
                </div>
                <div class="field"><label>网关标识（starpay 填 product 产品码，mkpay / 人工通道留空）</label>
                    <input name="gateway_ref" value="{$v('gateway_ref')}" placeholder="留空则用 .env 里的默认 product" />
                </div>
                <div class="field"><label>结算币种代码</label><input name="currency" value="{$v('currency')}" placeholder="如 MYR / VND / IDR" required /></div>
                <div class="field"><label>币种符号（金额前缀，前台展示用）</label><input name="currency_symbol" value="{$v('currency_symbol')}" placeholder="如 RM，留空则显示币种代码" /></div>
                <div class="field"><label>汇率：1 USD = ? 本币</label>
                    <input type="number" step="0.000001" name="usd_rate" value="{$v('usd_rate')}" placeholder="如 4.7020" required />
                    <div class="tip">下单时按这个汇率把套餐 USD 价折算成本币，并锁定写进订单；之后改汇率不影响已生成的订单。</div>
                </div>
                <div class="field"><label>自动汇率（每小时按中间价刷新）</label>
                    <select name="auto_rate">
                        <option value="1"{$selAutoRate}>开启（定时任务 fiat:rate 拉取 USD 中间价覆盖此值，含异常区间保护）</option>
                        <option value="0"{$selKeepRate}>关闭（手动维护汇率）</option>
                    </select>
                    <div class="tip">已支持的币种以任务代码里的数据源为准（当前：BRL）；其余币种选了也不会生效。</div>
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
                <a class="btn btn-cancel" href="/admin/live.RechargeChannel/index">取消</a>
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
            'auto_rate'       => $this->request->post('auto_rate', 0) === '1' ? 1 : 0,
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

    private function renderSuccess(string $msg): void
    {
        $listUrl = '/admin/live.RechargeChannel/index';
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
