<?php

declare(strict_types=1);

namespace app\admin\controller\live;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\live\Gift as GiftModel;

final class Gift extends Backend
{
    protected object $model;
    protected string|array $quickSearchField = ['gift_code', 'name', 'id'];
    protected bool $modelValidate = false;
    protected string|array $defaultSortField = 'id,desc';
    protected string|array $preExcludeFields = ['keyword'];
    protected array $noNeedPermission = ['keywords'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new GiftModel();
    }

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
            $giftIds = array_column($items, 'id');
            $mappings = Db::connect('live_mysql')
                ->table('lp_gift_keyword')
                ->whereIn('gift_id', $giftIds)
                ->column('keyword', 'gift_id');

            foreach ($items as &$item) {
                $item['keyword'] = $mappings[$item['id']] ?? '';
            }
            unset($item);
        }

        $this->success('', [
            'list' => $items,
            'total' => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    /**
     * 返回可选关键词列表（20大类）
     */
    public function keywords(): void
    {
        $list = Db::connect('live_mysql')
            ->table('lp_media_asset')
            ->where('persona', '<>', '')
            ->distinct(true)
            ->column('keywords');

        $list = array_values(array_unique(array_filter($list)));
        sort($list);

        $data = array_map(fn($kw) => ['keyword' => $kw], $list);
        $this->success('', ['list' => $data, 'total' => count($data)]);
    }

    public function add(): void
    {
        if (!$this->request->isPost()) {
            $this->error(__('Parameter error'));
        }

        $payload = $this->request->post();
        if (!$payload) {
            $this->error(__('Parameter %s can not be empty', ['']));
        }

        $keyword = $payload['keyword'] ?? '';

        // 保存礼物
        $data = $this->excludeFields($payload);
        $this->model->save($data);
        $giftId = (int) $this->model->id;

        // 保存关键词映射
        $this->syncGiftKeyword($giftId, $keyword);

        $this->success(__('Added successfully'));
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
            $payload = $this->request->post();
            if (!$payload) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $keyword = $payload['keyword'] ?? '';
            $data = $this->excludeFields($payload);
            $row->save($data);

            // 保存关键词映射
            $this->syncGiftKeyword($id, $keyword);

            $this->success(__('Update successful'));
        }

        // 预填关键词
        $existing = Db::connect('live_mysql')
            ->table('lp_gift_keyword')
            ->where('gift_id', $id)
            ->value('keyword');
        $row['keyword'] = $existing ?: '';

        $this->success('', ['row' => $row]);
    }

    private function syncGiftKeyword(int $giftId, string $keyword): void
    {
        // 先删后插
        Db::connect('live_mysql')
            ->table('lp_gift_keyword')
            ->where('gift_id', $giftId)
            ->delete();

        if ($keyword !== '') {
            Db::connect('live_mysql')
                ->table('lp_gift_keyword')
                ->insert([
                    'gift_id' => $giftId,
                    'keyword' => $keyword,
                    'priority' => 0,
                ]);
        }
    }
}
