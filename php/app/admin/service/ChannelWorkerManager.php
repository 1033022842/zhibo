<?php

declare(strict_types=1);

namespace app\admin\service;

/**
 * 管理 channel-worker 进程的启动/停止/状态查询
 * 通过 PID 文件追踪进程
 */
final class ChannelWorkerManager
{
    /**
     * 启动推流
     * @return array{ok: bool, message: string, pid?: int}
     */
    public function start(int $roomId): array
    {
        if ($this->isRunning($roomId)) {
            return ['ok' => false, 'message' => '该房间已在推流中'];
        }

        $scriptPath = $this->workerScriptPath();
        if (!file_exists($scriptPath)) {
            return ['ok' => false, 'message' => 'channel-worker 脚本不存在: ' . $scriptPath];
        }

        $pidDir = $this->pidDir();
        if (!is_dir($pidDir)) {
            mkdir($pidDir, 0755, true);
        }

        $pidFile = $this->pidFile($roomId);
        $logFile = $this->logFile($roomId);
        $workerDir = $this->workerDir();

        // 获取当前进程的 DB 环境变量，传递给子进程（channel-worker 用 getenv 读取）
        $envVars = $this->getEnvForChild();

        $descriptorSpec = [
            0 => ['pipe', 'r'],   // stdin
            1 => ['file', $logFile, 'a'],  // stdout → 日志文件
            2 => ['file', $logFile, 'a'],  // stderr → 日志文件
        ];

        $process = proc_open(
            sprintf('php "%s" --room=%d', $scriptPath, $roomId),
            $descriptorSpec,
            $pipes,
            $workerDir,
            $envVars,
        );

        if (!is_resource($process)) {
            return ['ok' => false, 'message' => '无法启动推流进程'];
        }

        // 关闭 stdin 管道
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        $status = proc_get_status($process);
        $pid = $status['pid'] ?? 0;

        // 关键：不能调 proc_close()，它会阻塞等待子进程退出
        // channel-worker 是无限循环的 ffmpeg 推流进程，永远不退出
        // 释放 PHP 侧的句柄引用，子进程会脱离继续运行
        unset($pipes, $process, $status);

        if ($pid > 0) {
            file_put_contents($pidFile, (string) $pid);
            $this->updateRoomState($roomId, 'public_live');
            return ['ok' => true, 'message' => "推流已启动 (PID: {$pid})", 'pid' => $pid];
        }

        return ['ok' => false, 'message' => '推流进程启动失败'];
    }

    /**
     * 停止推流
     * @return array{ok: bool, message: string}
     */
    public function stop(int $roomId): array
    {
        if (!$this->isRunning($roomId)) {
            return ['ok' => false, 'message' => '该房间未在推流'];
        }

        $pidFile = $this->pidFile($roomId);
        $pid = (int) @file_get_contents($pidFile);

        if ($pid <= 0) {
            @unlink($pidFile);
            $this->updateRoomState($roomId, 'offline');
            return ['ok' => true, 'message' => '已清理无效 PID 文件'];
        }

        $killed = false;
        if ($this->isWindows()) {
            // /T = 杀整个进程树（包括 ffmpeg 子进程）
            exec("taskkill /F /T /PID {$pid} 2>&1", $output, $code);
            $killed = ($code === 0);
        } else {
            // Linux: 杀死整个进程组（负 PID = process group）
            // channel-worker 和它的 ffmpeg 子进程都在同一个进程组内
            exec("kill -TERM -- -{$pid} 2>&1", $output, $code);
            $killed = ($code === 0);
            if (!$killed) {
                exec("kill -9 -- -{$pid} 2>&1", $output, $code);
                $killed = ($code === 0);
            }
        }

        @unlink($pidFile);
        $this->updateRoomState($roomId, 'offline');

        if ($killed) {
            return ['ok' => true, 'message' => "推流已停止 (PID: {$pid})"];
        }

        return ['ok' => true, 'message' => "已清理推流记录 (PID: {$pid} 可能已退出)"];
    }

