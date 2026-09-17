<?php

declare(strict_types=1);

namespace app\admin\controller\live;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\live\ShortEpisode as ShortEpisodeModel;

/**
 * AI 女友端：Candy Shorts 短剧剧集管理
 *
 * 一部短剧（lp_short_item）下挂多集（lp_short_episode），每集单独定价、单独解锁；
 * 短剧上的 free_episodes=N 表示前 N 集免费（集号 <= N 的集不讲价、免钻直接看）。
 */
final class ShortEpisode extends Backend
{
    protected object $model;
    protected string|array $quickSearchField = ['title', 'short_id', 'id'];
    protected bool $modelValidate = false;
    protected string|array $defaultSortField = ['short_id' => 'asc', 'episode_no' => 'asc'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new ShortEpisodeModel();
    }

    /**
     * 列表：补上所属短剧标题与前N集免费标记，方便运营核对
     */
    public function index(): void
    {
        [$where, $alias, $limit, $order] = $this->queryBuilder();
        $res = $this->model
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $items = $res->items();
        if ($items) {
            $shortIds = array_values(array_unique(array_filter(array_map('intval', array_column($items, 'short_id')))));
            $titles = [];
            $freeMap = [];
            if ($shortIds) {
                $series = Db::connect('live_mysql')
                    ->table('lp_short_item')
                    ->whereIn('id', $shortIds)
                    ->field('id, title, free_episodes')
                    ->select()
                    ->toArray();
                foreach ($series as $row) {
                    $titles[(int) $row['id']] = (string) $row['title'];
                    $freeMap[(int) $row['id']] = (int) $row['free_episodes'];
                }
            }

            foreach ($items as &$item) {
                $sid = (int) $item['short_id'];
                $item['short_title'] = $titles[$sid] ?? '';
                // 集号 <= 短剧的 free_episodes 时，本集实际免钻（price 不生效）
                $item['free_by_series'] = ($sid > 0 && ($freeMap[$sid] ?? 0) >= (int) $item['episode_no']) ? 1 : 0;
            }
            unset($item);
        }

        $this->success('', [
            'list'  => $items,
            'total' => $res->total(),
            'remark' => get_route_remark(),
        ]);
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
        $data['short_id']   = max(0, (int) ($data['short_id'] ?? $existing['short_id'] ?? 0));
        $data['episode_no'] = max(1, (int) ($data['episode_no'] ?? $existing['episode_no'] ?? 1));
        $data['title']      = trim((string) ($data['title'] ?? $existing['title'] ?? ''));
        $data['poster']     = $this->normalizeFileUrl((string) ($data['poster'] ?? $existing['poster'] ?? ''));
        $data['video_url']  = $this->normalizeFileUrl((string) ($data['video_url'] ?? $existing['video_url'] ?? ''));
        $data['duration']   = trim((string) ($data['duration'] ?? $existing['duration'] ?? ''));
        $data['price']      = max(0, (int) ($data['price'] ?? $existing['price'] ?? 0));
        $data['weigh']      = (int) ($data['weigh'] ?? $existing['weigh'] ?? 0);
        $data['status']     = (int) ($data['status'] ?? $existing['status'] ?? 1);

        if ($data['short_id'] <= 0) {
            $this->error('请选择所属短剧');
        }

        $series = Db::connect('live_mysql')
            ->table('lp_short_item')
            ->where('id', $data['short_id'])
            ->field('id')
            ->find();
        if (!$series) {
            $this->error('所属短剧不存在');
        }

        // 同一部剧里的集号唯一（表上也有唯一键，这里先给出可读的错误）
        $dup = Db::connect('live_mysql')
            ->table('lp_short_episode')
            ->where('short_id', $data['short_id'])
            ->where('episode_no', $data['episode_no'])
            ->where('id', '<>', (int) ($existing['id'] ?? 0))
            ->find();
        if ($dup) {
            $this->error('该短剧下第 ' . $data['episode_no'] . ' 集已存在');
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
