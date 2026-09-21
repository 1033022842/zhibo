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
 * 1. 用 Etherscan API v2 查询各 ETH 收款地址最新入账（txlist）
 * 2. 金额精确匹配待支付订单（第 7/8 位随机尾数标识）→ 自动确认入账
 * 3. 未匹配交易记入 lp_payment_callback_log(verify_status=0) 供后台人工处理
 *
 * 幂等性：tx hash 在 lp_payment_callback_log 唯一索引去重
 */
final class EthRechargeScanCron extends Command
{
    private const ETHERSCAN_API = 'https://api.etherscan.io/v2/api';
    private const CHAIN_ID      = 1;   // 1 = Ethereum mainnet
    private const GATEWAY       = 'eth';

    protected function configure()
    {
        $this->setName('recharge:scan-eth')->setDescription('Scan native ETH transfers and auto-confirm recharge orders');
    }

    protected function execute(Input $input, Output $output)
    {
        $confirmed = 0;
        $pending   = 0;
        $scanned   = 0;

        try {
            $channels = Db::connect('live_mysql')->table('lp_recharge_channel')
                ->where('status', 1)
                ->where('type', 'eth')
                ->where('confirm_mode', 'auto')
                ->where('address', '<>', '')
                ->select()->toArray();

            foreach ($channels as $ch) {
                // 占位地址（全 0）不扫，避免误配置空跑
                if (preg_match('/^0x0{40}$/i', (string) $ch['address'])) {
                    continue;
                }
                $txs = $this->fetchEthTransfers((string) $ch['address'], (string) ($ch['api_key'] ?? ''));
                foreach ($txs as $tx) {
                    $scanned++;
                    $r = $this->processTransfer($ch, $tx);
                    if ($r === 'confirmed') $confirmed++;
                    elseif ($r === 'pending') $pending++;
                }
            }

            $line = sprintf('[recharge:scan-eth] channels=%d scanned=%d confirmed=%d pending_manual=%d',
                count($channels), $scanned, $confirmed, $pending);
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
            // 唯一键冲突（并发重复）忽略
        }
    }

    /**
     * Etherscan v2 普通交易列表（含主链 ETH 转账），过滤失败交易
     */
    private function fetchEthTransfers(string $address, string $apiKey): array
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
            $res  = $this->httpGet($url);
            $data = json_decode($res, true);
            if (!is_array($data) || (string) ($data['status'] ?? '') !== '1' || !is_array($data['result'] ?? null)) {
                // "No transactions found" 也算正常
                return [];
            }

            $out = [];
            foreach ($data['result'] as $tx) {
                if ((string) ($tx['isError'] ?? '0') !== '0') {
                    continue; // 失败的交易不算入账
                }
                $out[] = $tx;
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
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
