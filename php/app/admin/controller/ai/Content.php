<?php

declare(strict_types=1);

namespace app\admin\controller\ai;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\ai\Content as ContentModel;

final class Content extends Backend
{
    protected object $model;
    protected string|array $quickSearchField = ['title', 'category', 'id'];
    protected bool $modelValidate = false;
    protected string|array $defaultSortField = 'weigh,desc';
    protected int|string $limit = 20;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new ContentModel();
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

    /**
     * 归一化提交数据：默认值 + 时间戳
     */
    private function normalizePayload(array $data, array $existing = []): array
    {
        $data['title']       = trim((string) ($data['title'] ?? $existing['title'] ?? ''));
        $data['category']    = trim((string) ($data['category'] ?? $existing['category'] ?? ''));
        $data['cover_url']   = trim((string) ($data['cover_url'] ?? $existing['cover_url'] ?? ''));
        $data['description'] = (string) ($data['description'] ?? $existing['description'] ?? '');
        $data['weigh']       = (int) ($data['weigh'] ?? $existing['weigh'] ?? 0);
        $data['is_public']   = (int) ($data['is_public'] ?? $existing['is_public'] ?? 1);
        $data['status']      = (int) ($data['status'] ?? $existing['status'] ?? 1);

        // personality：前端可能传数组或 JSON 字符串，统一转为数组（模型 $json 字段会自动入库为 JSON）
        if (isset($data['personality']) && is_string($data['personality'])) {
            $decoded = json_decode($data['personality'], true);
            if (is_array($decoded)) {
                $data['personality'] = $decoded;
            }
        }

        $now = date('Y-m-d H:i:s');
        $data['updated_at'] = $now;
        if (empty($existing)) {
            $data['created_at'] = $now;
        }

        return $data;
    }
}
