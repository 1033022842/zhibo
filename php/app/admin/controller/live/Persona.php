<?php

declare(strict_types=1);

namespace app\admin\controller\live;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\live\Persona as PersonaModel;

final class Persona extends Backend
{
    protected object $model;
    protected string|array $quickSearchField = ['code', 'name', 'id'];
    protected bool $modelValidate = false;
    protected string|array $defaultSortField = 'id,desc';
    protected array $withJoinTable = ['user'];
    protected int|string $limit = 20;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new PersonaModel();
    }

    /**
     * @throws Throwable
     */
    public function select(): void
    {
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        // status: 0=禁用, 1=未使用, 2=正在使用
        // select=1 时（下拉选择）只展示未使用的且未被房间绑定的
        if ($this->request->param('select')) {
            $where[] = ['persona.status', '=', 1];
            // 排除已有房间的人设
            $boundIds = \think\facade\Db::connect('live_mysql')
                ->table('lp_room')
                ->where('persona_id', '>', 0)
                ->column('persona_id');
            if ($boundIds) {
                $where[] = ['persona.id', 'NOT IN', $boundIds];
            }
        } else {
            $where[] = ['persona.status', '>', 0];
        }

        $res = $this->model
            ->alias($alias)
            ->withJoin(['user'])
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $this->success('', [
            'list' => $res->items(),
            'total' => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }
}
