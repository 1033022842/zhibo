<?php
declare(strict_types=1);

namespace ChannelWorker;

/**
 * HLS 推流 Worker — 单目录直写，无缝切换
 * 
 * 架构：
 * - 单一输出目录，ffmpeg 直接写入（Nginx alias 直出，无 symlink）
 * - 关键词触发：杀旧 ffmpeg → 同目录重启关键词播单（discont_start + omit_endlist）
 * - 关键词播完：ffmpeg 退出 → 主循环检测 → 同目录重启默认播单（discont_start）
 * - 接续分片编号（findNextSegNumber）避免播放器 404
 */
final class ChannelWorker
{
    private const PLAYLIST_SIZE = 5;

    /** @var array<int, resource> 追踪所有 ffmpeg 子进程，供信号处理清理 */
    private array $children = [];

    /** SIGTERM 是否已触发 */
    private bool $terminating = false;

    public function __construct(
        private readonly PlaylistRepository $repository,
        private readonly FfmpegCommandBuilder $builder,
        private readonly RedisStream $redisStream,
        private readonly array $config
    ) {
        $this->registerSignalHandlers();
    }

    /**
     * 注册 SIGTERM / SIGINT 信号处理，确保 supervisor stop 时能清理 ffmpeg 子进程。
     * 
     * 机制：
     * - pcntl_signal 注册异步信号（需配合 pcntl_signal_dispatch 轮询）
     * - register_shutdown_function 作为兜底（防止信号在 dispatch 间隙丢失）
     */
    private function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        $handler = function (int $sig): void {
            $name = $sig === SIGTERM ? 'SIGTERM' : 'SIGINT';
            $this->log("收到 {$name}，开始清理 ffmpeg 子进程...");
            $this->terminating = true;
            $this->killAllChildren();
            exit(0);
        };

        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);

        // 兜底：即使信号处理未触发（如 PHP 直接 exit），shutdown 函数也会执行清理
        register_shutdown_function(function (): void {
            if ($this->terminating) {
                return; // 已在信号处理中清理
            }
            $this->terminating = true;
            $this->killAllChildren();
        });
    }

    /**
     * 杀死所有已追踪的 ffmpeg 子进程
     */
    private function killAllChildren(): void
    {
        foreach ($this->children as $pid => $process) {
            @proc_terminate($process, 9);
            usleep(50_000);
            @proc_close($process);
            $this->log("已清理 ffmpeg PID={$pid}");
        }
        $this->children = [];
    }

    /**
     * 杀死并移除单个子进程
     */
    private function killChild(int $pid, $process): void
    {
        @proc_terminate($process, 9);
        usleep(200_000);
        @proc_close($process);
        unset($this->children[$pid]);
    }

    /**
     * 仅移除追踪（进程已自行退出）
     */
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
        $outputDir = $hlsBase . '/' . $streamAlias;

        // 清除旧版留下的 symlink，改为真实目录
        if (is_link($outputDir)) {
            @unlink($outputDir);
        }
        @mkdir($outputDir, 0775, true);

        $this->log("room={$roomId} persona={$persona} START single-dir mode");

        try {
            $this->redisStream->connect();
            $this->log("Redis connected");
            $this->debugLog('init', 'Redis connected OK');
        } catch (\Throwable $e) {
            $this->log("Redis connect failed: {$e->getMessage()}");
            $this->debugLog('init', 'Redis connect FAILED: ' . $e->getMessage());
        }

        // 首次启动：清理目录，从头开始默认播单
        $active = $this->startFfmpeg($persona, $streamAlias, $outputDir, null, $roomId, true);
        if ($active === null) {
            $this->log("FATAL: initial ffmpeg launch failed, retrying in 5s");
            sleep(5);
            $this->run($roomId);
            return; // never reached
        }
        $this->log("default playlist PID={$active['pid']}");

        $checkCount = 0;

        while (true) {
            // 检查当前 ffmpeg 是否还在跑
            $status = proc_get_status($active['process']);
            if (!($status['running'] ?? false)) {
                $this->log("ffmpeg PID={$active['pid']} died, restarting default playlist");
                $this->removeChild($active['pid']);
                @proc_close($active['process']);
                // 同目录重启，保留分片接续编号
                $active = $this->startFfmpeg($persona, $streamAlias, $outputDir, null, $roomId, false);
                if ($active === null) {
                    sleep(5);
                    continue;
                }
                continue;
            }

            // 消费 Redis 关键词指令（取最后一个有效的）
            $keywordCmd = null;
            while ($cmd = $this->redisStream->consumeKeyword($roomId)) {
                $keywordCmd = $cmd;
                $this->debugLog('kw_consume', json_encode($cmd, JSON_UNESCAPED_UNICODE));
            }

            if ($keywordCmd !== null) {
                $this->debugLog('kw_switch', "keyword={$keywordCmd['keyword']} dir={$outputDir}");
                $this->log("gift '{$keywordCmd['keyword']}' → killing old ffmpeg, starting keyword");

                // 杀旧 ffmpeg，同目录重启关键词播单（保留分片，接续编号）
                $this->killChild($active['pid'], $active['process']);
                $this->debugLog('kw_killed', "killed PID={$active['pid']}");

                $active = $this->startFfmpeg($persona, $streamAlias, $outputDir, $keywordCmd, $roomId, false);
                if ($active === null) {
                    $this->log("keyword ffmpeg failed, falling back to default");
                    $active = $this->startFfmpeg($persona, $streamAlias, $outputDir, null, $roomId, false);
                    if ($active === null) {
                        sleep(5);
                        continue;
                    }
                }
            }

            usleep(500_000);

            // 分发异步信号（pcntl 需要手动轮询）
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            if ($this->terminating) {
                break;
            }

            $checkCount++;
            if ($checkCount % 60 === 0) {
                $this->log("hb pid={$active['pid']} count={$checkCount}");
            }
        }
    }

    /**
     * 启动 ffmpeg
     * 
     * @param bool $cleanDir 是否清理目录重新开始（仅首次启动=true，后续重启=false）
     * @return array{process:resource, pid:int, videos:array, started_at:int}|null
     */
    private function startFfmpeg(
        string $persona,
        string $streamAlias,
        string $outputDir,
        ?array $keywordCmd,
        int $roomId = 0,
        bool $cleanDir = true
    ): ?array {
        $startNumber = 0;
        if ($cleanDir) {
            $this->cleanDir($outputDir);
        } else {
            $startNumber = $this->findNextSegNumber($outputDir);
        }
        @mkdir($outputDir, 0775, true);

        // 构建视频列表
        $videos = [];
        if ($keywordCmd !== null) {
            // 关键词触发的播单：仅一个匹配视频，播完 ffmpeg 退出 → 主循环重启默认播单
            $kwVideo = $this->repository->randomVideoByKeyword($persona, $keywordCmd['keyword']);
            if ($kwVideo === null) {
                $this->debugLog('kw_video', "NOT FOUND keyword={$keywordCmd['keyword']} persona={$persona}");
                $this->log("  keyword video: NOT FOUND (null)");
            } elseif (!file_exists($kwVideo['file_url'])) {
                $this->debugLog('kw_video', "FILE MISSING: {$kwVideo['file_url']}");
                $this->log("  keyword video file MISSING: {$kwVideo['file_url']}");
            } else {
                $videos[] = $kwVideo;
                $this->debugLog('kw_video', "OK id={$kwVideo['id']} title={$kwVideo['title']}");
                $this->log("  keyword video: {$kwVideo['title']}");
            }
        }
        if ($keywordCmd === null || empty($videos)) {
            // 默认播单：优先走房间专属播单模板，否则随机
            if ($roomId > 0) {
                $videos = $this->repository->roomPlaylistVideos($roomId);
                if (!empty($videos)) {
                    $this->debugLog('kw_video', "roomPlaylist roomId={$roomId} count=" . count($videos));
                    $this->log("  room playlist: " . count($videos) . " videos");
                }
            }
            if (empty($videos)) {
                while (count($videos) < self::PLAYLIST_SIZE) {
                    $v = $this->repository->randomVideo($persona);
                    if ($v) $videos[] = $v;
                }
            }
        }

        // playlist 文件
        $playlistFile = $this->playlistFilePath($streamAlias);
        $content = '';
        foreach ($videos as $v) {
            $content .= FfmpegCommandBuilder::concatLine($v['file_url']);
        }
        file_put_contents($playlistFile, $content);

        // 启动 ffmpeg（默认播单循环，关键词播单不循环 + omit_endlist）
        $loop = ($keywordCmd === null);
        $command = $this->builder->buildHlsToDir($outputDir, $playlistFile, $loop, $startNumber);
        $titles = implode(' → ', array_map(fn($v) => mb_substr($v['title'], 0, 12), $videos));
        $typeStr = $keywordCmd ? 'keyword' : 'default';
        $this->log("[{$typeStr}] {$titles}" . ($startNumber > 0 ? " start#={$startNumber}" : ""));

        // 桥接：ffmpeg -y 会立即清空旧 index.m3u8，但需要 ~2s 才写入第一个分片。
        // 保存旧 m3u8 内容，proc_open 后立即恢复，避免播放器在这 2s 内 404 卡死。
        $m3u8Path = $outputDir . '/index.m3u8';
        $bridgeFile = '';
        if ($startNumber > 0 && file_exists($m3u8Path)) {
            $bridgeFile = $outputDir . '/index.m3u8.bridge';
            @copy($m3u8Path, $bridgeFile);
        }

        $process = @proc_open($command, [1 => STDOUT, 2 => STDERR], $pipes, dirname(__DIR__, 2));
        if (!is_resource($process)) {
            @unlink($bridgeFile);
            $this->log("proc_open failed");
            return null;
        }

        // 立即恢复桥接文件，填补 ffmpeg 写首分片前的空窗
        if ($bridgeFile !== '' && file_exists($bridgeFile)) {
            @copy($bridgeFile, $m3u8Path);
            @unlink($bridgeFile);
        }

        $pid = proc_get_status($process)['pid'] ?? 0;

        // 追踪子进程，供信号处理时清理
        $this->children[$pid] = $process;

        return [
            'process'    => $process,
            'pid'        => $pid,
            'videos'     => $videos,
            'started_at' => time(),
        ];
    }

    /**
     * 清理目录中的 HLS 文件
     */
    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        array_map('unlink', glob($dir . '/seg_*.ts') ?: []);
        @unlink($dir . '/index.m3u8');
    }

    /**
     * 扫描目录中已有分片的最大编号 + 1，用于同槽重启时接续编号。
     * 如果目录为空则返回 0。
     */
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
