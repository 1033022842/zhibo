<?php

declare(strict_types=1);

namespace app\admin\controller\live;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\live\PostItem as PostItemModel;

/**
 * AI 女友端：Posts 动态管理
 */
final class PostItem extends Backend
{
    protected object $model;
    protected string|array $quickSearchField = ['character_name', 'post_id', 'id'];
    protected bool $modelValidate = false;
    protected string|array $defaultSortField = 'weigh,desc';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new PostItemModel();
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
        $data['post_id']          = trim((string) ($data['post_id'] ?? $existing['post_id'] ?? ''));
        $data['character_name']   = trim((string) ($data['character_name'] ?? $existing['character_name'] ?? ''));
        $data['character_avatar'] = $this->normalizeFileUrl((string) ($data['character_avatar'] ?? $existing['character_avatar'] ?? ''));
        $data['character_url']    = trim((string) ($data['character_url'] ?? $existing['character_url'] ?? ''));
        $data['video_url']        = $this->normalizeFileUrl((string) ($data['video_url'] ?? $existing['video_url'] ?? ''));
        $data['poster_url']       = $this->normalizeFileUrl((string) ($data['poster_url'] ?? $existing['poster_url'] ?? ''));
        $data['description']      = trim((string) ($data['description'] ?? $existing['description'] ?? ''));
        $data['likes']            = max(0, (int) ($data['likes'] ?? $existing['likes'] ?? 0));
        $data['views']            = max(0, (int) ($data['views'] ?? $existing['views'] ?? 0));
        $data['weigh']            = (int) ($data['weigh'] ?? $existing['weigh'] ?? 0);
        $data['status']           = (int) ($data['status'] ?? $existing['status'] ?? 1);

        if ($data['character_name'] === '') {
            $this->error('请填写角色名');
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
