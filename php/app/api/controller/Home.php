<?php
declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use app\live\model\HomeCharacter as CharacterModel;
use app\live\model\HomeBanner as BannerModel;

final class Home extends BaseController
{
    /**
     * 首页轮播图列表（无需登录）
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

        $list = BannerModel::where('status', BannerModel::STATUS_ENABLED)
            ->order('weigh', 'desc')
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
            ];
        }

        return $this->jsonSuccess(['list' => $data]);
    }

    /**
     * 首页推荐角色列表（无需登录）
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

        $list = CharacterModel::where('status', CharacterModel::STATUS_ENABLED)
            ->order('weigh', 'desc')
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
            ];
        }

        return $this->jsonSuccess(['list' => $data]);
    }
}
