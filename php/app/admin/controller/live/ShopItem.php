<?php

declare(strict_types=1);

namespace app\admin\controller\live;

use Throwable;
use app\common\controller\Backend;
use app\common\library\MediaUrl;
use app\admin\model\live\ShopItem as ShopItemModel;

/**
 * AI 女友端：Candy Shop 商店商品管理
 */
final class ShopItem extends Backend
{
    protected object $model;
    protected string|array $quickSearchField = ['title', 'id'];
    protected bool $modelValidate = false;
    protected string|array $defaultSortField = 'weigh,desc';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new ShopItemModel();
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
        $data['title']       = trim((string) ($data['title'] ?? $existing['title'] ?? ''));
        $data['description'] = trim((string) ($data['description'] ?? $existing['description'] ?? ''));
        // 上传组件在「未重新上传 / 清空 / 字段以 undefined 挂载」时会回写空串或数组，
        // 只改价格时不能把库里的图片覆盖掉，故统一走 MediaUrl 兜底。
        $data['images']      = MediaUrl::files($data['images'] ?? null, $existing['images'] ?? '');
        $data['cover_url']   = MediaUrl::file($data['cover_url'] ?? null, $existing['cover_url'] ?? '');
        $data['video_url']   = MediaUrl::file($data['video_url'] ?? null, $existing['video_url'] ?? '');
        $data['price']       = max(0, (int) ($data['price'] ?? $existing['price'] ?? 0));
        $data['rating']      = round(min(5, max(0, (float) ($data['rating'] ?? $existing['rating'] ?? 0))), 1);
        $data['reviews']     = max(0, (int) ($data['reviews'] ?? $existing['reviews'] ?? 0));
        $data['weigh']       = (int) ($data['weigh'] ?? $existing['weigh'] ?? 0);
        $data['status']      = (int) ($data['status'] ?? $existing['status'] ?? 1);

        if ($data['title'] === '') {
            $this->error('请填写商品名称');
        }

        $now = date('Y-m-d H:i:s');
        $data['updated_at'] = $now;
        if (empty($existing)) {
            $data['created_at'] = $now;
        }

        return $data;
    }
}
