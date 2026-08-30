<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

/**
 * USDT-TRC20 链上充值扫描（recharge:scan）
 *
 * 每分钟由 crontab 调度：
 * 1. 轮询 TronGrid TRC20 API（备用 Tronscan）查询各收款地址最新入账
 * 2. 金额精确匹配待支付订单（随机尾数标识）→ 自动确认入账
 * 3. 未匹配交易记入 lp_payment_callback_log(verify_status=0) 供后台人工处理
 * 4. 顺带关闭过期未支付订单
 *
 * 幂等性：transaction_id 在 lp_payment_callback_log 唯一索引去重
 */
final class RechargeScanCron extends Command
{
    /** USDT TRC20 合约 */
    private const USDT_CONTRACT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

    private const TRONGRID_API = 'https://api.trongrid.io';
    private const TRONSCAN_API = 'https://apilist.tronscanapi.com';

    protected function configure()
    {
        $this->setName('recharge:scan')->setDescription('Scan USDT TRC20 transfers and auto-confirm recharge orders');
    }

    protected function execute(Input $input, Output $output)
    {
        $confirmed = 0;
        $pending = 0;
        $expired = 0;

        try {
            $channels = Db::connect('live_mysql')->table('lp_recharge_channel')
                ->where('status', 1)
                ->where('confirm_mode', 'auto')
                ->where('address', '<>', '')
                ->select()->toArray();

            foreach ($channels as $ch) {
                $txs = $this->fetchTrc20Transfers($ch['address'], (string)($ch['api_key'] ?? ''));
                foreach ($txs as $tx) {
                    $to = (string)($tx['to'] ?? '');
                    if (strcasecmp($to, $ch['address']) !== 0) continue;
                    $token = (string)($tx['token_info']['address'] ?? '');
                    if (strcasecmp($token, self::USDT_CONTRACT) !== 0) continue;

                    $r = $this->processTransfer($ch, $tx);
                    if ($r === 'confirmed') $confirmed++;
                    elseif ($r === 'pending') $pending++;
                }
            }

            // 过期订单关闭
            $expired = (new \app\live\service\RechargeService())->expireOrders();

            $line = sprintf('[recharge:scan] confirmed=%d pending_manual=%d expired=%d', $confirmed, $pending, $expired);
        } catch (\Throwable $e) {
            $line = '[recharge:scan] ERROR ' . $e->getMessage();
        }

        $output->writeln($line);
        \think\facade\Log::info($line);
        return 0;
    }

    /**
     * 处理一笔入账转账：匹配订单 → 入账；未匹配 → 记待人工
     */
    private function processTransfer(array $channel, array $tx): string
    {
        $db = Db::connect('live_mysql');
        $txId = (string)($tx['transaction_id'] ?? '');
        $from = (string)($tx['from'] ?? '');
        $to = (string)($tx['to'] ?? '');
        $value = (float)((int)($tx['value'] ?? 0)) / 1000000; // 6 decimals
        $ts = (int)($tx['block_timestamp'] ?? 0) / 1000;
        $amount = number_format($value, 2, '.', '');

        // 幂等：已处理过的交易直接跳过
        $seen = $db->table('lp_payment_callback_log')
            ->where('gateway', 'usdt_trc20')
            ->where('payload_hash', $txId)
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
            (new \app\live\service\RechargeService())->confirmByChain((int)$order['id'], $tx);
            $this->logCallback((string)$order['order_no'], $txId, 1, $value, $from, $to, $ts, $tx);
            return 'confirmed';
        }

        // 未匹配 → 待人工（含已过期订单的入账、多转少转等）
        // 只记录近 2 小时内的交易，避免首次运行灌入历史
        if ($ts > time() - 7200) {
            $this->logCallback('', $txId, 0, $value, $from, $to, $ts, $tx);
            return 'pending';
        }
        return 'ignored';
    }

    private function logCallback(string $orderNo, string $txId, int $status, float $amount, string $from, string $to, int $ts, array $tx): void
    {
        try {
            Db::connect('live_mysql')->table('lp_payment_callback_log')->insert([
                'order_no'         => $orderNo,
                'gateway'          => 'usdt_trc20',
                'payload_hash'     => $txId,
                'raw_payload'      => json_encode($tx, JSON_UNESCAPED_UNICODE),
                'verify_status'    => $status,
                'amount'           => $amount,
                'from_address'     => $from,
                'to_address'       => $to,
                'block_timestamp'  => $ts,
                'created_at'       => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // 唯一键冲突（并发重复）忽略
        }
    }

    /**
     * TronGrid TRC20 转账查询（备用 Tronscan）
     */
    private function fetchTrc20Transfers(string $address, string $apiKey): array
    {
        try {
            $url = self::TRONGRID_API . '/v1/accounts/' . $address . '/transactions/trc20'
                . '?only_confirmed=true&only_to=true&limit=50&contract_address=' . self::USDT_CONTRACT;
            $headers = [];
            if ($apiKey !== '') {
                $headers[] = 'TRON-PRO-API-KEY: ' . $apiKey;
            }
            $res = $this->httpGet($url, $headers);
            $data = json_decode($res, true);
            if (($data['success'] ?? false) && isset($data['data']) && is_array($data['data'])) {
                return $data['data'];
            }
        } catch (\Throwable $e) {
            // fallback below
        }

        // 备用：Tronscan
        try {
            $url = self::TRONSCAN_API . '/api/token_trc20/transfers'
                . '?limit=50&sort=-timestamp&count=true&filterType=2&relatedAddress=' . $address
                . '&contract_address=' . self::USDT_CONTRACT;
            $res = $this->httpGet($url, []);
            $data = json_decode($res, true);
            $out = [];
            foreach ((array)($data['token_transfers'] ?? []) as $t) {
                $out[] = [
                    'transaction_id'  => $t['transaction_id'] ?? '',
                    'from'            => $t['from_address'] ?? '',
                    'to'              => $t['to_address'] ?? '',
                    'value'           => (string)($t['quant'] ?? '0'),
                    'block_timestamp' => $t['block_ts'] ?? 0,
                    'token_info'      => ['address' => $t['contract_address'] ?? self::USDT_CONTRACT],
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function httpGet(string $url, array $headers): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code >= 400) {
            throw new \RuntimeException('HTTP ' . $code . ' ' . substr((string)$body, 0, 120));
        }
        return (string)$body;
    }
}
