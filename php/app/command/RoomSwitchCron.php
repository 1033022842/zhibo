<?php
declare(strict_types=1);

namespace app\command;

use app\room\service\ChannelWorkerGateway;
use app\room\service\RoomSwitchScheduler;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;

final class RoomSwitchCron extends Command
{
    protected function configure(): void
    {
        $this->setName('room:switch:cron')
            ->setDescription('检查过期特权/互动并自动切回公共流');
    }

    protected function execute(Input $input, Output $output): int
    {
        $scheduler = new RoomSwitchScheduler();
        $gateway = new ChannelWorkerGateway();

        $results = $scheduler->checkExpiredPrivileges();
        $expiredCount = 0;

        foreach ($results as $r) {
            if ($r['expired'] ?? false) {
                $expiredCount++;
                $roomId = (int) ($r['room_id'] ?? 0);
                $taskId = (int) ($r['task_id'] ?? 0);

                if ($roomId > 0) {
                    $gateway->pushSwitchToPublic($roomId, $taskId);
                    $gateway->pushBroadcast($roomId, 'privilege_ended', [
                        'task_id' => $taskId,
                    ]);
                }

                $output->info("Room {$roomId} privilege expired, task={$taskId}");
            }
        }

        if ($expiredCount > 0) {
            Log::info("RoomSwitchCron: {$expiredCount} privileges expired");
        }

        $output->info("Checked expired privileges, {$expiredCount} expired");

        return 0;
    }
}
