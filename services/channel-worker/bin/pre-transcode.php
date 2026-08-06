<?php
declare(strict_types=1);

/**
 * 视频预转码脚本：将源视频批量转码为 720p@30fps
 *
 * 用法: php bin/pre-transcode.php --room=1
 *
 * 流程:
 *   1. 读取 runtime/playlist_{room}.txt (concat 格式)
 *   2. 对列表中每个视频执行 ffmpeg 转码
 *   3. 已存在的目标文件自动跳过（支持断点续转）
 *   4. 转完后生成 playlist_{room}_720p.txt
 */

// ────────────────────────────────────────────────────────────
// 1. 解析命令行参数
// ────────────────────────────────────────────────────────────
$argv = $_SERVER['argv'] ?? [];
$roomId = null;

for ($i = 1, $c = count($argv); $i < $c; $i++) {
    if (str_starts_with($argv[$i], '--room=')) {
        $roomId = (int) substr($argv[$i], 7);
    }
}

if ($roomId === null || $roomId <= 0) {
    fwrite(STDERR, "用法: php bin/pre-transcode.php --room=房间ID" . PHP_EOL);
    exit(1);
}

// ────────────────────────────────────────────────────────────
// 2. 加载配置 & 获取 ffmpeg 路径
// ────────────────────────────────────────────────────────────
// channel_worker.php 已处理 FFMPEG_BIN 环境变量读取 + 自动查找
$config = require dirname(__DIR__) . '/config/channel_worker.php';
$ffmpegBin = $config['ffmpeg_bin'] ?? 'ffmpeg';

// 项目根目录 (douyin/)
$projectRoot = dirname(__DIR__, 3);

// ────────────────────────────────────────────────────────────
// 3. 路径定义
// ────────────────────────────────────────────────────────────
$runtimeDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'runtime';
$playlistFile = $runtimeDir . DIRECTORY_SEPARATOR . 'playlist_' . $roomId . '.txt';
$outputDir = DIRECTORY_SEPARATOR === '\\'
    ? $projectRoot . DIRECTORY_SEPARATOR . '视频成品_720p'
    : '/www/wwwroot/douyin/视频成品_720p';
$outputPlaylistFile = $runtimeDir . DIRECTORY_SEPARATOR . 'playlist_' . $roomId . '_720p.txt';

// ────────────────────────────────────────────────────────────
// 4. 校验
// ────────────────────────────────────────────────────────────
if (!is_file($playlistFile)) {
    fwrite(STDERR, "播放列表文件不存在: {$playlistFile}" . PHP_EOL);
    exit(1);
}

// ────────────────────────────────────────────────────────────
// 5. 确保输出目录存在
// ────────────────────────────────────────────────────────────
if (!is_dir($outputDir)) {
    if (!mkdir($outputDir, 0777, true)) {
        fwrite(STDERR, "无法创建输出目录: {$outputDir}" . PHP_EOL);
        exit(1);
    }
}

// ────────────────────────────────────────────────────────────
// 6. 解析 concat 格式播放列表  (file 'path')
// ────────────────────────────────────────────────────────────
$lines = file($playlistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
    fwrite(STDERR, "无法读取播放列表: {$playlistFile}" . PHP_EOL);
    exit(1);
}

$videos = [];
foreach ($lines as $line) {
    $line = trim($line);
    // concat 格式: file '/path/to/video.mp4'
    if (preg_match("/^file\s+'([^']+)'$/", $line, $m)) {
        $videos[] = $m[1];
    }
}

if (count($videos) === 0) {
    fwrite(STDERR, "播放列表为空，未找到有效的视频条目" . PHP_EOL);
    exit(1);
}

$total = count($videos);
echo "房间 {$roomId}: 共 {$total} 个视频待转码" . PHP_EOL;
echo "输出目录: {$outputDir}" . PHP_EOL . PHP_EOL;

// ────────────────────────────────────────────────────────────
// 7. ffmpeg 参数模板
// ────────────────────────────────────────────────────────────
$videoFilter = 'scale=1280:720:force_original_aspect_ratio=decrease,pad=1280:720:(ow-iw)/2:(oh-ih)/2,fps=30';

