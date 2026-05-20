<?php
declare(strict_types=1);

namespace ChannelWorker;

final class PlaylistRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function roomStreamJob(int $roomId): array
    {
        $pdo = $this->database->pdo();

        $bindingStmt = $pdo->prepare(
            'SELECT rb.room_id, rb.stream_template_id, rb.playlist_template_id, st.webrtc_app, st.stream_alias_prefix
             FROM lp_room_binding rb
             LEFT JOIN lp_stream_template st ON st.id = rb.stream_template_id
             WHERE rb.room_id = :room_id
             LIMIT 1'
        );
        $bindingStmt->execute(['room_id' => $roomId]);
        $binding = $bindingStmt->fetch();
        if (!$binding) {
            throw new \RuntimeException("房间 {$roomId} 未配置流模板/播单绑定");
        }

        $itemsStmt = $pdo->prepare(
            'SELECT pti.asset_id, pti.seq, pti.loop_count, ma.asset_code, ma.title, ma.file_url, ma.duration_ms
             FROM lp_playlist_template_item pti
             INNER JOIN lp_media_asset ma ON ma.id = pti.asset_id
             WHERE pti.template_id = :template_id AND ma.status = 1
             ORDER BY pti.seq ASC, pti.id ASC'
        );
        $itemsStmt->execute(['template_id' => (int) $binding['playlist_template_id']]);
        $items = $itemsStmt->fetchAll();
        if (!$items) {
            throw new \RuntimeException("房间 {$roomId} 绑定的播单模板没有可用素材");
        }

        return [
            'room_id' => $roomId,
            'stream_alias' => ($binding['stream_alias_prefix'] ?: 'room') . '/' . $roomId,
            'webrtc_app' => $binding['webrtc_app'] ?: 'live',
            'playlist_template_id' => (int) $binding['playlist_template_id'],
            'items' => array_map(function (array $item): array {
                $fileUrl = (string) $item['file_url'];
                return [
                    'asset_id' => (int) $item['asset_id'],
                    'asset_code' => (string) $item['asset_code'],
                    'title' => (string) $item['title'],
                    'file_url' => $this->resolvePlayablePath($fileUrl),
                    'duration_ms' => (int) $item['duration_ms'],
                    'seq' => (int) $item['seq'],
                    'loop_count' => max(1, (int) $item['loop_count']),
                ];
            }, $items),
        ];
    }

    private function resolvePlayablePath(string $fileUrl): string
    {
        $fileUrl = trim($fileUrl);
        if ($fileUrl === '') {
            return '';
        }

        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $fileUrl)) {
            return $fileUrl;
        }

        if (preg_match('/^https?:\/\//i', $fileUrl)) {
            $path = (string) parse_url($fileUrl, PHP_URL_PATH);
            if ($path !== '') {
                $storagePath = $this->normalizeStorageRelativePath($path);
                if ($storagePath !== '') {
                    return $this->storageAbsolutePath($storagePath);
                }
            }
            return $fileUrl;
        }

        $storagePath = $this->normalizeStorageRelativePath($fileUrl);
        if ($storagePath !== '') {
            return $this->storageAbsolutePath($storagePath);
        }

        return $fileUrl;
    }

    private function normalizeStorageRelativePath(string $path): string
    {
        $normalizedPath = '/' . ltrim(str_replace('\\', '/', trim($path)), '/');
        if ($normalizedPath === '/') {
            return '';
        }

        if (str_starts_with($normalizedPath, '/storage/')) {
            return $normalizedPath;
        }

        if (str_starts_with($normalizedPath, '/public/storage/')) {
            return '/storage/' . ltrim(substr($normalizedPath, strlen('/public/storage/')), '/');
        }

        return '';
    }

    private function storageAbsolutePath(string $storagePath): string
    {
        return dirname(__DIR__, 3) . '/php/public' . $storagePath;
    }
}
