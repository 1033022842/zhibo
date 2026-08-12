<?php

declare(strict_types=1);

namespace app\admin\controller\live;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\live\MediaAsset as MediaAssetModel;

final class MediaAsset extends Backend
{
    protected object $model;
    protected string|array $quickSearchField = ['asset_code', 'title', 'id'];
    protected bool $modelValidate = false;
    protected string|array $defaultSortField = 'id,desc';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new MediaAssetModel();
    }

    /**
     * 获取所有 AI 电脑上报来源的 machine_id 列表（供前端 tab 切换）
     * 返回 [{machine_id, persona, cnt}]
     */
    public function machines(): void
    {
        $rows = \think\facade\Db::connect('live_mysql')
            ->table('lp_media_asset')
            ->where('source', 'machine')
            ->where('machine_id', '<>', '')
            ->field('machine_id, persona, COUNT(*) AS cnt')
            ->group('machine_id', 'persona')
            ->order('machine_id', 'asc')
            ->select()
            ->toArray();

        $this->success('', ['list' => $rows]);
    }

    /**
     * 按人设 + 关键词分组返回素材树（供编辑房间时多级菜单选择视频）
     * GET /admin/live.MediaAsset/tree?persona=白毛女
     * 返回 [{keyword, children: [{id, title, ...}]}]
     * 空关键词归到"待分类"
     */
    public function tree(): void
    {
        $persona = trim((string) $this->request->get('persona', ''));
        $assetIds = $this->request->get('asset_ids/a', []);

        if ($persona === '') {
            $this->success('', ['tree' => [], 'selected' => []]);
            return;
        }

        // 查该人设的所有启用视频
        $query = \think\facade\Db::connect('live_mysql')
            ->table('lp_media_asset')
            ->where('persona', $persona)
            ->where('asset_type', 'video')
            ->where('status', 1)
            ->field('id, title, keywords, file_url, duration_ms');
        $rows = $query->order('id', 'asc')->select()->toArray();

        // 按关键词分组（空关键词归"待分类"）
        $groups = [];
        foreach ($rows as $row) {
            $kws = trim((string) ($row['keywords'] ?? ''));
            $kwList = $kws === '' ? [''] : array_map('trim', explode(',', $kws));
            foreach ($kwList as $kw) {
                $key = $kw === '' ? '待分类' : $kw;
                if (!isset($groups[$key])) {
                    $groups[$key] = [];
                }
                $groups[$key][] = [
                    'id'    => (int) $row['id'],
                    'title' => $row['title'],
                    'duration_ms' => (int) ($row['duration_ms'] ?? 0),
                ];
            }
        }

        // 组装成树形结构
        $tree = [];
        foreach ($groups as $keyword => $children) {
            $tree[] = [
                'keyword'  => $keyword,
                'label'    => $keyword . ' (' . count($children) . ')',
                'children' => $children,
            ];
        }

        // 选中的 asset_ids 对应的视频详情（编辑回显用）
        $selected = [];
        if (!empty($assetIds)) {
            $selected = \think\facade\Db::connect('live_mysql')
                ->table('lp_media_asset')
                ->whereIn('id', $assetIds)
                ->where('status', 1)
                ->column('id', 'id');
        }

        $this->success('', ['tree' => $tree, 'selected' => $selected]);
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
     * @throws Throwable
     */
    public function select(): void
    {
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $where[] = ['media_asset.status', '=', (int) $this->request->param('status/d', 1)];

        $sceneType = trim((string) $this->request->param('scene_type/s', ''));
        if ($sceneType !== '') {
            $where[] = ['media_asset.scene_type', '=', $sceneType];
        }

        $assetType = trim((string) $this->request->param('asset_type/s', ''));
        if ($assetType !== '') {
            $where[] = ['media_asset.asset_type', '=', $assetType];
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

    private function normalizePayload(array $data, array $existing = []): array
    {
        $title = trim((string) ($data['title'] ?? $existing['title'] ?? ''));
        $existingFileUrl = $this->normalizeFileUrl((string) ($existing['file_url'] ?? ''));
        $fileUrl = $this->normalizeFileUrl((string) ($data['file_url'] ?? $existing['file_url'] ?? ''));
        $assetCode = trim((string) ($data['asset_code'] ?? $existing['asset_code'] ?? ''));

        if ($fileUrl === '') {
            $this->error('请先上传视频文件');
        }
        if ($title === '') {
            $title = $this->deriveTitle($fileUrl);
        }
        if ($assetCode === '') {
            $assetCode = 'asset_' . date('YmdHis') . '_' . substr(md5($title . '|' . microtime(true)), 0, 6);
        }

        $data['asset_code'] = $assetCode;
        $data['title'] = $title;
        $data['file_url'] = $fileUrl;
        $data['asset_type'] = (string) ($data['asset_type'] ?? $existing['asset_type'] ?? 'video');
        $data['scene_type'] = (string) ($data['scene_type'] ?? $existing['scene_type'] ?? 'public');
        $data['duration_ms'] = (int) ($data['duration_ms'] ?? $existing['duration_ms'] ?? 0);
        $data['status'] = (int) ($data['status'] ?? $existing['status'] ?? 1);
        $data['checksum'] = $this->resolveChecksum(
            $fileUrl,
            trim((string) ($data['checksum'] ?? '')),
            $existingFileUrl,
            (string) ($existing['checksum'] ?? '')
        );

        // 新字段：keywords / persona / weight
        $data['keywords'] = $this->normalizeKeywords((string) ($data['keywords'] ?? $existing['keywords'] ?? ''));
        $data['persona'] = trim((string) ($data['persona'] ?? $existing['persona'] ?? ''));
        $data['weight'] = (int) ($data['weight'] ?? $existing['weight'] ?? 1);

        return $data;
    }

    /**
     * keywords 多选数组 -> 逗号分隔字符串
     */
    private function normalizeKeywords(string $keywords): string
    {
        if ($keywords === '') {
            return '';
        }
        // 如果已是逗号分隔字符串
        if (!str_contains($keywords, '[')) {
            $parts = array_map('trim', explode(',', $keywords));
        } else {
            // JSON 数组格式
            $parts = json_decode($keywords, true) ?: [];
            if (!is_array($parts)) {
                $parts = [$keywords];
            }
        }
        $parts = array_filter($parts, fn($s) => is_string($s) && trim($s) !== '');
        return implode(',', array_unique(array_map('trim', $parts)));
    }

    private function normalizeFileUrl(string $fileUrl): string
    {
        $fileUrl = trim($fileUrl);
        if ($fileUrl === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $fileUrl)) {
            $path = (string) parse_url($fileUrl, PHP_URL_PATH);
            if ($path !== '') {
                $storagePath = $this->normalizeStorageRelativePath($path);
                if ($storagePath !== '') {
                    return $storagePath;
                }
            }
            return $fileUrl;
        }

        $storagePath = $this->normalizeStorageRelativePath($fileUrl);
        if ($storagePath !== '') {
            return $storagePath;
        }

        return $fileUrl;
    }

    private function normalizeStorageRelativePath(string $path): string
    {
        $normalizedPath = '/' . ltrim(str_replace('\\', '/', trim($path)), '/');
        if ($normalizedPath === '/') {
            return '';
        }

        if (str_starts_with($normalizedPath, '/storage/')) {
            return $normalizedPath;
        }

        $publicStorageRoot = rtrim(str_replace('\\', '/', root_path() . 'public/storage'), '/');
        if (str_starts_with($normalizedPath, $publicStorageRoot . '/')) {
            return '/storage/' . ltrim(substr($normalizedPath, strlen($publicStorageRoot) + 1), '/');
        }

        if (str_starts_with($normalizedPath, '/public/storage/')) {
            return '/storage/' . ltrim(substr($normalizedPath, strlen('/public/storage/')), '/');
        }

        if (str_starts_with($normalizedPath, '/storage')) {
            return '/storage/' . ltrim(substr($normalizedPath, strlen('/storage')), '/');
        }

        return '';
    }

    private function deriveTitle(string $fileUrl): string
    {
        $path = preg_match('/^https?:\/\//i', $fileUrl) ? ((string) parse_url($fileUrl, PHP_URL_PATH)) : $fileUrl;
        $filename = pathinfo($path, PATHINFO_FILENAME);
        return $filename !== '' ? $filename : '未命名素材';
    }

    private function resolveChecksum(
        string $fileUrl,
        string $submittedChecksum,
        string $existingFileUrl,
        string $existingChecksum
    ): string
    {
        if ($submittedChecksum !== '') {
            return $submittedChecksum;
        }

        if ($existingChecksum !== '' && $existingFileUrl === $fileUrl) {
            return $existingChecksum;
        }

        $localPath = $this->resolveLocalFilePath($fileUrl);
        return $localPath !== '' && is_file($localPath) ? sha1_file($localPath) ?: '' : '';
    }

    private function resolveLocalFilePath(string $fileUrl): string
    {
        if ($fileUrl === '') {
            return '';
        }

        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $fileUrl) || str_starts_with($fileUrl, root_path())) {
            return $fileUrl;
        }

        if (preg_match('/^https?:\/\//i', $fileUrl)) {
            $path = (string) parse_url($fileUrl, PHP_URL_PATH);
            if ($path === '') {
                return '';
            }
            $fileUrl = $path;
        }

        $storagePath = $this->normalizeStorageRelativePath($fileUrl);
        if ($storagePath === '') {
            return '';
        }

        return root_path() . 'public' . str_replace('/', DIRECTORY_SEPARATOR, $storagePath);
    }
}
