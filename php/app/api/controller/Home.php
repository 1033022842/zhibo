<?php
declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use app\live\model\HomeCharacter as CharacterModel;
use app\live\model\HomeBanner as BannerModel;
use app\live\service\WalletService;

final class Home extends BaseController
{
    /** 首页分类（candy.ai 式 Tab）：girls / anime / guys */
    public const CATEGORIES = ['girls', 'anime', 'guys'];

    /**
     * 校验分类参数：合法返回小写值，非法/空返回 ''（不过滤，向后兼容）
     */
    private function categoryParam(): string
    {
        $category = strtolower(trim((string) $this->request->param('category', '')));
        return in_array($category, self::CATEGORIES, true) ? $category : '';
    }

    /**
     * 首页轮播图列表（无需登录）
     * GET /api/v1/home/banners?limit=10&category=girls|anime|guys
     */
    public function banners()
    {
        $limit = (int) $this->request->param('limit/d', 10);
        if ($limit <= 0) {
            $limit = 10;
        }
        if ($limit > 20) {
            $limit = 20;
        }
        $category = $this->categoryParam();

        $query = BannerModel::where('status', BannerModel::STATUS_ENABLED);
        if ($category !== '') {
            $query = $query->where('category', $category);
        }
        $list = $query->order('weigh', 'desc')
            // 更新过素材的老条目也要浮到前面（同事「完善角色」常是更新已有行，仅 id 排序会沉底）
            ->order('updated_at', 'desc')
            ->order('id', 'desc')
            ->limit($limit)
            ->select();

        $data = [];
        foreach ($list as $row) {
            $data[] = [
                'id'        => (int) $row->id,
                'title'     => (string) $row->title,
                'cover_url' => (string) $row->cover_url,
                'link_url'  => (string) $row->link_url,
                'category'  => (string) ($row->category ?: 'girls'),
            ];
        }

        // 公开目录：内容变化不频繁，给 CDN 30 秒短缓存挡回源（签名地址 TTL 6h，不会过期）
        return $this->jsonSuccess(['list' => $data])
            ->header(['Cache-Control' => 'public, max-age=30, stale-while-revalidate=60']);
    }

    /**
     * 首页推荐角色列表（无需登录）
     * GET /api/v1/home/characters?limit=50&category=girls|anime|guys
     */
    public function characters()
    {
        $limit = (int) $this->request->param('limit/d', 12);
        if ($limit <= 0) {
            $limit = 12;
        }
        if ($limit > 50) {
            $limit = 50;
        }
        $category = $this->categoryParam();

        $query = CharacterModel::where('status', CharacterModel::STATUS_ENABLED);
        if ($category !== '') {
            $query = $query->where('category', $category);
        }
        $list = $query->order('weigh', 'desc')
            // 更新过素材的老条目也要浮到前面（同事「完善角色」常是更新已有行，仅 id 排序会沉底）
            ->order('updated_at', 'desc')
            ->order('id', 'desc')
            ->limit($limit)
            ->select();

        $data = [];
        foreach ($list as $row) {
            $tags = array_values(array_filter(
                array_map('trim', explode(',', (string) $row->tags)),
                static fn($s) => $s !== ''
            ));
            $data[] = [
                'id'          => (int) $row->id,
                'name'        => (string) $row->name,
                'age'         => (string) $row->age,
                'tagline'     => (string) $row->tagline,
                'description' => (string) $row->description,
                'cover_url'   => (string) $row->cover_url,
                'tags'        => $tags,
                'section'     => (string) $row->section,
                'link_url'    => (string) $row->link_url,
                'is_adult'    => (int) $row->is_adult,
                'category'    => (string) ($row->category ?: 'girls'),
            ];
        }

        // 公开目录：同 banners，30 秒 CDN 缓存足够，避免每次首屏都回源
        return $this->jsonSuccess(['list' => $data])
            ->header(['Cache-Control' => 'public, max-age=30, stale-while-revalidate=60']);
    }

    /**
     * 平台收入分成比例（公开，供创作者中心等前台展示）
     * GET /api/v1/home/commission
     * 返回各类型的平台抽成 / 创作者分成百分比（0~100）
     */
    public function commission()
    {
        $giftFallback = (string) WalletService::config('gift_commission_rate', '0.30');

        $rates = [
            'short'        => (string) WalletService::config('short_commission_rate', $giftFallback),
            'chat'         => (string) WalletService::config('chat_commission_rate', $giftFallback),
            'gift'         => $giftFallback,
            'crowdfunding' => (string) WalletService::config('crowdfunding_commission_rate', '0.05'),
        ];

        $data = [];
        foreach ($rates as $type => $raw) {
            $cut = max(0.0, min(0.9, (float) $raw)); // 平台抽成比例
            $data[$type] = [
                'platform' => (int) round($cut * 100),
                'creator'  => (int) round((1 - $cut) * 100),
            ];
        }

        return $this->jsonSuccess($data);
    }
}
