<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/php/vendor/autoload.php';
require dirname(__DIR__) . '/src/Database.php';
require dirname(__DIR__) . '/src/PlaylistRepository.php';
require dirname(__DIR__) . '/src/FfmpegCommandBuilder.php';
require dirname(__DIR__) . '/src/RedisStream.php';
require dirname(__DIR__) . '/src/ChannelWorker.php';

use ChannelWorker\ChannelWorker;
use ChannelWorker\Database;
use ChannelWorker\FfmpegCommandBuilder;
use ChannelWorker\PlaylistRepository;
use ChannelWorker\RedisStream;

$config = require dirname(__DIR__) . '/config/channel_worker.php';
$repository = new PlaylistRepository(new Database());
$builder = new FfmpegCommandBuilder($config);
$redis = new RedisStream($config['redis'] ?? []);
$worker = new ChannelWorker($repository, $builder, $redis, $config);

$argv = $_SERVER['argv'] ?? [];
$roomId = null;
for ($i = 1; $i < count($argv); $i++) {
    if (str_starts_with($argv[$i], '--room=')) {
        $roomId = (int) substr($argv[$i], 7);
    }
}

if ($roomId === null || $roomId <= 0) {
    fwrite(STDERR, "用法: php bin/channel-worker.php --room=房间ID" . PHP_EOL);
    exit(1);
}

$worker->run($roomId);
