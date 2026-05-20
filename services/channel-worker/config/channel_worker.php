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
    'seed_demo' => [
        'assets' => [
            'room1' => [
                'asset_code' => 'demo_live_asset_room_1',
                'title' => '深夜情感电台演示素材',
                'source_url' => 'https://samplelib.com/lib/preview/mp4/sample-5s.mp4',
                'local_path' => dirname(__DIR__) . '/runtime/assets/demo_live_asset_room_1.mp4',
                'file_url' => dirname(__DIR__) . '/runtime/assets/demo_live_asset_room_1.mp4',
                'duration_ms' => 5000,
            ],
            'room2' => [
                'asset_code' => 'demo_live_asset_room_2',
                'title' => '午后轻音乐直播间演示素材',
                'source_url' => 'https://samplelib.com/lib/preview/mp4/sample-10s.mp4',
                'local_path' => dirname(__DIR__) . '/runtime/assets/demo_live_asset_room_2.mp4',
                'file_url' => dirname(__DIR__) . '/runtime/assets/demo_live_asset_room_2.mp4',
                'duration_ms' => 10000,
            ],
            'room3' => [
                'asset_code' => 'demo_live_asset_room_3',
                'title' => '清晨自习直播间演示素材',
                'source_url' => 'https://samplelib.com/lib/preview/mp4/sample-15s.mp4',
                'local_path' => dirname(__DIR__) . '/runtime/assets/demo_live_asset_room_3.mp4',
                'file_url' => dirname(__DIR__) . '/runtime/assets/demo_live_asset_room_3.mp4',
                'duration_ms' => 15000,
            ],
        ],
    ],
];
