<?php
declare(strict_types=1);

namespace ChannelWorker;

/**
 * 关键词视频仓库
 * 从 lp_media_asset 查询视频，支持按 persona 随机和按关键词随机
 */
final class PlaylistRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * 获取房间绑定的 persona，以及流模板信息
     */
    public function roomStreamInfo(int $roomId): array
    {
        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare(
            'SELECT rb.room_id, rb.persona, st.webrtc_app, st.stream_alias_prefix
             FROM lp_room_binding rb
             LEFT JOIN lp_stream_template st ON st.id = rb.stream_template_id
             WHERE rb.room_id = :room_id LIMIT 1'
        );
        $stmt->execute(['room_id' => $roomId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new \RuntimeException("房间 {$roomId} 未配置流绑定");
        }
        if (empty($row['persona'])) {
            throw new \RuntimeException("房间 {$roomId} 未配置人设(persona)");
        }

        return [
            'room_id' => $roomId,
            'persona' => $row['persona'],
            'stream_alias' => ($row['stream_alias_prefix'] ?: 'room') . '/' . $roomId,
            'webrtc_app' => $row['webrtc_app'] ?: 'live',
        ];
    }

    /**
     * 按 persona 随机取一个视频
     */
    public function randomVideo(string $persona): ?array
    {
        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare(
            'SELECT id, asset_code, title, file_url, duration_ms, keywords
             FROM lp_media_asset
             WHERE persona = :persona AND asset_type = \'video\' AND status = 1
             ORDER BY RAND()
             LIMIT 1'
        );
        $stmt->execute(['persona' => $persona]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return $this->formatRow($row);
    }

    /**
     * 按 persona + 关键词随机取一个视频
     */
    public function randomVideoByKeyword(string $persona, string $keyword): ?array
    {
        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare(
            'SELECT id, asset_code, title, file_url, duration_ms, keywords
             FROM lp_media_asset
             WHERE persona = :persona
               AND asset_type = \'video\' AND status = 1
               AND FIND_IN_SET(:kw, REPLACE(keywords, \' \', \'\')) > 0
             ORDER BY RAND()
             LIMIT 1'
        );
        $stmt->execute(['persona' => $persona, 'kw' => $keyword]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return $this->formatRow($row);
    }

    private function formatRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'asset_code' => (string) $row['asset_code'],
            'title' => (string) $row['title'],
            'file_url' => $this->resolvePath((string) $row['file_url']),
            'duration_ms' => (int) $row['duration_ms'],
            'keywords' => (string) $row['keywords'],
        ];
    }

    private function resolvePath(string $fileUrl): string
    {
        $fileUrl = trim($fileUrl);
        if ($fileUrl === '') {
            return '';
        }
        // Windows 绝对路径直接返回
        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $fileUrl)) {
            return str_replace('/', DIRECTORY_SEPARATOR, $fileUrl);
        }
        // HTTP URL 映射到本地 storage
        if (preg_match('/^https?:\/\//i', $fileUrl)) {
            $path = parse_url($fileUrl, PHP_URL_PATH);
            if ($path) {
                $normalized = '/' . ltrim(str_replace('\\', '/', $path), '/');
                if (str_starts_with($normalized, '/storage/')) {
                    return dirname(__DIR__, 3) . '/php/public' . $normalized;
                }
            }
        }
        return $fileUrl;
    }
}
