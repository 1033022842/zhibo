<?php

$ffmpegBin = getenv('FFMPEG_BIN') ?: 'ffmpeg';

// 自动查找项目自带或 winget 安装的 ffmpeg
$binDir = dirname(__DIR__) . '/bin';
$localFfmpeg = null;
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($binDir, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($rii as $file) {
    if ($file->getFilename() === 'ffmpeg.exe') {
        $localFfmpeg = $file->getPathname();
        break;
    }
}
if ($localFfmpeg !== null) {
    $ffmpegBin = str_replace('/', DIRECTORY_SEPARATOR, $localFfmpeg);
} else {
    $localAppData = getenv('LOCALAPPDATA') ?: '';
    if ($localAppData !== '') {
        $matches = glob(str_replace('\\', '/', rtrim($localAppData, '\\/')) . '/Microsoft/WinGet/Packages/Gyan.FFmpeg.Essentials_*/ffmpeg-*/bin/ffmpeg.exe');
        if (!empty($matches)) {
            $ffmpegBin = str_replace('/', DIRECTORY_SEPARATOR, $matches[0]);
        }
    }
}

return [
    'ffmpeg_bin' => $ffmpegBin,
    'runtime_dir' => dirname(__DIR__) . '/runtime',
    'mediamtx' => [
        'rtmp_base' => getenv('MEDIAMTX_RTMP_BASE') ?: 'rtmp://127.0.0.1:1936',
    ],
    'media_base_dir' => dirname(__DIR__, 3),  // 项目根目录 (douyin/)，相对路径视频文件拼接此前缀
    'redis' => [
        'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('REDIS_PORT') ?: 6379),
        'password' => getenv('REDIS_PASSWORD') ?: '',
        'select' => (int)(getenv('REDIS_SELECT') ?: 0),
    ],
];
