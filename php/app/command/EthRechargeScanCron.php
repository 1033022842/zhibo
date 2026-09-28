<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

/**
 * ETH（以太坊主链）链上充值扫描（recharge:scan-eth）
 *
 * 与 recharge:scan（USDT-TRC20）同构，由 crontab 每分钟调度：
 * 1. 查询各 ETH 收款地址最新入账（首选 Etherscan v2，无 Key / 失败时回退 Blockscout）
 * 2. 金额精确匹配待支付订单（第 7/8 位随机尾数标识）→ 自动确认入账
 * 3. 未匹配交易记入 lp_payment_callback_log(verify_status=0) 供后台人工处理
 *
 * 幂等性：tx hash 在 lp_payment_callback_log 唯一索引去重
 *
 * 注意：Etherscan v2 强制要求 API Key（无 Key 直接返回 Missing/Invalid API Key），
 * 所以这里补了完全免 Key 的 Blockscout 兜底，保证 ETH 扫链在没有 Key 时也能跑。
 */
final class EthRechargeScanCron extends Command
{
    private const ETHERSCAN_API = 'https://api.etherscan.io/v2/api';
    private const BLOCKSCOUT_API = 'https://eth.blockscout.com/api';
    private const CHAIN_ID      = 1;   // 1 = Ethereum mainnet
    private const GATEWAY       = 'eth';

    protected function configure()
    {
        $this->setName('recharge:scan-eth')->setDescription('Scan native ETH transfers and auto-confirm recharge orders');
    }

