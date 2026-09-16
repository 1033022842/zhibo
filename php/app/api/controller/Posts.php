<?php
declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use app\common\web\ResultCode;
use think\facade\Db;

/**
 * AI 女友端：Posts 动态
 *
 * 动态由后台「直播运营 → 动态管理」配置，前台 posts 页读取：
 *   GET  /api/live/posts     动态列表（公开，带 liked 标记）
 *   POST /api/live/postLike  点赞 / 取消点赞（需登录）
 *
 * lp_post_item.likes 是原站基数，展示值 = 基数 + 本站真实点赞数。
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

        $posts = array_column($rows, 'post_id');
        $likeState = $this->likeState($posts);

        $list = [];
        foreach ($rows as $row) {
            $postId = (string) $row['post_id'];
            $item = $this->formatItem($row);
            $item['likes'] = $item['likes'] + ($likeState[$postId]['count'] ?? 0);
            $item['liked'] = !empty($likeState[$postId]['liked']);
            $list[] = $item;
        }

        return $this->jsonSuccess(['list' => $list]);
    }

    /**
     * 点赞 / 取消点赞（需登录）
     */
    public function like()
    {
        $userId = $this->getAuthUserId();
        $postId = trim((string) $this->request->post('post_id', ''));
        if ($postId === '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'post_id 不能为空');
        }

        $db = $this->table();
        $post = $db->table('lp_post_item')->where('post_id', $postId)->where('status', 1)->find();
        if (!$post) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, '动态不存在或已下架');
        }

        $exists = $db->table('lp_post_like')
            ->where('user_id', $userId)->where('post_id', $postId)
            ->find();

        if ($exists) {
            $db->table('lp_post_like')->where('id', $exists['id'])->delete();
            $liked = false;
        } else {
            $db->table('lp_post_like')->insert([
                'user_id'    => $userId,
                'post_id'    => $postId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $liked = true;
        }

        $count = (int) $db->table('lp_post_like')->where('post_id', $postId)->count();

        return $this->jsonSuccess([
            'post_id' => $postId,
            'liked'   => $liked ? 1 : 0,
            'likes'   => max(0, (int) $post['likes']) + $count,
        ]);
    }

    /**
     * 当前（可选登录）用户的点赞状态：post_id => ['liked' => bool]
     * 同时返回每个帖子的本站真实点赞数
     */
    private function likeState(array $postIds): array
    {
        $postIds = array_values(array_filter(array_map('strval', $postIds), fn ($v) => $v !== ''));
        if (!$postIds) {
            return [];
        }

        $db = $this->table();
        $state = [];

        $counts = $db->table('lp_post_like')
            ->whereIn('post_id', $postIds)
            ->field('post_id, COUNT(*) AS c')
            ->group('post_id')
            ->select()
            ->toArray();
        foreach ($counts as $row) {
            $state[(string) $row['post_id']] = ['count' => (int) $row['c'], 'liked' => false];
        }

        $userId = $this->optionalAuthUserId();
        if ($userId > 0) {
            $mine = $db->table('lp_post_like')
                ->where('user_id', $userId)
                ->whereIn('post_id', $postIds)
                ->field('post_id')
                ->select()
                ->toArray();
            foreach ($mine as $row) {
                $key = (string) $row['post_id'];
                $state[$key]['liked'] = true;
                if (!isset($state[$key]['count'])) {
                    $state[$key]['count'] = 0;
                }
            }
        }

        return $state;
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
