<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

/**
 * 多币种链上充值扫描（recharge:scan-alt）
 *
 * 覆盖 recharge:scan / scan-eth / scan-trx 之外的币种，由 crontab 每分钟调度：
 *   - 以太坊代币：usdt_erc20 / usdc_erc20 / dai_erc20 / tusd_erc20（Routescan tokentx，免 Key）
 *   - BNB Chain：usdt_bep20（公共 RPC eth_getLogs）、bnb 原生（公共 RPC 扫块，免 Key）
 *   - BTC：mempool.space（免 Key）
 *   - TON：toncenter（免 Key）
 *   - SOL：Solana 公共 RPC（免 Key）
 *   - XRP：XRPL 公共 RPC（免 Key）
 *
 * 原生 BNB 说明：BSC 公共 RPC 既没有免 Key 的浏览器 API，也不支持 trace_filter（需付费 token），
 * 只能逐块拉取（单块约 160KB）。因此加了闸门：只有在近 2 小时内有 BNB 订单时才真正扫块，
 * 平时只更新块高游标，常态流量≈0；活跃下单期间才有带宽消耗。
 *
 * 金额匹配与其它扫链任务同口径：订单 pay_amount 带随机尾数当识别标识，全等即入账。
 * 幂等：tx hash 在 lp_payment_callback_log 去重（payload_hash 上限 64 字符，
 *       Solana 的 base58 签名约 88 字符，取前 64 位作去重键，完整签名仍写进订单备注）。
 */