$ffmpegArgs = implode(' ', [
    '-c:v libx264',
    '-preset medium',
    '-crf 23',
    '-maxrate 2500k',
    '-bufsize 5000k',
    '-vf "' . $videoFilter . '"',
    '-c:a aac',
    '-b:a 128k',
    '-ar 44100',
    '-pix_fmt yuv420p',
    '-g 60',
    '-keyint_min 60',
    '-sc_threshold 0',
    '-y', // 覆盖已存在（已通过逻辑跳过，这里仅作保险）
]);

// ────────────────────────────────────────────────────────────
// 8. 主循环：逐文件转码
// ────────────────────────────────────────────────────────────
$successCount = 0;
$skipCount = 0;
$failCount = 0;

/** @var list<string> 输出播放列表条目 (concat 格式，使用正斜杠) */
$outputEntries = [];

foreach ($videos as $index => $srcVideo) {
    $indexDisplay = $index + 1; // 1-based for display

    // 将路径分隔符统一为当前系统
    $srcVideoNormalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $srcVideo);

    // 如果路径是绝对路径但使用了 / 开头 (Linux 风格)，在 Windows 下不做转换，
    // 仅在系统本身为 Linux 时保留
    if (!is_file($srcVideoNormalized)) {
        // 尝试在 Linux 路径和 Windows 路径间转换
        $altSrc = $srcVideo; // 保留原始路径再试
        if (!is_file($altSrc)) {
            echo sprintf('[%d/%d] FAIL: 源文件不存在: %s', $indexDisplay, $total, $srcVideo) . PHP_EOL;
            $failCount++;
            continue;
        }
        $srcVideoNormalized = $altSrc;
    }

    $srcVideoNormalized = realpath($srcVideoNormalized) ?: $srcVideoNormalized;
    $fileName = basename($srcVideoNormalized);
    $dstVideo = $outputDir . DIRECTORY_SEPARATOR . $fileName;

    // ── 断点续转：目标文件已存在则跳过 ──
    if (is_file($dstVideo) && filesize($dstVideo) > 0) {
        echo sprintf('[%d/%d] SKIP: 已存在 %s', $indexDisplay, $total, $fileName) . PHP_EOL;
        $skipCount++;

        // 使用正斜杠路径写入 concat 播放列表（ffmpeg 要求）
        $normalizedDst = str_replace('\\', '/', $dstVideo);
        $outputEntries[] = "file '{$normalizedDst}'";
        continue;
    }

    // ── 执行转码 ──
    echo sprintf('[%d/%d] 转码: %s', $indexDisplay, $total, $fileName) . PHP_EOL;

    $cmd = sprintf(
        '"%s" -i "%s" %s "%s"',
        $ffmpegBin,
        $srcVideoNormalized,
        $ffmpegArgs,
        $dstVideo
    );

    passthru($cmd, $exitCode);
    echo PHP_EOL;

    if ($exitCode === 0 && is_file($dstVideo) && filesize($dstVideo) > 0) {
        $successCount++;

        // 使用正斜杠路径写入 concat 播放列表（ffmpeg 要求）
        $normalizedDst = str_replace('\\', '/', $dstVideo);
        $outputEntries[] = "file '{$normalizedDst}'";
    } else {
        echo "  FAIL: ffmpeg 退出码 {$exitCode}" . PHP_EOL;
        $failCount++;
    }
}

// ────────────────────────────────────────────────────────────
// 9. 生成 720p 播放列表
// ────────────────────────────────────────────────────────────
if (count($outputEntries) > 0) {
    $content = implode(PHP_EOL, $outputEntries) . PHP_EOL;
    file_put_contents($outputPlaylistFile, $content);
}

// ────────────────────────────────────────────────────────────
// 10. 输出摘要
// ────────────────────────────────────────────────────────────
echo str_repeat('=', 40) . PHP_EOL;
echo "转码完成" . PHP_EOL;
echo "  成功: {$successCount}" . PHP_EOL;
echo "  跳过: {$skipCount}" . PHP_EOL;
echo "  失败: {$failCount}" . PHP_EOL;
echo "  输出播放列表: {$outputPlaylistFile}" . PHP_EOL;

exit($failCount > 0 ? 1 : 0);
