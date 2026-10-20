<?php
declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use app\common\web\ResultCode;
use think\facade\Db;

/**
 * AI 女友端：观看历史（首页 / 短剧页 / 私密内容页的 Continue watching）
 *
 *   POST /api/live/watchReport   上报观看进度（需登录）
 *   GET  /api/live/watchHistory  读取当前用户最近观看（公开接口，未登录返回空列表）
 *
 * 每个用户对每部短剧 / 每份私密内容只保留一条记录，重复观看时更新进度与时间。
 */
final class WatchHistory extends BaseController
{
    protected array $middleware = [
        \app\live\middleware\Auth::class => ['only' => ['report']],
    ];

    private function db()
    {
        return Db::connect('live_mysql');
    }

    /**
     * 上报观看进度
     * 参数：type=short|private, id, episode_id（短剧可选）, progress(0~100), position, duration
     */
    public function report()
    {
        $userId = $this->getAuthUserId();
        $type   = strtolower(trim((string) $this->request->post('type', '')));
        if (!in_array($type, ['short', 'private'], true)) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Invalid content type.');
        }

        $id = (int) $this->request->post('id/d', 0);
        if ($id <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'id 不能为空');
        }

        $db        = $this->db();
        $episodeId = 0;
        $episodeNo = 0;

        if ($type === 'short') {
            $row = $db->table('lp_short_item')->where('id', $id)->where('status', 1)->find();
            if (!$row) {
                return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, 'Content not found or removed.');
            }
            $title  = (string) $row['title'];
            $poster = (string) $row['poster'];

            $episodeId = (int) $this->request->post('episode_id/d', 0);
            if ($episodeId > 0) {
                $episode = $db->table('lp_short_episode')
                    ->where('id', $episodeId)->where('short_id', $id)->find();
                if ($episode) {
                    $episodeNo = (int) ($episode['episode_no'] ?? 0);
                    if ((string) ($episode['poster'] ?? '') !== '') {
                        $poster = (string) $episode['poster'];
                    }
                } else {
                    $episodeId = 0;
                }
            }
        } else {
            $row = $db->table('lp_private_item')->where('id', $id)->where('status', 1)->find();
            if (!$row) {
                return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, 'Content not found or removed.');
            }
            $title  = (string) $row['title'];
            $poster = (string) $row['poster'];
        }

        $position = max(0, (int) $this->request->post('position/d', 0));
        $duration = max(0, (int) $this->request->post('duration/d', 0));
        $progress = (float) $this->request->post('progress/f', 0);
        // 有时长时以播放位置为准，避免前端进度抖动
        if ($duration > 0) {
            $progress = ($position / $duration) * 100;
        }
        $progress = max(0.0, min(100.0, $progress));

        $now = date('Y-m-d H:i:s');
        $db->execute(
            'INSERT INTO `lp_user_watch_history`
               (`user_id`, `content_type`, `content_id`, `episode_id`, `episode_no`,
                `title`, `poster`, `progress`, `position`, `duration`, `created_at`, `updated_at`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               `episode_id` = VALUES(`episode_id`),
               `episode_no` = VALUES(`episode_no`),
               `title`      = VALUES(`title`),
               `poster`     = VALUES(`poster`),
               `progress`   = VALUES(`progress`),
               `position`   = VALUES(`position`),
               `duration`   = VALUES(`duration`),
               `updated_at` = VALUES(`updated_at`)',
            [$userId, $type, $id, $episodeId, $episodeNo, $title, $poster, $progress, $position, $duration, $now, $now]
        );

        return $this->jsonSuccess([
            'type'       => $type,
            'id'         => $id,
            'episode_id' => $episodeId,
            'progress'   => $progress,
        ]);
    }

    /**
     * 当前用户最近观看（按更新时间倒序）
     * 参数：type=all|short|private, limit（默认 20，最大 50）
     */
    public function items()
    {
        $userId = $this->optionalAuthUserId();
        if ($userId <= 0) {
            return $this->jsonSuccess(['list' => []]);
        }

        $type = strtolower(trim((string) $this->request->get('type', 'all')));
        $limit = (int) $this->request->get('limit/d', 20);
        $limit = max(1, min(50, $limit));

        $db = $this->db();
        $query = $db->table('lp_user_watch_history')->where('user_id', $userId);
        if (in_array($type, ['short', 'private'], true)) {
            $query->where('content_type', $type);
        }

        $rows = $query->order('updated_at', 'desc')->order('id', 'desc')->limit($limit)->select()->toArray();
        if (!$rows) {
            return $this->jsonSuccess(['list' => []]);
        }

        // 过滤已下架/删除的内容，避免首页出现点不开的死卡片
        $shortIds = $privateIds = [];
        foreach ($rows as $row) {
            if ($row['content_type'] === 'short') {
                $shortIds[] = (int) $row['content_id'];
            } else {
                $privateIds[] = (int) $row['content_id'];
            }
        }
        $valid = [];
        if ($shortIds) {
            foreach ($db->table('lp_short_item')->whereIn('id', $shortIds)->where('status', 1)->column('id') as $sid) {
                $valid['short:' . (int) $sid] = true;
            }
        }
        if ($privateIds) {
            foreach ($db->table('lp_private_item')->whereIn('id', $privateIds)->where('status', 1)->column('id') as $pid) {
                $valid['private:' . (int) $pid] = true;
            }
        }

        $list = [];
        foreach ($rows as $row) {
            $key = $row['content_type'] . ':' . (int) $row['content_id'];
            if (!isset($valid[$key])) {
                continue;
            }
            $list[] = [
                'type'       => (string) $row['content_type'],
                'id'         => (int) $row['content_id'],
                'episode_id' => (int) $row['episode_id'],
                'episode_no' => (int) $row['episode_no'],
                'title'      => (string) $row['title'],
                'poster'     => $this->mediaUrl((string) $row['poster']),
                'progress'   => (float) $row['progress'],
                'position'   => (int) $row['position'],
                'duration'   => (int) $row['duration'],
                'updated_at' => (string) $row['updated_at'],
            ];
        }

        return $this->jsonSuccess(['list' => $list]);
    }

    /**
     * 站内相对路径补全（与前台其它接口保持一致，不能用 request->domain() 以免丢端口）
     */
    private function mediaUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, 'http') || str_starts_with($url, '//')) {
            return $url;
        }
        return '/' . ltrim($url, '/');
    }
}