    /**
     * 检查推流状态
     * @return array{ok: bool, running: bool, pid: int|null, state: string}
     */
    public function status(int $roomId): array
    {
        $running = $this->isRunning($roomId);
        $pid = null;

        if ($running) {
            $pidFile = $this->pidFile($roomId);
            $pid = (int) @file_get_contents($pidFile);
        }

        $state = $this->getRoomState($roomId);

        return [
            'ok' => true,
            'running' => $running,
            'pid' => $pid,
            'state' => $state,
        ];
    }

    private function isRunning(int $roomId): bool
    {
        $pidFile = $this->pidFile($roomId);
        if (!file_exists($pidFile)) {
            return false;
        }

        $pid = (int) @file_get_contents($pidFile);
        if ($pid <= 0) {
            @unlink($pidFile);
            return false;
        }

        if ($this->isWindows()) {
            exec("tasklist /FI \"PID eq {$pid}\" 2>&1", $output);
            return count(array_filter($output, fn(string $line): bool => str_contains($line, (string) $pid))) > 0;
        }

        return file_exists("/proc/{$pid}");
    }

    /**
     * 获取子进程需要的环境变量
     * channel-worker 通过 getenv() 读取 DB 配置
     */
    private function getEnvForChild(): array
    {
        $env = [];
        $keys = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_CHARSET', 'DB_USER', 'DB_PASSWORD', 'FFMPEG_BIN'];

        foreach ($keys as $key) {
            $val = getenv($key);
            if ($val !== false && $val !== '') {
                $env[$key] = $val;
            }
        }

        // 如果系统环境没有，尝试从 ThinkPHP .env 配置读取
        if (empty($env['DB_HOST'])) {
            try {
                $dbConfig = \think\facade\Db::connect('live_mysql')->getConfig();
                $env['DB_HOST'] = $dbConfig['hostname'] ?? '127.0.0.1';
                $env['DB_PORT'] = (string) ($dbConfig['hostport'] ?? '3306');
                $env['DB_NAME'] = $dbConfig['database'] ?? 'live_platform';
                $env['DB_CHARSET'] = $dbConfig['charset'] ?? 'utf8mb4';
                $env['DB_USER'] = $dbConfig['username'] ?? 'root';
                $env['DB_PASSWORD'] = $dbConfig['password'] ?? 'root';
            } catch (\Throwable $e) {
                // fallback
            }
        }

        // 合并当前系统环境（保留 PATH 等必要变量）
        if (function_exists('getenv')) {
            $keepKeys = ['PATH', 'SystemRoot', 'TEMP', 'TMP', 'HOME', 'USER', 'SHELL', 'LOCALAPPDATA'];
            foreach ($keepKeys as $key) {
                $val = getenv($key);
                if ($val !== false && $val !== '') {
                    $env[$key] = $val;
                }
            }
        }

        return $env;
    }

    private function pidDir(): string
    {
        return root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'channel-worker';
    }

    private function pidFile(int $roomId): string
    {
        return $this->pidDir() . DIRECTORY_SEPARATOR . "room-{$roomId}.pid";
    }

    private function logFile(int $roomId): string
    {
        return $this->pidDir() . DIRECTORY_SEPARATOR . "room-{$roomId}.log";
    }

    private function workerDir(): string
    {
        // root_path() = d:\ever\douyin\douyin\php\
        // dirname(root_path()) = d:\ever\douyin\douyin
        return dirname(root_path()) . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'channel-worker';
    }

    private function workerScriptPath(): string
    {
        return $this->workerDir() . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'channel-worker.php';
    }

    private function isWindows(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
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
                        'current_mode' => $state === 'public_live' ? 'public' : 'public',
                        'version' => \think\facade\Db::raw('version + 1'),
                    ]);
            } else {
                \think\facade\Db::connect('live_mysql')
                    ->table('lp_room_state_snapshot')
                    ->insert([
                        'room_id' => $roomId,
                        'current_state' => $state,
                        'current_mode' => 'public',
                        'version' => 1,
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

            // 清理残留 PID 文件
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
}
