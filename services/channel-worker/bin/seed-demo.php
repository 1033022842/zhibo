<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/channel_worker.php';
$demoAssets = $config['seed_demo']['assets'] ?? [];
if ($demoAssets === []) {
    throw new RuntimeException('seed_demo.assets 未配置');
}
foreach ($demoAssets as $assetKey => $demoAsset) {
    $localAssetPath = $demoAsset['local_path'] ?? $demoAsset['file_url'] ?? '';
    if (!is_string($localAssetPath) || $localAssetPath === '') {
        throw new RuntimeException("seed_demo.assets.{$assetKey}.local_path 未配置");
    }

    $assetDir = dirname($localAssetPath);
    if (!is_dir($assetDir)) {
        mkdir($assetDir, 0777, true);
    }

    if (!is_file($localAssetPath) || filesize($localAssetPath) === 0) {
        $sourceUrl = (string) ($demoAsset['source_url'] ?? '');
        if ($sourceUrl === '') {
            throw new RuntimeException("seed_demo.assets.{$assetKey}.source_url 未配置");
        }

        $content = @file_get_contents($sourceUrl);
        if ($content === false || $content === '') {
            throw new RuntimeException('下载 demo 素材失败：' . $sourceUrl);
        }

        file_put_contents($localAssetPath, $content);
    }
}

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        getenv('DB_HOST') ?: '127.0.0.1',
        (int) (getenv('DB_PORT') ?: 3306),
        getenv('DB_NAME') ?: 'live_platform',
        getenv('DB_CHARSET') ?: 'utf8mb4'
    ),
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASSWORD') ?: 'root',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$demoRooms = [
    [
            'asset_key' => 'room1',
        'room_no' => 'R1001',
        'title' => '深夜情感电台',
        'subtitle' => '陪你聊天到天亮',
        'sort' => 120,
        'cover_url' => 'https://picsum.photos/seed/live-room-1/720/1280',
        'persona' => [
            'code' => 'persona_night_radio',
            'name' => '夜聊陪伴',
            'tags' => '温柔,陪伴,夜间',
            'cover_url' => 'https://picsum.photos/seed/persona-night/320/320',
        ],
        'tags' => ['热门', '情感'],
    ],
    [
            'asset_key' => 'room2',
        'room_no' => 'R1002',
        'title' => '午后轻音乐直播间',
        'subtitle' => '循环播放舒缓歌单和聊天互动',
        'sort' => 110,
        'cover_url' => 'https://picsum.photos/seed/live-room-2/720/1280',
        'persona' => [
            'code' => 'persona_light_music',
            'name' => '轻音陪伴',
            'tags' => '轻音乐,放松,陪伴',
            'cover_url' => 'https://picsum.photos/seed/persona-music/320/320',
        ],
        'tags' => ['轻音乐', '放松'],
    ],
    [
            'asset_key' => 'room3',
        'room_no' => 'R1003',
        'title' => '清晨自习直播间',
        'subtitle' => '适合切后台挂机的专注陪伴流',
        'sort' => 100,
        'cover_url' => 'https://picsum.photos/seed/live-room-3/720/1280',
        'persona' => [
            'code' => 'persona_focus_study',
            'name' => '专注搭子',
            'tags' => '学习,专注,清晨',
            'cover_url' => 'https://picsum.photos/seed/persona-study/320/320',
        ],
        'tags' => ['学习', '专注'],
    ],
];

$pdo->beginTransaction();

