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
    public function index(): void
    {
        list($where, $alias, $limit, $order) = $this->queryBuilder();

        // status: 0=禁用, 1=未使用, 2=正在使用
        $isSelect = $this->request->param('select');
        $initValue = $this->request->get('initValue');

        if ($isSelect && $initValue) {
            // 编辑时往回查当前绑定的人设（可能已是正在使用状态），允许 status 1+2
            $where[] = ['persona.status', 'in', [1, 2]];
        } elseif ($isSelect) {
            // 下拉列表：只展示未使用
            $where[] = ['persona.status', '=', 1];
        } else {
            // 列表页：展示所有非禁用
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
