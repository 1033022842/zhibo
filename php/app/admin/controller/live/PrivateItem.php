<?php

declare(strict_types=1);

namespace app\admin\controller\live;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\live\PrivateItem as PrivateItemModel;

/**
 * AI 女友端：Private Content 私密内容管理
 */
final class PrivateItem extends Backend
{
    private const MEDIA_TYPES = ['video', 'image', 'mixed'];

    protected object $model;
    protected string|array $quickSearchField = ['title', 'creator', 'id'];
    protected bool $modelValidate = false;
    protected string|array $defaultSortField = 'weigh,desc';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new PrivateItemModel();
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
        $mediaType = (string) ($data['media_type'] ?? $existing['media_type'] ?? 'video');
        if (!in_array($mediaType, self::MEDIA_TYPES, true)) {
            $mediaType = 'video';
        }

        $badge = trim((string) ($data['badge'] ?? $existing['badge'] ?? ''));
        if ($badge !== 'new') {
            $badge = '';
        }

        $data['title']       = trim((string) ($data['title'] ?? $existing['title'] ?? ''));
        $data['poster']      = $this->normalizeFileUrl((string) ($data['poster'] ?? $existing['poster'] ?? ''));
        $data['avatar']      = $this->normalizeFileUrl((string) ($data['avatar'] ?? $existing['avatar'] ?? ''));
        $data['creator']     = trim((string) ($data['creator'] ?? $existing['creator'] ?? ''));
        $data['price']       = max(0, (int) ($data['price'] ?? $existing['price'] ?? 0));
        $data['like_rate']   = max(0, min(100, (int) ($data['like_rate'] ?? $existing['like_rate'] ?? 0)));
        $data['media_type']  = $mediaType;
        $data['video_count'] = max(0, (int) ($data['video_count'] ?? $existing['video_count'] ?? 0));
        $data['duration']    = trim((string) ($data['duration'] ?? $existing['duration'] ?? ''));
        $data['image_count'] = max(0, (int) ($data['image_count'] ?? $existing['image_count'] ?? 0));
        $data['badge']       = $badge;
        $data['purchase_url'] = trim((string) ($data['purchase_url'] ?? $existing['purchase_url'] ?? ''));
        $data['weigh']       = (int) ($data['weigh'] ?? $existing['weigh'] ?? 0);
        $data['status']      = (int) ($data['status'] ?? $existing['status'] ?? 1);

        if ($data['title'] === '') {
            $this->error('请填写描述文案');
        }

        $now = date('Y-m-d H:i:s');
        $data['updated_at'] = $now;
        if (empty($existing)) {
            $data['created_at'] = $now;
        }

        return $data;
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
