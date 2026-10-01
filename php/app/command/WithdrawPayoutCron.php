<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 提现链上打款巡检（withdraw:payout）
 *
 * 每分钟由 crontab 调度：
 * 1. 回收卡在「打款处理中」的单据（进程被杀/超时），避免单据永久卡死
 * 2. 轮询已广播的提现交易，链上确认后把单据置为「打款完成」
 *
 * 注意：本任务不会自动重试打款，失败单需运营核对热钱包无出账后在后台手动重试，
 * 避免「链上其实已出账、系统却重发」造成重复打款。
 */
final class WithdrawPayoutCron extends Command
{
    protected function configure()
    {
        $this->setName('withdraw:payout')->setDescription('Confirm broadcast withdrawal payouts and reclaim stale ones');
    }

    protected function execute(Input $input, Output $output)
    {
        $service = new \app\live\service\WithdrawService();

        try {
            $reclaim = $service->reclaimStale();
            $stat    = $service->confirmBroadcast();
            $line = sprintf(
                '[withdraw:payout] reclaim=%d checked=%d confirmed=%d failed=%d pending=%d',
                $reclaim,
                $stat['checked'],
                $stat['confirmed'],
                $stat['failed'],
                $stat['pending']
            );
        } catch (\Throwable $e) {
            $line = '[withdraw:payout] ERROR ' . $e->getMessage();
        }

        $output->writeln($line);
        \think\facade\Log::info($line);
        return 0;
    }
}
