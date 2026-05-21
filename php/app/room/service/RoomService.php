<?php
declare(strict_types=1);

namespace app\room\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\room\model\Persona;
use app\room\model\Room;
use app\room\model\RoomBinding;
use app\room\model\RoomGroup;
use app\room\model\RoomTag;
use think\facade\Db;

final class RoomService
{
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 20;

    public function feedLive(?string $cursor, int $limit, string $domain): array
    {
        $limit = max(1, min($limit, self::MAX_LIMIT));
        $query = Room::where('status', 1)
            ->order('sort', 'desc')
            ->order('id', 'desc');

        $cursorData = $this->decodeCursor($cursor);
        if ($cursorData) {
            $query->where(function ($query) use ($cursorData) {
                $query->where('sort', '<', $cursorData['sort'])
                    ->whereOr(function ($query) use ($cursorData) {
                        $query->where('sort', '=', $cursorData['sort'])
                            ->where('id', '<', $cursorData['id']);
                    });
            });
        }

        $rows = $query->limit($limit + 1)->select()->toArray();
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $roomIds = array_column($rows, 'id');
        $personaIds = array_values(array_unique(array_column($rows, 'persona_id')));

        $personas = $this->loadPersonas($personaIds);
        $tags = $this->loadRoomTags($roomIds);
        [$bindings, $groups, $streamTemplates, $previewVideos] = $this->loadBindingContext($roomIds, $domain);
        $giftPanel = $this->loadGiftPanel();

        $list = [];
        foreach ($rows as $row) {
            $list[] = $this->formatRoom(
                $row,
                $personas[$row['persona_id']] ?? null,
                $tags[$row['id']] ?? [],
                $bindings[$row['id']] ?? null,
                $groups,
                $streamTemplates,
                $previewVideos,
                $giftPanel,
                $domain
            );
        }

        $nextCursor = null;
        if ($hasMore && !empty($rows)) {
            $last = end($rows);
            $nextCursor = $this->encodeCursor((int) $last['sort'], (int) $last['id']);
        }

        return [
            'list'     => $list,
            'cursor'   => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    public function detail(int $roomId, string $domain): array
    {
        $room = Room::find($roomId);
        if (!$room) {
            throw new BusinessException(ResultCode::ROOM_NOT_FOUND);
        }

        $roomData = $room->toArray();
        $personas = $this->loadPersonas([(int) $roomData['persona_id']]);
        $tags = $this->loadRoomTags([$roomId]);
        [$bindings, $groups, $streamTemplates, $previewVideos] = $this->loadBindingContext([$roomId], $domain);
        $giftPanel = $this->loadGiftPanel();

        $result = $this->formatRoom(
            $roomData,
            $personas[$roomData['persona_id']] ?? null,
            $tags[$roomId] ?? [],
            $bindings[$roomId] ?? null,
            $groups,
            $streamTemplates,
            $previewVideos,
            $giftPanel,
            $domain
        );

        $binding = $bindings[$roomId] ?? null;
        $group = $binding && !empty($binding['room_group_id']) ? ($groups[$binding['room_group_id']] ?? null) : null;
        $streamTemplate = $binding ? ($streamTemplates[$binding['stream_template_id']] ?? null) : null;

        $result['tags'] = $tags[$roomId] ?? [];
        $result['binding'] = [
            'room_group_id'        => $binding['room_group_id'] ?? null,
            'source_group_code'    => $group['source_group_code'] ?? '',
            'stream_template_id'   => $binding['stream_template_id'] ?? null,
            'playlist_template_id' => $binding['playlist_template_id'] ?? null,
            'stream_template_code' => $streamTemplate['template_code'] ?? '',
        ];

        return $result;
    }

    private function loadPersonas(array $personaIds): array
    {
        if (empty($personaIds)) {
            return [];
        }

        return Persona::whereIn('id', $personaIds)->column('*', 'id');
    }

    private function loadRoomTags(array $roomIds): array
    {
        if (empty($roomIds)) {
            return [];
        }

        $rows = RoomTag::whereIn('room_id', $roomIds)
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $tags = [];
        foreach ($rows as $row) {
            $tags[$row['room_id']][] = $row['tag_name'];
        }

        return $tags;
    }

    private function loadBindingContext(array $roomIds, string $domain): array
    {
        if (empty($roomIds)) {
            return [[], [], [], []];
        }

        $bindings = RoomBinding::whereIn('room_id', $roomIds)->select()->toArray();
        $bindingMap = [];
        $groupIds = [];
        $streamTemplateIds = [];

        foreach ($bindings as $binding) {
            $bindingMap[$binding['room_id']] = $binding;
            if (!empty($binding['room_group_id'])) {
                $groupIds[] = (int) $binding['room_group_id'];
            }
            if (!empty($binding['stream_template_id'])) {
                $streamTemplateIds[] = (int) $binding['stream_template_id'];
            }
        }

        $groups = [];
        if (!empty($groupIds)) {
            $groups = RoomGroup::whereIn('id', array_values(array_unique($groupIds)))->column('*', 'id');
        }

        $streamTemplates = [];
        if (!empty($streamTemplateIds)) {
            $streamTemplates = Db::connect('live_mysql')
                ->table('lp_stream_template')
                ->whereIn('id', array_values(array_unique($streamTemplateIds)))
                ->column('*', 'id');
        }

        $previewVideos = $this->loadPreviewVideos($bindings, $domain);

        return [$bindingMap, $groups, $streamTemplates, $previewVideos];
    }

    private function formatRoom(
        array $room,
        ?array $persona,
        array $roomTags,
        ?array $binding,
        array $groups,
        array $streamTemplates,
        array $previewVideos,
        array $giftPanel,
        string $domain
    ): array {
        $streamTemplate = $binding ? ($streamTemplates[$binding['stream_template_id']] ?? null) : null;
        $prefix = $streamTemplate['stream_alias_prefix'] ?? 'room';
        $webrtcApp = $streamTemplate['webrtc_app'] ?? 'live';
        $streamAlias = $prefix . '/' . $room['id'];
        $expireAt = time() + 3600;
        $httpBase = rtrim($domain, '/');
        $authority = $this->domainAuthority($domain);
        $group = $binding && !empty($binding['room_group_id']) ? ($groups[$binding['room_group_id']] ?? null) : null;
        $personaTags = $this->splitTags($persona['tags'] ?? '');

        return [
            'room_id'           => (int) $room['id'],
            'room_no'           => $room['room_no'],
            'title'             => $room['title'],
            'subtitle'          => $room['subtitle'],
            'status'            => $this->mapRoomStatus((int) $room['status']),
            'sort_score'        => (int) $room['sort'],
            'cover_url'         => $room['cover_url'],
            'preview_video_url' => $previewVideos[(int) $room['id']] ?? '',
            'persona'           => [
                'id'   => (int) ($persona['id'] ?? 0),
                'name' => $persona['name'] ?? '',
                'tags' => $personaTags,
            ],
            'display'           => [
                'badge_text'  => (int) $room['sort'] > 0 ? '热门' : '直播中',
                'online_text' => $this->formatCountText(0),
                'like_text'   => $this->formatCountText(0),
            ],
            'state'             => [
                'mode'                 => 'public',
                'privilege_active'     => false,
                'privilege_expire_at'  => 0,
                'room_group_code'      => $group['source_group_code'] ?? '',
            ],
            'play'              => [
                'stream_alias' => $streamAlias,
                'webrtc_url'   => 'webrtc://' . $authority . '/' . $webrtcApp . '/' . $streamAlias,
                'hls_url'      => $httpBase . '/hls/' . $streamAlias . '.m3u8',
                'play_token'   => sha1($streamAlias . '|' . $expireAt . '|' . config('jwt.secret')),
                'expire_at'    => $expireAt,
            ],
            'interaction'       => [
                'allow_chat' => true,
                'allow_like' => true,
                'allow_gift' => true,
            ],
            'gift_panel'        => $giftPanel,
            'room_tags'         => array_values($roomTags),
        ];
    }

    private function loadGiftPanel(): array
    {
        $rows = Db::connect('live_mysql')
            ->table('lp_gift')
            ->where('status', 1)
            ->order('price_diamond', 'asc')
            ->order('id', 'asc')
            ->field(['id', 'name', 'price_diamond', 'trigger_mode', 'trigger_duration_sec', 'effect_code'])
            ->limit(8)
            ->select()
            ->toArray();

        $quickGifts = [];
        foreach ($rows as $row) {
            $gift = [
                'gift_id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'price' => (float) $row['price_diamond'],
            ];

            $triggerMode = (string) ($row['trigger_mode'] ?? 'none');
            if ($triggerMode !== '' && $triggerMode !== 'none') {
                $gift['trigger_mode'] = $triggerMode;
            }

            $triggerDurationSec = (int) ($row['trigger_duration_sec'] ?? 0);
            if ($triggerDurationSec > 0) {
                $gift['trigger_duration_sec'] = $triggerDurationSec;
            }

            $effectCode = trim((string) ($row['effect_code'] ?? ''));
            if ($effectCode !== '') {
                $gift['effect_code'] = $effectCode;
            }

            $quickGifts[] = $gift;
        }

        return [
            'currency_name' => '钻石',
            'quick_gifts' => $quickGifts,
        ];
    }

    private function loadPreviewVideos(array $bindings, string $domain): array
    {
        $playlistTemplateIds = [];
        foreach ($bindings as $binding) {
            $playlistTemplateId = (int) ($binding['playlist_template_id'] ?? 0);
            if ($playlistTemplateId > 0) {
                $playlistTemplateIds[] = $playlistTemplateId;
            }
        }

        $playlistTemplateIds = array_values(array_unique($playlistTemplateIds));
        if ($playlistTemplateIds === []) {
            return [];
        }

        $rows = Db::connect('live_mysql')
            ->table('lp_playlist_template_item')
            ->alias('pti')
            ->join('lp_media_asset ma', 'ma.id = pti.asset_id')
            ->whereIn('pti.template_id', $playlistTemplateIds)
            ->where('ma.status', 1)
            ->where('ma.asset_type', 'video')
            ->field(['pti.template_id', 'ma.file_url'])
            ->order('pti.seq', 'asc')
            ->order('pti.id', 'asc')
            ->select()
            ->toArray();

        $templatePreviewUrls = [];
        foreach ($rows as $row) {
            $templateId = (int) ($row['template_id'] ?? 0);
            if ($templateId <= 0 || isset($templatePreviewUrls[$templateId])) {
                continue;
            }

            $previewUrl = $this->normalizePreviewVideoUrl((string) ($row['file_url'] ?? ''), $domain);
            if ($previewUrl === '') {
                continue;
            }

            $templatePreviewUrls[$templateId] = $previewUrl;
        }

        $roomPreviewUrls = [];
        foreach ($bindings as $binding) {
            $roomId = (int) ($binding['room_id'] ?? 0);
            $templateId = (int) ($binding['playlist_template_id'] ?? 0);
            if ($roomId > 0 && $templateId > 0 && isset($templatePreviewUrls[$templateId])) {
                $roomPreviewUrls[$roomId] = $templatePreviewUrls[$templateId];
            }
        }

        return $roomPreviewUrls;
    }

    private function normalizePreviewVideoUrl(string $fileUrl, string $domain): string
    {
        $fileUrl = trim($fileUrl);
        if ($fileUrl === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $fileUrl)) {
            return $fileUrl;
        }

        $normalizedPath = '/' . ltrim(str_replace('\\', '/', $fileUrl), '/');
        if (str_starts_with($normalizedPath, '/storage/')) {
            return rtrim($domain, '/') . $normalizedPath;
        }

        if (str_starts_with($normalizedPath, '/public/storage/')) {
            return rtrim($domain, '/') . '/storage/' . ltrim(substr($normalizedPath, strlen('/public/storage/')), '/');
        }

        $publicStorageRoot = rtrim(str_replace('\\', '/', root_path() . 'public/storage'), '/');
        if (str_starts_with(str_replace('\\', '/', $fileUrl), $publicStorageRoot . '/')) {
            return rtrim($domain, '/') . '/storage/' . ltrim(substr(str_replace('\\', '/', $fileUrl), strlen($publicStorageRoot) + 1), '/');
        }

        return '';
    }

    private function encodeCursor(int $sort, int $id): string
    {
        return rtrim(strtr(base64_encode(json_encode([
            'sort' => $sort,
            'id'   => $id,
        ], JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    }

    private function decodeCursor(?string $cursor): ?array
    {
        if (empty($cursor)) {
            return null;
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        $data = json_decode($decoded, true);
        if (!is_array($data) || !isset($data['sort'], $data['id'])) {
            return null;
        }

        return [
            'sort' => (int) $data['sort'],
            'id'   => (int) $data['id'],
        ];
    }

    private function splitTags(string $tags): array
    {
        if ($tags === '') {
            return [];
        }

        $items = array_map(function (string $tag) {
            $tag = trim($tag);
            $tag = preg_replace('/^\x{FEFF}/u', '', $tag) ?? $tag;
            return preg_replace('/\s+/u', ' ', $tag) ?? $tag;
        }, explode(',', $tags));

        return array_values(array_filter($items, static fn(string $tag) => $tag !== ''));
    }

    private function mapRoomStatus(int $status): string
    {
        return match ($status) {
            1 => 'living',
            2 => 'maintain',
            default => 'offline',
        };
    }

    private function formatCountText(int $count): string
    {
        if ($count >= 10000) {
            return round($count / 10000, 1) . 'w';
        }

        if ($count >= 1000) {
            return round($count / 1000, 1) . 'k';
        }

        return (string) $count;
    }

    private function domainAuthority(string $domain): string
    {
        $host = parse_url($domain, PHP_URL_HOST) ?: '127.0.0.1';
        $port = parse_url($domain, PHP_URL_PORT);

        return $port ? $host . ':' . $port : $host;
    }
}
