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
use app\admin\service\ChannelWorkerManager;

final class Room extends Backend
{
    protected object $model;
    protected array $withJoinTable = ['persona'];
    protected string|array $quickSearchField = ['room_no', 'title', 'subtitle', 'id'];
    protected string|array $preExcludeFields = ['tag_names', 'asset_ids', 'playlist_name'];
    protected bool $modelValidate = false;
    protected string|array $defaultSortField = 'sort,desc';
    protected array $noNeedPermission = ['streamStatus'];

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

        // 预填已选素材 ID，让编辑表单回显
        $rowData = $row->toArray();
        $binding = \app\admin\model\live\RoomBinding::where('room_id', $id)->find();
        if ($binding && !empty($binding->playlist_template_id)) {
            $assetIds = \think\facade\Db::connect('live_mysql')
                ->table('lp_playlist_template_item')
                ->where('template_id', (int) $binding->playlist_template_id)
                ->order('seq', 'asc')
                ->column('asset_id');
            $rowData['asset_ids'] = $assetIds ?: [];
        } else {
            $rowData['asset_ids'] = [];
        }

        $this->success('', ['row' => $rowData]);
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

                // 删除房间后，还原绑定的 persona 为未使用
                if ($room->persona_id > 0) {
                    Db::connect('live_mysql')->table('lp_persona')
                        ->where('id', $room->persona_id)
                        ->update(['status' => 1]);
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

    public function index(): void
    {
        ChannelWorkerManager::resetStreamStatesIfBooted();

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        // 批量查询每个房间的推流状态和播单配置
        $items = $res->items();
        if ($items) {
            $roomIds = array_column($items, 'id');

            // 推流状态
            $states = Db::connect('live_mysql')
                ->table('lp_room_state_snapshot')
                ->whereIn('room_id', $roomIds)
                ->column('current_state', 'room_id');

            // 是否配置了 persona（有人设才能推流）
            $bindings = Db::connect('live_mysql')
                ->table('lp_room_binding')
                ->whereIn('room_id', $roomIds)
                ->whereNotNull('persona')
                ->where('persona', '<>', '')
                ->column('persona', 'room_id');

            // SRS 实时探活：查服务器上真实有推流的房间（AI 电脑 ffmpeg → RTMP → SRS）
            // 用于交叉校验数据库状态：DB 标记推流但 SRS 无流 → abnormal（AI 电脑未推流）
            $srsOnlineIds = (new \app\common\service\SrsService())->getActiveRoomIds('room');
            $srsOnlineMap = array_flip($srsOnlineIds);

            foreach ($items as &$item) {
                $dbState = $states[$item['id']] ?? 'offline';
                $onSrs = isset($srsOnlineMap[(int) $item['id']]);

                if ($dbState === 'public_live' && !$onSrs) {
                    // 数据库标记推流，但 SRS 上没流 → AI 电脑未推流/已断开
                    $item['stream_state'] = 'abnormal';
                    $item['stream_running'] = false;
                } else {
                    $item['stream_state'] = $dbState;
                    $item['stream_running'] = $dbState === 'public_live';
                }
                $item['has_playlist'] = isset($bindings[$item['id']]);
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
     * 启动推流
     */
    public function startStream(): void
    {
        $id = (int) $this->request->param('id');
        if ($id <= 0) {
            $this->error('房间ID无效');
        }

        $room = $this->model->find($id);
        if (!$room) {
            $this->error('房间不存在');
        }

        // 检查房间是否绑定了 persona（人设）
        $binding = \app\admin\model\live\RoomBinding::where('room_id', $id)->find();
        if (!$binding || empty($binding->persona)) {
            $this->error('房间未配置人设(persona)，请在直播房间编辑中设置');
        }

        // 检查是否配置了播单素材
        if (empty($binding->playlist_template_id)) {
            $this->error('房间未配置播单素材，请编辑房间并选择视频素材后保存');
        }
        $itemCount = \think\facade\Db::connect('live_mysql')
            ->table('lp_playlist_template_item')
            ->where('template_id', (int) $binding->playlist_template_id)
            ->count();
        if ($itemCount === 0) {
            $this->error('房间播单素材为空，请编辑房间并选择视频素材后保存');
        }

        $manager = new ChannelWorkerManager();
        $result = $manager->start($id);

        if ($result['ok']) {
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }
    }

    /**
     * 停止推流
     */
    public function stopStream(): void
    {
        $id = (int) $this->request->param('id');
        if ($id <= 0) {
            $this->error('房间ID无效');
        }

        $manager = new ChannelWorkerManager();
        $result = $manager->stop($id);

        if ($result['ok']) {
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }
    }

    /**
     * 查询推流状态
     */
    public function streamStatus(): void
    {
        $id = (int) $this->request->param('id');
        if ($id <= 0) {
            $this->error('房间ID无效');
        }

        $manager = new ChannelWorkerManager();
        $result = $manager->status($id);
        $this->success('', $result);
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
                $oldPersonaId = (int) $room->persona_id;
                $room->save($data);
                $newPersonaId = (int) $data['persona_id'];
                // 更换了 persona：旧的回退为未使用，新的设为正在使用
                if ($oldPersonaId > 0 && $oldPersonaId !== $newPersonaId) {
                    Db::connect('live_mysql')->table('lp_persona')
                        ->where('id', $oldPersonaId)->update(['status' => 1]);
                }
                if ($newPersonaId > 0) {
                    Db::connect('live_mysql')->table('lp_persona')
                        ->where('id', $newPersonaId)->update(['status' => 2]);
                }
            } else {
                $room = new RoomModel();
                $room->save($data);
                // 新 room 绑定的 persona 设为正在使用
                if ((int) $data['persona_id'] > 0) {
                    Db::connect('live_mysql')->table('lp_persona')
                        ->where('id', (int) $data['persona_id'])->update(['status' => 2]);
                }
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

            // 从 lp_persona 获取人设名称，写入 lp_room_binding.persona
            $personaName = '';
            if ((int) ($data['persona_id'] ?? 0) > 0) {
                $persona = Db::connect('live_mysql')->table('lp_persona')
                    ->where('id', (int) $data['persona_id'])->find();
                $personaName = $persona['name'] ?? '';
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
                    'persona' => $personaName,
                ]);
            } else {
                $binding = new RoomBinding();
                $binding->save([
                    'room_id' => $roomIdValue,
                    'room_group_id' => null,
                    'stream_template_id' => $streamTemplateId,
                    'playlist_template_id' => (int) $playlist->id,
                    'persona' => $personaName,
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
