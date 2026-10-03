<?php
declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use app\common\web\ResultCode;
use app\live\service\VipService;
use app\live\service\WalletService;
use think\facade\Db;

/**
 * AI 女友端：Candy Shorts 短剧
 *
 * 卡片由后台「直播运营 → 短剧管理」配置，剧集由「短剧剧集」配置：
 *   GET  /api/live/shorts                            卡片列表（公开，带集数/起售价）
 *   GET  /api/live/shortEpisodes?short_id=x          某部剧的剧集列表（公开，未解锁集不下发视频）
 *   POST /api/live/shortUnlock                       解锁某一集（扣代币，需登录）
 *
 * 收费规则（按集解锁）：
 *   1) 剧上 free_episodes = N：集号 <= N 的集免代币；
 *   2) 单集 price = 0 也算免费；
 *   3) 其余集要解锁（写入 lp_user_item，item_type = 'short'，item_id = 剧集 id），
 *      未解锁的集不下发 video_url，保证「付费才能看」。
 *
 * 兼容：没有配置任何剧集的短剧（老卡片）仍走剧上 video_url 的单视频播放。
 */
final class Shorts extends BaseController
{
    private const FIELDS = 'id, title, description, poster, video_url, href, section, rank, progress, spicy, '
        . 'featured, new_episodes, free_episodes, category, character_type';

    private const EPISODE_FIELDS = 'id, short_id, episode_no, title, poster, video_url, duration, price';

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

        // 分区过滤：section=private 仅私人短剧栏（VIP 前端拉取）；不传时排除 private
        $section = trim((string) $this->request->get('section', ''));
        if ($section !== 'private' && $section !== '') {
            $section = '';
        }

        // owned=1：只返回当前用户已付费解锁过剧集的短剧（首页「我已解锁」栏）
        $owned = (string) $this->request->get('owned', '') === '1';
        $ownedCounts = [];
        if ($owned) {
            $userId = $this->optionalAuthUserId();
            if ($userId <= 0) {
                return $this->jsonSuccess(['list' => []]);
            }
            $ownedCounts = $this->ownedEpisodeCounts($userId);
            if (!$ownedCounts) {
                return $this->jsonSuccess(['list' => []]);
            }
        }

        $rows = $this->table()->table('lp_short_item')
            ->where('status', 1)
            ->field(self::FIELDS);

        if ($section === 'private') {
            $rows = $rows->where('section', 'private');
        } else {
            $rows = $rows->where('section', '<>', 'private');
        }

        if ($owned) {
            $rows = $rows->whereIn('id', array_keys($ownedCounts));
        }

        $rows = $rows
            // 分区顺序固定为 continue_watching -> top_series -> explore
            ->orderRaw("FIELD(`section`, 'continue_watching', 'top_series', 'explore') ASC")
            // 同一分区内：top_series 按 rank 升序，其它按 weigh 降序，最后用 id 兜底
            ->order('rank', 'asc')
            ->order('weigh', 'desc')
            ->order('id', 'asc')
            ->limit($limit)
            ->select()
            ->toArray();

        $stats = $this->episodeStats($rows);

        $list = [];
        foreach ($rows as $row) {
            $item = $this->formatItem($row, $stats[(int) $row['id']] ?? null);
            if ($owned) {
                // 该剧已解锁的集数，前台卡片用「Owned N」
                $item['owned_episodes'] = $ownedCounts[(int) $row['id']] ?? 0;
            }
            $list[] = $item;
        }

