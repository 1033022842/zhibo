<?php
declare(strict_types=1);

namespace ChannelWorker;

final class ChannelWorker
{
    public function __construct(
        private readonly PlaylistRepository $repository,
        private readonly FfmpegCommandBuilder $commandBuilder,
        private readonly array $config
    ) {
    }

    public function describe(int $roomId): array
    {
        $job = $this->repository->roomStreamJob($roomId);
        $build = $this->commandBuilder->build($job);

        return [
            'room_id' => $roomId,
            'stream_alias' => $job['stream_alias'],
            'manifest' => $build['manifest'],
            'playlist_file' => $build['playlist_file'],
            'publish_url' => $build['publish_url'],
            'command' => $build['command'],
        ];
    }

    public function run(int $roomId): never
    {
        $info = $this->describe($roomId);
        $command = array_map([$this, 'escape'], $info['command']);
        $commandLine = implode(' ', $command);

        fwrite(STDOUT, '[channel-worker] room=' . $roomId . PHP_EOL);
        fwrite(STDOUT, '[channel-worker] alias=' . $info['stream_alias'] . PHP_EOL);
        fwrite(STDOUT, '[channel-worker] manifest=' . $info['manifest'] . PHP_EOL);
        fwrite(STDOUT, '[channel-worker] cmd=' . $commandLine . PHP_EOL);

        while (true) {
            $descriptors = [
                0 => STDIN,
                1 => STDOUT,
                2 => STDERR,
            ];

            $process = proc_open($commandLine, $descriptors, $pipes, dirname(__DIR__, 2));
            if (!is_resource($process)) {
                throw new \RuntimeException('无法启动 ffmpeg 进程，请确认 ffmpeg 已安装并可执行');
            }

            $exitCode = proc_close($process);
            fwrite(STDERR, '[channel-worker] ffmpeg 已退出，exit=' . $exitCode . '，' . (int) $this->config['restart_delay_sec'] . ' 秒后重试' . PHP_EOL);
            sleep((int) $this->config['restart_delay_sec']);
        }
    }

    private function escape(string $argument): string
    {
        return '"' . str_replace('"', '\"', $argument) . '"';
    }
}
