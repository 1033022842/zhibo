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

        // worker 接单后卡死（OOM/重启/跨境断线）→ 重试一次，再失败则退款
        $reaped = $service->reapStuckCustomTasks(90);
        if ($reaped) {
            $output->writeln('回收卡死的定制生成任务 ' . count($reaped) . ' 个:');
            foreach ($reaped as $item) {
                $extra = isset($item['refunded']) ? ', refunded=' . ($item['refunded'] ? 'yes' : 'no') : '';
                $output->writeln("  task_id={$item['task_id']}, action={$item['action']}{$extra}");
            }
        }

        // 50 条短视频通道（ai_short_*）：独立回收，不碰上面的 custom_* 逻辑
        $shortReaped = (new \app\ai\service\ShortTaskService())->reapStuck(90);
        if ($shortReaped) {
            $output->writeln('回收卡死的短视频任务 ' . count($shortReaped) . ' 个:');
            foreach ($shortReaped as $item) {
                $extra = isset($item['refunded']) ? ', refunded=' . ($item['refunded'] ? 'yes' : 'no') : '';
                $output->writeln("  task_id={$item['task_id']}, action={$item['action']}{$extra}");
            }
        }
    }
}
