<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

/**
 * 法币通道汇率自动更新（fiat:rate）
 *
 * 每小时由 crontab 调度：把 lp_fiat_channel 里 auto_rate=1 的通道汇率
 * 刷新为 USD→本币的中间价（例：巴西 Pix 通道 1 USD = x.xx BRL）。
 *
 * 数据源（免 key、按币种配置，前者失败自动换下一个）：
 *   BRL: open.er-api.com（主） / frankfurter.app（备，ECB 数据）
 *
 * 安全边界（防数据源坏了把收款价改飞）：
 *   1. 已知币种的绝对区间（BRL: 3~8）
 *   2. 相对当前配置汇率 ±30% 以内
 *   两个都过才落库；任一失败保持原汇率并告警日志。
 *
 * 想在中间价上加点位（覆盖波动/手续费），改 MARKUP_PERCENT。
 * 汇率只在「下单那一刻」锁定进订单，本任务改汇率不影响已生成订单。
 */
final class FiatRateCron extends Command
{
    /** 中间价上加价百分比（0 = 纯中间价；如填 2 表示中间价 × 1.02） */
    private const MARKUP_PERCENT = 0.0;

    /** 各币种安全区间 [min, max]（1 USD = ? 本币），防数据源异常把价格改飞 */
    private const SANITY_BOUNDS = [
        'BRL' => [3.0, 8.0],
        'MYR' => [3.0, 7.0],
        'VND' => [20000, 40000],
        'IDR' => [12000, 22000],
        'MXN' => [10.0, 30.0],
    ];

    /** 相对当前汇率的最大允许偏移（±30%），超过视为数据源异常 */
    private const MAX_DRIFT = 0.30;

    protected function configure()
    {
        $this->setName('fiat:rate')->setDescription('Refresh fiat channel USD rates from mid-market sources');
    }

    protected function execute(Input $input, Output $output)
    {
        $updated = 0;
        $skipped = 0;
        $lines   = [];

        try {
            $channels = Db::connect('live_mysql')->table('lp_fiat_channel')
                ->where('status', 1)
                ->where('auto_rate', 1)
                ->select()->toArray();

            if (!$channels) {
                $output->writeln('[fiat:rate] no auto_rate channels');
                return 0;
            }

            foreach ($channels as $ch) {
                $currency = strtoupper(trim((string) $ch['currency']));
                if ($currency === '') {
                    continue;
                }
                $old = (float) $ch['usd_rate'];
                $rate = $this->fetchMidRate($currency);
                if ($rate <= 0) {
                    $skipped++;
                    $lines[] = sprintf('%s(#%d) 拉取失败，保持 %.6f', $currency, (int) $ch['id'], $old);
                    continue;
                }

                $new = round($rate * (1 + self::MARKUP_PERCENT / 100), 6);

                // 安全边界：绝对区间 + 相对当前值 ±30%（当前值非法时只查绝对区间）
                $bounds = self::SANITY_BOUNDS[$currency] ?? null;
                if ($bounds !== null && ($new < $bounds[0] || $new > $bounds[1])) {
                    $skipped++;
                    $msg = sprintf('[fiat:rate] %s(#%d) 中间价 %.6f 超出安全区间[%s,%s]，已跳过（当前 %.6f）',
                        $currency, (int) $ch['id'], $new, $bounds[0], $bounds[1], $old);
                    $lines[] = $msg;
                    \think\facade\Log::warning($msg);
                    continue;
                }
                if ($old > 0 && abs($new - $old) / $old > self::MAX_DRIFT) {
                    $skipped++;
                    $msg = sprintf('[fiat:rate] %s(#%d) 新价 %.6f 偏离当前 %.6f 超过 ±%d%%，已跳过',
                        $currency, (int) $ch['id'], $new, $old, (int) (self::MAX_DRIFT * 100));
                    $lines[] = $msg;
                    \think\facade\Log::warning($msg);
                    continue;
                }

                if (abs($new - $old) < 0.000001) {
                    $lines[] = sprintf('%s(#%d) %.6f 无变化', $currency, (int) $ch['id'], $old);
                    continue;
                }

                Db::connect('live_mysql')->table('lp_fiat_channel')
                    ->where('id', (int) $ch['id'])
                    ->update(['usd_rate' => $new, 'updated_at' => date('Y-m-d H:i:s')]);
                $updated++;
                $lines[] = sprintf('%s(#%d) %.6f -> %.6f', $currency, (int) $ch['id'], $old, $new);
            }

            $line = sprintf('[fiat:rate] updated=%d skipped=%d | %s', $updated, $skipped, implode(' ; ', $lines));
        } catch (\Throwable $e) {
            $line = '[fiat:rate] ERROR ' . $e->getMessage();
        }

        $output->writeln($line);
        \think\facade\Log::info($line);
        return 0;
    }

    /**
     * 拉取 USD→币种 中间价，所有数据源失败返回 0
     */
    private function fetchMidRate(string $currency): float
    {
        // 主源：open.er-api.com（免 key，日更）
        try {
            $body = $this->httpGet('https://open.er-api.com/v6/latest/USD');
            $data = json_decode($body, true);
            if (($data['result'] ?? '') === 'success' && isset($data['rates'][$currency])) {
                return (float) $data['rates'][$currency];
            }
        } catch (\Throwable $e) {
            // fallback below
        }

        // 备源：frankfurter.app（ECB 参考汇率，免 key）
        try {
            $body = $this->httpGet('https://api.frankfurter.app/latest?from=USD&to=' . urlencode($currency));
            $data = json_decode($body, true);
            if (isset($data['rates'][$currency])) {
                return (float) $data['rates'][$currency];
            }
        } catch (\Throwable $e) {
        }

        return 0.0;
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
