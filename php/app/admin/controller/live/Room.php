<?php

declare(strict_types=1);

namespace app\admin\controller\live;

use Throwable;
use think\Model;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\live\Room as RoomModel;
use app\admin\model\live\RoomTag;
use app\admin\model\live\RoomBinding;
use app\admin\model\live\PlaylistTemplate;
use app\admin\model\live\PlaylistTemplateItem;

final class Room extends Backend
{
    protected object $model;
    protected array $withJoinTable = ['persona'];
    protected string|array $quickSearchField = ['room_no', 'title', 'subtitle', 'id'];
    protected string|array $preExcludeFields = ['tag_names', 'asset_ids', 'playlist_name'];
    protected bool $modelValidate = false;
    protected string|array $defaultSortField = 'sort,desc';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new RoomModel();
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

        $this->persistRoom($payload);
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

            $this->persistRoom($payload, $id);
            $this->success(__('Update successful'));
        }

        $this->success('', ['row' => $row]);
    }

    /**
     * @throws Throwable
     */
    public function del(): void
    {
        $ids = array_values(array_filter(array_map('intval', (array) $this->request->param('ids/a', []))));
        if (!$ids) {
            $this->error(__('No rows were deleted'));
        }

        Db::connect('live_mysql')->transaction(function () use ($ids) {
            foreach ($ids as $id) {
                /** @var RoomModel|null $room */
                $room = $this->model->find($id);
                if (!$room) {
                    continue;
                }

                $binding = RoomBinding::where('room_id', $id)->find();
                if ($binding) {
                    $playlistId = (int) $binding->playlist_template_id;
                    $binding->delete();

                    if ($playlistId > 0 && RoomBinding::where('playlist_template_id', $playlistId)->count() === 0) {
                        PlaylistTemplateItem::where('template_id', $playlistId)->delete();
                        PlaylistTemplate::destroy($playlistId);
                    }
                }

                RoomTag::where('room_id', $id)->delete();
                $room->delete();
            }
        });

        $this->success(__('Deleted successfully'));
    }

    /**
     * @throws Throwable
     */
    public function select(): void
    {
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $where[] = ['room.status', '=', 1];
        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
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

    private function persistRoom(array $payload, ?int $roomId = null): void
    {
        $data = $this->excludeFields($payload);
        $data['room_no'] = trim((string) ($data['room_no'] ?? ''));
        $data['title'] = trim((string) ($data['title'] ?? ''));
        $data['subtitle'] = trim((string) ($data['subtitle'] ?? ''));
        $data['persona_id'] = (int) ($data['persona_id'] ?? 0);
        $data['room_type'] = 'live';
        $data['status'] = (int) ($data['status'] ?? 1);
        $data['sort'] = (int) ($data['sort'] ?? 0);
        $data['cover_url'] = trim((string) ($data['cover_url'] ?? ''));

        $assetIds = $this->normalizeAssetIds((array) ($payload['asset_ids'] ?? []));
        $tagNames = $this->normalizeTags((string) ($payload['tag_names'] ?? ''));

        if ($data['room_no'] === '' || $data['title'] === '') {
            $this->error('房间号和标题不能为空');
        }
        if ($data['persona_id'] <= 0) {
            $this->error('请选择人设');
        }
        if (!$assetIds) {
            $this->error('请至少选择一个视频素材');
        }

        $this->ensureUniqueRoom($data['room_no'], $roomId);

        Db::connect('live_mysql')->transaction(function () use ($data, $assetIds, $tagNames, $roomId) {
            /** @var RoomModel $room */
            if ($roomId) {
                $room = $this->model->find($roomId);
                if (!$room) {
                    $this->error(__('Record not found'));
                }
                $room->save($data);
            } else {
                $room = new RoomModel();
                $room->save($data);
            }

            $roomIdValue = (int) $room->id;
            RoomTag::where('room_id', $roomIdValue)->delete();
            foreach ($tagNames as $tagName) {
                $tag = new RoomTag();
                $tag->save(['room_id' => $roomIdValue, 'tag_name' => $tagName]);
            }

            $binding = RoomBinding::where('room_id', $roomIdValue)->find();
            $streamTemplateId = $binding ? (int) $binding->stream_template_id : $this->defaultStreamTemplateId();
            if ($streamTemplateId <= 0) {
                $this->error('未找到可用流模板，请先初始化默认流模板');
            }

            $playlist = $binding && $binding->playlist_template_id
                ? PlaylistTemplate::find((int) $binding->playlist_template_id)
                : null;

            if (!$playlist) {
                $playlist = new PlaylistTemplate();
                $playlist->save([
                    'template_code' => 'room_playlist_' . $roomIdValue,
                    'name' => $room->title . '播单',
                    'mode' => 'public',
                    'status' => 1,
                ]);
            } else {
                $playlist->save([
                    'name' => $room->title . '播单',
                    'mode' => 'public',
                    'status' => 1,
                ]);
            }

            PlaylistTemplateItem::where('template_id', (int) $playlist->id)->delete();
            foreach ($assetIds as $index => $assetId) {
                $item = new PlaylistTemplateItem();
                $item->save([
                    'template_id' => (int) $playlist->id,
                    'asset_id' => $assetId,
                    'seq' => $index + 1,
                    'loop_count' => 1,
                    'weight' => 1,
                    'start_offset_ms' => 0,
                ]);
            }

            if ($binding) {
                $binding->save([
                    'stream_template_id' => $streamTemplateId,
                    'playlist_template_id' => (int) $playlist->id,
                ]);
            } else {
                $binding = new RoomBinding();
                $binding->save([
                    'room_id' => $roomIdValue,
                    'room_group_id' => null,
                    'stream_template_id' => $streamTemplateId,
                    'playlist_template_id' => (int) $playlist->id,
                ]);
            }
        });
    }

    private function ensureUniqueRoom(string $roomNo, ?int $roomId = null): void
    {
        $query = $this->model->where('room_no', $roomNo);
        if ($roomId) {
            $query->where('id', '<>', $roomId);
        }
        if ($query->find()) {
            $this->error('房间号已存在');
        }
    }

    private function normalizeAssetIds(array $assetIds): array
    {
        $assetIds = array_values(array_filter(array_map(static fn($id): int => (int) $id, $assetIds)));
        return array_values(array_unique($assetIds));
    }

    private function normalizeTags(string $tagNames): array
    {
        $parts = preg_split('/[\s,，]+/u', trim($tagNames)) ?: [];
        $parts = array_map(static fn(string $tag): string => trim($tag), $parts);
        $parts = array_values(array_filter($parts, static fn(string $tag): bool => $tag !== ''));
        return array_values(array_unique($parts));
    }

    private function defaultStreamTemplateId(): int
    {
        return (int) Db::connect('live_mysql')->name('lp_stream_template')->where('status', 1)->order('id', 'asc')->value('id');
    }
}
