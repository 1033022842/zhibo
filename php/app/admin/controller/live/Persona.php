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
        if ($this->request->param('status/d', 1) === 1) {
            $where[] = ['persona.status', '=', 1];
        }

        $res = $this->model
            ->alias($alias)
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
