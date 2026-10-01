<?php
declare(strict_types=1);

namespace app\live\service\chain;

/**
 * TRON（USDT-TRC20）链上打款
 *
 * 流程：TronGrid 构造未签名交易 → 本地用热钱包私钥离线签名 → 广播 → 轮询确认。
 * 私钥全程只在本地参与签名，不经过任何第三方接口。
 */
final class TronPayout
{
    private const USDT_DECIMALS = 6;

    public function __construct(
        private readonly array $cfg,
        private readonly string $privateKey,
    ) {
    }

    /** 热钱包地址（由私钥推导，不依赖配置） */
    public function address(): string
    {
        return ChainCrypto::publicKeyToTronAddress(ChainCrypto::publicKeyFromPrivate($this->privateKey));
    }

    public function validateAddress(string $address): bool
    {
        $payload = ChainCrypto::base58CheckDecode(trim($address));
        return $payload !== null && strlen($payload) === 21 && $payload[0] === "\x41";
    }

    /** 热钱包 USDT 余额（人类可读） */
    public function tokenBalance(): string
    {
        $res = $this->post('/wallet/triggerconstantcontract', [
            'owner_address'     => $this->address(),
            'contract_address'  => $this->cfg['contract'],
            'function_selector' => 'balanceOf(address)',
            'parameter'         => ChainCrypto::padLeft($this->addressHex($this->address()), 32),
            'visible'           => true,
        ]);
        $hex = (string) ($res['constant_result'][0] ?? '');
        if ($hex === '') {
            return '0';
        }
        return ChainCrypto::fromUnits(gmp_strval(gmp_init($hex, 16), 10), (int) ($this->cfg['decimals'] ?? self::USDT_DECIMALS));
    }

    /** 热钱包 TRX 余额（用于支付能量费/带宽费） */
    public function gasBalance(): string
    {
        $res = $this->post('/wallet/getaccount', [
            'address' => $this->address(),
            'visible' => true,
        ]);
        return ChainCrypto::fromUnits((string) ($res['balance'] ?? 0), 6);
    }

    /**
     * 发起 TRC20 转账
     *
     * @return array{tx_hash:string, raw_tx:string}
     */
    public function send(string $to, string $amount): array
    {
        $to = trim($to);
        if (!$this->validateAddress($to)) {
            throw new \RuntimeException('TRON 收款地址不合法（base58 校验和不通过）');
        }
        $decimals = (int) ($this->cfg['decimals'] ?? self::USDT_DECIMALS);
        $units    = ChainCrypto::toUnits($amount, $decimals);
        $feeLimit = (int) round((float) ($this->cfg['fee_limit'] ?? 30) * 1_000_000);

        // 余额前置校验：账上不够时直接给出人类可读错误，不用等链上模拟报一句含糊的 REVERT
        $tokenBal = $this->tokenBalance();
        if (bccomp(bcmul($tokenBal, bcpow('10', (string) $decimals), 0), $units, 0) < 0) {
            throw new \RuntimeException(sprintf('热钱包 USDT 余额不足：当前 %s，本次需 %s', $tokenBal, $amount));
        }

        $parameter = ChainCrypto::padLeft($this->addressHex($to), 32)
            . ChainCrypto::padLeft(gmp_strval(gmp_init($units, 10), 16), 32);

        $res = $this->post('/wallet/triggersmartcontract', [
            'owner_address'     => $this->address(),
            'contract_address'  => $this->cfg['contract'],
            'function_selector' => 'transfer(address,uint256)',
            'parameter'         => $parameter,
            'fee_limit'         => $feeLimit,
            'call_value'        => 0,
            'visible'           => true,
        ]);

        if (($res['result']['result'] ?? false) !== true) {
            $msg = (string) ($res['result']['message'] ?? '');
            $code = (string) ($res['result']['code'] ?? '');
            throw new \RuntimeException('构造 TRC20 交易失败：' . ($msg !== '' ? $msg : ($code !== '' ? $code : '节点未返回有效交易')));
        }
        $tx = $res['transaction'] ?? null;
        if (!is_array($tx) || empty($tx['txID']) || empty($tx['raw_data_hex'])) {
            throw new \RuntimeException('构造 TRC20 交易失败：节点未返回交易体');
        }

        // 签名前先离线自校验签名，绝不上链一笔签错的交易
        $txId      = (string) $tx['txID'];
        $signature = ChainCrypto::sign($txId, $this->privateKey);
        if (!ChainCrypto::verify($txId, $signature['compact'], ChainCrypto::publicKeyFromPrivate($this->privateKey))) {
            throw new \RuntimeException('TRC20 交易签名自校验失败，已阻止广播');
        }

        $tx['signature'] = [$signature['tron']];
        $broadcast = $this->post('/wallet/broadcasttransaction', $tx);

        if (($broadcast['result'] ?? false) === true) {
            return ['tx_hash' => (string) ($broadcast['txid'] ?? $txId), 'raw_tx' => json_encode($tx, JSON_UNESCAPED_UNICODE)];
        }

        $code = strtoupper((string) ($broadcast['code'] ?? ''));
        $msg  = (string) ($broadcast['message'] ?? '');
        // 重复广播说明链上已有这笔交易，按成功处理（幂等）
        if ($code === 'DUP_TRANSACTION_ERROR') {
            return ['tx_hash' => $txId, 'raw_tx' => json_encode($tx, JSON_UNESCAPED_UNICODE)];
        }
        throw new \RuntimeException('TRC20 交易广播失败：' . ($code !== '' ? $code : '') . ' ' . ($msg !== '' ? $msg : '未知错误'));
    }

    /**
     * 查询交易链上状态
     *
     * @return array{state:string, message:string} state = pending|success|failed
     */
    public function txState(string $txHash): array
    {
        $res = $this->post('/wallet/gettransactioninfobyid', ['value' => $txHash]);
        if (!is_array($res) || $res === []) {
            return ['state' => 'pending', 'message' => ''];
        }
        if (!isset($res['blockNumber'])) {
            return ['state' => 'pending', 'message' => ''];
        }
        $result = (string) ($res['receipt']['result'] ?? '');
        if ($result === 'SUCCESS') {
            return ['state' => 'success', 'message' => ''];
        }
        return ['state' => 'failed', 'message' => $result !== '' ? $result : '交易执行失败'];
    }

    /** 地址 base58 → 21 字节 hex（含 0x41 前缀） */
    private function addressHex(string $address): string
    {
        $payload = ChainCrypto::base58CheckDecode(trim($address));
        if ($payload === null) {
            throw new \RuntimeException('TRON 地址不合法：' . $address);
        }
        return bin2hex($payload);
    }

    private function api(string $path): string
    {
        return rtrim((string) $this->cfg['api'], '/') . $path;
    }

    private function post(string $path, array $payload): array
    {
        return ChainHttp::postJson($this->api($path), $payload, $this->headers());
    }

    /** TronGrid 可选的 API Key（无 Key 也能用，只是限流更低） */
    private function headers(): array
    {
        $key = trim((string) ($this->cfg['api_key'] ?? ''));
        return $key !== '' ? ['TRON-PRO-API-KEY: ' . $key] : [];
    }
}
