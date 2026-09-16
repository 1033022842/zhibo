<?php
declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use think\facade\Db;

/**
 * AI 女友端：Candy Shorts 短剧
 *
 * 卡片由后台「直播运营 → 短剧管理」配置，前台 Shorts 页读取：
 *   GET /api/live/shorts  短剧卡片列表（公开）
 */
final class Shorts extends BaseController
{
    private const FIELDS = 'id, title, poster, href, section, rank, progress, spicy, featured, new_episodes';

    private function table()
    {
        return Db::connect('live_mysql');
    }

    /**
     * 短剧卡片列表（公开）
     */
    public function items()
    {
        $limit = (int) $this->request->get('limit/d', 200);
        $limit = max(1, min(500, $limit));

        $rows = $this->table()->table('lp_short_item')
            ->where('status', 1)
            ->field(self::FIELDS)
            // 分区顺序固定为 continue_watching -> top_series -> explore
            ->orderRaw("FIELD(`section`, 'continue_watching', 'top_series', 'explore') ASC")
            // 同一分区内：top_series 按 rank 升序，其它按 weigh 降序，最后用 id 兜底
            ->order('rank', 'asc')
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
            'id'           => (int) $row['id'],
            'title'        => (string) $row['title'],
            'poster'       => $this->absoluteUrl((string) $row['poster']),
            'href'         => (string) $row['href'],
            'section'      => (string) $row['section'],
            'rank'         => (int) $row['rank'],
            'progress'     => (float) $row['progress'],
            'spicy'        => (bool) $row['spicy'],
            'featured'     => (string) $row['featured'],
            'new_episodes' => (bool) $row['new_episodes'],
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
