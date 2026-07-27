<?php
declare(strict_types=1);

namespace ChannelWorker;

/**
 * 构建 ffmpeg HLS 推流命令（stdin 管道模式）
 * 不再生成静态播放列表文件，而是通过 -i pipe:0 接收动态指令
 */
final class FfmpegCommandBuilder
{
    public function __construct(private readonly array $config)
    {
    }

    /**
     * 构建 ffmpeg 命令数组
     * 关键变更：-i pipe:0 替代 -i playlist.txt，-stream_loop 去掉
     */
    public function build(int $roomId, string $streamAlias): array
    {
        $output = $this->outputFiles($streamAlias);

        $command = sprintf(
            '%s -hide_banner -y -re '
            . '-protocol_whitelist file,http,https,tcp,tls '
            . '-f concat -safe 0 -i pipe:0 '
            . '-c:v libx264 -preset veryfast -c:a aac -ar 44100 '
            . '-f hls -hls_time %s -hls_list_size %s '
            . '-hls_flags delete_segments+append_list+omit_endlist '
            . '-hls_segment_filename "%s" "%s"',
            $this->config['ffmpeg_bin'],
            $this->config['segment_time'],
            $this->config['list_size'],
            $output['segment_pattern'],
            $output['manifest']
        );

        return [
            'command' => $command,
            'manifest' => $output['manifest'],
            'publish_url' => rtrim((string)($this->config['srs']['rtmp_publish_base'] ?? ''), '/') . '/' . $streamAlias,
        ];
    }

    /**
     * 生成 concat 指令行（写入 stdin 管道）
     */
    public static function concatLine(string $filePath): string
    {
        return "file '" . str_replace("'", "\\'", str_replace('\\', '/', $filePath)) . "'" . PHP_EOL;
    }

    private function outputFiles(string $streamAlias): array
    {
        $publicHlsDir = rtrim((string) $this->config['public_hls_dir'], '/\\');
        $segments = explode('/', $streamAlias);
        $basename = array_pop($segments);
        $subDir = implode(DIRECTORY_SEPARATOR, $segments);
        $targetDir = $publicHlsDir . DIRECTORY_SEPARATOR . $subDir;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        return [
            'manifest' => $targetDir . DIRECTORY_SEPARATOR . $basename . '.m3u8',
            'segment_pattern' => $targetDir . DIRECTORY_SEPARATOR . $basename . '_%06d.ts',
        ];
    }
}