try {
    $assetSelect = $pdo->prepare('SELECT id FROM lp_media_asset WHERE asset_code = :asset_code LIMIT 1');
    $assetInsert = $pdo->prepare(
        'INSERT INTO lp_media_asset (asset_code, asset_type, scene_type, title, file_url, duration_ms, status)
         VALUES (:asset_code, :asset_type, :scene_type, :title, :file_url, :duration_ms, :status)'
    );
    $assetUpdate = $pdo->prepare(
        'UPDATE lp_media_asset
         SET title = :title, file_url = :file_url, duration_ms = :duration_ms, status = :status
         WHERE id = :id'
    );

    $assetIds = [];
    foreach ($demoAssets as $assetKey => $demoAsset) {
        $assetSelect->execute(['asset_code' => $demoAsset['asset_code']]);
        $assetId = $assetSelect->fetchColumn();
        if (!$assetId) {
            $assetInsert->execute([
                'asset_code' => $demoAsset['asset_code'],
                'asset_type' => 'video',
                'scene_type' => 'public',
                'title' => $demoAsset['title'],
                'file_url' => $demoAsset['file_url'],
                'duration_ms' => $demoAsset['duration_ms'],
                'status' => 1,
            ]);
            $assetId = (int) $pdo->lastInsertId();
        } else {
            $assetId = (int) $assetId;
            $assetUpdate->execute([
                'id' => $assetId,
                'title' => $demoAsset['title'],
                'file_url' => $demoAsset['file_url'],
                'duration_ms' => $demoAsset['duration_ms'],
                'status' => 1,
            ]);
        }
        $assetIds[$assetKey] = $assetId;
    }

    $streamTemplateId = (int) $pdo->query("SELECT id FROM lp_stream_template WHERE template_code = 'default_live' ORDER BY id ASC LIMIT 1")->fetchColumn();
    $roomGroupId = $pdo->query("SELECT room_group_id FROM lp_room_binding WHERE room_id = 1 LIMIT 1")->fetchColumn();
    $roomGroupId = $roomGroupId !== false ? (int) $roomGroupId : null;

    $personaSelect = $pdo->prepare('SELECT id FROM lp_persona WHERE code = :code LIMIT 1');
    $personaInsert = $pdo->prepare(
        'INSERT INTO lp_persona (code, name, tags, cover_url, status)
         VALUES (:code, :name, :tags, :cover_url, 1)'
    );
    $personaUpdate = $pdo->prepare(
        'UPDATE lp_persona
         SET name = :name, tags = :tags, cover_url = :cover_url, status = 1
         WHERE id = :id'
    );

    $roomSelect = $pdo->prepare('SELECT id FROM lp_room WHERE room_no = :room_no LIMIT 1');
    $roomInsert = $pdo->prepare(
        'INSERT INTO lp_room (room_no, title, subtitle, persona_id, room_type, status, cover_url, sort)
         VALUES (:room_no, :title, :subtitle, :persona_id, :room_type, :status, :cover_url, :sort)'
    );
    $roomUpdate = $pdo->prepare(
        'UPDATE lp_room
         SET title = :title, subtitle = :subtitle, persona_id = :persona_id, room_type = :room_type, status = :status, cover_url = :cover_url, sort = :sort
         WHERE id = :id'
    );

    $bindingSelect = $pdo->prepare('SELECT id FROM lp_room_binding WHERE room_id = :room_id LIMIT 1');
    $bindingInsert = $pdo->prepare(
        'INSERT INTO lp_room_binding (room_id, room_group_id, stream_template_id, playlist_template_id)
         VALUES (:room_id, :room_group_id, :stream_template_id, :playlist_template_id)'
    );
    $bindingUpdate = $pdo->prepare(
        'UPDATE lp_room_binding
         SET room_group_id = :room_group_id, stream_template_id = :stream_template_id, playlist_template_id = :playlist_template_id
         WHERE room_id = :room_id'
    );

    $playlistSelect = $pdo->prepare('SELECT id FROM lp_playlist_template WHERE template_code = :template_code LIMIT 1');
    $playlistInsert = $pdo->prepare(
        'INSERT INTO lp_playlist_template (template_code, name, mode, status)
         VALUES (:template_code, :name, :mode, 1)'
    );
    $playlistUpdate = $pdo->prepare(
        'UPDATE lp_playlist_template
         SET name = :name, mode = :mode, status = 1
         WHERE id = :id'
    );
    $playlistItemDelete = $pdo->prepare('DELETE FROM lp_playlist_template_item WHERE template_id = :template_id');
    $playlistItemInsert = $pdo->prepare(
        'INSERT INTO lp_playlist_template_item (template_id, asset_id, seq, loop_count, weight, start_offset_ms)
         VALUES (:template_id, :asset_id, 1, 1, 1, 0)'
    );

    $deleteTags = $pdo->prepare('DELETE FROM lp_room_tag WHERE room_id = :room_id');
    $insertTag = $pdo->prepare('INSERT INTO lp_room_tag (room_id, tag_name) VALUES (:room_id, :tag_name)');

    $seededRooms = [];
    foreach ($demoRooms as $demoRoom) {
        $persona = $demoRoom['persona'];

        $personaSelect->execute(['code' => $persona['code']]);
        $personaId = $personaSelect->fetchColumn();
        if (!$personaId) {
            $personaInsert->execute([
                'code' => $persona['code'],
                'name' => $persona['name'],
                'tags' => $persona['tags'],
                'cover_url' => $persona['cover_url'],
            ]);
            $personaId = (int) $pdo->lastInsertId();
        } else {
            $personaId = (int) $personaId;
            $personaUpdate->execute([
                'id' => $personaId,
                'name' => $persona['name'],
                'tags' => $persona['tags'],
                'cover_url' => $persona['cover_url'],
            ]);
        }

        $roomSelect->execute(['room_no' => $demoRoom['room_no']]);
        $roomId = $roomSelect->fetchColumn();
        if (!$roomId) {
            $roomInsert->execute([
                'room_no' => $demoRoom['room_no'],
                'title' => $demoRoom['title'],
                'subtitle' => $demoRoom['subtitle'],
                'persona_id' => $personaId,
                'room_type' => 'live',
                'status' => 1,
                'cover_url' => $demoRoom['cover_url'],
                'sort' => $demoRoom['sort'],
            ]);
            $roomId = (int) $pdo->lastInsertId();
        } else {
            $roomId = (int) $roomId;
            $roomUpdate->execute([
                'id' => $roomId,
                'title' => $demoRoom['title'],
                'subtitle' => $demoRoom['subtitle'],
                'persona_id' => $personaId,
                'room_type' => 'live',
                'status' => 1,
                'cover_url' => $demoRoom['cover_url'],
                'sort' => $demoRoom['sort'],
            ]);
        }

        $playlistCode = 'playlist_public_demo_room_' . $roomId;
        $playlistName = $demoRoom['title'] . '播单';
        $playlistSelect->execute(['template_code' => $playlistCode]);
        $playlistId = $playlistSelect->fetchColumn();
        if (!$playlistId) {
            $playlistInsert->execute([
                'template_code' => $playlistCode,
                'name' => $playlistName,
                'mode' => 'public',
            ]);
            $playlistId = (int) $pdo->lastInsertId();
        } else {
            $playlistId = (int) $playlistId;
            $playlistUpdate->execute([
                'id' => $playlistId,
                'name' => $playlistName,
                'mode' => 'public',
            ]);
        }

        $playlistItemDelete->execute(['template_id' => $playlistId]);
        $playlistItemInsert->execute([
            'template_id' => $playlistId,
            'asset_id' => $assetIds[$demoRoom['asset_key']],
        ]);

        $bindingSelect->execute(['room_id' => $roomId]);
        if ($bindingSelect->fetchColumn()) {
            $bindingUpdate->execute([
                'room_id' => $roomId,
                'room_group_id' => $roomGroupId,
                'stream_template_id' => $streamTemplateId,
                'playlist_template_id' => $playlistId,
            ]);
        } else {
            $bindingInsert->execute([
                'room_id' => $roomId,
                'room_group_id' => $roomGroupId,
                'stream_template_id' => $streamTemplateId,
                'playlist_template_id' => $playlistId,
            ]);
        }

        $deleteTags->execute(['room_id' => $roomId]);
        foreach ($demoRoom['tags'] as $tagName) {
            $insertTag->execute([
                'room_id' => $roomId,
                'tag_name' => $tagName,
            ]);
        }

        $seededRooms[] = [
            'room_id' => $roomId,
            'room_no' => $demoRoom['room_no'],
            'title' => $demoRoom['title'],
            'persona_id' => $personaId,
            'asset_code' => $demoAssets[$demoRoom['asset_key']]['asset_code'],
            'playlist_template_id' => $playlistId,
            'stream_template_id' => $streamTemplateId,
        ];
    }

    $pdo->commit();
    echo json_encode([
        'stream_template_id' => $streamTemplateId,
        'assets' => $assetIds,
        'rooms' => $seededRooms,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
