<?php
declare(strict_types=1);

namespace ChannelWorker;

final class FfmpegCommandBuilder
{
    public function __construct(public readonly array $config) {}

    /** Relay 推流：-c copy 直推到 MediaMTX，返回数组供 ChannelWorker 使用 */
    public function buildRelay(int $roomId, string $streamAlias): array
    {
        $rtmp = rtrim($this->config['mediamtx']['rtmp_base'] ?? 'rtmp://127.0.0.1:1936', '/') . '/' . $streamAlias;
        return ['command' => sprintf('%s -hide_banner -y -re -stream_loop -1 -fflags +genpts+discardcorrupt -err_detect ignore_err -f concat -safe 0 -i "%s" -c copy -f flv -flvflags no_duration_filesize -rtmp_live live "%s"',
            $this->config['ffmpeg_bin'], $this->playlistFile($streamAlias), $rtmp)];
    }

    /** Passthrough 直通模式：同 relay，不带 overlay */
    public function buildPassthrough(int $roomId, string $streamAlias): array
    {
        return $this->buildRelay($roomId, $streamAlias);
    }

    /** Overlay 礼物叠加模式：将礼物视频合成到主视频上 */
    public function buildOverlay(string $streamAlias, string $giftPath): array
    {
        $rtmp = rtrim($this->config['mediamtx']['rtmp_base'] ?? 'rtmp://127.0.0.1:1936', '/') . '/' . $streamAlias;
        $ov = $this->overlayFile($streamAlias);
        @file_put_contents($ov, self::concatLine($giftPath));
        return ['command' => sprintf('%s -hide_banner -y -re -stream_loop -1 -fflags +genpts+discardcorrupt -err_detect ignore_err -f concat -safe 0 -i "%s" -re -stream_loop -1 -f concat -safe 0 -i "%s" -filter_complex "[0:v]scale=720:1280,fps=30,setpts=PTS-STARTPTS[main];[1:v]scale=720:1280,fps=30,setpts=PTS-STARTPTS[over];[main][over]overlay=0:0[out]" -map "[out]" -map 0:a -c:v libx264 -preset ultrafast -tune zerolatency -crf 23 -maxrate 8000k -bufsize 16000k -g 15 -keyint_min 15 -sc_threshold 0 -pix_fmt yuv420p -c:a aac -b:a 128k -ar 44100 -max_muxing_queue_size 4096 -f flv -flvflags no_duration_filesize -rtmp_live live "%s"',
            $this->config['ffmpeg_bin'], $this->playlistFile($streamAlias), $ov, $rtmp)];
    }

    public static function concatLine(string $fp): string
    { return "file '" . str_replace("'", "\\'", str_replace('\\', '/', $fp)) . "'\n"; }

    public function overlayFile(string $a): string
    { return rtrim($this->config['runtime_dir'] ?? sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'overlay_' . explode('/', $a)[1] . '.txt'; }

    public function playlistFile(string $a): string
    { return rtrim($this->config['runtime_dir'] ?? sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'playlist_' . explode('/', $a)[1] . '.txt'; }
}