        return $this->jsonSuccess(['list' => $list]);
    }

    /**
     * 某部短剧的剧集列表（公开；未登录/未解锁的集不下发 video_url）
     */
    public function episodes()
    {
        $shortId = (int) $this->request->get('short_id/d', 0);
        if ($shortId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'short_id 不能为空');
        }

        $db = $this->table();
        $short = $db->table('lp_short_item')->where('id', $shortId)->where('status', 1)->find();
        if (!$short) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, '短剧不存在或已下架');
        }

        $freeEpisodes = max(0, (int) ($short['free_episodes'] ?? 0));

        $rows = $db->table('lp_short_episode')
            ->where('short_id', $shortId)
            ->where('status', 1)
            ->field(self::EPISODE_FIELDS)
            ->order('episode_no', 'asc')
            ->order('weigh', 'desc')
            ->select()
            ->toArray();

        $unlocked = $this->unlockedSet(array_column($rows, 'id'));

        $episodes = [];
        foreach ($rows as $row) {
            $episodes[] = $this->formatEpisode($row, $freeEpisodes, $unlocked);
        }

        return $this->jsonSuccess([
            'short' => [
                'id'             => (int) $short['id'],
                'title'          => (string) $short['title'],
                'description'    => (string) ($short['description'] ?? ''),
                'poster'         => $this->absoluteUrl((string) $short['poster']),
                'free_episodes'  => $freeEpisodes,
                'episode_count'  => count($episodes),
                // 没有剧集时的兜底单视频（老卡片）
                'video_url'      => $episodes ? '' : $this->absoluteUrl((string) ($short['video_url'] ?? '')),
            ],
            'episodes' => $episodes,
        ]);
    }

    /**
     * 解锁某一集：扣代币 → 记录已购（需登录）
     *
     * 已购/免费集直接放行不扣费（幂等）；扣费与入库在同一事务内，
     * 唯一键 uk_user_item 兜住并发重复解锁。
     */
    public function unlock()
    {
        $userId = $this->getAuthUserId();
        $episodeId = (int) $this->request->post('episode_id/d', 0);
        if ($episodeId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'episode_id 不能为空');
        }

        $db = $this->table();
        $episode = $db->table('lp_short_episode')->where('id', $episodeId)->where('status', 1)->find();
        if (!$episode) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, '剧集不存在或已下架');
        }

        $short = $db->table('lp_short_item')->where('id', (int) $episode['short_id'])->find();
        if (!$short || (int) $short['status'] !== 1) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, '短剧不存在或已下架');
        }

        $freeEpisodes = max(0, (int) ($short['free_episodes'] ?? 0));
        $price = max(0, (int) $episode['price']);
        // 前 N 集免费 or 单集价格 0 → 免代币
        $isFree = (int) $episode['episode_no'] <= $freeEpisodes || $price === 0;

        // 会员折扣（Premium 9 折 / Premium Plus 8 折）
        $vipService = new VipService();
        $ent        = $vipService->entitlements($userId);
        $discount   = (int) ($ent['discount_percent'] ?? 0);
        $payDiamond = $vipService->discounted($price, $discount);

        $wallet = new WalletService();

        $exists = $db->table('lp_user_item')
            ->where('user_id', $userId)
            ->where('item_type', 'short')
            ->where('item_id', $episodeId)
            ->find();
        if ($exists) {
            return $this->jsonSuccess([
                'unlocked' => 1,
                'charged'  => 0,
                'amount'   => 0,
                'balance'  => $wallet->balance($userId),
                'episode'  => $this->formatEpisode($episode, $freeEpisodes, [$episodeId => true]),
            ]);
        }

        $amount = $isFree ? 0.0 : (float) $payDiamond;

        $db->startTrans();
        try {
            if ($amount > 0) {
                $remark = '解锁短剧第' . (int) $episode['episode_no'] . '集：' . mb_substr((string) $short['title'], 0, 30);
                if ($payDiamond < $price) {
                    $remark .= '（' . $ent['level_name'] . ' 折扣 -' . $discount . '%）';
                }
                $wallet->debit(
                    $userId,
                    $amount,
                    'short_unlock',
                    $episodeId,
                    $remark
                );
            }

            $now = date('Y-m-d H:i:s');
            $db->table('lp_user_item')->insert([
                'user_id'    => $userId,
                'item_type'  => 'short',
                'item_id'    => $episodeId,
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
            'unlocked'         => 1,
            'charged'          => $amount > 0 ? 1 : 0,
            'amount'           => $amount,
            'original_amount'  => $price,
            'discount_percent' => $payDiamond < $price ? $discount : 0,
            'balance'          => $wallet->balance($userId),
            'episode'          => $this->formatEpisode($episode, $freeEpisodes, [$episodeId => true]),
        ]);
    }

    /**
     * 当前（可选登录）用户已解锁的剧集 id 集合
     */
    private function unlockedSet(array $episodeIds): array
    {
        $userId = $this->optionalAuthUserId();
        if ($userId <= 0 || !$episodeIds) {
            return [];
        }

        $rows = $this->table()->table('lp_user_item')
            ->where('user_id', $userId)
            ->where('item_type', 'short')
            ->whereIn('item_id', array_map('intval', $episodeIds))
            ->field('item_id')
            ->select()
            ->toArray();

        $set = [];
        foreach ($rows as $row) {
            $set[(int) $row['item_id']] = true;
        }
        return $set;
    }

    /**
     * 当前用户已解锁剧集数：按短剧聚合
     * （lp_user_item.item_id 存的是剧集 id，item_type = 'short'）
     *
     * @return array<int,int> short_id => 已解锁集数
     */
    private function ownedEpisodeCounts(int $userId): array
    {
        $episodeIds = $this->table()->table('lp_user_item')
            ->where('user_id', $userId)
            ->where('item_type', 'short')
            ->column('item_id');
        if (!$episodeIds) {
            return [];
        }

        $rows = $this->table()->table('lp_short_episode')
            ->whereIn('id', array_map('intval', $episodeIds))
            ->field('short_id, COUNT(*) AS c')
            ->group('short_id')
            ->select()
            ->toArray();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['short_id']] = (int) $row['c'];
        }
        return $out;
    }

    /**
     * 按短剧聚合剧集信息：已上架集数 + 最低付费价（免费集与「前N集免费」的集不计入）
     *
     * 一次查完在 PHP 里聚合，避免为兼容不同 MySQL 版本写条件聚合 SQL。
     *
     * @param array $shorts lp_short_item 行（需含 id / free_episodes）
     */
    private function episodeStats(array $shorts): array
    {
        $ids = array_filter(array_map('intval', array_column($shorts, 'id')));
        if (!$ids) {
            return [];
        }

        $freeMap = [];
        foreach ($shorts as $row) {
            $freeMap[(int) $row['id']] = max(0, (int) ($row['free_episodes'] ?? 0));
        }

        $rows = $this->table()->table('lp_short_episode')
            ->whereIn('short_id', $ids)
            ->where('status', 1)
            ->field('short_id, episode_no, price')
            ->select()
            ->toArray();

        $stats = [];
        foreach ($rows as $row) {
            $sid = (int) $row['short_id'];
            if (!isset($stats[$sid])) {
                $stats[$sid] = ['total' => 0, 'min_paid' => 0];
            }
            $stats[$sid]['total']++;

            $price = max(0, (int) $row['price']);
            $no = (int) $row['episode_no'];
            if ($price > 0 && $no > ($freeMap[$sid] ?? 0)) {
                $min = $stats[$sid]['min_paid'];
                $stats[$sid]['min_paid'] = ($min === 0 || $price < $min) ? $price : $min;
            }
        }

        return $stats;
    }

    /**
     * @param array|null $stat 该剧的剧集聚合（null 表示没有剧集）
     */
    private function formatItem(array $row, ?array $stat): array
    {
        $episodeCount = $stat ? (int) $stat['total'] : 0;
        $hasEpisodes = $episodeCount > 0;

        return [
            'id'            => (int) $row['id'],
            'title'         => (string) $row['title'],
            'description'   => (string) ($row['description'] ?? ''),
            'poster'        => $this->absoluteUrl((string) $row['poster']),
            // 有剧集时以剧集为准，不再下发剧上的视频（避免绕过按集付费）
            'video_url'     => $hasEpisodes ? '' : $this->absoluteUrl((string) ($row['video_url'] ?? '')),
            'href'          => (string) $row['href'],
            'section'       => (string) $row['section'],
            'rank'          => (int) $row['rank'],
            'progress'      => (float) $row['progress'],
            'spicy'         => (bool) $row['spicy'],
            'featured'      => (string) $row['featured'],
            'new_episodes'  => (bool) $row['new_episodes'],
            'free_episodes' => max(0, (int) ($row['free_episodes'] ?? 0)),
            'has_episodes'  => $hasEpisodes,
            'episode_count' => $episodeCount,
            // 0 表示全免费；>0 表示最低付费集的代币价
            'price_from'    => $stat ? (int) $stat['min_paid'] : 0,
            // 前台筛选栏用：分类标签数组（逗号分隔存库）+ 角色类型（creator/character/other）
            'category'      => $this->splitTags((string) ($row['category'] ?? '')),
            'type'          => (string) ($row['character_type'] ?? ''),
        ];
    }

    /**
     * 逗号分隔标签 → 去空去重的数组
     */
    private function splitTags(string $raw): array
    {
        $out = [];
        foreach (explode(',', $raw) as $tag) {
            $tag = trim($tag);
            if ($tag !== '' && !in_array($tag, $out, true)) {
                $out[] = $tag;
            }
        }
        return $out;
    }

    /**
     * 剧集输出：未解锁不下发 video_url
     */
    private function formatEpisode(array $row, int $freeEpisodes, array $unlocked): array
    {
        $id = (int) $row['id'];
        $no = (int) $row['episode_no'];
        $price = max(0, (int) $row['price']);
        $isFree = $no <= $freeEpisodes || $price === 0;
        $isUnlocked = $isFree || isset($unlocked[$id]);

        $episode = [
            'id'         => $id,
            'short_id'   => (int) $row['short_id'],
            'episode_no' => $no,
            'title'      => (string) $row['title'],
            'poster'     => $this->absoluteUrl((string) $row['poster']),
            'duration'   => (string) $row['duration'],
            'price'      => $price,
            'free'       => $isFree ? 1 : 0,
            'unlocked'   => $isUnlocked ? 1 : 0,
            'video_url'  => '',
        ];

        if ($isUnlocked) {
            $episode['video_url'] = $this->absoluteUrl((string) $row['video_url']);
        }

        return $episode;
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
