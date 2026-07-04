<?php

declare(strict_types=1);

namespace app\admin\controller\live;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;

final class Leaderboard extends Backend
{
    protected string $connection = 'live_mysql';

    public function initialize(): void
    {
        parent::initialize();
    }

    /**
     * 收入排行榜：全部角色按累计礼物收入降序
     * @throws Throwable
     */
    public function select(): void
    {
        $page  = (int) $this->request->get('page/d', 1);
        $limit = (int) $this->request->get('limit/d', 20);

        $subQuery = Db::connect($this->connection)
            ->table('lp_gift_order go')
            ->field([
                'go.room_id',
                'SUM(go.total_price) AS total_gift',
                'COUNT(DISTINCT go.user_id) AS donor_count',
            ])
            ->group('go.room_id');

        $total = Db::connect($this->connection)
            ->table('lp_room r')
            ->join('lp_persona p', 'r.persona_id = p.id')
            ->join([$subQuery => 's'], 's.room_id = r.id', 'LEFT')
            ->count();

        $list = Db::connect($this->connection)
            ->table('lp_room r')
            ->join('lp_persona p', 'r.persona_id = p.id')
            ->join([$subQuery => 's'], 's.room_id = r.id', 'LEFT')
            ->field([
                'p.id AS persona_id',
                'p.name AS persona_name',
                'r.id AS room_id',
                'r.room_no',
                'IFNULL(s.total_gift, 0) AS total_gift',
                'IFNULL(s.donor_count, 0) AS donor_count',
            ])
            ->order('total_gift', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $rank = ($page - 1) * $limit + 1;
        foreach ($list as &$row) {
            $row['rank'] = $rank++;
        }
        unset($row);

        $this->success('', [
            'list'   => $list,
            'total'  => $total,
            'remark' => get_route_remark(),
        ]);
    }
}
