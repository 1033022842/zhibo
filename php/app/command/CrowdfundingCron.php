<?php
declare(strict_types=1);

namespace app\command;

use app\live\service\CrowdfundingService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;

final class CrowdfundingCron extends Command
{
    protected function configure(): void
    {
        $this->setName('crowdfunding:settle')
            ->setDescription('扫描到期众筹项目，自动结算（成功划转 / 失败退款）');
    }

    protected function execute(Input $input, Output $output): int
    {
        $service = new CrowdfundingService();
        $result = $service->settleExpiredProjects();

        $msg = "众筹结算完成: 成功={$result['success']}, 失败退款={$result['failed']}";
        $output->info($msg);

        if ($result['success'] > 0 || $result['failed'] > 0) {
            Log::info("CrowdfundingCron: {$msg}");
        }

        return 0;
    }
}
