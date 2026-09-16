<?php
declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use think\facade\Db;

/**
 * AI 女友端：Posts 动态
 *
 * 动态由后台「直播运营 → 动态管理」配置，前台 posts 页读取：
 *   GET /api/live/posts  动态列表（只返回已上架）
 */
final class Posts extends BaseController
{
    private const FIELDS = 'id, post_id, character_name, character_avatar, character_url, video_url, poster_url, description, likes, views';

    private function table()
    {
        return Db::connect('live_mysql');
    }

    /**
     * 动态列表（公开）
     */
    public function items()
    {
        $limit = (int) $this->request->get('limit/d', 100);
        $limit = max(1, min(200, $limit));

        $rows = $this->table()->table('lp_post_item')
            ->where('status', 1)
            ->field(self::FIELDS)
            ->order('weigh', 'desc')
            ->order('id', 'asc')
            ->limit($limit)
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $row) {
            $list[] = $this->formatItem($row);
        }

        return $this->jsonSuccess(['list' => $list]);
    }

    private function formatItem(array $row): array
    {
        return [
            'id'               => (int) $row['id'],
            'post_id'          => (string) $row['post_id'],
            'character_name'   => (string) $row['character_name'],
            'character_avatar' => $this->absoluteUrl((string) $row['character_avatar']),
            'character_url'    => (string) $row['character_url'],
            'video_url'        => (string) $row['video_url'],
            'poster_url'       => $this->absoluteUrl((string) $row['poster_url']),
            'description'      => (string) $row['description'],
            'likes'            => max(0, (int) $row['likes']),
            'views'            => max(0, (int) $row['views']),
        ];
    }

    /**
     * 相对路径补全为完整 URL，方便前端直接加载
     */
    private function absoluteUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, 'http') || str_starts_with($url, '//')) {
            return $url;
        }

        // 站内资源返回根相对路径，由浏览器按当前域名+端口解析；
        // 反代 / 非 80 端口部署时不能拼 request->domain()，否则会丢端口导致图片被 ORB 拦截
        return '/' . ltrim($url, '/');
    }
}
