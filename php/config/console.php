<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        \app\command\RoomSwitchCron::class,
        \app\command\AiTaskCron::class,
        \app\command\MaintenanceCheck::class,
        \app\command\ResetStreams::class,
        \app\command\CrowdfundingCron::class,
        \app\command\RechargeScanCron::class,
    ],
];
