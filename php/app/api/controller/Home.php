<?php
declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use app\live\model\HomeCharacter as CharacterModel;

final class Home extends BaseController
{
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
                'link_url'    => (string) $row->link_url,
                'is_adult'    => (int) $row->is_adult,
            ];
        }

        return $this->jsonSuccess(['list' => $data]);
    }
}
