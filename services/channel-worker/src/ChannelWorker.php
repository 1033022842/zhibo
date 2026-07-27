<?php
declare(strict_types=1);

namespace ChannelWorker;

/**
 * 关键词驱动推流 Worker
 *
 * 架构变更：
 * - 静态播单 → stdin 管道动态控制
 * - 固定顺序 → 按 persona 随机取视频
 * - 新增：Redis Stream 消费关键词插播指令
 */
final class ChannelWorker
{
    public function __construct(
        private readonly PlaylistRepository $repository,
        private readonly FfmpegCommandBuilder $commandBuilder,
        private readonly RedisStream $redisStream,
        private readonly array $config
    ) {
    }

    public function run(int $roomId): never
    {
        // 获取房间信息
        $info = $this->repository->roomStreamInfo($roomId);
        $persona = $info['persona'];
        $streamAlias = $info['stream_alias'];

        // 连接 Redis（非致命：Redis 不可用时也能正常轮播）
        try {
            $this->redisStream->connect();
        } catch (\Throwable $e) {
            fwrite(STDERR, "[channel-worker] Redis 连接失败，将不处理礼物触发: {$e->getMessage()}\n");
        }

        // 构建 ffmpeg 命令
        $build = $this->commandBuilder->build($roomId, $streamAlias);

        fwrite(STDOUT, '[channel-worker] room=' . $roomId . PHP_EOL);
        fwrite(STDOUT, '[channel-worker] persona=' . $persona . PHP_EOL);
        fwrite(STDOUT, '[channel-worker] alias=' . $streamAlias . PHP_EOL);
        fwrite(STDOUT, '[channel-worker] manifest=' . $build['manifest'] . PHP_EOL);
        fwrite(STDOUT, '[channel-worker] mode=stdin-pipe' . PHP_EOL);

        while (true) {
            $this->runFfmpegLoop($roomId, $persona, $build['command']);
            fwrite(STDERR, '[channel-worker] ffmpeg 已退出，' . $this->config['restart_delay_sec'] . 's 后重试' . PHP_EOL);
            sleep((int) $this->config['restart_delay_sec']);
        }
    }

    private function runFfmpegLoop(int $roomId, string $persona, string $commandLine): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin — 我们写 concat 指令
            1 => STDOUT,
            2 => STDERR,
        ];

        $process = proc_open($commandLine, $descriptors, $pipes, dirname(__DIR__, 2));
        if (!is_resource($process)) {
            throw new \RuntimeException('无法启动 ffmpeg 进程');
        }

        $stdin = $pipes[0];

        // 先写入第一个视频，让 ffmpeg 开始工作
        $firstVideo = $this->pickVideo($roomId, $persona);
        if ($firstVideo) {
            fwrite($stdin, FfmpegCommandBuilder::concatLine($firstVideo['file_url']));
            fwrite(STDOUT, "[channel-worker] START: {$firstVideo['title']} ({$firstVideo['duration_ms']}ms)\n");
        }

        // 主循环：等待 → 选择 → 写入
        while (true) {
            // 检查 ffmpeg 是否还活着
            $status = proc_get_status($process);
            if (!$status['running']) {
                fwrite(STDERR, "[channel-worker] ffmpeg 进程已退出\n");
                break;
            }

            // 决定下一个视频的来源
            $nextVideo = $this->pickNextVideo($roomId, $persona);

            if (!$nextVideo) {
                fwrite(STDERR, "[channel-worker] 无可用视频，1s 后重试\n");
                sleep(1);
                continue;
            }

            // 计算等待时间：当前视频的时长 - 200ms 缓冲
            $waitMs = max(200, $nextVideo['duration_ms'] - 200);
            $waitSec = $waitMs / 1000;

            // 写入下一行（ffmpeg 会在当前视频播完后才开始读下一行）
            usleep((int)($waitMs * 1000));

            // 再次确认进程存活
            $status = proc_get_status($process);
            if (!$status['running']) break;

            fwrite($stdin, FfmpegCommandBuilder::concatLine($nextVideo['file_url']));
            fwrite(STDOUT, "[channel-worker] NEXT: {$nextVideo['title']} ({$nextVideo['duration_ms']}ms) kw={$nextVideo['keywords']}\n");

            // 更新"当前视频"为刚写入的
            $currentDuration = $nextVideo['duration_ms'];
        }

        // 清理
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }
        proc_close($process);
    }

    /**
     * 选择下一个要播放的视频
     * 优先检查 Redis Stream 中的关键词指令
     * 否则从 persona 池随机选取
     */
    private function pickNextVideo(int $roomId, string $persona): ?array
    {
        // 1. 检查 Redis 关键词指令
        $cmd = $this->redisStream->consumeKeyword($roomId);
        if ($cmd) {
            $video = $this->repository->randomVideoByKeyword($persona, $cmd['keyword']);
            if ($video) {
                fwrite(STDOUT, "[channel-worker] GIFT-TRIGGER keyword={$cmd['keyword']} → {$video['title']}\n");
                return $video;
            }
            fwrite(STDERR, "[channel-worker] 关键词 '{$cmd['keyword']}' 无匹配视频\n");
        }

        // 2. 正常轮播
        return $this->pickVideo($roomId, $persona);
    }

    private function pickVideo(int $roomId, string $persona): ?array
    {
        $video = $this->repository->randomVideo($persona);
        if (!$video) {
            fwrite(STDERR, "[channel-worker] persona '{$persona}' 无可用视频\n");
        }
        return $video;
    }
}
