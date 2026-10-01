<?php

declare(strict_types=1);

namespace app\common\service;

/**
 * SRS 流媒体服务器 API 封装
 *
 * 架构变更后，AI 电脑用 ffmpeg 推 RTMP 到服务器端 SRS（端口 1935），
 * SRS 转 HLS 由 Nginx 分发。本类封装对 SRS HTTP API（端口 1985）的查询，
 * 用于：
 *  - C 端 feedLive 过滤"真正有推流的房间"（RoomService）
 *  - 后台列表 abnormal 校验：DB 标记推流但 SRS 无流 → 异常（Room 控制器）
 *  - 单房间状态查询（ChannelWorkerManager::status）
 *
 * 部署/配置见 docs/srs-deploy.md
 */
final class SrsService
{
    /** SRS HTTP API 基址（默认本机 1986，可通过环境变量或 .env 覆盖） */
    private string $apiBase;

    public function __construct()
    {
        // 优先系统环境变量；其次 ThinkPHP .env（「共用 CDN」模式下在 .env 指向远端 SRS）
        $host = getenv('SRS_API_HOST');
        $port = getenv('SRS_API_PORT');
        if ((!$host || !$port) && function_exists('env')) {
            $host = $host ?: (string) env('SRS_API_HOST', '');
            $port = $port ?: (string) env('SRS_API_PORT', '');
        }
        $this->apiBase = 'http://' . ($host ?: '127.0.0.1') . ':' . ($port ?: '1986');
    }

    /**
     * 获取 SRS 上当前所有活跃流的原始列表
     *
     * SRS /api/v1/streams/ 返回结构：
     * {"code":0,"server_id":"...","streams":[{"id":N,"name":"5","vhost":"...","app":"room",...}]}
     *
     * @return array<int,array> 流信息数组，每项含 name/app 等字段；失败返回空数组
     */
    public function getStreams(): array
    {
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 2]]);
            $json = @file_get_contents($this->apiBase . '/api/v1/streams/', false, $ctx);
            if (!$json) {
                return [];
            }
            $data = json_decode($json, true);
            if (!is_array($data) || !isset($data['streams']) || !is_array($data['streams'])) {
                return [];
            }
            return $data['streams'];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 获取 SRS 上"有活跃推流"的房间 ID 列表
     *
     * 约定：推流 stream name = 房间 id（AI 电脑推 rtmp://server/room/{id}，
     *      SRS 解析 app=room、name={id}）。匹配 app 为指定前缀（默认 room）。
     *
     * @param string $app RTMP app 名，与 stream_alias_prefix 对齐（默认 room）
     * @return int[] 房间 ID 数组
     */
    public function getActiveRoomIds(string $app = 'room'): array
    {
        $roomIds = [];
        foreach ($this->getStreams() as $item) {
            $name = (string) ($item['name'] ?? '');
            $itemApp = (string) ($item['app'] ?? '');
            if ($itemApp === $app && preg_match('/^\d+$/', $name)) {
                $roomIds[] = (int) $name;
            }
        }
        return $roomIds;
    }

    /**
     * 查询指定房间在 SRS 上是否有活跃推流
     *
     * @param int $roomId 房间 ID
     * @param string $app RTMP app 名（默认 room）
     */
    public function isRoomStreaming(int $roomId, string $app = 'room'): bool
    {
        $target = (string) $roomId;
        foreach ($this->getStreams() as $item) {
            if ((string) ($item['name'] ?? '') === $target
                && (string) ($item['app'] ?? '') === $app) {
                return true;
            }
        }
        return false;
    }
}
