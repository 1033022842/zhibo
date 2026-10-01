<?php
/**
 * 提现打款配置
 * 访问: /admin/live.PayoutSetting/index?_key=<ADMIN_FLOAT_KEY>
 *
 * 在这里配置：提现汇率/手续费/起提线、打款模式、各网络热钱包私钥与链参数。
 * 私钥加密后存库（密钥在 .env [PAYOUT] SECRET），页面永不回显明文。
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\live\service\ChainPayoutService;
use app\live\service\chain\SecretBox;

final class PayoutSetting extends Backend
{
    protected array $noNeedLogin = ['index', 'save', 'ping'];
    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    /** 普通（非敏感）配置项白名单：表单字段 => [类型, 说明] */
    private const PLAIN_FIELDS = [
        'diamond_to_usdt_rate'        => ['float', '钻石兑USDT比例（1钻=多少USDT）'],
        'withdraw_fee_rate'           => ['float', '提现手续费比例'],
        'min_withdraw_diamond'        => ['float', '最低提现钻石数'],
        'payout_max_attempts'         => ['int', '单笔提现最多自动打款次数'],
        'payout_trc20_fee_limit'      => ['float', 'TRC20 单笔 fee_limit（TRX）'],
        'payout_erc20_rpc'            => ['str', 'ERC20 RPC 节点（逗号分隔可配多个）'],
        'payout_bep20_rpc'            => ['str', 'BEP20 RPC 节点（逗号分隔可配多个）'],
        'payout_erc20_gas_limit'      => ['int', 'ERC20 单笔 gas limit'],
        'payout_bep20_gas_limit'      => ['int', 'BEP20 单笔 gas limit'],
        'payout_erc20_gas_price_gwei' => ['float', 'ERC20 gas price（gwei，0=用节点建议价）'],
        'payout_bep20_gas_price_gwei' => ['float', 'BEP20 gas price（gwei，0=用节点建议价）'],
        'payout_trc20_api_key'        => ['str', 'TronGrid API Key（可选）'],
    ];

    /** 三个网络的热钱包私钥字段 */
    private const KEY_FIELDS = [
        'TRC20' => 'payout_trc20_key',
        'ERC20' => 'payout_erc20_key',
        'BEP20' => 'payout_bep20_key',
    ];

    public function index(): void
    {
        $service = new ChainPayoutService();
        $db      = \think\facade\Db::connect('live_mysql');

        // 当前值（空白项显示默认，便于运营知道兜底值是什么）
        $values = [];
        foreach (array_keys(self::PLAIN_FIELDS) as $key) {
            $values[$key] = (string) $db->table('lp_platform_config')->where('key', $key)->value('value');
        }
        $mode        = $service->mode();
        $maxAttempts = $service->maxAttempts();

        // 各网络：私钥来源 + 已推导的热钱包地址（不查链上余额，避免首屏卡住）
        $cards = '';
        foreach (array_keys((array) config('payout.networks')) as $network) {
            $cfg    = $service->config($network);
            $source = $service->keySource($network);
            $addr   = '';
            $err    = '';
            if ($source !== 'none') {
                try {
                    $addr = $service->hotAddress($network);
                } catch (\Throwable $e) {
                    $err = $e->getMessage();
                }
            }
            $cards .= $this->networkCard($network, $cfg, $source, $addr, $err);
        }

        $secretWarn = SecretBox::hasSecret()
            ? ''
            : "<div class='warn'>⚠ .env 里没有配置 <b>[PAYOUT] SECRET</b>，无法加密保存热钱包私钥。"
              . "请在服务器 .env 的 [PAYOUT] 段补一行 <code>SECRET = 随机字符串</code>（改完需重启 php-fpm），"
              . "或继续用 .env 的 TRC20_KEY/ERC20_KEY/BEP20_KEY 兜底。</div>";

        $selManual = $mode === ChainPayoutService::MODE_MANUAL ? ' selected' : '';
        $selAuto   = $mode === ChainPayoutService::MODE_AUTO ? ' selected' : '';

        $f = static fn(string $k, string $default = ''): string => htmlspecialchars(
            $values[$k] !== '' ? $values[$k] : $default,
            ENT_QUOTES
        );
        $rate   = $f('diamond_to_usdt_rate', '0.01');
        $fee    = $f('withdraw_fee_rate', '0.01');
        $minWd  = $f('min_withdraw_diamond', '1000');
        $attempts = max(1, $maxAttempts);
        $trcApi = $f('payout_trc20_api_key');
        $trcFee = $f('payout_trc20_fee_limit', '30');
        $ercRpc = $f('payout_erc20_rpc', 'https://ethereum-rpc.publicnode.com,https://1rpc.io/eth');
        $bepRpc = $f('payout_bep20_rpc', 'https://bsc-dataseed.bnbchain.org,https://1rpc.io/bnb');
        $ercGas = $f('payout_erc20_gas_limit', '65000');
        $bepGas = $f('payout_bep20_gas_limit', '100000');
        $ercGp  = $f('payout_erc20_gas_price_gwei', '0');
        $bepGp  = $f('payout_bep20_gas_price_gwei', '0');

        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>提现打款配置</title>
<style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:-apple-system,sans-serif;background:#f5f7fa;color:#333;padding:20px;font-size:14px}
    .container{max-width:980px;margin:0 auto}
    .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:18px}
    h2{font-size:17px;margin-bottom:16px;display:flex;align-items:center;gap:8px}
    h2 .dot{width:6px;height:18px;background:#409eff;border-radius:3px}
    .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}
    .field{margin-bottom:12px}
    .field label{display:block;font-size:13px;color:#666;margin-bottom:4px}
    .field input,.field select{width:100%;padding:9px 10px;border:1px solid #ddd;border-radius:6px;font-size:14px}
    .tip{font-size:12px;color:#999;margin-top:4px;line-height:1.5}
    .btn{padding:10px 26px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
    .btn-primary{background:#409eff;color:#fff}
    .btn-mini{padding:5px 12px;font-size:12px;background:#eef4ff;color:#409eff;border:none;border-radius:6px;cursor:pointer}
    .warn{background:#fff7e6;border:1px solid #ffd591;color:#ad6800;padding:12px 16px;border-radius:10px;margin-bottom:18px;font-size:13px;line-height:1.7}
    code{background:#f2f3f5;padding:1px 5px;border-radius:4px}
    .kv{font-size:13px;line-height:1.9;color:#555}
    .kv b{color:#333}
    .ok{color:#00a870}
    .bad{color:#ff2d55}
    .row-key{display:flex;gap:10px;align-items:flex-end}
    .row-key .field{flex:1;margin-bottom:0}
    .hinthide{font-size:12px;color:#bbb}
</style>
</head>
<body>
<form class="container" method="post" action="/admin/live.PayoutSetting/save" onsubmit="return onSubmit()">
    {$secretWarn}

    <div class="card">
        <h2><span class="dot"></span>提现基础参数</h2>
        <div class="grid">
            <div class="field"><label>钻石兑 USDT 比例（1 钻 = 多少 USDT）</label><input name="diamond_to_usdt_rate" value="{$rate}" placeholder="0.01"></div>
            <div class="field"><label>提现手续费比例（0.01 = 1%，在折算出的 USDT 上扣）</label><input name="withdraw_fee_rate" value="{$fee}" placeholder="0.01"></div>
            <div class="field"><label>最低提现钻石数</label><input name="min_withdraw_diamond" value="{$minWd}" placeholder="1000"></div>
            <div class="field"><label>单笔最多自动打款次数（超过转人工）</label><input name="payout_max_attempts" value="{$attempts}" placeholder="3"></div>
        </div>
    </div>

    <div class="card">
        <h2><span class="dot"></span>打款模式</h2>
        <div class="field">
            <select name="payout_mode">
                <option value="manual"{$selManual}>人工打款（运营自行链上转账，后台登记 TxHash）</option>
                <option value="auto"{$selAuto}>自动链上打款（审核通过后由热钱包自动转账）</option>
            </select>
            <div class="tip">
                切到「自动链上打款」前，请先确认对应网络的热钱包私钥已配置、且热钱包里有足够的 USDT 与手续费币（TRC20 需要 TRX / ERC20 需要 ETH / BEP20 需要 BNB）。
                未配置私钥的网络即使开了自动模式也不会打款，会在后台提现列表里显示失败原因。
            </div>
        </div>
    </div>

    {$cards}

    <div class="card">
        <h2><span class="dot"></span>链参数（一般不用改）</h2>
        <div class="grid">
            <div class="field"><label>TronGrid API Key（可选，配置后限流更宽松）</label><input name="payout_trc20_api_key" value="{$trcApi}" placeholder="留空则用 .env 或公共限流"></div>
            <div class="field"><label>TRC20 单笔 fee_limit（TRX，能量费上限）</label><input name="payout_trc20_fee_limit" value="{$trcFee}" placeholder="30"></div>
            <div class="field"><label>ERC20 RPC 节点（逗号分隔可配多个降级）</label><input name="payout_erc20_rpc" value="{$ercRpc}"></div>
            <div class="field"><label>BEP20 RPC 节点（逗号分隔可配多个降级）</label><input name="payout_bep20_rpc" value="{$bepRpc}"></div>
            <div class="field"><label>ERC20 单笔 gas limit</label><input name="payout_erc20_gas_limit" value="{$ercGas}"></div>
            <div class="field"><label>BEP20 单笔 gas limit</label><input name="payout_bep20_gas_limit" value="{$bepGas}"></div>
            <div class="field"><label>ERC20 gas price（gwei，0=用节点建议价）</label><input name="payout_erc20_gas_price_gwei" value="{$ercGp}"></div>
            <div class="field"><label>BEP20 gas price（gwei，0=用节点建议价）</label><input name="payout_bep20_gas_price_gwei" value="{$bepGp}"></div>
        </div>
    </div>

    <div class="card" style="display:flex;justify-content:space-between;align-items:center">
        <div class="tip" id="saveTip">改动保存后立即生效，无需重启。</div>
        <div>
            <button type="button" class="btn-mini" onclick="refreshBalance()">刷新热钱包余额</button>
            <button class="btn btn-primary" type="submit" style="margin-left:8px">保存配置</button>
        </div>
    </div>
</form>

<script>
function onSubmit(){
    var mode = document.querySelector('[name=payout_mode]').value;
    if (mode === 'auto') {
        return confirm('确认开启「自动链上打款」？开启后后台点「确认打款」会由热钱包真实转账。');
    }
    return true;
}
function refreshBalance(){
    var box = document.getElementById('saveTip');
    box.innerHTML = '查询链上余额中，请稍候...';
    fetch('/admin/live.PayoutSetting/ping').then(function(r){return r.json()}).then(function(d){
        if (!d.code) { box.innerHTML = '<span class="bad">查询失败：' + (d.msg || '') + '</span>'; return; }
        d.data.forEach(function(row){
            var el = document.getElementById('bal-' + row.network);
            if (!el) return;
            if (row.error) { el.innerHTML = '<span class="bad">' + row.error + '</span>'; return; }
            el.innerHTML = row.hot_address
                ? '<span class="ok">' + row.hot_address + '</span> · USDT ' + row.token_balance + ' · ' + row.gas_symbol + ' ' + row.gas_balance
                : '<span class="hinthide">未配置私钥</span>';
        });
        box.innerHTML = '余额已刷新';
    }).catch(function(e){ box.innerHTML = '<span class="bad">查询异常：' + e + '</span>'; });
}
window.addEventListener('load', refreshBalance);
</script>
</body>
</html>
HTML;
        exit;
    }

    public function save(): void
    {
        $service = new ChainPayoutService();

        try {
            // 1. 普通配置项
            foreach (self::PLAIN_FIELDS as $key => [$type, $remark]) {
                $raw = trim((string) $this->request->post($key, ''));
                $value = match ($type) {
                    'int'   => (string) max(0, (int) $raw),
                    'float' => $raw === '' ? '' : (string) (float) $raw,
                    default => $raw,
                };
                if (in_array($type, ['int', 'float'], true) && $value === '') {
                    $value = '0';
                }
                $service->saveSetting($key, $value, $remark);
            }

            // 2. 打款模式
            $mode = (string) $this->request->post('payout_mode', ChainPayoutService::MODE_MANUAL);
            $mode = $mode === ChainPayoutService::MODE_AUTO ? ChainPayoutService::MODE_AUTO : ChainPayoutService::MODE_MANUAL;
            $service->saveSetting('payout_mode', $mode, '提现打款模式 manual/auto');

            // 3. 热钱包私钥（留空=不改动；勾选清空=删掉后台保存的私钥）
            $saved  = [];
            $errors = [];
            foreach (self::KEY_FIELDS as $network => $configKey) {
                $input = trim((string) $this->request->post($configKey, ''));
                $clear = (string) $this->request->post('clear_' . $configKey, '') === '1';

                if ($clear) {
                    $service->saveSetting($configKey, '', $network . ' 热钱包私钥（加密存储）');
                    $errors[] = $network . ' 私钥已清空';
                    continue;
                }
                if ($input === '') {
                    continue; // 未填 = 保持原样
                }
                if (!SecretBox::hasSecret()) {
                    throw new \InvalidArgumentException('未配置 [PAYOUT] SECRET，无法加密保存私钥（见页面顶部提示）');
                }
                $err = ChainPayoutService::privateKeyError($input);
                if ($err !== '') {
                    throw new \InvalidArgumentException($network . ' ' . $err);
                }
                // 立刻推导地址：填错私钥时当场就能看出来，而不是等打款失败
                $address = match ($network) {
                    'TRC20' => \app\live\service\chain\ChainCrypto::publicKeyToTronAddress(
                        \app\live\service\chain\ChainCrypto::publicKeyFromPrivate($input)
                    ),
                    default => \app\live\service\chain\ChainCrypto::publicKeyToEvmAddress(
                        \app\live\service\chain\ChainCrypto::publicKeyFromPrivate($input)
                    ),
                };
                $service->saveSetting($configKey, SecretBox::encrypt($input), $network . ' 热钱包私钥（加密存储）');
                $saved[] = $network . ' 热钱包地址 = ' . $address;
            }

            $msg = '保存成功（模式：' . ($mode === ChainPayoutService::MODE_AUTO ? '自动链上打款' : '人工打款') . '）';
            if ($saved) {
                $msg .= '；' . implode('；', $saved);
            }
            if ($errors) {
                $msg .= '；' . implode('；', $errors);
            }
            $this->renderToast(true, $msg);
        } catch (\Throwable $e) {
            $this->renderToast(false, $e->getMessage());
        }
    }

    /**
     * 热钱包实况（页面异步刷新用）
     */
    public function ping(): void
    {
        try {
            $rows = (new ChainPayoutService())->overview();
            echo json_encode(['code' => 1, 'msg' => '', 'data' => $rows], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(['code' => 0, 'msg' => $e->getMessage(), 'data' => []], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function networkCard(string $network, array $cfg, string $source, string $address, string $error): string
    {
        $label  = htmlspecialchars((string) $cfg['label'], ENT_QUOTES);
        $contract = htmlspecialchars((string) $cfg['contract'], ENT_QUOTES);
        $decimals = (int) $cfg['decimals'];
        $keyName  = self::KEY_FIELDS[$network];
        $keyId    = 'k_' . strtolower($network);

        $sourceText = match ($source) {
            'db'    => '<span class="ok">已配置（后台保存，加密存储）</span>',
            'env'   => '<span class="ok">已配置（来自 .env）</span>',
            default => '<span class="bad">未配置</span>',
        };
        $addrHtml = $error !== ''
            ? '<span class="bad">' . htmlspecialchars($error, ENT_QUOTES) . '</span>'
            : ($address !== '' ? '<span class="ok">' . htmlspecialchars($address, ENT_QUOTES) . '</span>' : '<span class="hinthide">配置私钥后自动显示</span>');

        $clearRow = $source === 'db'
            ? "<label style='font-size:12px;color:#999;display:block;margin-top:6px'><input type='checkbox' name='clear_{$keyName}' value='1' style='width:auto'> 清空后台保存的私钥</label>"
            : '';

        return <<<HTML
        <div class="card">
            <h2><span class="dot"></span>{$label} 热钱包</h2>
            <div class="kv">
                <div>状态：{$sourceText} · 合约 <code>{$contract}</code> · 精度 {$decimals}</div>
                <div>热钱包地址：{$addrHtml}</div>
                <div>链上余额：<span id="bal-{$network}" class="hinthide">查询中...</span></div>
            </div>
            <div class="row-key" style="margin-top:12px">
                <div class="field">
                    <label>热钱包私钥（64 位十六进制，留空表示不修改）</label>
                    <input type="password" id="{$keyId}" name="{$keyName}" placeholder="留空不修改；填入即覆盖" autocomplete="new-password">
                    {$clearRow}
                </div>
            </div>
            <div class="tip">
                私钥只在服务器本地参与签名，不会发送给任何第三方接口；保存时会加密入库，页面也不会回显明文。
                请务必核对保存后显示的「热钱包地址」是不是你预期的钱包。
            </div>
        </div>
        HTML;
    }

    private function renderToast(bool $success, string $msg): void
    {
        $listUrl = '/admin/live.PayoutSetting/index';
        $escMsg  = htmlspecialchars($msg, ENT_QUOTES);
        // 成功才自动跳回；失败要停住让运营看清原因
        $redirect = $success ? "<script>setTimeout(function(){location.href='{$listUrl}'},5000)</script>" : '';
        $color   = $success ? '#00a870' : '#ff2d55';
        $icon    = $success
            ? '<polyline points="20 6 9 17 4 12"/>'
            : '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>';
        echo <<<HTML
        <!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>保存结果</title>
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
            <a href="{$listUrl}">返回配置页</a>
            {$redirect}
        </div></body></html>
        HTML;
        exit;
    }
}
