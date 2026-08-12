<?php

declare(strict_types=1);

namespace app\admin\service;

use app\common\service\SrsService;

/**
 * 直播房间推流状态管理
 *
 * 【架构变更说明】
 * 推流已从"服务器本地跑 ffmpeg 编码"改为"AI 电脑 ffmpeg 编码 → RTMP → 服务器 SRS"。
 * 实际的 worker.py 进程在 AI 电脑本地启动（见 services/channel-worker/python/），
 * 服务器端不再启动任何推流进程。因此本类不再负责进程的启动/停止，只维护：
 *   1. 房间推流状态快照（lp_room_state_snapshot）的读写
 *   2. 通过 SRS HTTP API 探活真实推流情况
 *
 * 后台直播间列表的"启动/停止"按钮已改为"只读状态灯"（见 Room 控制器 index +
 * 前端 index.vue）。start()/stop() 仅用于手动同步状态标记。
 *
 * 部署/架构见：
 *   - docs/srs-deploy.md（服务器 SRS）
 *   - services/channel-worker/python/README_STREAM.md（AI 电脑推流）
 */
final class ChannelWorkerManager
{
    /**
     * 标记房间为推流中（实际推流由 AI 电脑负责，这里只更新状态快照）
     * @return array{ok: bool, message: string}
     */
    public function start(int $roomId): array
    {
        $this->updateRoomState($roomId, 'public_live');
        return ['ok' => true, 'message' => '房间已标记为推流中（实际推流由 AI 电脑负责）'];
    }

    /**
     * 标记房间为已停止
     * @return array{ok: bool, message: string}
     */
    public function stop(int $roomId): array
    {
        $this->updateRoomState($roomId, 'offline');
        return ['ok' => true, 'message' => '房间已标记为停止推流'];
    }

    /**
     * 检查房间推流状态
     *
     * running 字段基于 SRS API 实时探活（真实推流情况），state 字段读数据库快照。
     * @return array{ok: bool, running: bool, pid: int|null, state: string}
     */
    public function status(int $roomId): array
    {
        $running = (new SrsService())->isRoomStreaming($roomId, 'room');
        $state = $this->getRoomState($roomId);

        return [
            'ok'      => true,
            'running' => $running,
            'pid'     => null, // 进程在 AI 电脑上，服务器端无 PID
            'state'   => $state,
        ];
    }

    /**
     * 服务器重启后清空所有推流状态
     * 通过 PID 哨兵文件判断是否重启：写入的 PID 与当前进程不同 = 服务器重启过
     */
    public static function resetStreamStatesIfBooted(): void
    {
        // PHP-FPM 模式下每请求 PID 都不同，不做自动检测
        // 生产环境通过部署脚本执行：php think reset:streams
        if (\PHP_SAPI === 'fpm-fcgi') {
            return;
        }

        $sentinelFile = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'channel-worker' . DIRECTORY_SEPARATOR . '.server_pid';

        $currentPid = getmypid();
        $previousPid = 0;
        if (file_exists($sentinelFile)) {
            $previousPid = (int) @file_get_contents($sentinelFile);
        }

        // PID 不同 = 新服务器进程，需要重置
        if ($previousPid !== $currentPid) {
            try {
                \think\facade\Db::connect('live_mysql')
                    ->table('lp_room_state_snapshot')
                    ->where('current_state', 'public_live')
                    ->update(['current_state' => 'offline', 'current_mode' => 'public', 'version' => \think\facade\Db::raw('version + 1')]);
            } catch (\Throwable $e) {
                // 数据库操作失败不影响主流程
            }

            // 清理残留 PID 文件（历史遗留，现在不再写 PID 文件，但保留清理）
            $pidDir = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'channel-worker';
            if (is_dir($pidDir)) {
                foreach (glob($pidDir . DIRECTORY_SEPARATOR . 'room-*.pid') as $pidFile) {
                    @unlink($pidFile);
                }
            }

            // 记录当前 PID 作为哨兵
            if (!is_dir(dirname($sentinelFile))) {
                mkdir(dirname($sentinelFile), 0755, true);
            }
            @file_put_contents($sentinelFile, (string) $currentPid);
        }
    }

    private function updateRoomState(int $roomId, string $state): void
    {
        try {
            $exists = \think\facade\Db::connect('live_mysql')
                ->table('lp_room_state_snapshot')
                ->where('room_id', $roomId)
                ->find();

            if ($exists) {
                \think\facade\Db::connect('live_mysql')
                    ->table('lp_room_state_snapshot')
                    ->where('room_id', $roomId)
                    ->update([
                        'current_state' => $state,
                        'current_mode'  => 'public',
                        'version'       => \think\facade\Db::raw('version + 1'),
                    ]);
            } else {
                \think\facade\Db::connect('live_mysql')
                    ->table('lp_room_state_snapshot')
                    ->insert([
                        'room_id'       => $roomId,
                        'current_state' => $state,
                        'current_mode'  => 'public',
                        'version'       => 1,
                    ]);
            }
        } catch (\Throwable $e) {
            // 状态更新失败不影响主流程
        }
    }

    private function getRoomState(int $roomId): string
    {
        try {
            $state = \think\facade\Db::connect('live_mysql')
                ->table('lp_room_state_snapshot')
                ->where('room_id', $roomId)
                ->value('current_state');
            return $state ?: 'offline';
        } catch (\Throwable $e) {
            return 'offline';
        }
    }
}
