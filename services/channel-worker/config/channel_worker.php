<?php

$ffmpegBin = getenv('FFMPEG_BIN') ?: 'ffmpeg';
$localAppData = getenv('LOCALAPPDATA') ?: '';
if ($localAppData !== '') {
    $matches = glob(str_replace('\\', '/', rtrim($localAppData, '\\/')) . '/Microsoft/WinGet/Packages/Gyan.FFmpeg.Essentials_*/ffmpeg-*/bin/ffmpeg.exe');
    if (!empty($matches)) {
        $ffmpegBin = str_replace('/', DIRECTORY_SEPARATOR, $matches[0]);
    }
}

return [
    'ffmpeg_bin' => $ffmpegBin,
    'public_hls_dir' => dirname(__DIR__, 3) . '/php/public/hls',
    'runtime_dir' => dirname(__DIR__) . '/runtime',
    'segment_time' => 4,
    'list_size' => 6,
    'restart_delay_sec' => 3,
    'srs' => [
        'enabled' => false,
        'rtmp_publish_base' => getenv('SRS_RTMP_PUBLISH_BASE') ?: 'rtmp://127.0.0.1/live',
    ],
    'redis' => [
        'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('REDIS_PORT') ?: 6379),
        'password' => getenv('REDIS_PASSWORD') ?: '',
        'select' => (int)(getenv('REDIS_SELECT') ?: 0),
    ],
];
