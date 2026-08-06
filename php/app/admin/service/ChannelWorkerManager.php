<?php

declare(strict_types=1);

namespace app\admin\service;

/**
 * 管理 channel-worker 进程的启动/停止/状态查询
 * - Windows: 通过 proc_open + PID 文件追踪进程
 * - Linux: 通过 supervisorctl 控制（生产环境用 Supervisor 管理进程）
 */
final class ChannelWorkerManager
{
    private const SUPERVISOR_PROGRAM = 'channel-worker-room';

    /**
     * 启动推流
     * @return array{ok: bool, message: string, pid?: int}
     */
    public function start(int $roomId): array
    {
        if ($this->isRunning($roomId)) {
            return ['ok' => false, 'message' => '该房间已在推流中'];
        }

        // Windows: 本地开发，用 proc_open 启动
        if ($this->isWindows()) {
            return $this->startWindows($roomId);
        }

        // Linux: 生产环境，用 supervisorctl
        return $this->startSupervisor($roomId);
    }

    /**
     * 停止推流
     * @return array{ok: bool, message: string}
     */
    public function stop(int $roomId): array
    {
        $wasRunning = $this->isRunning($roomId);

        // Windows: 本地开发，用 taskkill（无论 isRunning 结果如何都执行，防止孤儿 ffmpeg 残留）
        if ($this->isWindows()) {
            return $this->stopWindows($roomId, $wasRunning);
        }

        // Linux: 生产环境，用 supervisorctl
        if (!$wasRunning) {
            $this->updateRoomState($roomId, 'offline');
            return ['ok' => false, 'message' => '该房间未在推流'];
        }

        return $this->stopSupervisor($roomId);
    }

