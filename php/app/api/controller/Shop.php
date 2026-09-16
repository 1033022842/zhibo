<?php
declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use app\common\web\ResultCode;
use app\live\service\WalletService;
use think\facade\Db;

/**
 * AI 女友端：Candy Shop 商店
 *
 * 商品由后台「直播运营 → 商品管理」配置，前台 Shop 页读取：
 *   GET  /api/live/shopItems  商品列表（带 owned 已购数量标记）
 *   GET  /api/live/shopItem?id=  商品详情（含描述与附加图片，详情弹窗用）
 *   GET  /api/live/inventory  我的背包（需登录）
 *   POST /api/live/shopBuy    购买商品，扣钻石（需登录）
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

        $owned = $this->ownedMap(array_column($rows, 'id'));

        $list = [];
        foreach ($rows as $row) {
            $item = $this->formatItem($row, false);
            $item['owned'] = $owned[(int) $row['id']] ?? 0;
            $list[] = $item;
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

        $item = $this->formatItem($row, true);
        $owned = $this->ownedMap([$id]);
        $item['owned'] = $owned[$id] ?? 0;

        return $this->jsonSuccess($item);
    }

    /**
     * 我的背包：已购商品 + 已解锁私密内容（需登录）
     */
    public function inventory()
    {
        $userId = $this->getAuthUserId();
        $db = $this->table();

        $rows = $db->table('lp_user_item')
            ->where('user_id', $userId)
            ->order('updated_at', 'desc')
            ->order('id', 'desc')
            ->select()
            ->toArray();

        $shopIds = [];
        $privateIds = [];
        foreach ($rows as $row) {
            $type = (string) $row['item_type'];
            if ($type === 'shop') {
                $shopIds[] = (int) $row['item_id'];
            } elseif ($type === 'private') {
                $privateIds[] = (int) $row['item_id'];
            }
        }

        $shopMap = [];
        if ($shopIds) {
            foreach ($db->table('lp_shop_item')->whereIn('id', $shopIds)
                ->field('id, title, cover_url, price')->select()->toArray() as $row) {
                $shopMap[(int) $row['id']] = $row;
            }
        }
        $privateMap = [];
        if ($privateIds) {
            foreach ($db->table('lp_private_item')->whereIn('id', $privateIds)
                ->field('id, title, poster, avatar, creator, price')->select()->toArray() as $row) {
                $privateMap[(int) $row['id']] = $row;
            }
        }

        $list = [];
        foreach ($rows as $row) {
            $type = (string) $row['item_type'];
            $itemId = (int) $row['item_id'];

            if ($type === 'shop' && isset($shopMap[$itemId])) {
                $src = $shopMap[$itemId];
                $list[] = [
                    'item_type'   => 'shop',
                    'item_id'     => $itemId,
                    'title'       => (string) $src['title'],
                    'cover_url'   => $this->absoluteUrl((string) $src['cover_url']),
                    'creator'     => '',
                    'price'       => max(0, (int) $src['price']),
                    'quantity'    => (int) $row['quantity'],
                    'acquired_at' => (string) ($row['created_at'] ?? ''),
                ];
            } elseif ($type === 'private' && isset($privateMap[$itemId])) {
                $src = $privateMap[$itemId];
                $list[] = [
                    'item_type'   => 'private',
                    'item_id'     => $itemId,
                    'title'       => (string) $src['title'],
                    'cover_url'   => $this->absoluteUrl((string) $src['poster']),
                    'creator'     => (string) $src['creator'],
                    'avatar'      => $this->absoluteUrl((string) $src['avatar']),
                    'price'       => max(0, (int) $src['price']),
                    'quantity'    => (int) $row['quantity'],
                    'acquired_at' => (string) ($row['created_at'] ?? ''),
                ];
            }
        }

        return $this->jsonSuccess(['list' => $list, 'count' => count($list)]);
    }

    /**
     * 购买商品：扣钻石 → 写入背包（需登录）
     *
     * 扣费与入库在同一事务内，唯一键 uk_user_item 兜住并发重复购买。
     */
    public function buy()
    {
        $userId = $this->getAuthUserId();
        $id = (int) $this->request->post('id/d', 0);
        $quantity = (int) $this->request->post('quantity/d', 1);
        $quantity = max(1, min(10, $quantity));

        if ($id <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'id 不能为空');
        }

        $db = $this->table();
        $row = $db->table('lp_shop_item')->where('id', $id)->where('status', 1)->find();
        if (!$row) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, '商品不存在或已下架');
        }

        $price = max(0, (int) $row['price']);
        $amount = (float) ($price * $quantity);
        $wallet = new WalletService();

        $db->startTrans();
        try {
            if ($amount > 0) {
                $wallet->debit($userId, $amount, 'shop_buy', $id, '购买商品：' . (string) $row['title']);
            }

            $owned = $db->table('lp_user_item')
                ->where('user_id', $userId)->where('item_type', 'shop')->where('item_id', $id)
                ->find();
            $now = date('Y-m-d H:i:s');
            if ($owned) {
                $ownedQty = (int) $owned['quantity'] + $quantity;
                $db->table('lp_user_item')->where('id', $owned['id'])->update([
                    'quantity'   => $ownedQty,
                    'price'      => $price,
                    'updated_at' => $now,
                ]);
            } else {
                $ownedQty = $quantity;
                $db->table('lp_user_item')->insert([
                    'user_id'    => $userId,
                    'item_type'  => 'shop',
                    'item_id'    => $id,
                    'quantity'   => $ownedQty,
                    'price'      => $price,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        return $this->jsonSuccess([
            'item_id'  => $id,
            'quantity' => $quantity,
            'owned'    => $ownedQty,
            'amount'   => $amount,
            'balance'  => $wallet->balance($userId),
        ]);
    }

    /**
     * 当前（可选登录）用户已购的商品：item_id => quantity
     */
    private function ownedMap(array $itemIds): array
    {
        $userId = $this->optionalAuthUserId();
        if ($userId <= 0 || !$itemIds) {
            return [];
        }

        $rows = $this->table()->table('lp_user_item')
            ->where('user_id', $userId)
            ->where('item_type', 'shop')
            ->whereIn('item_id', array_map('intval', $itemIds))
            ->field('item_id, quantity')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['item_id']] = (int) $row['quantity'];
        }
        return $map;
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
