<?php
declare(strict_types=1);

namespace ChannelWorker;

/**
 * Relay 架构 Worker
 * 默认直通（不 overlay），送礼时切换到 overlay 模式，播完自动恢复。
 */
final class ChannelWorker
{
    private int $lastGiftTime = 0;
    private const GIFT_DURATION = 8;
    private const GIFT_COOLDOWN = 3;
    private mixed $relayProcess = null;

    public function __construct(
        private readonly PlaylistRepository $repository,
        private readonly FfmpegCommandBuilder $builder,
        private readonly RedisStream $redisStream,
        private readonly array $config
    ) {}

    public function run(int $roomId): never
    {
        $info = $this->repository->roomStreamInfo($roomId);
        $persona = $info['persona'];
        $streamAlias = $info['stream_alias'];

        try { $this->redisStream->connect(); } catch (\Throwable $e) {
            $this->log("Redis fail: {$e->getMessage()}");
        }

        $relayCmd = $this->builder->buildRelay($roomId, $streamAlias)['command'];
        $passthroughCmd = $this->builder->buildPassthrough($roomId, $streamAlias)['command'];
        $playlistFile = $this->builder->playlistFile($streamAlias);

        $this->log("room={$roomId} persona={$persona}");
        $this->refreshPlaylist($roomId, $persona, $playlistFile);

        $relayPid = $this->startRelay($relayCmd);

        while (true) {
            // 保持 relay 活着
            if ($relayPid <= 0 || !$this->isAlive($relayPid)) {
                $this->log("relay down, restart...");
                $relayPid = $this->startRelay($relayCmd);
            }

            // 礼物超时 → 恢复直通
            if ($this->lastGiftTime > 0 && time() - $this->lastGiftTime >= self::GIFT_DURATION) {
                $this->lastGiftTime = 0;
                $this->log("gift ended, back to passthrough");
            }

            $this->refreshPlaylist($roomId, $persona, $playlistFile);

            // 选择命令：有礼物用 overlay，无礼物用直通
            $activeCmd = $this->lastGiftTime > 0
                ? $this->builder->buildOverlay($streamAlias, $this->currentOverlayVideo($streamAlias))['command']
                : $passthroughCmd;

            try {
                $this->runOutput($roomId, $persona, $activeCmd, $streamAlias);
            } catch (\Throwable $e) {
                $this->log("output crash: {$e->getMessage()}");
            }

            sleep(1);
        }
    }

    private function currentOverlayVideo(string $streamAlias): string
    {
        $f = $this->builder->overlayFile($streamAlias);
        if (file_exists($f)) {
            $lines = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $l) {
                if (preg_match("/file '(.*)'/", $l, $m)) return $m[1];
            }
        }
        return ''; // 不应该走到这里
    }

    private function startRelay(string $command): int
    {
        if ($this->relayProcess !== null && is_resource($this->relayProcess)) {
            @proc_close($this->relayProcess);
        }
        $this->relayProcess = null;

        $process = @proc_open($command, [1 => STDOUT, 2 => STDERR], $pipes, dirname(__DIR__, 2));
        if (!is_resource($process)) {
            $this->log("relay start failed");
            return 0;
        }
        $this->relayProcess = $process;
        $pid = proc_get_status($process)['pid'] ?? 0;
        $this->log("relay PID={$pid}");

        sleep(3);

        if (!$this->isAlive($pid)) {
            $this->log("relay died immediately");
            $this->relayProcess = null;
            return 0;
        }
        return $pid;
    }

    private function runOutput(int $roomId, string $persona, string $command, string $streamAlias): void
    {
        $process = @proc_open($command, [1 => STDOUT, 2 => STDERR], $pipes, dirname(__DIR__, 2));
        if (!is_resource($process)) { $this->log("output start failed"); return; }

        $pid = proc_get_status($process)['pid'] ?? 0;
        $this->log("output PID={$pid} " . ($this->lastGiftTime > 0 ? '(overlay)' : '(passthrough)'));

        sleep(1);
        if (!(proc_get_status($process)['running'] ?? false)) {
            $this->log("output crashed on start");
            @proc_close($process);
            return;
        }

        $loop = 0;
        while (true) {
            if (!(proc_get_status($process)['running'] ?? false)) break;

            // 礼物到期检测
            if ($this->lastGiftTime > 0 && time() - $this->lastGiftTime >= self::GIFT_DURATION) {
                $this->log("gift timeout, kill output");
                @proc_close($process);
                return;
            }

            // 消费礼物
            while ($cmd = $this->redisStream->consumeKeyword($roomId)) {
                try {
                    $video = $this->repository->randomVideoByKeyword($persona, $cmd['keyword']);
                    if (!$video || !file_exists($video['file_url'])) continue;
                    if ($this->lastGiftTime > 0 && time() - $this->lastGiftTime < self::GIFT_COOLDOWN) continue;

                    $this->log("GIFT {$cmd['keyword']} -> {$video['title']}");
                    // 写入 overlay 文件
                    file_put_contents(
                        $this->builder->overlayFile($streamAlias),
                        FfmpegCommandBuilder::concatLine($video['file_url'])
                    );
                    $this->lastGiftTime = time();
                    @proc_close($process);
                    return;
                } catch (\Throwable $e) {
                    $this->log("GIFT err: {$e->getMessage()}");
                }
            }

            $loop++;
            if ($loop % 50 === 0) {
                $g = $this->lastGiftTime > 0 ? ' gift:' . max(0, self::GIFT_DURATION - (time() - $this->lastGiftTime)) . 's' : '';
                $this->log("hb {$loop}{$g}");
            }
            usleep(200000);
        }

        @proc_close($process);
    }

    private function isAlive(int $pid): bool
    {
        if ($pid <= 0) return false;
        @exec("tasklist /FI \"PID eq {$pid}\" 2>&1", $out);
        foreach ($out as $l) { if (str_contains($l, (string) $pid)) return true; }
        return false;
    }

    private function refreshPlaylist(int $roomId, string $persona, string $playlistFile): void
    {
        $roomVideos = $this->repository->roomPlaylistVideos($roomId);
        if (!empty($roomVideos)) {
            $videos = [];
            for ($r = 0; $r < 3; $r++) {
                foreach ($roomVideos as $v) $videos[] = FfmpegCommandBuilder::concatLine($v['file_url']);
            }
            file_put_contents($playlistFile, implode('', $videos));
            return;
        }
        $base = [];
        for ($i = 0; $i < 20; $i++) {
            $v = $this->repository->randomVideo($persona);
            if ($v) $base[] = FfmpegCommandBuilder::concatLine($v['file_url']);
        }
        if (empty($base)) throw new \RuntimeException("{$persona} no videos");
        file_put_contents($playlistFile, implode('', array_merge($base, $base, $base)));
    }

    private function log(string $msg): void
    {
        @fwrite(STDERR, '[' . date('H:i:s') . '] ' . $msg . PHP_EOL);
    }
}
