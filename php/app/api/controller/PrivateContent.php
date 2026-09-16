<?php
declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use think\facade\Db;

/**
 * AI 女友端：Private Content 私密内容
 *
 * 卡片由后台「直播运营 → 私密内容管理」配置，前台 Private Content 页读取：
 *   GET /api/live/privateContents?tab=all|most_liked  卡片列表（公开）
 */
final class PrivateContent extends BaseController
{
    private const FIELDS = 'id, title, poster, avatar, creator, price, like_rate, media_type, '
        . 'video_count, duration, image_count, badge, purchase_url';

    private function table()
    {
        return Db::connect('live_mysql');
    }

    /**
     * 私密内容卡片列表（公开）
     */
    public function items()
    {
        $tab = (string) $this->request->get('tab', 'all');

        $query = $this->table()->table('lp_private_item')
            ->where('status', 1)
            ->field(self::FIELDS);

        if ($tab === 'most_liked') {
            // 「Most liked」：点赞率降序，其次权重降序，最后 id 兜底
            $query->order('like_rate', 'desc')
                ->order('weigh', 'desc')
                ->order('id', 'asc');
        } else {
            // 「All」：权重降序，其次 id 兜底
            $query->order('weigh', 'desc')
                ->order('id', 'asc');
        }

        $rows = $query->select()->toArray();

        $list = [];
        foreach ($rows as $row) {
            $list[] = $this->formatItem($row);
        }

        return $this->jsonSuccess(['list' => $list]);
    }

    private function formatItem(array $row): array
    {
        return [
            'id'          => (int) $row['id'],
            'title'       => (string) $row['title'],
            'poster'      => $this->absoluteUrl((string) $row['poster']),
            'avatar'      => $this->absoluteUrl((string) $row['avatar']),
            'creator'     => (string) $row['creator'],
            'price'       => (int) $row['price'],
            'like_rate'   => (int) $row['like_rate'],
            'media_type'  => (string) $row['media_type'],
            'video_count' => (int) $row['video_count'],
            'duration'    => (string) $row['duration'],
            'image_count' => (int) $row['image_count'],
            'badge'       => (string) $row['badge'],
            // 详情/购买链接可能是站外链，原样返回
            'purchase_url' => (string) $row['purchase_url'],
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
