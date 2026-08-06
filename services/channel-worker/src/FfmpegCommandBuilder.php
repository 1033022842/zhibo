<?php
declare(strict_types=1);

namespace ChannelWorker;

/**
 * ffmpeg 命令构建器 — concat demuxer + HLS 直出 + 480p 低负载
 */
final class FfmpegCommandBuilder
{
    public function __construct(public readonly array $config) {}

    /**
     * 构建 HLS 480p@15fps concat demuxer 命令
     * 低 CPU 配置：480p 分辨率 + 15fps + CRF 32，适合低配服务器
     */
    /**
     * @param bool $loop 是否循环播放列表。默认视频应循环(true)，关键词触发的一次性播单不应循环(false)
     * @param int  $startNumber 起始分片号，0=从头开始，>0=接续已有分片避免编号跳变
     */
    public function buildHlsToDir(string $hlsDir, string $playlistFile, bool $loop = true, int $startNumber = 0): string
    {
        $m3u8 = $hlsDir . '/index.m3u8';
        $segPat = $hlsDir . '/seg_%05d.ts';
        $loopFlag = $loop ? '-stream_loop -1 ' : '';
        $startOpt = $startNumber > 0 ? "-start_number {$startNumber} " : '';
        // 非循环播单（关键词视频）：加 omit_endlist 防止 ffmpeg 退出时写 #EXT-X-ENDLIST，
        // 避免 hls.js 认为流结束停止轮询，从而无法发现后续重启的默认播单。
        $endlistOpt = $loop ? '' : '+omit_endlist';

        return sprintf(
            '%s -hide_banner -y -re -loglevel warning '
            . $loopFlag
            . '-f concat -safe 0 -protocol_whitelist file,pipe,crypto,data -i "%s" '
            . '-c:v libx264 -preset ultrafast -tune zerolatency -crf 36 -maxrate 600k -bufsize 1200k '
            . '-vf "scale=360:640:force_original_aspect_ratio=decrease,pad=360:640:(ow-iw)/2:(oh-ih)/2,fps=15" '
            . '-g 30 -keyint_min 30 -sc_threshold 0 -pix_fmt yuv420p '
            . '-c:a aac -b:a 48k -ar 44100 '
            . '-max_muxing_queue_size 4096 '
            . '-f hls -hls_time 2 -hls_list_size 30 -hls_flags delete_segments+program_date_time+discont_start'
            . $endlistOpt . ' '
            . $startOpt
            . '-hls_segment_filename "%s" "%s"',
            $this->config['ffmpeg_bin'], $playlistFile, $segPat, $m3u8
        );
    }

    /**
     * 便捷方法：根据 streamAlias 推导目录，清理后构建命令
     */
    public function buildHlsPlaylist(string $streamAlias, string $playlistFile): string
    {
        $hlsDir = $this->hlsDir($streamAlias);
        @mkdir($hlsDir, 0755, true);
        array_map('unlink', glob($hlsDir . '/*.ts') ?: []);
        @unlink($hlsDir . '/index.m3u8');
        return $this->buildHlsToDir($hlsDir, $playlistFile);
    }

    public static function concatLine(string $fp): string
    {
        return "file '" . str_replace("'", "\\'", str_replace('\\', '/', $fp)) . "'\n";
    }

    public function hlsDir(string $a): string
    {
        return rtrim($this->config['hls']['output_dir'] ?? '/www/wwwroot/douyin/hls', '/') . '/' . $a;
    }
}
