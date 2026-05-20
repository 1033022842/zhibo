<?php
declare(strict_types=1);

namespace ChannelWorker;

final class FfmpegCommandBuilder
{
    public function __construct(private readonly array $config)
    {
    }

    public function build(array $job): array
    {
        $streamAlias = (string) $job['stream_alias'];
        $roomId = (int) $job['room_id'];
        $output = $this->outputFiles($streamAlias);
        $playlistFile = $this->buildConcatPlaylist($roomId, (array) $job['items']);

        $command = [
            (string) $this->config['ffmpeg_bin'],
            '-hide_banner',
            '-y',
            '-re',
            '-stream_loop', '-1',
            '-protocol_whitelist', 'file,http,https,tcp,tls',
            '-f', 'concat',
            '-safe', '0',
            '-i', $playlistFile,
            '-c:v', 'libx264',
            '-preset', 'veryfast',
            '-c:a', 'aac',
            '-ar', '44100',
            '-f', 'hls',
            '-hls_time', (string) $this->config['segment_time'],
            '-hls_list_size', (string) $this->config['list_size'],
            '-hls_flags', 'delete_segments+append_list+omit_endlist',
            '-hls_segment_filename', $output['segment_pattern'],
            $output['manifest'],
        ];

        return [
            'command' => $command,
            'manifest' => $output['manifest'],
            'playlist_file' => $playlistFile,
            'publish_url' => rtrim((string) ($this->config['srs']['rtmp_publish_base'] ?? ''), '/') . '/' . $streamAlias,
        ];
    }

    private function buildConcatPlaylist(int $roomId, array $items): string
    {
        $runtimeDir = rtrim((string) $this->config['runtime_dir'], '/\\');
        if (!is_dir($runtimeDir)) {
            mkdir($runtimeDir, 0777, true);
        }

        $playlistFile = $runtimeDir . DIRECTORY_SEPARATOR . 'room_' . $roomId . '_playlist.txt';
        $lines = [];
        foreach ($items as $item) {
            $loops = max(1, (int) ($item['loop_count'] ?? 1));
            for ($i = 0; $i < $loops; $i++) {
                $lines[] = "file '" . str_replace("'", "\\'", (string) $item['file_url']) . "'";
            }
        }

        file_put_contents($playlistFile, implode(PHP_EOL, $lines) . PHP_EOL);
        return $playlistFile;
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
