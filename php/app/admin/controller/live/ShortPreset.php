<?php

declare(strict_types=1);

namespace app\admin\controller\live;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\live\ShortPreset as ShortPresetModel;

/**
 * AI 工作室：卡片管理（每卡代币定价 / 上下架 / 权重）
 *
 * 生成参数（prompt / lora_chain / 采样等）刻意不开放编辑——
 * 这些是调优过的出片参数，误改会直接破坏生成质量。
 */
final class ShortPreset extends Backend
{
    protected object $model;
    protected string|array $quickSearchField = ['title', 'code', 'id'];
    protected bool $modelValidate = false;
    protected string|array $defaultSortField = 'weigh,desc';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new ShortPresetModel();
    }

    /**
     * 列表不吐 prompt/lora_chain 大字段
     */
    public function index(): void
    {
        if (!$this->request->isGet()) {
            $this->error(__('Parameter error'));
        }

        $limit = min(100, max(1, (int) ($this->request->get('limit', 20))));
        $page = max(1, (int) ($this->request->get('page', 1)));
        $kw = trim((string) ($this->request->get('search', '')));

        $q = $this->model->order('weigh', 'desc')->order('id', 'asc');
        if ($kw !== '') {
            $q->where(function ($q2) use ($kw) {
                $q2->whereLike('title', "%{$kw}%")->whereOr()->whereLike('code', "%{$kw}%");
            });
        }
        $total = (clone $q)->count();
        $rows = $q->limit(($page - 1) * $limit, $limit)
            ->field('id, code, title, category, kind, duration_sec, price_tokens, weigh, status, cover_url, created_at')
            ->select()->toArray();

        $this->success('', ['list' => $rows, 'total' => $total, 'page' => $page, 'limit' => $limit]);
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
     * 只允许改展示与定价字段，生成参数（prompt/lora/采样）一律透传原值
     */
    private function normalizePayload(array $data, array $existing): array
    {
        $data['title']        = trim((string) ($data['title'] ?? $existing['title'] ?? ''));
        $data['category']     = trim((string) ($data['category'] ?? $existing['category'] ?? ''));
        $data['kind']         = in_array(($data['kind'] ?? $existing['kind'] ?? ''), ['video', 'image'], true)
            ? (string) ($data['kind'] ?? $existing['kind']) : (string) ($existing['kind'] ?? 'video');
        $data['duration_sec'] = max(0, min(60, (int) ($data['duration_sec'] ?? $existing['duration_sec'] ?? 0)));
        $data['price_tokens'] = round(max(-1.0, min(99999.0, (float) ($data['price_tokens'] ?? $existing['price_tokens'] ?? -1))), 2);
        $data['weigh']        = (int) ($data['weigh'] ?? $existing['weigh'] ?? 0);
        $data['status']       = (int) ($data['status'] ?? $existing['status'] ?? 1) ? 1 : 0;

        $now = date('Y-m-d H:i:s');
        $data['updated_at'] = $now;

        return $data;
    }
}
