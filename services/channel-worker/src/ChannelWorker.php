<?php
declare(strict_types=1);

namespace ChannelWorker;

/**
 * HLS 推流 Worker — 逐视频滚动推流
 *
 * 架构：
 * - 每次 ffmpeg 只播放一个视频，播完后自动接力下一个
 * - 无礼物时循环播放默认播单
 * - 有礼物时：当前视频播完后，下一个视频切换为关键词视频
 * - index.m3u8 是 symlink，原子 rename 切换
 * - 不启用 delete_segments，由 PHP 定时清理旧分片
 */
final class ChannelWorker
{
    private const FIRST_SEG_TIMEOUT = 12;
    private const SEG_OFFSET        = 10000;
    private const CLEANUP_AGE       = 300;
    private const FFMPEG_WARMUP     = 3;

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

        $this->log("room={$roomId} persona={$persona} START (per-video mode)");

        try {
            $this->redisStream->connect();
            $this->log("Redis connected");
        } catch (\Throwable $e) {
            $this->log("Redis connect failed: {$e->getMessage()}");
        }

        $defaultVideos = $this->getDefaultVideos($persona, $roomId);
        if (empty($defaultVideos)) {
            $this->log("no default videos, retrying in 5s");
            sleep(5);
            $this->run($roomId);
            return;
        }

        $active = $this->playOneVideo($defaultVideos[0], $streamAlias, $outputDir, $activeLink, true);
        if ($active === null) { sleep(5); $this->run($roomId); return; }

        $defaultIdx = 0;
        $checkCount = 0;