    /**
     * 检查推流状态
     * @return array{ok: bool, running: bool, pid: int|null, state: string}
     */
    public function status(int $roomId): array
    {
        $running = $this->isRunning($roomId);
        $pid = null;

        if (!$this->isWindows() && $running) {
            // Linux: 从 supervisorctl status 解析 PID
            $pid = $this->getSupervisorPid($roomId);
        } elseif ($running) {
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

    // ============= Supervisor (Linux 生产环境) =============

    private function supervisorProgram(int $roomId): string
    {
        return self::SUPERVISOR_PROGRAM . $roomId;
    }

    private function startSupervisor(int $roomId): array
    {
        $program = $this->supervisorProgram($roomId);

        // 启动前确保 supervisor 配置文件存在
        $confFile = "/etc/supervisor/conf.d/{$program}.conf";
        if (!file_exists($confFile)) {
            // 尝试查找所有可能的 supervisor 配置
            $altConfFile = "/etc/supervisor/conf.d/channel-worker.conf";
            if (!file_exists($altConfFile)) {
                return ['ok' => false, 'message' => "Supervisor 配置不存在: {$confFile}"];
            }
        }

        exec("sudo supervisorctl start {$program} 2>&1", $output, $code);
        $outputStr = implode("\n", $output);

        if ($code !== 0) {
            return ['ok' => false, 'message' => "supervisorctl 启动失败: {$outputStr}"];
        }

        // 等待进程启动
        $maxWait = 10;
        for ($i = 0; $i < $maxWait; $i++) {
            if ($this->isRunning($roomId)) {
                $this->updateRoomState($roomId, 'public_live');
                return ['ok' => true, 'message' => "推流已启动 ({$program})"];
            }
            usleep(500000); // 0.5 秒
        }

        $this->updateRoomState($roomId, 'public_live');
        return ['ok' => true, 'message' => "推流启动指令已发送 ({$program})"];
    }

    private function stopSupervisor(int $roomId): array
    {
        $program = $this->supervisorProgram($roomId);

        exec("sudo supervisorctl stop {$program} 2>&1", $output, $code);
        $outputStr = implode("\n", $output);

        // 防御性清理：即使 supervisorctl stop 成功，也可能有孤儿 ffmpeg 残留
        // （PHP 进程收到 SIGTERM 后未正确处理子进程清理，或 PHP 崩溃导致 ffmpeg 变成孤儿）
        $this->killOrphanFfmpegLinux($roomId);

        // supervisorctl 返回非 0 也可能是正常情况（进程本来就停了）
        $this->updateRoomState($roomId, 'offline');

        if ($code === 0) {
            return ['ok' => true, 'message' => "推流已停止 ({$program})"];
        }

        // 如果是因为进程已停止，也算成功
        if (stripos($outputStr, 'not running') !== false || stripos($outputStr, 'STOPPED') !== false) {
            return ['ok' => true, 'message' => "推流已停止 (进程本身已退出)"];
        }

        return ['ok' => false, 'message' => "supervisorctl 停止失败: {$outputStr}"];
    }

    private function getSupervisorPid(int $roomId): ?int
    {
        $program = $this->supervisorProgram($roomId);
        exec("sudo supervisorctl status {$program} 2>&1", $output, $code);

        foreach ($output as $line) {
            // 格式: channel-worker-room1    RUNNING   pid 12345, uptime 1:23:45
            if (preg_match('/\bpid\s+(\d+)/i', $line, $m)) {
                return (int) $m[1];
            }
        }
        return null;
    }

    // ============= Windows (本地开发) =============

    private function startWindows(int $roomId): array
    {
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

        $envVars = $this->getEnvForChild();

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['file', $logFile, 'a'],
            2 => ['file', $logFile, 'a'],
        ];

        $process = proc_open(
            sprintf('"%s" "%s" --room=%d', $this->phpBinary(), $scriptPath, $roomId),
            $descriptorSpec,
            $pipes,
            $workerDir,
            $envVars,
        );

        if (!is_resource($process)) {
            return ['ok' => false, 'message' => '无法启动推流进程'];
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        $status = proc_get_status($process);
        $pid = $status['pid'] ?? 0;

        unset($pipes, $process, $status);

        if ($pid > 0) {
            file_put_contents($pidFile, (string) $pid);
            $this->updateRoomState($roomId, 'public_live');
            return ['ok' => true, 'message' => "推流已启动 (PID: {$pid})", 'pid' => $pid];
        }

        return ['ok' => false, 'message' => '推流进程启动失败'];
    }

    private function stopWindows(int $roomId, bool $wasRunning): array
    {
        $pidFile = $this->pidFile($roomId);
        $pid = (int) @file_get_contents($pidFile);

        if ($pid > 0) {
            exec("taskkill /F /T /PID {$pid} 2>&1", $output, $code);
        }

        // 防御性清理：Windows 下 taskkill /T 无法 100% 保证子进程树被终止，
        // 通过命令行参数匹配，直接杀掉该房间的残留 ffmpeg 进程
        $this->killOrphanFfmpeg($roomId);

        @unlink($pidFile);
        $this->updateRoomState($roomId, 'offline');

        if ($pid <= 0) {
            return ['ok' => true, 'message' => '已清理推流记录（无效 PID）'];
        }

        if ($wasRunning) {
            return ['ok' => true, 'message' => "推流已停止 (PID: {$pid})"];
        }

        return ['ok' => true, 'message' => "已清理残留推流进程 (PID: {$pid})"];
    }

    /**
     * 防御性清理：通过 stream_alias 匹配并杀掉该房间的残留 ffmpeg 进程
     *
     * 场景：ChannelWorker PHP 进程已经崩溃，但 ffmpeg 变成孤儿进程继续运行。
     * taskkill /T 依赖进程树，无法覆盖孤儿场景，需要按命令行参数精确匹配杀。
     */
    private function killOrphanFfmpeg(int $roomId): void
    {
        try {
            // 从 stream_template 获取 stream_alias_prefix，构造该房间的流别名
            $row = \think\facade\Db::connect('live_mysql')
                ->table('lp_room_binding')
                ->alias('rb')
                ->leftJoin('lp_stream_template st', 'st.id = rb.stream_template_id')
                ->where('rb.room_id', $roomId)
                ->field('st.stream_alias_prefix')
                ->find();

            $prefix = $row['stream_alias_prefix'] ?? 'room';
            $streamAlias = $prefix . '/' . $roomId;

            // 第一步：tasklist 获取所有 ffmpeg.exe 的 PID（CSV 格式，稳定可靠）
            exec('tasklist /FI "IMAGENAME eq ffmpeg.exe" /FO CSV /NH 2>&1', $tasklistOut);

            $ffmpegPids = [];
            foreach ($tasklistOut as $line) {
                $parts = str_getcsv(trim($line));
                $pid = (int) ($parts[1] ?? 0);
                if ($pid > 0) {
                    $ffmpegPids[] = $pid;
                }
            }

            if (empty($ffmpegPids)) {
                return;
            }

            // 第二步：逐个查命令行，匹配 stream_alias 则杀掉
            foreach ($ffmpegPids as $ffPid) {
                exec("wmic process where ProcessId={$ffPid} get CommandLine /format:value 2>&1", $wmicOut);
                $cmdLine = implode(' ', $wmicOut);
                if (stripos($cmdLine, $streamAlias) !== false) {
                    exec("taskkill /F /PID {$ffPid} 2>&1");
                }
            }
        } catch (\Throwable $e) {
            // 清理失败不阻塞主流程
        }
    }

    /**
     * Linux 端防御性清理：通过 stream_alias 匹配并杀掉该房间的残留 ffmpeg 进程
     *
     * 场景与 killOrphanFfmpeg 相同，但使用 Linux 工具链（pgrep + /proc/{pid}/cmdline）
     */
    private function killOrphanFfmpegLinux(int $roomId): void
    {
        try {
            $row = \think\facade\Db::connect('live_mysql')
                ->table('lp_room_binding')
                ->alias('rb')
                ->leftJoin('lp_stream_template st', 'st.id = rb.stream_template_id')
                ->where('rb.room_id', $roomId)
                ->field('st.stream_alias_prefix')
                ->find();

            $prefix = $row['stream_alias_prefix'] ?? 'room';
            $streamAlias = $prefix . '/' . $roomId;

            // pgrep -a 列出所有 ffmpeg 进程及其命令行
            exec('pgrep -a ffmpeg 2>&1', $pgrepOut);

            foreach ($pgrepOut as $line) {
                // 格式: "12345 ffmpeg -hide_banner ... room/1 ..."
                $parts = preg_split('/\s+/', trim($line), 2);
                $pid = (int) ($parts[0] ?? 0);
                $cmd = $parts[1] ?? '';

                if ($pid > 0 && strpos($cmd, $streamAlias) !== false) {
                    exec("kill -9 {$pid} 2>&1");
                }
            }
        } catch (\Throwable $e) {
            // 清理失败不阻塞主流程
        }
    }

    private function isRunning(int $roomId): bool
    {
        // Windows: 检查 PID 文件和进程
        if ($this->isWindows()) {
            return $this->isRunningWindows($roomId);
        }

        // Linux: 检查 supervisorctl status
        return $this->isRunningSupervisor($roomId);
    }

    private function isRunningSupervisor(int $roomId): bool
    {
        $program = $this->supervisorProgram($roomId);
        exec("sudo supervisorctl status {$program} 2>&1", $output, $code);

        $outputStr = implode("\n", $output);

        // supervisorctl 返回非 0 可能是程序未配置
        if ($code !== 0) {
            // 检查是不是 "no such process" 之类的情况
            if (stripos($outputStr, 'no such') !== false || stripos($outputStr, 'not found') !== false) {
                // 配置文件不存在，回退到数据库状态
                return $this->getRoomState($roomId) === 'public_live';
            }
            return false;
        }

        // 解析状态输出: channel-worker-room1    RUNNING   pid 12345, ...
        return stripos($outputStr, 'RUNNING') !== false;
    }

    private function isRunningWindows(int $roomId): bool
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

        exec("tasklist /FI \"PID eq {$pid}\" 2>&1", $output);
        return count(array_filter($output, fn(string $line): bool => str_contains($line, (string) $pid))) > 0;
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

    private function phpBinary(): string
    {
        // 使用当前 ThinkPHP 进程的 PHP 路径
        if (defined('PHP_BINARY') && PHP_BINARY !== '') {
            return PHP_BINARY;
        }
        // 回退：从 PHP_BINDIR 推断
        if (defined('PHP_BINDIR') && PHP_BINDIR !== '') {
            $bindir = rtrim(PHP_BINDIR, '/\\');
            $exe = $this->isWindows() ? 'php.exe' : 'php';
            $candidate = $bindir . DIRECTORY_SEPARATOR . $exe;
            if (file_exists($candidate)) {
                return $candidate;
            }
        }
        return 'php';
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
