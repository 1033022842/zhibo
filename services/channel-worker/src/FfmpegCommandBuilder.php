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
     * 构建 HLS 命令
     *
     * 注意：不使用 delete_segments，由 PHP 侧统一清理旧分片，避免双 ffmpeg 共存时互相误删。
     *
     * @param bool   $loop         是否循环播放列表
     * @param int    $startNumber  起始分片号，0=从头开始，>0=接续已有分片
     * @param string $m3u8Basename m3u8 文件名（不含路径），默认 'index'，双播单场景用 'default'/'keyword'
     */
    public function buildHlsToDir(
        string $hlsDir,
        string $playlistFile,
        bool $loop = true,
        int $startNumber = 0,
        string $m3u8Basename = 'index'
    ): string {
        $m3u8 = $hlsDir . '/' . $m3u8Basename . '.m3u8';
        $segPat = $hlsDir . '/seg_%05d.ts';
        $loopFlag = $loop ? '-stream_loop -1 ' : '';
        $startOpt = $startNumber > 0 ? "-start_number {$startNumber} " : '';
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
            . '-f hls -hls_time 2 -hls_list_size 30 -hls_flags program_date_time+discont_start'
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
