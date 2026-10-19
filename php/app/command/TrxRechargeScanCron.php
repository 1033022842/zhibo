<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

/**
 * TRX（TRON 原生币）链上充值扫描（recharge:scan-trx）
 *
 * 与 recharge:scan（USDT-TRC20）同链，由 crontab 每分钟调度：
 * 1. 查询各 TRX 收款地址的原生转账入账（Tronscan /api/transfer，免 Key）
 * 2. 金额精确匹配待支付订单（小数第 5/6 位随机尾数标识）→ 自动确认入账
 * 3. 未匹配交易记入 lp_payment_callback_log(verify_status=0) 供后台人工处理
 *
 * 幂等性：tx hash 在 lp_payment_callback_log 唯一索引去重
 *
 * 注意：Tronscan /api/transfer 会同时带出「转入」和「转出」，必须按 transferToAddress 过滤；
 *       该接口也会返回地址上的 TRC-20 转账，故再用 tokenId='_' 只保留原生 TRX；
 *       amount 是最小单位 SUN（1 TRX = 1e6 SUN）。
 */
final class TrxRechargeScanCron extends Command
{
    private const TRONSCAN_API = 'https://apilist.tronscanapi.com';
    private const GATEWAY      = 'trx';
    /** 1 TRX = 1e6 SUN */
    private const SUN_UNIT     = '1000000';
    /** TRX 链上精度（6 位小数） */
    private const DECIMALS     = 6;

    protected function configure()
    {
        $this->setName('recharge:scan-trx')->setDescription('Scan native TRX transfers and auto-confirm recharge orders');
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
                ->where('type', 'trx')
                ->where('confirm_mode', 'auto')
                ->where('address', '<>', '')
                ->select()->toArray();

            foreach ($channels as $ch) {
                // 地址非法时扫链必然失败，先拦下来并明确报错（避免静默空转）
                $addrErr = \app\live\service\RechargeService::addressError('trx', (string) $ch['address']);
                if ($addrErr !== '') {
                    $badAddress++;
                    $msg = sprintf('[recharge:scan-trx] 渠道「%s」收款地址非法已跳过扫链：%s（address=%s）',
                        (string) $ch['name'], $addrErr, (string) $ch['address']);
                    $output->writeln($msg);
                    \think\facade\Log::warning($msg);
                    continue;
                }

                [$txs, $ok] = $this->fetchTrxTransfers((string) $ch['address']);
                if (!$ok) {
                    // 抓取失败（限流/网络/接口报错）：本轮扫不到，订单留在待支付等下一轮，
                    // 必须显式报出来，否则又是「静默空转」
                    $fetchFailed++;
                    $msg = sprintf('[recharge:scan-trx] 渠道「%s」交易列表抓取失败，本轮跳过', (string) $ch['name']);
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

            $line = sprintf('[recharge:scan-trx] channels=%d scanned=%d confirmed=%d pending_manual=%d%s',
                count($channels), $scanned, $confirmed, $pending, $extra);
        } catch (\Throwable $e) {
            $line = '[recharge:scan-trx] ERROR ' . $e->getMessage();
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
        $ts     = (int) ($tx['ts'] ?? 0);

        if ($txHash === '' || strcasecmp($to, (string) $channel['address']) !== 0) {
            return 'ignored';
        }

        // SUN → TRX（保留 6 位，与订单 pay_amount 同精度）
        $value  = (float) bcdiv((string) ($tx['amount'] ?? '0'), self::SUN_UNIT, self::DECIMALS);
        $amount = number_format($value, self::DECIMALS, '.', '');

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
            ], 'TRX链上自动充值');
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
                \think\facade\Log::error('[recharge:scan-trx] 回调日志写入失败：' . $e->getMessage());
            }
        }
    }

    /**
     * 查询地址上的原生 TRX 入账（Tronscan /api/transfer，免 Key）
     *
     * @return array{0: array<int, array>, 1: bool} [交易列表, 抓取是否成功]
     */
    private function fetchTrxTransfers(string $address): array
    {
        try {
            $url = self::TRONSCAN_API . '/api/transfer?' . http_build_query([
                'sort'    => '-timestamp',
                'count'   => 'true',
                'limit'   => 50,
                'start'   => 0,
                'address' => $address,
            ]);
            $raw  = $this->httpGet($url);
            $data = json_decode($raw, true);
            if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
                \think\facade\Log::warning('[recharge:scan-trx] Tronscan 返回异常：' . substr($raw, 0, 200));
                return [[], false];
            }

            $out = [];
            foreach ($data['data'] as $t) {
                if (!is_array($t)) {
                    continue;
                }
                if (($t['confirmed'] ?? false) !== true) {
                    continue; // 未确认
                }
                if (!empty($t['revert'])) {
                    continue; // 已回滚
                }
                if ((string) ($t['contractRet'] ?? '') !== 'SUCCESS') {
                    continue; // 失败交易不算入账
                }
                if ((string) (($t['tokenInfo']['tokenId'] ?? '')) !== '_') {
                    continue; // 只认原生 TRX，排除该地址上的 TRC-20 / TRC-10 转账
                }
                $to = (string) ($t['transferToAddress'] ?? '');
                if (strcasecmp($to, $address) !== 0) {
                    continue; // 该接口会带出转出记录，按收款地址过滤
                }
                $out[] = [
                    'hash'   => (string) ($t['transactionHash'] ?? ''),
                    'from'   => (string) ($t['transferFromAddress'] ?? ''),
                    'to'     => $to,
                    'amount' => (string) ($t['amount'] ?? '0'),
                    'ts'     => (int) round(((int) ($t['timestamp'] ?? 0)) / 1000),
                ];
            }
            return [$out, true];
        } catch (\Throwable $e) {
            \think\facade\Log::error('[recharge:scan-trx] Tronscan 查询失败：' . $e->getMessage());
            return [[], false];
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
