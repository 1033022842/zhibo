<?php
declare(strict_types=1);

namespace ChannelWorker;

final class ChannelWorker
{
    private int $lastGiftTime = 0;
    private const GIFT_DURATION = 8;
    private const GIFT_COOLDOWN = 3;

    public function __construct(
        private readonly PlaylistRepository $repo,
        private readonly FfmpegCommandBuilder $bld,
        private readonly RedisStream $redis,
    ) {}

    public function run(int $roomId): never
    {
        $info = $this->repo->roomStreamInfo($roomId);
        $p = $info['persona'];
        $alias = $info['stream_alias'];
        try { $this->redis->connect(); } catch (\Throwable) {}

        $plFile = $this->bld->playlistFile($alias);
        $ovFile = $this->bld->overlayFile($alias);

        $this->log("room{$roomId} persona={$p}");
        $this->refreshPlaylist($roomId, $p, $plFile);

        $normalCmd = $this->bld->buildNormal($roomId, $alias);

        while (true) {
            // 礼物到期 → 切回 normal
            if ($this->lastGiftTime > 0 && time() - $this->lastGiftTime >= self::GIFT_DURATION) {
                $this->log("gift ended, switch to normal");
                $this->lastGiftTime = 0;
            }

            $this->refreshPlaylist($roomId, $p, $plFile);

            $cmd = $normalCmd;
            if ($this->lastGiftTime > 0) {
                $giftPath = $this->readOverlayVideo($ovFile);
                if ($giftPath && file_exists($giftPath)) {
                    $cmd = $this->bld->buildGift($roomId, $alias, $giftPath);
                } else {
                    $this->lastGiftTime = 0;
                }
            }

            $this->runFfmpeg($roomId, $p, $cmd, $ovFile);
            usleep(300000);
        }
    }

    private function runFfmpeg(int $roomId, string $persona, string $cmd, string $ovFile): void
    {
        $mode = $this->lastGiftTime > 0 ? 'GIFT' : 'N';
        $proc = @proc_open($cmd, [1 => STDOUT, 2 => STDERR], $pipes, dirname(__DIR__, 2));
        if (!is_resource($proc)) return;

        $pid = proc_get_status($proc)['pid'] ?? 0;
        $this->log("ffmpeg PID={$pid} {$mode}");

        sleep(2);
        if (!(proc_get_status($proc)['running'] ?? false)) { @proc_close($proc); return; }

        $loop = 0;
        while (true) {
            if (!(proc_get_status($proc)['running'] ?? false)) break;

            // 礼物到期
            if ($this->lastGiftTime > 0 && time() - $this->lastGiftTime >= self::GIFT_DURATION) {
                @proc_close($proc);
                return;
            }

            // 消费礼物
            while ($c = $this->redis->consumeKeyword($roomId)) {
                $v = $this->repo->randomVideoByKeyword($persona, $c['keyword']);
                if (!$v || !file_exists($v['file_url'])) continue;
                if ($this->lastGiftTime > 0 && time() - $this->lastGiftTime < self::GIFT_COOLDOWN) continue;

                $this->log("GIFT {$c['keyword']} -> {$v['title']}");
                file_put_contents($ovFile, FfmpegCommandBuilder::concatLine($v['file_url']));
                $this->lastGiftTime = time();
                @proc_close($proc);
                return;
            }

            $loop++;
            if ($loop % 50 === 0) {
                $g = $this->lastGiftTime > 0 ? ' g:' . max(0, self::GIFT_DURATION - (time() - $this->lastGiftTime)) . 's' : '';
                $this->log("hb{$loop}{$g}");
            }
            usleep(200000);
        }
        @proc_close($proc);
    }

    private function readOverlayVideo(string $f): string
    {
        if (!file_exists($f)) return '';
        foreach (file($f) as $l) {
            if (preg_match("/file '(.*)'/", $l, $m)) return $m[1];
        }
        return '';
    }

    private function refreshPlaylist(int $roomId, string $persona, string $plFile): void
    {
        $list = $this->repo->roomPlaylistVideos($roomId);
        if (!empty($list)) {
            $lines = [];
            for ($r = 0; $r < 3; $r++)
                foreach ($list as $v) $lines[] = FfmpegCommandBuilder::concatLine($v['file_url']);
            file_put_contents($plFile, implode('', $lines));
            return;
        }
        $base = [];
        for ($i = 0; $i < 20; $i++) {
            $v = $this->repo->randomVideo($persona);
            if ($v) $base[] = FfmpegCommandBuilder::concatLine($v['file_url']);
        }
        if (empty($base)) throw new \RuntimeException("no videos");
        file_put_contents($plFile, implode('', array_merge($base, $base, $base)));
    }

    private function log(string $msg): void
    {
        @fwrite(STDERR, '[' . date('H:i:s') . '] ' . $msg . PHP_EOL);
    }
}
