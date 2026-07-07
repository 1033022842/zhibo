<?php

declare(strict_types=1);

namespace app\command;

use app\admin\model\live\MaintenanceTask;
use app\common\service\TgService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

final class MaintenanceCheck extends Command
{
    protected function configure()
    {
        $this->setName('maintenance:check')
            ->setDescription('检查到期的维护任务并发送 TG 通知');
    }

    protected function execute(Input $input, Output $output)
    {
        $today = date('Y-m-d');
        $tgService = new TgService();

        // 查询：待通知的到期任务 + 开启重复提醒的已通知任务（到期当天或之前）
        $tasks = MaintenanceTask::where(function ($query) use ($today) {
            // 待通知且到期
            $query->where('status', 0)->where('due_date', '<=', $today);
        })->whereOr(function ($query) use ($today) {
            // 已通知但开启重复提醒，且到期日期在当天或之前
            $query->where('status', 1)->where('repeat_remind', 1)->where('due_date', '<=', $today);
        })->select();

        if ($tasks->isEmpty()) {
            $output->writeln("[$today] 无到期维护任务");
            return;
        }

        foreach ($tasks as $task) {
            $message = "{$today} 维护到期-{$task->name}";

            $result = $tgService->sendMessage($message);

            if ($result) {
                $task->status = 1;
                $task->last_notify_at = date('Y-m-d H:i:s');
                $task->save();
                $output->writeln("[$today] 已通知: {$task->name} (ID:{$task->id})");
            } else {
                $output->writeln("[$today] 通知失败: {$task->name} (ID:{$task->id})");
            }
        }
    }
}
