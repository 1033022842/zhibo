<?php

declare(strict_types=1);

namespace app\admin\controller\ai;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\ai\Media as MediaModel;

final class Media extends Backend
{
    protected object $model;
    protected string|array $quickSearchField = ['title', 'content_id', 'id'];
    protected bool $modelValidate = false;
    protected string|array $defaultSortField = 'weigh,desc';
    protected int|string $limit = 20;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new MediaModel();
    }

    /**
     * 供后台下拉选择的角色列表
     */
    public function contentOptions(): void
    {
        $rows = \think\facade\Db::connect('live_mysql')
            ->table('lp_ai_content')
            ->field(['id', 'title'])
            ->order('weigh', 'desc')
            ->order('id', 'desc')
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $row) {
            $list[] = ['id' => (int) $row['id'], 'title' => $row['title']];
        }
        $this->success('', ['list' => $list]);
    }

    public function add(): void
    {
        if (!$this->request->isPost()) {
            $this->error(__('Parameter error'));
        }

        $data = $this->request->post();
        if (!$data) {
            $this->error(__('Parameter %s can not be empty', ['']));
        }

        $data = $this->normalizePayload($data);
        $result = false;
        $this->model->startTrans();
        try {
            $result = $this->model->save($data);
            $this->model->commit();
        } catch (Throwable $e) {
            $this->model->rollback();
            $this->error($e->getMessage());
        }

        $result !== false ? $this->success(__('Added successfully')) : $this->error(__('No rows were added'));
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
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data = $this->normalizePayload($data, $row->toArray());
            $result = false;
            $this->model->startTrans();
            try {
                $result = $row->save($data);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }

            $result !== false ? $this->success(__('Update successful')) : $this->error(__('No rows updated'));
        }

        $this->success('', ['row' => $row]);
    }

    private function normalizePayload(array $data, array $existing = []): array
    {
        $data['content_id'] = (int) ($data['content_id'] ?? $existing['content_id'] ?? 0);
        $data['title']      = trim((string) ($data['title'] ?? $existing['title'] ?? ''));
        $data['video_url']  = trim((string) ($data['video_url'] ?? $existing['video_url'] ?? ''));
        $data['cover_url']  = trim((string) ($data['cover_url'] ?? $existing['cover_url'] ?? ''));
        $data['media_type'] = trim((string) ($data['media_type'] ?? $existing['media_type'] ?? 'normal'));
        $data['media_kind'] = trim((string) ($data['media_kind'] ?? $existing['media_kind'] ?? 'video'));
        if (!in_array($data['media_kind'], ['video', 'voice'], true)) {
            $data['media_kind'] = 'video';
        }
        $data['unlock_price'] = (int) ($data['unlock_price'] ?? $existing['unlock_price'] ?? 0);
        $data['keywords']     = trim((string) ($data['keywords'] ?? $existing['keywords'] ?? ''));
        $data['weigh']      = (int) ($data['weigh'] ?? $existing['weigh'] ?? 0);
        $data['status']     = (int) ($data['status'] ?? $existing['status'] ?? 1);

        if ($data['video_url'] === '') {
            $this->error('请先上传视频文件');
        }
        if ($data['title'] === '') {
            $data['title'] = '素材' . ($existing['id'] ?? '');
        }

        $now = date('Y-m-d H:i:s');
        $data['updated_at'] = $now;
        if (empty($existing)) {
            $data['created_at'] = $now;
        }

        return $data;
    }
}
