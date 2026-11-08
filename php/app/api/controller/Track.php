<?php
declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use app\live\service\TrackService;

/**
 * 行为埋点采集（公开接口，前端 tracking.js 批量上报）
 *
 * - POST /api/v1/track/collect
 *   body: {"events":[{type:'pv'|'event'|'leave', ...}, ...]}
 *   兼容 navigator.sendBeacon 发送的 Blob（text/plain / application/json）
 */
final class Track extends BaseController
{
    public function collect()
    {
        // 优先原始 JSON 解码：项目全局 input filter（app/Request）会把 JSON 里的引号转成 HTML 实体，
        // 埋点字段必须存原文。表单编码（少数兜底场景）才回落到 post()。
        $content = (string) $this->request->getContent();
        $decoded = $content !== '' ? json_decode($content, true) : null;
        if (is_array($decoded)) {
            $payload = $decoded;
        } else {
            $payload = $this->request->post();
            if (!is_array($payload)) {
                $payload = [];
            }
        }

        $events = $payload['events'] ?? [];
        if (!is_array($events)) {
            $events = [];
        }

        // 批次上下文：vid/sid/domain 在批次顶层，单条事件缺省时回退使用
        $context = [
            'vid'    => is_scalar($payload['vid'] ?? '') ? (string) $payload['vid'] : '',
            'sid'    => is_scalar($payload['sid'] ?? '') ? (string) $payload['sid'] : '',
            'domain' => is_scalar($payload['domain'] ?? '') ? (string) $payload['domain'] : '',
        ];

        $count = 0;
        try {
            $count = TrackService::collect(
                $events,
                (string) $this->request->ip(),
                (string) $this->request->header('user-agent', ''),
                $context
            );
        } catch (\Throwable $e) {
            // 统计失败不影响页面
        }

        return $this->jsonSuccess(['hit' => $count]);
    }
}
