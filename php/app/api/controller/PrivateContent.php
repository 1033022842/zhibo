<?php
declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use app\common\web\ResultCode;
use app\live\service\WalletService;
use think\facade\Db;

/**
 * AI 女友端：Private Content 私密内容
 *
 * 卡片由后台「直播运营 → 私密内容管理」配置，前台 Private Content 页读取：
 *   GET  /api/live/privateContents?tab=all|most_liked  卡片列表（公开，带 unlocked 标记）
 *   POST /api/live/privateUnlock  解锁（扣钻石，需登录），返回可观看的媒体地址
 *
 * 未解锁的卡片不下发 video_url / images，解锁后才下发，保证「付费才能看」。
 */
final class PrivateContent extends BaseController
{
    private const FIELDS = 'id, title, poster, video_url, images, avatar, creator, price, like_rate, media_type, '
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

        $unlocked = $this->unlockedSet(array_column($rows, 'id'));

        $list = [];
        foreach ($rows as $row) {
            $list[] = $this->formatItem($row, isset($unlocked[(int) $row['id']]));
        }

        return $this->jsonSuccess(['list' => $list]);
    }

    /**
     * 解锁私密内容：扣钻石 → 记录已购（需登录）
     *
     * 已解锁的直接返回，不重复扣费（幂等）。
     * 扣费与入库在同一事务内，唯一键 uk_user_item 兜住并发重复解锁。
     */
    public function unlock()
    {
        $userId = $this->getAuthUserId();
        $id = (int) $this->request->post('id/d', 0);
        if ($id <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'id 不能为空');
        }

        $db = $this->table();
        $row = $db->table('lp_private_item')->where('id', $id)->where('status', 1)->find();
        if (!$row) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, '内容不存在或已下架');
        }

        $wallet = new WalletService();

        $exists = $db->table('lp_user_item')
            ->where('user_id', $userId)->where('item_type', 'private')->where('item_id', $id)
            ->find();
        if ($exists) {
            return $this->jsonSuccess([
                'unlocked' => 1,
                'charged'  => 0,
                'amount'   => 0,
                'balance'  => $wallet->balance($userId),
                'item'     => $this->formatItem($row, true),
            ]);
        }

        $price = max(0, (int) $row['price']);
        $amount = (float) $price;

        $db->startTrans();
        try {
            if ($amount > 0) {
                $wallet->debit($userId, $amount, 'private_unlock', $id, '解锁私密内容：' . mb_substr((string) $row['title'], 0, 40));
            }

            $now = date('Y-m-d H:i:s');
            $db->table('lp_user_item')->insert([
                'user_id'    => $userId,
                'item_type'  => 'private',
                'item_id'    => $id,
                'quantity'   => 1,
                'price'      => $price,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        return $this->jsonSuccess([
            'unlocked' => 1,
            'charged'  => 1,
            'amount'   => $amount,
            'balance'  => $wallet->balance($userId),
            'item'     => $this->formatItem($row, true),
        ]);
    }

    /**
     * 当前（可选登录）用户已解锁的内容 id 集合
     */
    private function unlockedSet(array $itemIds): array
    {
        $userId = $this->optionalAuthUserId();
        if ($userId <= 0 || !$itemIds) {
            return [];
        }

        $rows = $this->table()->table('lp_user_item')
            ->where('user_id', $userId)
            ->where('item_type', 'private')
            ->whereIn('item_id', array_map('intval', $itemIds))
            ->field('item_id')
            ->select()
            ->toArray();

        $set = [];
        foreach ($rows as $row) {
            $set[(int) $row['item_id']] = true;
        }
        return $set;
    }

    private function formatItem(array $row, bool $unlocked = false): array
    {
        $item = [
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
            'unlocked'     => $unlocked ? 1 : 0,
        ];

        // 未解锁不下发媒体地址
        if ($unlocked) {
            $item['video_url'] = $this->absoluteUrl((string) ($row['video_url'] ?? ''));
            $images = [];
            foreach (explode(',', (string) ($row['images'] ?? '')) as $url) {
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
