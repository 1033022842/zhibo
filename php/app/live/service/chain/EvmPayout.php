<?php
declare(strict_types=1);

namespace app\live\service\chain;

/**
 * EVM 系（USDT-ERC20 / USDT-BEP20）链上打款
 *
 * 流程：节点取 nonce/价格 → 本地 RLP + EIP-155 组装并离线签名 → eth_sendRawTransaction → 查回执。
 * 私钥全程只在本地参与签名。
 */
final class EvmPayout
{
    private const TRANSFER_SELECTOR = 'a9059cbb'; // transfer(address,uint256)

    /** RPC 节点列表（配置支持逗号分隔多个，逐个降级重试） */
    private readonly array $rpcs;

    public function __construct(
        private readonly array $cfg,
        private readonly string $privateKey,
    ) {
        $this->rpcs = array_values(array_filter(array_map('trim', explode(',', (string) $cfg['rpc']))));
    }

    /** 热钱包地址（由私钥推导，不依赖配置） */
    public function address(): string
    {
        return ChainCrypto::publicKeyToEvmAddress(ChainCrypto::publicKeyFromPrivate($this->privateKey));
    }

    public function validateAddress(string $address): bool
    {
        return (bool) preg_match('/^0x[0-9a-fA-F]{40}$/', trim($address));
    }

    /** 热钱包 USDT 余额（人类可读） */
    public function tokenBalance(): string
    {
        $result = (string) $this->rpc('eth_call', [[
            'to'   => $this->cfg['contract'],
            'data' => '0x70a08231' . ChainCrypto::padLeft($this->address(), 32), // balanceOf(address)
        ], 'latest']);
        if ($result === '' || $result === '0x') {
            return '0';
        }
        return ChainCrypto::fromUnits(gmp_strval(gmp_init(ChainCrypto::normalizeHex($result), 16), 10), (int) $this->cfg['decimals']);
    }

    /** 热钱包原生币余额（ETH / BNB，用于支付矿工费） */
    public function gasBalance(): string
    {
        return ChainCrypto::fromUnits($this->gasWei(), 18);
    }

    /**
     * 发起 USDT 转账
     *
     * @return array{tx_hash:string, raw_tx:string}
     */
    public function send(string $to, string $amount): array
    {
        $to = trim($to);
        if (!$this->validateAddress($to)) {
            throw new \RuntimeException($this->cfg['label'] . ' 收款地址不合法（应为 0x 开头的 42 位十六进制）');
        }

        $from     = $this->address();
        $contract = (string) $this->cfg['contract'];
        $decimals = (int) $this->cfg['decimals'];
        $chainId  = (int) $this->cfg['chain_id'];
        $units    = ChainCrypto::toUnits($amount, $decimals);

        // 余额前置校验：余额不足时直接给出人类可读错误，避免上链后白白烧掉矿工费
        $tokenBal = $this->tokenBalance();
        if (bccomp(bcmul($tokenBal, bcpow('10', (string) $decimals), 0), $units, 0) < 0) {
            throw new \RuntimeException(sprintf('热钱包 USDT 余额不足：当前 %s，本次需 %s', $tokenBal, $amount));
        }

        $data = '0x' . self::TRANSFER_SELECTOR
            . ChainCrypto::padLeft($to, 32)
            . ChainCrypto::padLeft(gmp_strval(gmp_init($units, 10), 16), 32);

        $nonce    = (int) hexdec((string) $this->rpc('eth_getTransactionCount', [$from, 'pending']));
        $gasPrice = $this->gasPrice();
        $gasLimit = $this->gasLimit($from, $contract, $data);

        $gasCost = gmp_mul(gmp_init((string) $gasLimit, 10), gmp_init((string) $gasPrice, 10));
        $gasBal  = gmp_init($this->gasWei(), 10);
        if (gmp_cmp($gasBal, $gasCost) < 0) {
            throw new \RuntimeException(sprintf(
                '热钱包 %s 不足以支付矿工费：需要约 %s，当前 %s，请先充值 %s',
                $this->cfg['gas_symbol'],
                ChainCrypto::fromUnits(gmp_strval($gasCost, 10), 18),
                ChainCrypto::fromUnits(gmp_strval($gasBal, 10), 18),
                $this->cfg['gas_symbol']
            ));
        }

        $base = [
            ChainCrypto::intToMinimalHex($nonce),
            ChainCrypto::intToMinimalHex($gasPrice),
            ChainCrypto::intToMinimalHex($gasLimit),
            $contract,
            '',                     // value = 0
            $data,
        ];
        // EIP-155：签名原文尾部带上 chainId, 0, 0
        $signingHash = ChainCrypto::keccak256(self::rlp(array_merge($base, [
            ChainCrypto::intToMinimalHex($chainId), '', '',
        ])));

        $signature = ChainCrypto::sign($signingHash, $this->privateKey);
        if (!ChainCrypto::verify($signingHash, $signature['compact'], ChainCrypto::publicKeyFromPrivate($this->privateKey))) {
            throw new \RuntimeException('EVM 交易签名自校验失败，已阻止广播');
        }

        $v       = $signature['recid'] + 35 + 2 * $chainId;
        $rawTx   = '0x' . bin2hex(self::rlp(array_merge($base, [
            ChainCrypto::intToMinimalHex($v),
            ChainCrypto::toMinimalHex($signature['r']),
            ChainCrypto::toMinimalHex($signature['s']),
        ])));
        $localTx = '0x' . ChainCrypto::keccak256(ChainCrypto::hexToBin($rawTx));

        try {
            $nodeTx = (string) $this->rpc('eth_sendRawTransaction', [$rawTx]);
        } catch (\Throwable $e) {
            // 已经在交易池里说明这笔交易此前已成功发出，按成功处理（幂等）
            if (stripos($e->getMessage(), 'already known') !== false || stripos($e->getMessage(), 'ALREADY_EXISTS') !== false) {
                return ['tx_hash' => $localTx, 'raw_tx' => $rawTx];
            }
            throw $e;
        }

        return [
            'tx_hash' => $nodeTx !== '' ? $nodeTx : $localTx,
            'raw_tx'  => $rawTx,
        ];
    }

