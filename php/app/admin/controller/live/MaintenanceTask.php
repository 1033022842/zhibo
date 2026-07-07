<?php

declare(strict_types=1);

namespace app\admin\controller\live;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\live\MaintenanceTask as MaintenanceTaskModel;

final class MaintenanceTask extends Backend
{
    protected object $model;
    protected string|array $quickSearchField = ['name', 'id'];
    protected bool $modelValidate = false;
    protected string|array $defaultSortField = 'due_date,asc';
    protected array $withJoinTable = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new MaintenanceTaskModel();
    }

    /**
     * @throws Throwable
     */
    public function index(): void
    {
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $isSelect = $this->request->param('select');

        if (!$isSelect) {
            $where[] = ['maintenance_task.status', 'in', [0, 1]];
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

    public function add(): void
    {
        if (!$this->request->isPost()) {
            $this->error(__('Parameter error'));
        }

        $payload = $this->request->post();
        if (!$payload) {
            $this->error(__('Parameter %s can not be empty', ['']));
        }

        $data = $this->excludeFields($payload);
        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['due_date'] = trim((string) ($data['due_date'] ?? ''));
        $data['remark'] = trim((string) ($data['remark'] ?? ''));
        $data['repeat_remind'] = (int) ($data['repeat_remind'] ?? 0);
        $data['status'] = 0;

        if ($data['name'] === '' || $data['due_date'] === '') {
            $this->error('任务名称和到期日期不能为空');
        }

        $this->model->save($data);
        $this->success(__('Added successfully'));
    }

    public function edit(): void
    {
        $pk = $this->model->getPk();
        $id = (int) $this->request->param($pk);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }

        if ($this->request->isPost()) {
            $payload = $this->request->post();
            if (!$payload) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data = $this->excludeFields($payload);
            $data['name'] = trim((string) ($data['name'] ?? ''));
            $data['due_date'] = trim((string) ($data['due_date'] ?? ''));
            $data['remark'] = trim((string) ($data['remark'] ?? ''));
            $data['repeat_remind'] = (int) ($data['repeat_remind'] ?? 0);

            if ($data['name'] === '' || $data['due_date'] === '') {
                $this->error('任务名称和到期日期不能为空');
            }

            $row->save($data);
            $this->success(__('Update successful'));
        }

        $this->success('', ['row' => $row]);
    }
}