        while (true) {
            $status = proc_get_status($active['process']);
            if (!($status['running'] ?? false)) {
                $this->log("ffmpeg PID={$active['pid']} exited, next video");
                $this->removeChild($active['pid']);
                @proc_close($active['process']);

                $defaultIdx = ($defaultIdx + 1) % count($defaultVideos);
                $active = $this->playOneVideo($defaultVideos[$defaultIdx], $streamAlias, $outputDir, $activeLink, false);
                if ($active === null) { sleep(2); continue; }
                continue;
            }

            $pendingKeyword = null;
            while ($cmd = $this->redisStream->consumeKeyword($roomId)) {
                $pendingKeyword = $cmd['keyword'] ?? null;
                $this->debugLog('kw_consume', json_encode($cmd, JSON_UNESCAPED_UNICODE));
            }

            $elapsed = time() - $active['started_at'];
            $remaining = $active['duration'] - $elapsed;

            if ($remaining <= self::FFMPEG_WARMUP) {
                $nextVideo = null;

                if ($pendingKeyword !== null) {
                    $kwVideo = $this->repository->randomVideoByKeyword($persona, $pendingKeyword);
                    if ($kwVideo !== null && file_exists($kwVideo['file_url'])) {
                        $nextVideo = $kwVideo;
                        $this->log("gift '{$pendingKeyword}' → keyword video: {$kwVideo['title']}");
                    } else {
                        $this->log("gift '{$pendingKeyword}' → no keyword video found, using default");
                    }
                }

                if ($nextVideo === null) {
                    $defaultIdx = ($defaultIdx + 1) % count($defaultVideos);
                    $nextVideo = $defaultVideos[$defaultIdx];
                }

                $active = $this->transitionVideo($active, $nextVideo, $streamAlias, $outputDir, $activeLink);
                if ($active === null) { sleep(2); continue; }
            }

            usleep(500_000);
            if (function_exists('pcntl_signal_dispatch')) pcntl_signal_dispatch();
            if ($this->terminating) break;

            $checkCount++;
            if ($checkCount % 120 === 0) {
                $this->cleanupOldSegments($outputDir);
            }
            if ($checkCount % 60 === 0) {
                $this->log("hb pid={$active['pid']} elapsed={$elapsed}s remain={$remaining}s cnt={$checkCount}");
            }
        }
    }

    private function playOneVideo(
        array $video, string $streamAlias, string $outputDir, string $activeLink, bool $cleanDir
    ): ?array {
        if (!file_exists($video['file_url'])) {
            $this->log("video not found: {$video['file_url']}");
            return null;
        }

        $duration = $this->probeDuration($video);
        $m3u8Name = $this->nextM3u8Name();
        $startNumber = $cleanDir ? 0 : $this->findNextSegNumber($outputDir);
        if ($cleanDir) $this->cleanDir($outputDir);
        @mkdir($outputDir, 0775, true);

        $playlistFile = $this->playlistFilePath($streamAlias);
        file_put_contents($playlistFile, FfmpegCommandBuilder::concatLine($video['file_url']));

        $command = $this->builder->buildHlsToDir($outputDir, $playlistFile, false, $startNumber, $m3u8Name);
        $this->log("[play] {$video['title']} dur={$duration}s #={$startNumber} m3u8={$m3u8Name}");

        $process = @proc_open($command, [1 => STDOUT, 2 => STDERR], $pipes, dirname(__DIR__, 2));
        if (!is_resource($process)) { $this->log("proc_open failed"); return null; }

        $pid = proc_get_status($process)['pid'] ?? 0;
        $this->children[$pid] = $process;

        if (!$this->waitFirstSegment($outputDir, $startNumber)) {
            $this->log("first segment timeout for PID={$pid}");
            $this->killChild($pid, $process);
            return null;
        }

        $this->switchSymlink($activeLink, $m3u8Name . '.m3u8');
        $this->log("index.m3u8 → {$m3u8Name}.m3u8  PID={$pid}");

        return [
            'process' => $process, 'pid' => $pid, 'video' => $video,
            'duration' => $duration, 'started_at' => time(), 'm3u8Name' => $m3u8Name,
        ];
    }

    private function transitionVideo(
        array $oldActive, array $nextVideo, string $streamAlias, string $outputDir, string $activeLink
    ): ?array {
        if (!file_exists($nextVideo['file_url'])) {
            $this->log("next video not found, keeping current");
            return $oldActive;
        }

        $duration = $this->probeDuration($nextVideo);
        $m3u8Name = $this->nextM3u8Name();
        $offsetNumber = $this->findNextSegNumber($outputDir) + self::SEG_OFFSET;

        $playlistFile = $this->playlistFilePath($streamAlias);
        file_put_contents($playlistFile, FfmpegCommandBuilder::concatLine($nextVideo['file_url']));

        $command = $this->builder->buildHlsToDir($outputDir, $playlistFile, false, $offsetNumber, $m3u8Name);
        $this->log("[transition] {$nextVideo['title']} dur={$duration}s #={$offsetNumber} m3u8={$m3u8Name}");

        $process = @proc_open($command, [1 => STDOUT, 2 => STDERR], $pipes, dirname(__DIR__, 2));
        if (!is_resource($process)) { return $oldActive; }

        $pid = proc_get_status($process)['pid'] ?? 0;
        $this->children[$pid] = $process;

        if (!$this->waitFirstSegment($outputDir, $offsetNumber)) {
            $this->log("transition timeout, killing new ffmpeg");
            $this->killChild($pid, $process);
            return $oldActive;
        }

        $this->switchSymlink($activeLink, $m3u8Name . '.m3u8');
        $this->log("index.m3u8 → {$m3u8Name}.m3u8  PID={$pid}");
        $this->killChild($oldActive['pid'], $oldActive['process']);

        return [
            'process' => $process, 'pid' => $pid, 'video' => $nextVideo,
            'duration' => $duration, 'started_at' => time(), 'm3u8Name' => $m3u8Name,
        ];
    }

    // ─── 工具方法 ──────────────────────────────────────────────────

    private function getDefaultVideos(string $persona, int $roomId): array
    {
        $videos = $this->repository->roomPlaylistVideos($roomId);
        if (empty($videos)) {
            while (count($videos) < 3) {
                $v = $this->repository->randomVideo($persona);
                if ($v) $videos[] = $v;
            }
        }
        return $videos;
    }

    private function probeDuration(array $video): float
    {
        $dur = $this->repository->probeDuration($video['file_url']);
        if ($dur > 0) return $dur;
        $dur = ($video['duration_ms'] ?? 0) / 1000.0;
        return $dur > 0 ? $dur : 8.0;
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
                if ((int)$m[1] < $keepFrom) { @unlink($f); $deleted++; }
            }
        }
        $m3u8s = glob($dir . '/live_*.m3u8') ?: [];
        sort($m3u8s);
        while (count($m3u8s) > 5) { @unlink(array_shift($m3u8s)); }
        if ($deleted > 0) $this->log("cleanup: deleted {$deleted} segments");
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
