<?php

declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

/**
 * 重置所有推流状态 — 部署/重启后执行
 * 运行：php think reset:streams
 */
class ResetStreams extends Command
{
    protected function configure(): void
    {
        $this->setName('reset:streams')
            ->setDescription('重置所有推流状态为 offline（部署后清理脏状态）');
    }

    protected function execute(Input $input, Output $output): void
    {
        $updated = Db::connect('live_mysql')
            ->table('lp_room_state_snapshot')
            ->where('current_state', 'public_live')
            ->update([
                'current_state' => 'offline',
                'current_mode' => 'public',
            ]);

        $output->writeln("<info>已重置 {$updated} 个推流状态</info>");
    }
}