    protected function execute(Input $input, Output $output)
    {
        $confirmed    = 0;
        $pending      = 0;
        $scanned      = 0;
        $badAddress   = 0;
        $noKey        = 0;
        $viaBlockscout = 0;
        $fetchFailed  = 0;

        try {
            $channels = Db::connect('live_mysql')->table('lp_recharge_channel')
                ->where('status', 1)
                ->where('type', 'eth')
                ->where('confirm_mode', 'auto')
                ->where('address', '<>', '')
                ->select()->toArray();

            foreach ($channels as $ch) {
                // 地址非法时扫链必然失败，先拦下来并明确报错（避免静默空转）
                $addrErr = \app\live\service\RechargeService::addressError('eth', (string) $ch['address']);
                if ($addrErr !== '') {
                    $badAddress++;
                    $msg = sprintf('[recharge:scan-eth] 渠道「%s」收款地址非法已跳过扫链：%s（address=%s）',
                        (string) $ch['name'], $addrErr, (string) $ch['address']);
                    $output->writeln($msg);
                    \think\facade\Log::warning($msg);
                    continue;
                }
                if (trim((string) ($ch['api_key'] ?? '')) === '') {
                    $noKey++;
                }

                [$txs, $via, $ok] = $this->fetchEthTransfers((string) $ch['address'], (string) ($ch['api_key'] ?? ''));
                if ($via === 'blockscout') {
                    $viaBlockscout++;
                }
                if (!$ok) {
                    // 抓取失败（限流/网络/接口报错）：本轮扫不到，订单会留在待审核等人工补单，
                    // 必须显式报出来，否则又是「静默空转」
                    $fetchFailed++;
                    $msg = sprintf('[recharge:scan-eth] 渠道「%s」交易列表抓取失败（via=%s），本轮跳过',
                        (string) $ch['name'], $via);
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
            if ($badAddress > 0 || $noKey > 0) {
                $extra .= sprintf(' bad_addr=%d no_key=%d', $badAddress, $noKey);
            }
            if ($viaBlockscout > 0) {
                $extra .= sprintf(' via_blockscout=%d', $viaBlockscout);
            }
            if ($fetchFailed > 0) {
                $extra .= sprintf(' fetch_failed=%d', $fetchFailed);
            }

            $line = sprintf('[recharge:scan-eth] channels=%d scanned=%d confirmed=%d pending_manual=%d%s',
                count($channels), $scanned, $confirmed, $pending, $extra);
        } catch (\Throwable $e) {
            $line = '[recharge:scan-eth] ERROR ' . $e->getMessage();
        }

        $output->writeln($line);
        \think\facade\Log::info($line);
        return 0;
    }

    /**
     * 处理一笔入账：匹配订单 → 入账；未匹配 → 记待人工
     */
    private function processTransfer(array $channel, array $tx): string
    {
        $db     = Db::connect('live_mysql');
        $txHash = (string) ($tx['hash'] ?? '');
        $from   = (string) ($tx['from'] ?? '');
        $to     = (string) ($tx['to'] ?? '');
        $ts     = (int) ($tx['timeStamp'] ?? 0);
        $value  = (float) bcdiv((string) ($tx['value'] ?? '0'), '1000000000000000000', 8); // wei → ETH
        $amount = number_format($value, 8, '.', '');

        if ($txHash === '' || strcasecmp($to, (string) $channel['address']) !== 0) {
            return 'ignored';
        }

        // 幂等：已处理过的交易直接跳过
        $seen = $db->table('lp_payment_callback_log')
            ->where('gateway', self::GATEWAY)
            ->where('payload_hash', $txHash)
            ->find();
        if ($seen) {
            return 'seen';
        }

        // 金额精确匹配（尾数标识）+ 渠道一致 + 待支付 + 未过期
        $order = $db->table('lp_recharge_order')
            ->where('channel_id', $channel['id'])
            ->where('pay_amount', $amount)
            ->where('status', 0)
            ->where('expire_at', '>', date('Y-m-d H:i:s'))
            ->order('id', 'asc')
            ->find();

        if ($order) {
            (new \app\live\service\RechargeService())->confirmByChain((int) $order['id'], [
                'transaction_id' => $txHash,
            ], 'ETH链上自动充值');
            $this->logCallback((string) $order['order_no'], $txHash, 1, $value, $from, $to, $ts, $tx);
            return 'confirmed';
        }

        // 未匹配 → 待人工（金额不符 / 订单已过期等），只记近 2 小时内的交易
        if ($ts > time() - 7200) {
            $this->logCallback('', $txHash, 0, $value, $from, $to, $ts, $tx);
            return 'pending';
        }
        return 'ignored';
    }

    private function logCallback(string $orderNo, string $txHash, int $status, float $amount, string $from, string $to, int $ts, array $tx): void
    {
        try {
            Db::connect('live_mysql')->table('lp_payment_callback_log')->insert([
                'order_no'        => $orderNo,
                'gateway'         => self::GATEWAY,
                'payload_hash'    => $txHash,
                'raw_payload'     => json_encode($tx, JSON_UNESCAPED_UNICODE),
                'verify_status'   => $status,
                'amount'          => $amount,
                'from_address'    => $from,
                'to_address'      => $to,
                'block_timestamp' => $ts,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // 只有唯一键冲突（并发/重复扫描）才是正常的；其它错误（例如表结构缺列）
            // 必须暴露出来，否则「未匹配的链上转账」会被静默丢掉、后台完全看不到
            if (stripos($e->getMessage(), 'Duplicate') === false) {
                \think\facade\Log::error('[recharge:scan-eth] 回调日志写入失败：' . $e->getMessage());
            }
        }
    }

    /**
     * 查询地址上的原生 ETH 入账
     *
     * @return array{0: array<int, array>, 1: string, 2: bool} [交易列表, 实际使用的通道, 抓取是否成功]
     */
    private function fetchEthTransfers(string $address, string $apiKey): array
    {
        // 首选 Etherscan v2（需要 API Key，无 Key 会直接返回 Missing/Invalid API Key）
        if (trim($apiKey) !== '') {
            $txs = $this->fetchFromEtherscan($address, $apiKey);
            if ($txs !== null) {
                return [$txs, 'etherscan', true];
            }
        }

        // 兜底 Blockscout：Etherscan 兼容协议，完全免 Key（注意公共额度会限流 429）
        $txs = $this->fetchFromBlockscout($address);
        return [$txs ?? [], 'blockscout', $txs !== null];
    }

    private function fetchFromEtherscan(string $address, string $apiKey): ?array
    {
        try {
            $url = self::ETHERSCAN_API . '?' . http_build_query([
                'chainid'    => self::CHAIN_ID,
                'module'     => 'account',
                'action'     => 'txlist',
                'address'    => $address,
                'startblock' => 0,
                'endblock'   => 99999999,
                'page'       => 1,
                'offset'     => 50,
                'sort'       => 'desc',
                'apikey'     => $apiKey,
            ]);
            $raw = $this->httpGet($url);
            $txs = $this->parseTxList((array) json_decode($raw, true));
            if ($txs === null) {
                \think\facade\Log::warning('[recharge:scan-eth] Etherscan 返回异常，回退 Blockscout：' . substr($raw, 0, 200));
            }
            return $txs;
        } catch (\Throwable $e) {
            \think\facade\Log::warning('[recharge:scan-eth] Etherscan 查询失败，回退 Blockscout：' . $e->getMessage());
            return null;
        }
    }

    private function fetchFromBlockscout(string $address): ?array
    {
        try {
            $url = self::BLOCKSCOUT_API . '?' . http_build_query([
                'module'  => 'account',
                'action'  => 'txlist',
                'address' => $address,
                'sort'    => 'desc',
                'page'    => 1,
                'offset'  => 50,
            ]);
            $raw = $this->httpGet($url);
            $txs = $this->parseTxList((array) json_decode($raw, true));
            if ($txs === null) {
                // 常见是 {"message":"Too many requests","result":null,"status":"0"} 限流
                \think\facade\Log::warning('[recharge:scan-eth] Blockscout 返回异常：' . substr($raw, 0, 200));
            }
            return $txs;
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:scan-eth] Blockscout 查询失败：' . $e->getMessage());
            return null;
        }
    }

    /**
     * 解析 Etherscan / Blockscout 兼容的 txlist 响应，过滤失败交易
     *
     * 两者响应同构，唯一差别：Etherscan 带 status 字段（"1" 成功），
     * Blockscout 兼容层只返回 message + result，所以不能强依赖 status。
     */
    private function parseTxList(array $data): ?array
    {
        if (!is_array($data['result'] ?? null)) {
            return null;
        }
        if (isset($data['status']) && (string) $data['status'] !== '1') {
            return str_contains((string) ($data['message'] ?? ''), 'No transactions found') ? [] : null;
        }

        $out = [];
        foreach ($data['result'] as $tx) {
            if (!is_array($tx) || (string) ($tx['isError'] ?? '0') !== '0') {
                continue; // 失败的交易不算入账
            }
            $out[] = $tx;
        }
        return $out;
    }

    private function httpGet(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
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
}
