<?php

declare(strict_types=1);

namespace app\admin\controller\live;

use Throwable;
use app\common\controller\Backend;
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
        $data['images']      = $this->normalizeImages((string) ($data['images'] ?? $existing['images'] ?? ''));
        $data['cover_url']   = $this->normalizeFileUrl((string) ($data['cover_url'] ?? $existing['cover_url'] ?? ''));
        $data['video_url']   = $this->normalizeFileUrl((string) ($data['video_url'] ?? $existing['video_url'] ?? ''));
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

    /**
     * 附加图片统一为英文逗号分隔，去掉中文逗号、多余空格与空项
     */
    private function normalizeImages(string $images): string
    {
        $images = str_replace(['，', '；', ';', "\r", "\n"], ',', $images);
        $items = array_filter(array_map('trim', explode(',', $images)), static fn (string $v): bool => $v !== '');

        return implode(',', $items);
    }

    /**
     * 站内附件统一存相对路径，避免部署域名变化后失效
     */
    private function normalizeFileUrl(string $url): string
    {
        $url = trim(str_replace('\\', '/', $url));
        if ($url === '' || !preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($path !== '' && str_starts_with($path, '/storage/')) {
            return $path;
        }

        return $url;
    }
}
