<?php
declare(strict_types=1);

namespace app\command;

use app\ai\service\AiTaskService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

final class AiTaskCron extends Command
{
    protected function configure()
    {
        $this->setName('ai:task:cron')
            ->setDescription('过期超时未处理的 AI 任务 + 推流超时检查');
    }

    protected function execute(Input $input, Output $output)
    {
        $service = new AiTaskService();

        $result = $service->expireOverdueTasks();
        $count = count($result);

        if ($count > 0) {
            $output->writeln("已过期 {$count} 个 AI 任务:");
            foreach ($result as $item) {
                $output->writeln("  task_id={$item['task_id']}, task_no={$item['task_no']}, room_id={$item['room_id']}");
            }
        } else {
            $output->writeln('无过期 AI 任务');
        }

        $streamResult = $service->expireOverdueStreams();
        $streamCount = count($streamResult);

        if ($streamCount > 0) {
            $output->writeln("已结束 {$streamCount} 个超时推流:");
            foreach ($streamResult as $item) {
                $ended = $item['ended'] ?? false ? '已切回' : '失败';
                $error = $item['error'] ?? '';
                $output->writeln("  room_id={$item['room_id']}, switch_task_id={$item['switch_task_id']}, ended={$ended}" . ($error ? ", error={$error}" : ''));
            }
        } else {
            $output->writeln('无超时推流');
        }
    }
}
