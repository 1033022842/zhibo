<?php

declare(strict_types=1);

namespace app\admin\controller\live;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\live\ReplayClip as ReplayClipModel;

final class ReplayClip extends Backend
{
    protected object $model;
    protected string|array $quickSearchField = ['title', 'id'];
    protected bool $modelValidate = false;
    protected string|array $defaultSortField = 'live_date,desc';
    protected array $withJoinTable = ['persona', 'room'];
    protected array $preExcludeFields = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new ReplayClipModel();
    }

    /**
     * @throws Throwable
     */
    public function select(): void
    {
        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $res = $this->model
            ->alias($alias)
            ->withJoin(['persona', 'room'])
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
        $data['title'] = trim((string) ($data['title'] ?? ''));
        $data['video_url'] = trim((string) ($data['video_url'] ?? ''));
        $data['cover_url'] = trim((string) ($data['cover_url'] ?? ''));
        $data['duration'] = (int) ($data['duration'] ?? 0);
        $data['live_date'] = trim((string) ($data['live_date'] ?? ''));
        $data['persona_id'] = (int) ($data['persona_id'] ?? 0);
        $data['room_id'] = (int) ($data['room_id'] ?? 0);
        $data['status'] = (int) ($data['status'] ?? 1);

        if ($data['title'] === '' || $data['video_url'] === '' || $data['live_date'] === '') {
            $this->error('标题、视频、直播日期不能为空');
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
            $data['title'] = trim((string) ($data['title'] ?? ''));
            $data['video_url'] = trim((string) ($data['video_url'] ?? ''));
            $data['cover_url'] = trim((string) ($data['cover_url'] ?? ''));
            $data['duration'] = (int) ($data['duration'] ?? 0);
            $data['live_date'] = trim((string) ($data['live_date'] ?? ''));
            $data['persona_id'] = (int) ($data['persona_id'] ?? 0);
            $data['room_id'] = (int) ($data['room_id'] ?? 0);
            $data['status'] = (int) ($data['status'] ?? 1);

            if ($data['title'] === '' || $data['video_url'] === '' || $data['live_date'] === '') {
                $this->error('标题、视频、直播日期不能为空');
            }

            $row->save($data);
            $this->success(__('Update successful'));
        }

        $this->success('', ['row' => $row]);
    }
}
