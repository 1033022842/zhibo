<?php
declare(strict_types=1);

namespace app\ai\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

/**
 * LivePortrait 直播端服务
 *
 * 新策略：直播端由 AI 电脑用 LivePortrait 实时推理推流，只需单张立绘 + 模特动作模板。
 * 本服务向 AI 端提供：
 *   1. config()        — 房间立绘 + 动作模板库 + 公共流 RTMP 推流参数
 *   2. instructions()  — 实时动作指令（礼物关键词 → 动作模板），供 LivePortrait 实时切换动作
 */
final class LivePortraitService
{
    private const INSTRUCTION_LIST_PREFIX = 'list:keyword:room:';
    private const MAX_INSTRUCTIONS_PER_PULL = 20;

    /**
     * 获取房间的 LivePortrait 配置
     */
    public function config(int $roomId): array
    {
        $conn = Db::connect('live_mysql');

        $room = $conn->table('lp_room')->where('id', $roomId)->find();
        if (!$room) {
            throw new BusinessException(ResultCode::ROOM_NOT_FOUND, "房间 {$roomId} 不存在");
        }

        $persona = null;
        $personaId = (int)($room['persona_id'] ?? 0);
        if ($personaId > 0) {
            $persona = $conn->table('lp_persona')->where('id', $personaId)->find();
        }

        $binding = $conn->table('lp_room_binding')->where('room_id', $roomId)->find();
        $personaName = trim((string)($binding['persona'] ?? ''));
        if ($personaName === '') {
            $personaName = trim((string)($persona['name'] ?? ''));
        }

        return [
            'room_id'      => $roomId,
            'persona'      => $personaName,
            'room_title'   => (string)($room['title'] ?? ''),
            'driving_mode' => 'liveportrait',
            'portrait'     => $this->resolvePortrait($conn, $binding, $persona),
            'motions'      => $this->listMotions($conn, $personaName),
            'stream'       => $this->publicStreamToken($roomId),
        ];
    }

    /**
     * 拉取实时动作指令（礼物关键词 → 动作模板），供 AI 端实时切换 LivePortrait 动作
     */
    public function instructions(int $roomId): array
    {
        $instructions = [];

        try {
            $redis = Cache::store('redis')->handler();
            $listKey = self::INSTRUCTION_LIST_PREFIX . $roomId;

            for ($i = 0; $i < self::MAX_INSTRUCTIONS_PER_PULL; $i++) {
                $payload = $redis->lPop($listKey);
                if ($payload === false || $payload === null) {
                    break;
                }

                $data = json_decode((string)$payload, true);
                if (!is_array($data)) {
                    continue;
                }

                $keyword = trim((string)($data['params']['keyword'] ?? ''));
                if ($keyword === '') {
                    continue;
                }

                $instructions[] = [
                    'keyword' => $keyword,
                    'motion'  => $this->motionByKeyword($roomId, $keyword),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("LivePortraitService: instructions failed for room {$roomId}: {$e->getMessage()}");
        }

        return [
            'room_id'      => $roomId,
            'instructions' => $instructions,
        ];
    }

    /**
     * 解析单张立绘：优先房间绑定专属立绘素材，否则回退人设封面
     */
    private function resolvePortrait($conn, ?array $binding, ?array $persona): array
    {
        $portraitAssetId = (int)($binding['portrait_asset_id'] ?? 0);
        if ($portraitAssetId > 0) {
            $asset = $conn->table('lp_media_asset')->where('id', $portraitAssetId)->find();
            if ($asset && trim((string)($asset['file_url'] ?? '')) !== '') {
                return [
                    'source'    => 'portrait_asset',
                    'asset_id'  => (int)$asset['id'],
                    'url'       => (string)$asset['file_url'],
                ];
            }
        }

        return [
            'source' => 'persona_cover',
            'url'    => (string)($persona['cover_url'] ?? ''),
        ];
    }

    /**
     * 列出动作模板库（asset_role=motion），优先该人设，无人设专属时返回全局
     */
    private function listMotions($conn, string $personaName): array
    {
        $query = $conn->table('lp_media_asset')
            ->where('asset_role', 'motion')
            ->where('status', 1);

        if ($personaName !== '') {
            $query->where('persona', $personaName);
        }

        $rows = $query->field(['id', 'title', 'file_url', 'duration_ms', 'keywords'])
            ->order('id', 'asc')
            ->select()
            ->toArray();

        // 人设专属动作为空时回退到全局动作模板
        if (empty($rows) && $personaName !== '') {
            $rows = $conn->table('lp_media_asset')
                ->where('asset_role', 'motion')
                ->where('status', 1)
                ->field(['id', 'title', 'file_url', 'duration_ms', 'keywords'])
                ->order('id', 'asc')
                ->select()
                ->toArray();
        }

        return array_map(fn(array $r) => $this->formatMotion($r), $rows);
    }

    /**
     * 关键词 → 动作模板（优先该人设，找不到则全局）
     */
    private function motionByKeyword(int $roomId, string $keyword): ?array
    {
        $conn = Db::connect('live_mysql');

        $binding = $conn->table('lp_room_binding')->where('room_id', $roomId)->find();
        $personaName = trim((string)($binding['persona'] ?? ''));

        $motion = null;
        if ($personaName !== '') {
            $motion = $conn->table('lp_media_asset')
                ->where('asset_role', 'motion')
                ->where('status', 1)
                ->where('persona', $personaName)
                ->whereRaw('FIND_IN_SET(?, REPLACE(keywords, \' \', \'\')) > 0', [$keyword])
                ->field(['id', 'title', 'file_url', 'duration_ms', 'keywords'])
                ->orderRaw('RAND()')
                ->limit(1)
                ->find();
        }

        if (!$motion) {
            $motion = $conn->table('lp_media_asset')
                ->where('asset_role', 'motion')
                ->where('status', 1)
                ->whereRaw('FIND_IN_SET(?, REPLACE(keywords, \' \', \'\')) > 0', [$keyword])
                ->field(['id', 'title', 'file_url', 'duration_ms', 'keywords'])
                ->orderRaw('RAND()')
                ->limit(1)
                ->find();
        }

        return $motion ? $this->formatMotion($motion) : null;
    }

    private function formatMotion(array $r): array
    {
        return [
            'asset_id'    => (int)$r['id'],
            'title'       => (string)$r['title'],
            'keywords'    => (string)($r['keywords'] ?? ''),
            'file_url'    => (string)$r['file_url'],
            'duration_ms' => (int)($r['duration_ms'] ?? 0),
        ];
    }

    /**
     * 公共流 RTMP 推流参数（AI 端 LivePortrait 实时推理后推此地址）
     */
    private function publicStreamToken(int $roomId): array
    {
        $pushUrl = rtrim((string)config('ai.stream.rtmp_push_url', 'rtmp://127.0.0.1:1935/live/'), '/');
        $streamAlias = 'room/' . $roomId;
        $expireAt = time() + 3600;
        $token = hash_hmac('sha256', $streamAlias . '|' . $expireAt, (string)config('jwt.secret'));

        return [
            'stream_alias' => $streamAlias,
            'push_url'     => $pushUrl . '/' . $streamAlias,
            'play_hls'     => '/hls/' . $streamAlias . '.m3u8',
            'token'        => $token,
            'expire_at'    => $expireAt,
        ];
    }
}