    /**
     * 查询交易链上状态
     *
     * @return array{state:string, message:string} state = pending|success|failed
     */
    public function txState(string $txHash): array
    {
        $receipt = $this->rpc('eth_getTransactionReceipt', [$txHash]);
        if (!is_array($receipt) || $receipt === []) {
            return ['state' => 'pending', 'message' => ''];
        }
        $status = (string) ($receipt['status'] ?? '');
        if ($status === '0x1') {
            return ['state' => 'success', 'message' => ''];
        }
        return ['state' => 'failed', 'message' => '交易在链上执行失败（status=' . $status . '）'];
    }

    // ------------------------------------------------------------------ 内部

    /** 热钱包原生币余额（最小单位 wei，十进制字符串） */
    private function gasWei(): string
    {
        $hex = ChainCrypto::normalizeHex((string) $this->rpc('eth_getBalance', [$this->address(), 'latest']));
        return $hex === '' ? '0' : gmp_strval(gmp_init($hex, 16), 10);
    }

    private function gasPrice(): int
    {
        $gwei = (float) ($this->cfg['gas_price_gwei'] ?? 0);
        if ($gwei > 0) {
            return (int) round($gwei * 1_000_000_000);
        }
        $hex = (string) $this->rpc('eth_gasPrice', []);
        $wei = (int) hexdec(ChainCrypto::normalizeHex($hex));
        // 上浮 15%，避免节点建议价刚好卡在临界值
        return (int) ceil($wei * 1.15);
    }

    private function gasLimit(string $from, string $contract, string $data): int
    {
        $configured = (int) ($this->cfg['gas_limit'] ?? 0);
        try {
            $estimate = (int) hexdec(ChainCrypto::normalizeHex((string) $this->rpc('eth_estimateGas', [[
                'from'  => $from,
                'to'    => $contract,
                'value' => '0x0',
                'data'  => $data,
            ]])));
        } catch (\Throwable $e) {
            if ($configured > 0) {
                return $configured;
            }
            throw new \RuntimeException('估算矿工费失败：' . $e->getMessage());
        }

        // 估算值上浮 15%；若与配置值冲突则取较大者，避免 under-gas
        return max($configured, (int) ceil($estimate * 1.15));
    }

    /**
     * JSON-RPC 调用，按配置顺序在多个节点间降级
     *
     * 公共节点经常出现「能查余额却拒绝查回执」这类局部不可用，
     * 单节点会让提现单永远卡在「链上确认中」，所以这里逐个重试。
     */
    private function rpc(string $method, array $params): mixed
    {
        if (!$this->rpcs) {
            throw new \RuntimeException('未配置 ' . $this->cfg['label'] . ' 的 RPC 节点');
        }

        $lastError = null;
        foreach ($this->rpcs as $url) {
            try {
                $res = ChainHttp::postJson($url, [
                    'jsonrpc' => '2.0',
                    'id'      => 1,
                    'method'  => $method,
                    'params'  => $params,
                ]);
                if (isset($res['error'])) {
                    $msg = (string) ($res['error']['message'] ?? json_encode($res['error'], JSON_UNESCAPED_UNICODE));
                    throw new \RuntimeException('RPC ' . $method . ' 失败：' . $msg);
                }
                return $res['result'] ?? null;
            } catch (\Throwable $e) {
                $lastError = $e;
            }
        }

        throw new \RuntimeException($lastError ? $lastError->getMessage() : 'RPC 调用失败');
    }

    /** RLP 编码（入参为 hex 字符串或由 hex 字符串组成的数组） */
    private static function rlp(string|array $input): string
    {
        if (is_array($input)) {
            $out = '';
            foreach ($input as $item) {
                $out .= self::rlp($item);
            }
            return self::rlpHeader(strlen($out), 0xc0) . $out;
        }

        $bin = ChainCrypto::hexToBin($input);
        if (strlen($bin) === 1 && ord($bin) < 0x80) {
            return $bin;
        }
        return self::rlpHeader(strlen($bin), 0x80) . $bin;
    }

    private static function rlpHeader(int $length, int $offset): string
    {
        if ($length < 56) {
            return chr($offset + $length);
        }
        $lenBin = ChainCrypto::hexToBin(ChainCrypto::intToMinimalHex($length));
        return chr($offset + 55 + strlen($lenBin)) . $lenBin;
    }
}
