<?php
declare(strict_types=1);

namespace ChannelWorker;

/**
 * HLS 推流 Worker — 单播单架构 + 视频边界对齐
 *
 * 架构：
 * - 每次启动一个 ffmpeg，播单 = [关键词视频?] + 默认视频×N
 * - 关键词视频播完后自然过渡到默认内容，不需要"切回"
 * - index.m3u8 是 symlink，原子 rename 切换到当前活跃 m3u8（live_NNN.m3u8）
 * - 送礼触发时：先预热 keyword ffmpeg，等当前视频播完再切换 symlink
 * - 不启用 delete_segments，由 PHP 定时清理旧分片
 */
final class ChannelWorker
{
    private const PLAYLIST_SIZE     = 5;
    private const FIRST_SEG_TIMEOUT = 10;
    private const SEG_OFFSET        = 10000;
    private const CLEANUP_AGE       = 300;
    private const FFMPEG_WARMUP     = 3;      // ffmpeg 启动+首分片预估耗时（秒）

    /** @var array<int, resource> */
    private array $children = [];
    private bool $terminating = false;
    private int $m3u8Seq = 0;

    public function __construct(
        private readonly PlaylistRepository $repository,
        private readonly FfmpegCommandBuilder $builder,
        private readonly RedisStream $redisStream,
        private readonly array $config
    ) {
        $this->registerSignalHandlers();
    }

