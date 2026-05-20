<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/php/vendor/autoload.php';
require dirname(__DIR__) . '/src/Database.php';
require dirname(__DIR__) . '/src/PlaylistRepository.php';
require dirname(__DIR__) . '/src/FfmpegCommandBuilder.php';
require dirname(__DIR__) . '/src/ChannelWorker.php';

use ChannelWorker\ChannelWorker;
use ChannelWorker\Database;
use ChannelWorker\FfmpegCommandBuilder;
use ChannelWorker\PlaylistRepository;

$config = require dirname(__DIR__) . '/config/channel_worker.php';
$repository = new PlaylistRepository(new Database());
$builder = new FfmpegCommandBuilder($config);
$worker = new ChannelWorker($repository, $builder, $config);

$argv = $_SERVER['argv'] ?? [];
$roomId = null;
$dryRun = false;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--room=')) {
        $roomId = (int) substr($argument, 7);
    }
    if ($argument === '--dry-run') {
        $dryRun = true;
    }
}

if ($roomId === null || $roomId <= 0) {
    fwrite(STDERR, "用法: php services/channel-worker/bin/channel-worker.php --room=房间ID [--dry-run]" . PHP_EOL);
    exit(1);
}

if ($dryRun) {
    $info = $worker->describe($roomId);
    echo json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$worker->run($roomId);