final class AltRechargeScanCron extends Command
{
    /** 以太坊 ERC-20 代币合约与精度（Routescan tokentx 查询用） */
    private const EVM_TOKENS = [
        'usdt_erc20' => ['contract' => '0xdac17f958d2ee523a2206206994597c13d831ec7', 'decimals' => 6],
        'usdc_erc20' => ['contract' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48', 'decimals' => 6],
        'dai_erc20'  => ['contract' => '0x6b175474e89094c44da98b954eedeac495271d0f', 'decimals' => 18],
        'tusd_erc20' => ['contract' => '0x0000000000085d4780b73119b644ae5ecd22b376', 'decimals' => 18],
    ];

    private const TYPES = ['usdt_erc20', 'usdc_erc20', 'dai_erc20', 'tusd_erc20',
                           'usdt_bep20', 'bnb', 'btc', 'ton', 'sol', 'xrp'];

    private const ROUTESCAN_API = 'https://api.routescan.io/v2/network/mainnet/evm/1/etherscan/api';
    private const MEMPOOL_API   = 'https://mempool.space/api';
    private const TONCENTER_API = 'https://toncenter.com/api/v2';
    private const SOL_RPC       = 'https://api.mainnet-beta.solana.com';
    private const XRP_RPC       = 'https://xrplcluster.com';

    /** BNB Chain（BSC）：免 Key 只能走公共 RPC */
    private const BSC_RPC          = 'https://bsc-rpc.publicnode.com';
    private const BSC_USDT_BEP20   = '0x55d398326f99059ff775485246999027b3197955';
    /** ERC-20/BEP-20 Transfer 事件签名 */
    private const TRANSFER_TOPIC   = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
    /** BEP-20 代币查询回看的块数（约 45 分钟，重叠区间靠 payload_hash 去重） */
    private const BSC_LOG_LOOKBACK = 900;
    /** 原生 BNB 单次最多回看的块数（防止长时间停摆后一次拉太多） */
    private const BSC_BLOCK_MAX    = 300;
    /** 原生 BNB 扫链进度（块高）在 lp_platform_config 里的键名 */
    private const BSC_BLOCK_KEY    = 'bsc_scan_block';

    /** lp_payment_callback_log.payload_hash 长度上限 */
    private const HASH_MAX = 64;

    protected function configure()
    {
        $this->setName('recharge:scan-alt')
             ->setDescription('Scan ERC-20 / BTC / TON / SOL / XRP transfers and auto-confirm recharge orders');
    }

    protected function execute(Input $input, Output $output)
    {
        $confirmed   = 0;
        $pending     = 0;
        $scanned     = 0;
        $badAddress  = 0;
        $fetchFailed = 0;

        try {
            $channels = Db::connect('live_mysql')->table('lp_recharge_channel')
                ->where('status', 1)
                ->whereIn('type', self::TYPES)
                ->where('confirm_mode', 'auto')
                ->where('address', '<>', '')
                ->select()->toArray();

            foreach ($channels as $ch) {
                $type    = (string) $ch['type'];
                $addrErr = \app\live\service\RechargeService::addressError($type, (string) $ch['address']);
                if ($addrErr !== '') {
                    $badAddress++;
                    $msg = sprintf('[recharge:scan-alt] 渠道「%s」(type=%s) 收款地址非法已跳过扫链：%s',
                        (string) $ch['name'], $type, $addrErr);
                    $output->writeln($msg);
                    \think\facade\Log::warning($msg);
                    continue;
                }

                [$txs, $ok] = $this->fetch($type, (string) $ch['address']);
                if (!$ok) {
                    $fetchFailed++;
                    $msg = sprintf('[recharge:scan-alt] 渠道「%s」(type=%s) 交易列表抓取失败，本轮跳过',
                        (string) $ch['name'], $type);
                    $output->writeln($msg);
                    \think\facade\Log::warning($msg);
                }
                foreach ($txs as $tx) {
                    $scanned++;
                    $r = $this->processTransfer($ch, $tx);
                    if ($r === 'confirmed') $confirmed++;
                    elseif ($r === 'pending') $pending++;
                }
            }

            $extra = '';
            if ($badAddress > 0)  $extra .= sprintf(' bad_addr=%d', $badAddress);
            if ($fetchFailed > 0) $extra .= sprintf(' fetch_failed=%d', $fetchFailed);

            $line = sprintf('[recharge:scan-alt] channels=%d scanned=%d confirmed=%d pending_manual=%d%s',
                count($channels), $scanned, $confirmed, $pending, $extra);
        } catch (\Throwable $e) {
            $line = '[recharge:scan-alt] ERROR ' . $e->getMessage();
        }

        $output->writeln($line);
        \think\facade\Log::info($line);
        return 0;
    }

    /**
     * 按币种分发抓取，统一返回 [['hash','from','to','amount'(币本位字符串),'memo','ts'], 是否成功]
     */
    private function fetch(string $type, string $address): array
    {
        return match ($type) {
            'usdt_erc20', 'usdc_erc20', 'dai_erc20', 'tusd_erc20' => $this->fetchEvmToken($address, self::EVM_TOKENS[$type]),
            'usdt_bep20' => $this->fetchBep20($address),
            'bnb' => $this->fetchBnb($address),
            'btc' => $this->fetchBtc($address),
            'ton' => $this->fetchTon($address),
            'sol' => $this->fetchSol($address),
            'xrp' => $this->fetchXrp($address),
            default => [[], true],
        };
    }

    private function processTransfer(array $channel, array $tx): string
    {
        $db   = Db::connect('live_mysql');
        $type = (string) $channel['type'];
        $spec = \app\live\service\RechargeService::specOf($channel);
        $hash = (string) ($tx['hash'] ?? '');
        $to   = (string) ($tx['to'] ?? '');
        $ts   = (int) ($tx['ts'] ?? 0);

        if ($hash === '' || strcasecmp($to, (string) $channel['address']) !== 0) {
            return 'ignored';
        }

        // 精度对齐到该币种（与下单时 pay_amount 一致，例：USDT 2 位、BTC 8 位、TON/SOL/XRP 6 位）
        $amount = $this->normalizeAmount((string) ($tx['amount'] ?? '0'), (int) $spec['decimals']);

        // 需要备注的链（XRP Destination Tag / TON Comment）：渠道配了备注时转账必须带一致备注
        $memo = trim((string) ($channel['memo'] ?? ''));
        if ($memo !== '' && trim((string) ($tx['memo'] ?? '')) !== $memo) {
            return 'ignored';
        }

        $key  = substr($hash, 0, self::HASH_MAX);
        $seen = $db->table('lp_payment_callback_log')
            ->where('gateway', $type)
            ->where('payload_hash', $key)
            ->find();
        if ($seen) {
            return 'seen';
        }

        $order = $db->table('lp_recharge_order')
            ->where('channel_id', $channel['id'])
            ->where('pay_amount', $amount)
            ->where('status', 0)
            ->where('expire_at', '>', date('Y-m-d H:i:s'))
            ->order('id', 'asc')
            ->find();

        if ($order) {
            (new \app\live\service\RechargeService())->confirmByChain((int) $order['id'], [
                'transaction_id' => $hash,
            ], strtoupper((string) $spec['unit']) . '链上自动充值');
            $this->logCallback($type, (string) $order['order_no'], $key, 1, (float) $amount, (string) ($tx['from'] ?? ''), $to, $ts, $tx);
            return 'confirmed';
        }

        // 未匹配 → 待人工（金额不符 / 订单已过期等），只记近 2 小时内的交易
        if ($ts > time() - 7200) {
            $this->logCallback($type, '', $key, 0, (float) $amount, (string) ($tx['from'] ?? ''), $to, $ts, $tx);
            return 'pending';
        }
        return 'ignored';
    }

    private function logCallback(string $type, string $orderNo, string $key, int $status, float $amount, string $from, string $to, int $ts, array $tx): void
    {
        try {
            Db::connect('live_mysql')->table('lp_payment_callback_log')->insert([
                'order_no'        => $orderNo,
                'gateway'         => $type,
                'payload_hash'    => $key,
                'raw_payload'     => json_encode($tx, JSON_UNESCAPED_UNICODE),
                'verify_status'   => $status,
                'amount'          => $amount,
                'from_address'    => $from,
                'to_address'      => $to,
                'block_timestamp' => $ts,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate') === false) {
                \think\facade\Log::error('[recharge:scan-alt] 回调日志写入失败：' . $e->getMessage());
            }
        }
    }

    /** 按目标精度归一化金额（bcmath 截断，用户转的就是页面显示的精确金额） */
    private function normalizeAmount(string $v, int $dec): string
    {
        if ($v === '' || !is_numeric($v)) {
            return '0';
        }
        return bcadd($v, '0', max(0, min(18, $dec)));
    }

    /** 以太坊 ERC-20 转账（Routescan tokentx，Etherscan 兼容） */
    private function fetchEvmToken(string $address, array $token): array
    {
        try {
            $url = self::ROUTESCAN_API . '?' . http_build_query([
                'module'          => 'account',
                'action'          => 'tokentx',
                'address'         => $address,
                'contractaddress' => $token['contract'],
                'page'            => 1,
                'offset'          => 50,
                'sort'            => 'desc',
            ]);
            $raw  = $this->httpGet($url);
            $data = json_decode($raw, true);
            if (!is_array($data) || !is_array($data['result'] ?? null)) {
                \think\facade\Log::warning('[recharge:scan-alt] Routescan tokentx 返回异常：' . substr($raw, 0, 200));
                return [[], false];
            }

            $out = [];
            foreach ($data['result'] as $t) {
                if (!is_array($t)) continue;
                if (strcasecmp((string) ($t['to'] ?? ''), $address) !== 0) continue;
                if (strcasecmp((string) ($t['contractAddress'] ?? ''), $token['contract']) !== 0) continue;
                $dec = (int) ($t['tokenDecimal'] ?? $token['decimals']);
                $out[] = [
                    'hash'   => (string) ($t['hash'] ?? ''),
                    'from'   => (string) ($t['from'] ?? ''),
                    'to'     => (string) ($t['to'] ?? ''),
                    'amount' => bcdiv((string) ($t['value'] ?? '0'), bcpow('10', (string) $dec), $dec),
                    'memo'   => '',
                    'ts'     => (int) ($t['timeStamp'] ?? 0),
                ];
            }
            return [$out, true];
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:scan-alt] Routescan 查询失败：' . $e->getMessage());
            return [[], false];
        }
    }

    /** BTC 原生转账（mempool.space，免 Key） */
    private function fetchBtc(string $address): array
    {
        try {
            $raw  = $this->httpGet(self::MEMPOOL_API . '/address/' . $address . '/txs');
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                \think\facade\Log::warning('[recharge:scan-alt] mempool.space 返回异常：' . substr($raw, 0, 200));
                return [[], false];
            }

            $out = [];
            foreach ($data as $t) {
                if (!is_array($t) || !($t['status']['confirmed'] ?? false)) continue;
                $from = '';
                foreach ((array) ($t['vin'] ?? []) as $v) {
                    $a = (string) ($v['prevout']['scriptpubkey_address'] ?? '');
                    if ($a !== '') { $from = $a; break; }
                }
                $recv = 0;
                foreach ((array) ($t['vout'] ?? []) as $v) {
                    if (strcasecmp((string) ($v['scriptpubkey_address'] ?? ''), $address) === 0) {
                        $recv += (int) ($v['value'] ?? 0);   // sats
                    }
                }
                if ($recv <= 0) continue;
                $out[] = [
                    'hash'   => (string) ($t['txid'] ?? ''),
                    'from'   => $from,
                    'to'     => $address,
                    'amount' => bcdiv((string) $recv, '100000000', 8),
                    'memo'   => '',
                    'ts'     => (int) ($t['status']['block_time'] ?? 0),
                ];
            }
            return [$out, true];
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:scan-alt] mempool.space 查询失败：' . $e->getMessage());
            return [[], false];
        }
    }

    /** TON 原生转账（toncenter，免 Key） */
    private function fetchTon(string $address): array
    {
        try {
            $raw  = $this->httpGet(self::TONCENTER_API . '/getTransactions?address=' . urlencode($address) . '&limit=50');
            $data = json_decode($raw, true);
            if (!is_array($data) || empty($data['ok']) || !is_array($data['result'] ?? null)) {
                \think\facade\Log::warning('[recharge:scan-alt] toncenter 返回异常：' . substr($raw, 0, 200));
                return [[], false];
            }

            $out = [];
            foreach ($data['result'] as $t) {
                $in = $t['in_msg'] ?? null;
                if (!is_array($in)) continue;
                // 外部消息（用户自己发起转账时携带）value 为 0，只认真金白银的入账
                $value = (string) ($in['value'] ?? '0');
                if (!is_numeric($value) || (float) $value <= 0) continue;
                $out[] = [
                    'hash'   => (string) ($t['transaction_id']['hash'] ?? ''),
                    'from'   => (string) ($in['source'] ?? ''),
                    'to'     => $address,   // 接口已按地址过滤，in_msg 即入账
                    'amount' => bcdiv($value, '1000000000', 9),   // nanoton -> TON
                    'memo'   => (string) ($in['message'] ?? ''),
                    'ts'     => (int) ($t['utime'] ?? 0),
                ];
            }
            return [$out, true];
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:scan-alt] toncenter 查询失败：' . $e->getMessage());
            return [[], false];
        }
    }

    /** SOL 原生转账（公共 RPC，免 Key）：余额差值 > 0 即为入账 */
    private function fetchSol(string $address): array
    {
        try {
            $sigs = json_decode($this->httpPost(self::SOL_RPC, json_encode([
                'jsonrpc' => '2.0', 'id' => 1, 'method' => 'getSignaturesForAddress',
                'params'  => [$address, ['limit' => 10]],
            ], JSON_UNESCAPED_UNICODE)), true);
            if (!is_array($sigs) || !is_array($sigs['result'] ?? null)) {
                \think\facade\Log::warning('[recharge:scan-alt] Solana getSignaturesForAddress 返回异常');
                return [[], false];
            }

            $out = [];
            foreach ($sigs['result'] as $s) {
                if (!is_array($s) || !empty($s['err'])) continue;
                $sig = (string) ($s['signature'] ?? '');
                if ($sig === '') continue;

                $tx = json_decode($this->httpPost(self::SOL_RPC, json_encode([
                    'jsonrpc' => '2.0', 'id' => 1, 'method' => 'getTransaction',
                    'params'  => [$sig, ['encoding' => 'jsonParsed', 'maxSupportedTransactionVersion' => 0]],
                ], JSON_UNESCAPED_UNICODE)), true);
                $res = $tx['result'] ?? null;
                if (!is_array($res)) continue;
                $meta = $res['meta'] ?? null;
                $msg  = $res['transaction']['message'] ?? null;
                if (!is_array($meta) || !is_array($msg) || ($meta['err'] ?? null) !== null) continue;

                $idx = -1;
                foreach ((array) ($msg['accountKeys'] ?? []) as $i => $k) {
                    $pk = is_array($k) ? (string) ($k['pubkey'] ?? '') : (string) $k;
                    if (strcasecmp($pk, $address) === 0) { $idx = (int) $i; break; }
                }
                if ($idx < 0) continue;

                $delta = (int) ($meta['postBalances'][$idx] ?? 0) - (int) ($meta['preBalances'][$idx] ?? 0);
                if ($delta <= 0) continue;   // 只认净入账

                $out[] = [
                    'hash'   => $sig,
                    'from'   => '',
                    'to'     => $address,
                    'amount' => bcdiv((string) $delta, '1000000000', 9),   // lamport -> SOL
                    'memo'   => '',
                    'ts'     => (int) ($res['blockTime'] ?? 0),
                ];
            }
            return [$out, true];
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:scan-alt] Solana 查询失败：' . $e->getMessage());
            return [[], false];
        }
    }

    /** XRP 原生转账（XRPL 公共 RPC，免 Key） */
    private function fetchXrp(string $address): array
    {
        try {
            $raw = $this->httpPost(self::XRP_RPC, json_encode([
                'method' => 'account_tx',
                'params' => [[
                    'account'          => $address,
                    'limit'            => 50,
                    'ledger_index_min' => -1,
                    'ledger_index_max' => -1,
                    'binary'           => false,
                ]],
            ], JSON_UNESCAPED_UNICODE));
            $data = json_decode($raw, true);
            $rows = $data['result']['transactions'] ?? null;
            if (!is_array($rows)) {
                \think\facade\Log::warning('[recharge:scan-alt] XRPL account_tx 返回异常：' . substr($raw, 0, 200));
                return [[], false];
            }

            $out = [];
            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $t = $row['tx'] ?? $row['tx_json'] ?? null;
                if (!is_array($t)) continue;
                if ((string) ($t['TransactionType'] ?? '') !== 'Payment') continue;
                if ((string) ($row['meta']['TransactionResult'] ?? '') !== 'tesSUCCESS') continue;

                $amt = $t['Amount'] ?? '';
                // Amount 为字符串才是 XRP 原生（drops）；对象是 IOU，不认
                if (!is_string($amt) || !is_numeric($amt)) continue;

                $ts = 0;
                if (!empty($row['close_time_iso'])) {
                    $ts = (int) strtotime((string) $row['close_time_iso']);
                } elseif (!empty($t['date'])) {
                    $ts = 946684800 + (int) $t['date'];   // Ripple epoch (2000-01-01)
                }

                $out[] = [
                    'hash'   => (string) ($t['hash'] ?? ''),
                    'from'   => (string) ($t['Account'] ?? ''),
                    'to'     => (string) ($t['Destination'] ?? ''),
                    'amount' => bcdiv($amt, '1000000', 6),   // drops -> XRP
                    'memo'   => (string) ($t['DestinationTag'] ?? ''),
                    'ts'     => $ts,
                ];
            }
            return [$out, true];
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:scan-alt] XRPL 查询失败：' . $e->getMessage());
            return [[], false];
        }
    }

    /**
     * USDT-BEP20 转入（BSC 公共 RPC eth_getLogs，只回传命中日志，流量很小）
     */
    private function fetchBep20(string $address): array
    {
        try {
            $latest = $this->rpc('eth_blockNumber', []);
            if (!is_string($latest)) {
                return [[], false];
            }
            $from = '0x' . dechex(max(0, hexdec($latest) - self::BSC_LOG_LOOKBACK));

            $logs = $this->rpc('eth_getLogs', [[
                'fromBlock' => $from,
                'toBlock'   => 'latest',
                'address'   => self::BSC_USDT_BEP20,
                'topics'    => [self::TRANSFER_TOPIC, null, $this->topicOf($address)],
            ]]);
            if (!is_array($logs)) {
                return [[], false];
            }

            $out = [];
            foreach ($logs as $lg) {
                if (!is_array($lg)) continue;
                // USDT-BEP20 是 18 位小数（注意与以太坊上的 6 位不同）
                $out[] = [
                    'hash'   => (string) ($lg['transactionHash'] ?? ''),
                    'from'   => $this->addressFromTopic((string) ($lg['topics'][1] ?? '')),
                    'to'     => $this->addressFromTopic((string) ($lg['topics'][2] ?? '')),
                    'amount' => bcdiv($this->hexToDec((string) ($lg['data'] ?? '0x0')), '1000000000000000000', 18),
                    'memo'   => '',
                    // 日志本身不带时间戳，回看窗口只有 45 分钟，按「刚发生」处理即可
                    'ts'     => time(),
                ];
            }
            return [$out, true];
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:scan-alt] BSC getLogs 查询失败：' . $e->getMessage());
            return [[], false];
        }
    }

    /**
     * 原生 BNB 转入（BSC 公共 RPC 逐块扫，免 Key）
     *
     * 平时只推进块高游标不拉块；只有近 2 小时内有 BNB 订单时才真正扫块。
     */
    private function fetchBnb(string $address): array
    {
        try {
            $latest = $this->rpc('eth_blockNumber', []);
            if (!is_string($latest)) {
                return [[], false];
            }
            $latestNo = (int) hexdec($latest);

            if (!$this->hasRecentOrder('bnb')) {
                $this->saveScanBlock($latestNo);
                return [[], true];
            }

            $fromNo = max($this->loadScanBlock($latestNo - 20) + 1, $latestNo - self::BSC_BLOCK_MAX);
            $out    = [];
            for ($no = $fromNo; $no <= $latestNo; $no++) {
                $blk = $this->rpc('eth_getBlockByNumber', ['0x' . dechex($no), true]);
                if (!is_array($blk)) {
                    continue;
                }
                $ts = (int) hexdec((string) ($blk['timestamp'] ?? '0x0'));
                foreach ((array) ($blk['transactions'] ?? []) as $tx) {
                    if (!is_array($tx)) continue;
                    $to = (string) ($tx['to'] ?? '');
                    if ($to === '' || strcasecmp($to, $address) !== 0) continue;
                    $value = (string) ($tx['value'] ?? '0x0');
                    if ($value === '0x0' || $value === '0x') continue;   // 合约调用等无价值的跳过
                    $out[] = [
                        'hash'   => (string) ($tx['hash'] ?? ''),
                        'from'   => (string) ($tx['from'] ?? ''),
                        'to'     => $to,
                        'amount' => bcdiv($this->hexToDec($value), '1000000000000000000', 18),
                        'memo'   => '',
                        'ts'     => $ts,
                    ];
                }
            }
            $this->saveScanBlock($latestNo);
            return [$out, true];
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:scan-alt] BSC 扫块失败：' . $e->getMessage());
            return [[], false];
        }
    }

    /** 近 2 小时内是否有该渠道的订单（BSC 扫块成本高，用它做闸门） */
    private function hasRecentOrder(string $type): bool
    {
        return (bool) Db::connect('live_mysql')->table('lp_recharge_order')
            ->where('pay_channel', $type)
            ->where('created_at', '>', date('Y-m-d H:i:s', time() - 7200))
            ->count();
    }

    private function loadScanBlock(int $default): int
    {
        $v = Db::connect('live_mysql')->table('lp_platform_config')->where('key', self::BSC_BLOCK_KEY)->value('value');
        return ($v === null || $v === '') ? $default : (int) $v;
    }

    private function saveScanBlock(int $blockNo): void
    {
        $db = Db::connect('live_mysql');
        $now = date('Y-m-d H:i:s');
        if ($db->table('lp_platform_config')->where('key', self::BSC_BLOCK_KEY)->find()) {
            $db->table('lp_platform_config')->where('key', self::BSC_BLOCK_KEY)
               ->update(['value' => (string) $blockNo, 'updated_at' => $now]);
        } else {
            $db->table('lp_platform_config')->insert([
                'key'        => self::BSC_BLOCK_KEY,
                'value'      => (string) $blockNo,
                'remark'     => '原生 BNB 扫链进度（recharge:scan-alt 自用）',
                'updated_at' => $now,
            ]);
        }
    }

    /** JSON-RPC 调用（BSC 公共 RPC） */
    private function rpc(string $method, array $params)
    {
        $raw = $this->httpPost(self::BSC_RPC, json_encode([
            'jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params,
        ], JSON_UNESCAPED_UNICODE));
        $d = json_decode($raw, true);
        if (!is_array($d) || isset($d['error'])) {
            \think\facade\Log::warning('[recharge:scan-alt] BSC RPC ' . $method . ' 异常：' . substr($raw, 0, 200));
            return null;
        }
        return $d['result'] ?? null;
    }

    /** 地址 -> 32 字节 topic（左侧补零） */
    private function topicOf(string $address): string
    {
        $hex = strtolower(trim($address));
        if (str_starts_with($hex, '0x')) {
            $hex = substr($hex, 2);   // 不能用 ltrim(...,'0x')：会把地址本身的 0 也吃掉
        }
        return '0x' . str_pad($hex, 64, '0', STR_PAD_LEFT);
    }

    /** 32 字节 topic -> 地址 */
    private function addressFromTopic(string $topic): string
    {
        $hex = ltrim(substr(strtolower($topic), -40), '0');
        return $hex === '' ? '' : '0x' . str_pad($hex, 40, '0', STR_PAD_LEFT);
    }

    /** 16 进制字符串（可带 0x）-> 十进制字符串（bcmath，避免超出浮点精度） */
    private function hexToDec(string $hex): string
    {
        $hex = strtolower(ltrim(trim($hex), '0x'));
        if ($hex === '') {
            return '0';
        }
        $dec = '0';
        foreach (str_split($hex) as $c) {
            $dec = bcadd(bcmul($dec, '16'), (string) hexdec($c));
        }
        return $dec;
    }

    private function httpGet(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code >= 400) {
            throw new \RuntimeException('HTTP ' . $code . ' ' . substr((string) $body, 0, 120));
        }
        return (string) $body;
    }

    private function httpPost(string $url, string $json): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code >= 400) {
            throw new \RuntimeException('HTTP ' . $code . ' ' . substr((string) $body, 0, 120));
        }
        return (string) $body;
    }
}