    private function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) return;

        $handler = function (int $sig): void {
            $this->terminating = true;
            $this->killAllChildren();
            exit(0);
        };
        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);
        register_shutdown_function(function (): void {
            if ($this->terminating) return;
            $this->terminating = true;
            $this->killAllChildren();
        });
    }

    private function killAllChildren(): void
    {
        foreach ($this->children as $pid => $process) {
            @proc_terminate($process, 9);
            usleep(50_000);
            @proc_close($process);
        }
        $this->children = [];
    }

    private function killChild(int $pid, $process): void
    {
        @proc_terminate($process, 9);
        usleep(200_000);
        @proc_close($process);
        unset($this->children[$pid]);
    }

    private function removeChild(int $pid): void
    {
        unset($this->children[$pid]);
    }

    public function run(int $roomId): void
    {
        $info = $this->repository->roomStreamInfo($roomId);
        $persona = $info['persona'];
        $streamAlias = $info['stream_alias'];

        $hlsBase = rtrim($this->config['hls']['output_dir'] ?? '/www/wwwroot/douyin/hls', '/');
        $outputDir  = $hlsBase . '/' . $streamAlias;
        $activeLink = $outputDir . '/index.m3u8';

        if (is_link($outputDir)) @unlink($outputDir);
        if (!is_dir($outputDir)) @mkdir($outputDir, 0775, true);

        $this->log("room={$roomId} persona={$persona} START");

        try {
            $this->redisStream->connect();
            $this->log("Redis connected");
        } catch (\Throwable $e) {
            $this->log("Redis connect failed: {$e->getMessage()}");
        }

        // 启动初始默认播单（循环）
        $active = $this->launchDefault($persona, $streamAlias, $outputDir, $roomId, true);
        if ($active === null) { sleep(5); $this->run($roomId); return; }

        // 等待首分片并切换 symlink
        if (!$this->waitFirstSegment($outputDir, $active['startNumber'])) {
            sleep(5); $this->run($roomId); return;
        }
        $this->switchSymlink($activeLink, $active['m3u8Name'] . '.m3u8');
        $this->log("index.m3u8 → {$active['m3u8Name']}.m3u8  PID={$active['pid']}");

        $checkCount = 0;

        while (true) {
            // ffmpeg 死亡 → 重启默认播单
            $status = proc_get_status($active['process']);
            if (!($status['running'] ?? false)) {
                $this->log("ffmpeg PID={$active['pid']} died, restarting default");
                $this->removeChild($active['pid']);
                @proc_close($active['process']);
                $active = $this->launchDefault($persona, $streamAlias, $outputDir, $roomId, false);
                if ($active !== null && $this->waitFirstSegment($outputDir, $active['startNumber'])) {
                    $this->switchSymlink($activeLink, $active['m3u8Name'] . '.m3u8');
                }
                continue;
            }

            // 消费关键词（只取最后一条）
            $keywordCmd = null;
            while ($cmd = $this->redisStream->consumeKeyword($roomId)) {
                $keywordCmd = $cmd;
                $this->debugLog('kw_consume', json_encode($cmd, JSON_UNESCAPED_UNICODE));
            }

            if ($keywordCmd !== null) {
                $active = $this->handleKeyword(
                    $active, $keywordCmd, $persona, $streamAlias,
                    $outputDir, $roomId, $activeLink
                );
            }

            usleep(500_000);
            if (function_exists('pcntl_signal_dispatch')) pcntl_signal_dispatch();
            if ($this->terminating) break;

            $checkCount++;
            if ($checkCount % 120 === 0) {
                $this->cleanupOldSegments($outputDir);
            }
            if ($checkCount % 60 === 0) {
                $this->log("hb pid={$active['pid']} cnt={$checkCount}");
            }
        }
    }

    /**
     * 处理关键词切换：预热 → 等边界 → 原子切换
     */
    private function handleKeyword(
        array $active,
        array $keywordCmd,
        string $persona,
        string $streamAlias,
        string $outputDir,
        int $roomId,
        string $activeLink
    ): array {
        $this->log("gift '{$keywordCmd['keyword']}' → preparing switch");

        // 1. 获取关键词视频
        $kwVideo = $this->repository->randomVideoByKeyword($persona, $keywordCmd['keyword']);
        if ($kwVideo === null || !file_exists($kwVideo['file_url'])) {
            $this->log("keyword video not found, skipping");
            return $active;
        }

        // 2. 获取默认播单视频
        $defaultVideos = $this->getDefaultVideos($persona, $roomId);
        if (empty($defaultVideos)) {
            $this->log("no default videos, skipping");
            return $active;
        }

        // 3. 构建新播单：[关键词视频] + 默认×N
        $videos = array_merge([$kwVideo], $defaultVideos);

        // 4. 用 ffprobe 获取时长，计算当前视频边界
        $segDurations = $this->probePlaylistDurations($active['videos']);
        if (empty($segDurations)) {
            $this->log("no durations, switching immediately");
            return $this->doSwitch($active, $videos, false, $streamAlias, $outputDir, $activeLink);
        }

        $totalDur = array_sum($segDurations);
        $elapsed = time() - $active['started_at'];
        $posInLoop = $elapsed % (int)max(1, $totalDur);

        // 找当前在第几个视频
        $accum = 0;
        $currentIdx = 0;
        foreach ($segDurations as $i => $dur) {
            $accum += $dur;
            if ($posInLoop < $accum) {
                $currentIdx = $i;
                break;
            }
        }

        // 当前视频结束时间 = 下个视频边界
        $boundaryTime = $accum;
        $secsToBoundary = $boundaryTime - $posInLoop;

        $this->log("current video #{$currentIdx}, {$secsToBoundary}s to boundary (total={$totalDur}s, elapsed={$elapsed}s)");

        // 5. 决定启动时机：等到 boundary - FFMPEG_WARMUP 启动新 ffmpeg
        //    这样新 ffmpeg 的首分片刚好在视频边界时就绪
        //    视频都是 ~8s，最长等待也就 ~8s，不需要设上限
        $waitTime = max(0, $secsToBoundary - self::FFMPEG_WARMUP);
        if ($secsToBoundary <= self::FFMPEG_WARMUP) {
            // 剩余时间不够 warmup，立即启动（可能跨越边界 1-2 秒，可接受）
            $waitTime = 0;
        }

        if ($waitTime > 0) {
            $this->log("waiting {$waitTime}s for video boundary...");
            $this->sleepInterruptible($waitTime, $active['process']);
        }

        return $this->doSwitch($active, $videos, false, $streamAlias, $outputDir, $activeLink);
    }

    /**
     * 执行切换：启动新 ffmpeg → 等首分片 → 原子切换 symlink → 杀旧
     */
    private function doSwitch(
        array $oldActive,
        array $videos,
        bool $loop,
        string $streamAlias,
        string $outputDir,
        string $activeLink
    ): array {
        $m3u8Name = $this->nextM3u8Name();
        $offsetNumber = $this->findNextSegNumber($outputDir) + self::SEG_OFFSET;

        $standby = $this->startFfmpegInstance($outputDir, $streamAlias, $videos, $loop, $offsetNumber, $m3u8Name);
        if ($standby === null) {
            $this->log("new ffmpeg failed, keeping current");
            return $oldActive;
        }

        if (!$this->waitFirstSegment($outputDir, $offsetNumber)) {
            $this->log("first segment timeout, killing new ffmpeg");
            $this->killChild($standby['pid'], $standby['process']);
            return $oldActive;
        }

        // 原子切换
        $this->switchSymlink($activeLink, $m3u8Name . '.m3u8');
        $this->log("index.m3u8 → {$m3u8Name}.m3u8  PID={$standby['pid']}");
        $this->killChild($oldActive['pid'], $oldActive['process']);

        return $standby;
    }

    // ─── ffmpeg 实例管理 ──────────────────────────────────────────

    /**
     * 启动默认播单 ffmpeg
     */
    private function launchDefault(
        string $persona, string $streamAlias, string $outputDir, int $roomId, bool $cleanDir
    ): ?array {
        $videos = $this->getDefaultVideos($persona, $roomId);
        if (empty($videos)) {
            while (count($videos) < self::PLAYLIST_SIZE) {
                $v = $this->repository->randomVideo($persona);
                if ($v) $videos[] = $v;
            }
        }
        $m3u8Name = $this->nextM3u8Name();
        $startNumber = $cleanDir ? 0 : $this->findNextSegNumber($outputDir);
        return $this->startFfmpegInstance($outputDir, $streamAlias, $videos, true, $startNumber, $m3u8Name, $cleanDir);
    }

    /**
     * 启动一个 ffmpeg 实例
     */
    private function startFfmpegInstance(
        string $outputDir,
        string $streamAlias,
        array $videos,
        bool $loop,
        int $startNumber,
        string $m3u8Name,
        bool $cleanDir = false
    ): ?array {
        if ($cleanDir) $this->cleanDir($outputDir);
        @mkdir($outputDir, 0775, true);

        $playlistFile = $this->playlistFilePath($streamAlias);
        $content = '';
        foreach ($videos as $v) {
            $content .= FfmpegCommandBuilder::concatLine($v['file_url']);
        }
        file_put_contents($playlistFile, $content);

        $command = $this->builder->buildHlsToDir($outputDir, $playlistFile, $loop, $startNumber, $m3u8Name);
        $titles = implode(' → ', array_map(fn($v) => mb_substr($v['title'], 0, 12), $videos));
        $this->log("[launch] {$titles} #={$startNumber} loop=" . ($loop ? 1 : 0) . " m3u8={$m3u8Name}");

        $process = @proc_open($command, [1 => STDOUT, 2 => STDERR], $pipes, dirname(__DIR__, 2));
        if (!is_resource($process)) { $this->log("proc_open failed"); return null; }

        $pid = proc_get_status($process)['pid'] ?? 0;
        $this->children[$pid] = $process;

        return [
            'process'      => $process,
            'pid'          => $pid,
            'videos'       => $videos,
            'started_at'   => time(),
            'm3u8Name'     => $m3u8Name,
            'startNumber'  => $startNumber,
        ];
    }

    // ─── 工具方法 ──────────────────────────────────────────────────

    private function getDefaultVideos(string $persona, int $roomId): array
    {
        $videos = $this->repository->roomPlaylistVideos($roomId);
        if (empty($videos)) {
            while (count($videos) < self::PLAYLIST_SIZE) {
                $v = $this->repository->randomVideo($persona);
                if ($v) $videos[] = $v;
            }
        }
        return $videos;
    }

    /**
     * 批量探测播单视频时长（秒）
     */
    private function probePlaylistDurations(array $videos): array
    {
        $durations = [];
        foreach ($videos as $v) {
            $dur = $this->repository->probeDuration($v['file_url']);
            if ($dur <= 0) {
                // 回退到 duration_ms
                $dur = ($v['duration_ms'] ?? 0) / 1000.0;
            }
            if ($dur <= 0) return []; // 探测失败，放弃边界计算
            $durations[] = $dur;
        }
        return $durations;
    }

    private function nextM3u8Name(): string
    {
        return 'live_' . str_pad((string)(++$this->m3u8Seq), 3, '0', STR_PAD_LEFT);
    }

    private function switchSymlink(string $linkPath, string $target): void
    {
        $tmpLink = $linkPath . '.swap_' . getmypid();
        @unlink($tmpLink);
        @symlink($target, $tmpLink);
        @rename($tmpLink, $linkPath);
        @unlink($tmpLink);
    }

    private function waitFirstSegment(string $dir, int $minNumber = 0): bool
    {
        $start = time();
        while (time() - $start < self::FIRST_SEG_TIMEOUT) {
            foreach (glob($dir . '/seg_*.ts') ?: [] as $f) {
                if (preg_match('/seg_(\d+)\.ts$/', $f, $m) && (int)$m[1] >= $minNumber) {
                    usleep(300_000);
                    return true;
                }
            }
            usleep(300_000);
        }
        return false;
    }

    /**
     * 可中断的 sleep：每秒检查 ffmpeg 是否还活着
     */
    private function sleepInterruptible(int $seconds, $process): void
    {
        $end = time() + $seconds;
        while (time() < $end) {
            $status = proc_get_status($process);
            if (!($status['running'] ?? false)) return;
            if (function_exists('pcntl_signal_dispatch')) pcntl_signal_dispatch();
            if ($this->terminating) return;
            sleep(1);
        }
    }

    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        array_map('unlink', glob($dir . '/seg_*.ts') ?: []);
        array_map('unlink', glob($dir . '/*.m3u8') ?: []);
        @unlink($dir . '/index.m3u8');
    }

    private function findNextSegNumber(string $dir): int
    {
        $max = -1;
        foreach (glob($dir . '/seg_*.ts') ?: [] as $f) {
            if (preg_match('/seg_(\d+)\.ts$/', $f, $m)) {
                $n = (int) $m[1];
                if ($n > $max) $max = $n;
            }
        }
        return $max + 1;
    }

    private function cleanupOldSegments(string $dir): void
    {
        $max = $this->findNextSegNumber($dir) - 1;
        $keepFrom = $max - self::CLEANUP_AGE;
        if ($keepFrom < 0) return;

        $deleted = 0;
        foreach (glob($dir . '/seg_*.ts') ?: [] as $f) {
            if (preg_match('/seg_(\d+)\.ts$/', $f, $m)) {
                if ((int)$m[1] < $keepFrom) {
                    @unlink($f);
                    $deleted++;
                }
            }
        }
        // 同时清理旧 m3u8 文件（保留最近 5 个）
        $m3u8s = glob($dir . '/live_*.m3u8') ?: [];
        sort($m3u8s);
        while (count($m3u8s) > 5) {
            @unlink(array_shift($m3u8s));
        }
        if ($deleted > 0) {
            $this->log("cleanup: deleted {$deleted} segments");
        }
    }

    private function playlistFilePath(string $streamAlias): string
    {
        $id = explode('/', $streamAlias)[1] ?? '0';
        return rtrim($this->config['runtime_dir'] ?? sys_get_temp_dir(), '/\\')
            . DIRECTORY_SEPARATOR . 'stream_' . $id . '.txt';
    }

    private function log(string $msg): void
    {
        @fwrite(STDERR, '[' . date('H:i:s') . '] ' . $msg . PHP_EOL);
    }

    private function debugLog(string $tag, string $msg): void
    {
        $line = date('Y-m-d H:i:s') . " [$tag] $msg" . PHP_EOL;
        $dir = dirname(__DIR__) . '/runtime';
        @file_put_contents($dir . '/debug.log', $line, FILE_APPEND);
    }
}
