<?php
declare(strict_types=1);

namespace ChannelWorker;

final class FfmpegCommandBuilder
{
    public function __construct(private readonly array $config) {}

    public function buildRelay(int $roomId, string $streamAlias): array
    {
        $relayAlias = $streamAlias . '_src';
        $rtmpUrl = rtrim((string) ($this->config['mediamtx']['rtmp_base'] ?? 'rtmp://127.0.0.1:1936'), '/') . '/' . $relayAlias;

        $command = sprintf(
            '%s -hide_banner -y -re -stream_loop -1 '
            . '-fflags +genpts+discardcorrupt -err_detect ignore_err '
            . '-f concat -safe 0 -i "%s" '
            . '-c copy '
            . '-f flv -flvflags no_duration_filesize '
            . '-rtmp_live live '
            . '"%s"',
            $this->config['ffmpeg_bin'],
            $this->playlistFile($streamAlias),
            $rtmpUrl
        );

        return ['command' => $command, 'publish_url' => $rtmpUrl];
    }

    /**
     * 直通模式：读取 relay，重新编码推送到 final（不加任何 overlay）
     */
    public function buildPassthrough(int $roomId, string $streamAlias): array
    {
        $relayAlias = $streamAlias . '_src';
        $relayUrl = rtrim((string) ($this->config['mediamtx']['rtmp_base'] ?? 'rtmp://127.0.0.1:1936'), '/') . '/' . $relayAlias;
        $rtmpUrl = rtrim((string) ($this->config['mediamtx']['rtmp_base'] ?? 'rtmp://127.0.0.1:1936'), '/') . '/' . $streamAlias;

        $command = sprintf(
            '%s -hide_banner -y '
            . '-analyzeduration 10M -probesize 10M '
            . '-fflags +genpts+discardcorrupt -err_detect ignore_err '
            . '-i "%s" '
            . '-max_muxing_queue_size 4096 '
            . '-c:v libx264 -preset ultrafast -tune zerolatency -crf 23 '
            . '-maxrate 8000k -bufsize 16000k '
            . '-g 15 -keyint_min 15 -sc_threshold 0 '
            . '-pix_fmt yuv420p -vf "scale=720:1280,fps=30" '
            . '-c:a aac -b:a 128k -ar 44100 '
            . '-f flv -flvflags no_duration_filesize '
            . '-rtmp_live live '
            . '"%s"',
            $this->config['ffmpeg_bin'],
            $relayUrl,
            $rtmpUrl
        );

        return ['command' => $command, 'publish_url' => $rtmpUrl];
    }

    /**
     * 叠加模式：读取 relay + overlay，叠加后推送到 final
     */
    public function buildOverlay(string $streamAlias, string $giftVideoPath): array
    {
        $relayAlias = $streamAlias . '_src';
        $relayUrl = rtrim((string) ($this->config['mediamtx']['rtmp_base'] ?? 'rtmp://127.0.0.1:1936'), '/') . '/' . $relayAlias;
        $rtmpUrl = rtrim((string) ($this->config['mediamtx']['rtmp_base'] ?? 'rtmp://127.0.0.1:1936'), '/') . '/' . $streamAlias;

        // 临时写入 overlay concat 文件（只含一个视频）
        $tmpOverlay = $this->overlayFile($streamAlias);
        file_put_contents($tmpOverlay, self::concatLine($giftVideoPath));

        $command = sprintf(
            '%s -hide_banner -y '
            . '-analyzeduration 10M -probesize 10M '
            . '-fflags +genpts+discardcorrupt -err_detect ignore_err '
            . '-i "%s" '
            . '-re -stream_loop -1 -f concat -safe 0 -i "%s" '
            . '-max_muxing_queue_size 4096 '
            . '-filter_complex '
            . '"[0:v]scale=720:1280,fps=30,setpts=PTS-STARTPTS[main];'
            . '[1:v]scale=720:1280,fps=30,setpts=PTS-STARTPTS[over];'
            . '[main][over]overlay=0:0[out]" '
            . '-map "[out]" -map 0:a '
            . '-c:v libx264 -preset ultrafast -tune zerolatency -crf 23 '
            . '-maxrate 8000k -bufsize 16000k '
            . '-g 15 -keyint_min 15 -sc_threshold 0 '
            . '-pix_fmt yuv420p '
            . '-c:a aac -b:a 128k -ar 44100 '
            . '-f flv -flvflags no_duration_filesize '
            . '-rtmp_live live '
            . '"%s"',
            $this->config['ffmpeg_bin'],
            $relayUrl,
            $tmpOverlay,
            $rtmpUrl
        );

        return ['command' => $command, 'publish_url' => $rtmpUrl];
    }

    public static function concatLine(string $filePath): string
    {
        return "file '" . str_replace("'", "\\'", str_replace('\\', '/', $filePath)) . "'" . PHP_EOL;
    }

    public function overlayFile(string $streamAlias): string
    {
        $parts = explode('/', $streamAlias);
        $basename = end($parts);
        $runtimeDir = rtrim((string) ($this->config['runtime_dir'] ?? sys_get_temp_dir()), '/\\');
        return $runtimeDir . DIRECTORY_SEPARATOR . 'overlay_' . $basename . '.txt';
    }

    public function playlistFile(string $streamAlias): string
    {
        $parts = explode('/', $streamAlias);
        $basename = end($parts);
        $runtimeDir = rtrim((string) ($this->config['runtime_dir'] ?? sys_get_temp_dir()), '/\\');
        return $runtimeDir . DIRECTORY_SEPARATOR . 'playlist_' . $basename . '.txt';
    }
}
