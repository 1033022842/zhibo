<?php

declare(strict_types=1);

namespace app\admin\controller\live;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\live\Persona as PersonaModel;
use app\common\util\StrHelper;

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
     * 新增：人设编码留空自动生成；重复编码给友好提示
     */
    public function add(): void
    {
        if ($this->request->isPost()) {
            $code = trim((string) $this->request->post('code', ''));
            if ($code === '') {
                $this->request->withPost(['code' => StrHelper::orderNo('P')]);
            } elseif (PersonaModel::where('code', $code)->find()) {
                $this->error("人设编码「{$code}」已存在，请更换或留空自动生成");
            }
        }
        parent::add();
    }

    /**
     * 编辑：编码重复校验（排除自身）
     */
    public function edit(): void
    {
        if ($this->request->isPost()) {
            $ids = $this->request->param('ids', $this->request->param('id', 0));
            $id  = is_array($ids) ? (int)reset($ids) : (int)$ids;
            $code = trim((string) $this->request->post('code', ''));
            if ($code !== '' && PersonaModel::where('code', $code)->where('id', '<>', $id)->find()) {
                $this->error("人设编码「{$code}」已被其他人设使用");
            }
        }
        parent::edit();
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
            ->withJoin(['user'], 'LEFT')
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
