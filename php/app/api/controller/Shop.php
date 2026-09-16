<?php
declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use app\common\web\ResultCode;
use think\facade\Db;

/**
 * AI 女友端：Candy Shop 商店
 *
 * 商品由后台「直播运营 → 商品管理」配置，前台 Shop 页读取：
 *   GET /api/live/shopItems  商品列表
 *   GET /api/live/shopItem?id=  商品详情（含描述与附加图片，详情弹窗用）
 */
final class Shop extends BaseController
{
    private const FIELDS = 'id, title, description, cover_url, video_url, images, price, rating, reviews';

    private function table()
    {
        return Db::connect('live_mysql');
    }

    /**
     * 商品列表（公开）
     */
    public function items()
    {
        $limit = (int) $this->request->get('limit/d', 100);
        $limit = max(1, min(200, $limit));

        $rows = $this->table()->table('lp_shop_item')
            ->where('status', 1)
            ->field(self::FIELDS)
            ->order('weigh', 'desc')
            ->order('id', 'asc')
            ->limit($limit)
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $row) {
            $list[] = $this->formatItem($row, false);
        }

        return $this->jsonSuccess(['list' => $list]);
    }

    /**
     * 商品详情（公开）
     */
    public function item()
    {
        $id = (int) $this->request->get('id/d', 0);
        if ($id <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'id 不能为空');
        }

        $row = $this->table()->table('lp_shop_item')
            ->where('id', $id)
            ->where('status', 1)
            ->field(self::FIELDS)
            ->find();

        if (!$row) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, '商品不存在或已下架');
        }

        return $this->jsonSuccess($this->formatItem($row, true));
    }

    private function formatItem(array $row, bool $withDetail): array
    {
        $rating = (float) $row['rating'];
        $rating = max(0.0, min(5.0, $rating));

        $item = [
            'id'         => (int) $row['id'],
            'title'      => (string) $row['title'],
            'cover_url'  => $this->absoluteUrl((string) $row['cover_url']),
            'video_url'  => $this->absoluteUrl((string) $row['video_url']),
            'price'      => max(0, (int) $row['price']),
            'rating'     => round($rating, 1),
            // 星级条宽度，与原站算法一致（4.6 → 92%）
            'rating_pct' => (int) round($rating / 5 * 100),
            'reviews'    => max(0, (int) $row['reviews']),
        ];

        if ($withDetail) {
            $item['description'] = (string) $row['description'];

            $images = [];
            foreach (explode(',', (string) $row['images']) as $url) {
                $url = trim($url);
                if ($url !== '') {
                    $images[] = $this->absoluteUrl($url);
                }
            }
            $item['images'] = $images;
        }

        return $item;
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
