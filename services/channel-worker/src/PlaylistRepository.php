<?php
declare(strict_types=1);

namespace ChannelWorker;

/**
 * 关键词视频仓库
 * 从 lp_media_asset 查询视频，支持按 persona 随机和按关键词随机
 */
final class PlaylistRepository
{
    public function __construct(
        private readonly Database $database,
        private readonly string $mediaBaseDir = '',
        private readonly string $ffprobeBin = 'ffprobe'
    ) {
    }

    /**
     * 用 ffprobe 获取视频真实时长（秒），失败时回退到 duration_ms
     */
    public function probeDuration(string $filePath): float
    {
        $cmd = sprintf(
            '%s -v error -show_entries format=duration -of csv=p=0 "%s" 2>/dev/null',
            escapeshellarg($this->ffprobeBin),
            str_replace('"', '\"', $filePath)
        );
        $out = @shell_exec($cmd);
        if ($out !== null && is_numeric(trim($out))) {
            return (float) trim($out);
        }
        return 0.0;
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
     * 获取房间专属播单的所有视频（按后台设置的顺序）
     */
    public function roomPlaylistVideos(int $roomId): array
    {
        $pdo = $this->database->pdo();

        // 先诊断各表数据
        $binding = $pdo->prepare('SELECT playlist_template_id, persona FROM lp_room_binding WHERE room_id = :rid');
        $binding->execute(['rid' => $roomId]);
        $bindRow = $binding->fetch();

        if (!$bindRow) {
            fwrite(STDERR, "[PlaylistRepo] 房间 {$roomId} 无 lp_room_binding 记录！\n");
            return [];
        }

        $ptId = (int) ($bindRow['playlist_template_id'] ?? 0);
        fwrite(STDERR, "[PlaylistRepo] room={$roomId} playlist_template_id={$ptId} persona={$bindRow['persona']}\n");

        if ($ptId <= 0) {
            fwrite(STDERR, "[PlaylistRepo] 房间 {$roomId} playlist_template_id 为空！\n");
            return [];
        }

        // 检查播单模板项数量
        $cntStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM lp_playlist_template_item WHERE template_id = :tid');
        $cntStmt->execute(['tid' => $ptId]);
        $cnt = (int) $cntStmt->fetch()['cnt'];
        fwrite(STDERR, "[PlaylistRepo] template_id={$ptId} 有 {$cnt} 个素材条目\n");

        if ($cnt === 0) {
            return [];
        }

        $stmt = $pdo->prepare(
            'SELECT ma.id, ma.asset_code, ma.title, ma.file_url, ma.duration_ms, ma.keywords
             FROM lp_playlist_template_item pti
             JOIN lp_media_asset ma ON ma.id = pti.asset_id
             WHERE pti.template_id = :tid
               AND ma.asset_type = \'video\'
               AND ma.status = 1
             ORDER BY pti.seq ASC, pti.id ASC'
        );
        $stmt->execute(['tid' => $ptId]);
        $rows = $stmt->fetchAll();

        fwrite(STDERR, "[PlaylistRepo] JOIN 查询返回 " . count($rows) . " 个视频\n");
        if (!empty($rows)) {
            foreach ($rows as $i => $r) {
                fwrite(STDERR, "[PlaylistRepo]   [{$i}] id={$r['id']} title={$r['title']} file={$r['file_url']}\n");
            }
        }

        if (empty($rows)) {
            return [];
        }
        return array_map(fn(array $row) => $this->formatRow($row), $rows);
    }

    /**
     * 按 persona + 关键词随机取一个视频
     */
    public function randomVideoByKeyword(string $persona, string $keyword): ?array
    {
        $pdo = $this->database->pdo();
        // 优先按 persona 匹配，找不到则全局匹配（礼物特效不限制 persona）
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
        if ($row) {
            return $this->formatRow($row);
        }

        // persona 匹配不到，全局搜索
        $stmt = $pdo->prepare(
            'SELECT id, asset_code, title, file_url, duration_ms, keywords
             FROM lp_media_asset
             WHERE asset_type = \'video\' AND status = 1
               AND FIND_IN_SET(:kw, REPLACE(keywords, \' \', \'\')) > 0
             ORDER BY RAND()
             LIMIT 1'
        );
        $stmt->execute(['kw' => $keyword]);
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
        // 相对路径：拼接 media_base_dir 前缀
        if ($this->mediaBaseDir !== '') {
            return $this->mediaBaseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fileUrl);
        }
        return $fileUrl;
    }
}
