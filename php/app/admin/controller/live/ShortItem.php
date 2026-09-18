<?php

declare(strict_types=1);

namespace app\admin\controller\live;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\live\ShortItem as ShortItemModel;

/**
 * AI 女友端：Candy Shorts 短剧卡片管理
 */
final class ShortItem extends Backend
{
    protected object $model;
    protected string|array $quickSearchField = ['title', 'id'];
    protected bool $modelValidate = false;
    protected string|array $defaultSortField = 'weigh,desc';

    /** 允许的分区 */
    private const SECTIONS = ['continue_watching', 'top_series', 'explore'];
    /** 允许的高亮变体 */
    private const FEATURED = ['', 'ring', 'gradient'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new ShortItemModel();
    }

    /**
     * remoteSelect 下拉数据（「短剧剧集」里选所属短剧用）
     *
     * traits\Backend::index() 在 select=true 时先调本方法；这里必须用 success() 收尾
     * （内部抛 HttpResponseException 截断），否则会继续跑默认的分页查询。
     */
    public function select(): void
    {
        $quickSearch = trim((string) $this->request->get('quickSearch/s', ''));
        $initValue   = (string) $this->request->get('initValue', '');

        $query = $this->model->where('status', 1);
        if ($quickSearch !== '') {
            $query->where('title', 'like', '%' . $quickSearch . '%');
        }

        $list = $query->field('id,title')
            ->order('weigh', 'desc')
            ->order('id', 'desc')
            ->limit(20)
            ->select()
            ->toArray();

        // 编辑回显：当前绑定的剧若已下架或不在前 20 条里，补进列表
        if ($initValue !== '' && !in_array((int) $initValue, array_map('intval', array_column($list, 'id')), true)) {
            $current = $this->model->field('id,title')->find((int) $initValue);
            if ($current) {
                array_unshift($list, ['id' => (int) $current['id'], 'title' => (string) $current['title']]);
            }
        }

        $this->success('', ['list' => $list, 'total' => count($list)]);
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
        $data['title']        = trim((string) ($data['title'] ?? $existing['title'] ?? ''));
        $data['description']  = trim((string) ($data['description'] ?? $existing['description'] ?? ''));
        $data['free_episodes'] = max(0, (int) ($data['free_episodes'] ?? $existing['free_episodes'] ?? 0));
        $data['poster']       = $this->normalizeFileUrl((string) ($data['poster'] ?? $existing['poster'] ?? ''));
        $data['href']         = trim((string) ($data['href'] ?? $existing['href'] ?? ''));
        $data['section']      = (string) ($data['section'] ?? $existing['section'] ?? 'explore');
        $data['rank']         = max(0, (int) ($data['rank'] ?? $existing['rank'] ?? 0));
        $data['progress']     = round(min(100, max(0, (float) ($data['progress'] ?? $existing['progress'] ?? 0))), 2);
        $data['spicy']        = (int) ($data['spicy'] ?? $existing['spicy'] ?? 0) ? 1 : 0;
        $data['featured']     = (string) ($data['featured'] ?? $existing['featured'] ?? '');
        $data['new_episodes'] = (int) ($data['new_episodes'] ?? $existing['new_episodes'] ?? 0) ? 1 : 0;
        $data['weigh']        = (int) ($data['weigh'] ?? $existing['weigh'] ?? 0);
        $data['status']       = (int) ($data['status'] ?? $existing['status'] ?? 1);

        if ($data['title'] === '') {
            $this->error('请填写标题');
        }

        if (!in_array($data['section'], self::SECTIONS, true)) {
            $this->error('分区取值不正确');
        }

        if (!in_array($data['featured'], self::FEATURED, true)) {
            $this->error('高亮变体取值不正确');
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
