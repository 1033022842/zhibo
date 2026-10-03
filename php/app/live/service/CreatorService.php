<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\util\StrHelper;
use app\common\web\ResultCode;
use app\live\model\CreatorSubmission;
use app\room\model\Persona;
use app\room\model\Room;
use think\facade\Db;

final class CreatorService
{
    /**
     * 提交独立完成的角色（待后台审核）
     */
    public function submit(int $userId, array $data): CreatorSubmission
    {
        Db::startTrans();
        try {
            $item = new CreatorSubmission();
            $item->user_id       = $userId;
            $item->persona_name  = $data['persona_name'];
            $item->description   = $data['description'] ?? '';
            $item->tags          = $data['tags'] ?? '';
            $item->style         = $data['style'] ?? '';
            $item->gender        = $data['gender'] ?? '';
            $item->age_range     = $data['age_range'] ?? '';
            $item->language      = $data['language'] ?? '';
            $item->personality   = $data['personality'] ?? '';
            $item->voice_style   = $data['voice_style'] ?? '';
            $item->publish_type  = $data['publish_type'] ?? 'chat';
            $item->is_adult      = (int) ($data['is_adult'] ?? 0);
            $item->highlights    = $data['highlights'] ?? '';
            $item->reference_url = $data['reference_url'] ?? '';
            $item->cover_url     = $data['cover_url'] ?? '';
            $item->materials     = json_encode($data['materials'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $item->episodes      = json_encode($data['episodes'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $item->free_episodes = (int) ($data['free_episodes'] ?? 0);
            $item->status        = CreatorSubmission::STATUS_PENDING;
            $item->created_at    = date('Y-m-d H:i:s');
            $item->updated_at    = date('Y-m-d H:i:s');
            $item->save();

            Db::commit();
            return $item;
        } catch (BusinessException $e) {
            Db::rollback();
            throw $e;
        } catch (\Exception $e) {
            Db::rollback();
            throw new BusinessException(ResultCode::SERVER_ERROR, '提交失败: ' . $e->getMessage());
        }
    }

    /**
     * 我的创作列表
     */
    public function myCreations(int $userId, int $limit = 100): array
    {
        $rows = CreatorSubmission::where('user_id', $userId)
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        return array_map(static function (array $row): array {
            return [
                'id'            => (int) $row['id'],
                'persona_name'  => (string) $row['persona_name'],
                'description'   => (string) $row['description'],
                'publish_type'  => (string) $row['publish_type'],
                'status'        => (int) $row['status'],
                'is_adult'      => (int) $row['is_adult'],
                'tags'          => self::splitList((string) $row['tags']),
                'cover_url'     => (string) $row['cover_url'],
                'reject_reason' => (string) $row['reject_reason'],
                'created_at'    => (string) $row['created_at'],
                'episode_count' => count((array) json_decode((string) ($row['episodes'] ?? ''), true)),
                'free_episodes' => (int) ($row['free_episodes'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * 我的创作详情（编辑回填）
     */
    public function detail(int $userId, int $id): array
    {
        $item = $this->findOwn($userId, $id);
        $row  = $item->toArray();

        $materials = json_decode((string) $item->materials, true);
        $episodes  = json_decode((string) $item->episodes, true);

        return [
            'id'            => (int) $row['id'],
            'persona_name'  => (string) $row['persona_name'],
            'description'   => (string) $row['description'],
            'tags'          => self::splitList((string) $row['tags']),
            'style'         => (string) $row['style'],
            'gender'        => (string) $row['gender'],
            'age_range'     => (string) $row['age_range'],
            'language'      => (string) $row['language'],
            'personality'   => self::splitList((string) $row['personality']),
            'voice_style'   => (string) $row['voice_style'],
            'publish_type'  => (string) $row['publish_type'],
            'is_adult'      => (int) $row['is_adult'],
            'highlights'    => (string) $row['highlights'],
            'reference_url' => (string) $row['reference_url'],
            'cover_url'     => (string) $row['cover_url'],
            'materials'     => is_array($materials) ? $materials : [],
            'episodes'      => is_array($episodes) ? $episodes : [],
            'free_episodes' => (int) ($row['free_episodes'] ?? 0),
            'status'        => (int) $row['status'],
            'reject_reason' => (string) $row['reject_reason'],
            'created_at'    => (string) $row['created_at'],
        ];
    }

    /**
     * 更新自己的创作（仅待审核 / 已驳回可改，改后重新进入待审核）
     */
    public function update(int $userId, int $id, array $data): CreatorSubmission
    {
        $item = $this->findOwn($userId, $id);
        if ((int) $item->status === CreatorSubmission::STATUS_ONLINE) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '已上线的作品暂不支持修改');
        }

        $item->persona_name  = $data['persona_name'];
        $item->description   = $data['description'] ?? '';
        $item->tags          = $data['tags'] ?? '';
        $item->style         = $data['style'] ?? '';
        $item->gender        = $data['gender'] ?? '';
        $item->age_range     = $data['age_range'] ?? '';
        $item->language      = $data['language'] ?? '';
        $item->personality   = $data['personality'] ?? '';
        $item->voice_style   = $data['voice_style'] ?? '';
        $item->publish_type  = $data['publish_type'] ?? 'chat';
        $item->is_adult      = (int) ($data['is_adult'] ?? 0);
        $item->highlights    = $data['highlights'] ?? '';
        $item->reference_url = $data['reference_url'] ?? '';
        $item->cover_url     = $data['cover_url'] ?? '';
        $item->materials     = json_encode($data['materials'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $item->episodes      = json_encode($data['episodes'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $item->free_episodes = (int) ($data['free_episodes'] ?? 0);
        // 修改后重新进入待审核
        $item->status        = CreatorSubmission::STATUS_PENDING;
        $item->reject_reason = '';
        $item->updated_at    = date('Y-m-d H:i:s');
        $item->save();

        return $item;
    }

    /**
     * 删除自己的创作
     */
    public function remove(int $userId, int $id): void
    {
        $item = $this->findOwn($userId, $id);
        $item->delete();
    }

    /**
     * 按 ID + 归属人查询自己的创作
     */
    private function findOwn(int $userId, int $id): CreatorSubmission
    {
        $item = CreatorSubmission::where('id', $id)->where('user_id', $userId)->find();
        if (!$item) {
            throw new BusinessException(ResultCode::RECORD_NOT_FOUND, '作品不存在');
        }
        return $item;
    }

    /**
     * 作品上架站内（幂等，审核通过后由后台调用）
     *  1) 还没角色的，按提交内容创建 lp_persona（归属提交用户，可聊天）；
     *     publish_type=live 的再补一个维护态直播间（等运营上传素材后开播）
     *  2) 上架首页推荐角色 lp_home_character，分类按性别/风格落到 girls / anime / guys
     * 返回 ['persona_id' => int, 'home_character_id' => int]
     */
    public function publish(int $id): array
    {
        $item = CreatorSubmission::find($id);
        if (!$item) {
            throw new BusinessException(ResultCode::RECORD_NOT_FOUND, '创作不存在');
        }
        if ((int) $item->status !== CreatorSubmission::STATUS_ONLINE) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '作品还没上线，请先审核通过');
        }

        Db::startTrans();
        try {
            // 1) 角色：没有就按提交内容建一个（可聊天）
            $personaId = (int) $item->persona_id;
            if ($personaId <= 0) {
                $persona = new Persona();
                $persona->code      = StrHelper::orderNo('P');
                $persona->user_id   = (int) $item->user_id;
                $persona->name      = mb_substr((string) $item->persona_name, 0, 64);
                $persona->tags      = (string) $item->tags;
                $persona->cover_url = (string) $item->cover_url;
                $persona->status    = 1; // 1=可直接使用
                $persona->source_fields = json_encode([
                    'creator_submission_id' => (int) $item->id,
                    'publish_type' => (string) $item->publish_type,
                    'style'        => (string) $item->style,
                    'gender'       => (string) $item->gender,
                    'age_range'    => (string) $item->age_range,
                    'language'     => (string) $item->language,
                    'personality'  => (string) $item->personality,
                    'voice_style'  => (string) $item->voice_style,
                    'highlights'   => (string) $item->highlights,
                    'description'  => (string) $item->description,
                ], JSON_UNESCAPED_UNICODE);
                $persona->created_at = date('Y-m-d H:i:s');
                $persona->updated_at = date('Y-m-d H:i:s');
                $persona->save();
                $personaId = (int) $persona->id;
                $item->persona_id = $personaId;
            }

            // 直播型作品：建可用直播间（没有才建，避免重复）
            $roomId = 0;
            if ((string) $item->publish_type === 'live' && $personaId > 0) {
                $room = Room::where('persona_id', $personaId)->find();
                if (!$room) {
                    $room = new Room();
                    $room->room_no    = StrHelper::orderNo('R');
                    $room->title      = mb_substr((string) $item->persona_name, 0, 64) . '的直播间';
                    $room->persona_id = $personaId;
                    $room->room_type  = 'live';
                    $room->status     = 1; // 1=启用（有推流后才会出现在前台直播列表）
                    $room->cover_url  = (string) $item->cover_url;
                    $room->created_at = date('Y-m-d H:i:s');
                    $room->updated_at = date('Y-m-d H:i:s');
                    $room->save();
                }
                $roomId = (int) $room->id;
                // 补绑定 + 播单模板，房间才真正可开播（否则后台无从配置）
                $this->ensureRoomBinding($roomId, (string) $room->title, (string) $item->persona_name);
            }

            // 短剧型作品：上架 Candy Shorts（lp_short_item + 剧集），没有才建避免重复
            $shortId = (int) ($item->short_id ?? 0);
            if ((string) $item->publish_type === 'drama' && $shortId <= 0) {
                $shortId = $this->publishShort($item);
                $item->short_id = $shortId;
            }

            // 2) 首页推荐角色卡（没有才上架，避免重复）
            $homeId = (int) $item->home_character_id;
            if ($homeId <= 0) {
                $now = date('Y-m-d H:i:s');
                $homeId = (int) Db::connect('live_mysql')->table('lp_home_character')->insertGetId([
                    'name'        => mb_substr((string) $item->persona_name, 0, 60),
                    'age'         => (string) $item->age_range,
                    'tagline'     => $this->taglineOf($item),
                    'description' => (string) $item->description,
                    'cover_url'   => (string) $item->cover_url,
                    'tags'        => (string) $item->tags,
                    'link_url'    => './Chat.html?id=my-' . $personaId,
                    'is_adult'    => (int) $item->is_adult,
                    'category'    => $this->homeCategory($item),
                    'weigh'       => 0,
                    'status'      => 1,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
                $item->home_character_id = $homeId;
            }

            $item->updated_at = date('Y-m-d H:i:s');
            $item->save();

            Db::commit();
        } catch (BusinessException $e) {
            Db::rollback();
            throw $e;
        } catch (\Exception $e) {
            Db::rollback();
            throw new BusinessException(ResultCode::SERVER_ERROR, '上架失败: ' . $e->getMessage());
        }

        return [
            'persona_id'        => $personaId,
            'home_character_id' => $homeId,
            'room_id'           => $roomId,
            'short_id'          => $shortId,
        ];
    }

    /**
     * 短剧上架：建 lp_short_item + 全部剧集（与后台「短剧管理/短剧剧集」同结构）
     * 返回 lp_short_item.id
     */
    private function publishShort(CreatorSubmission $item): int
    {
        $db  = Db::connect('live_mysql');
        $now = date('Y-m-d H:i:s');

        $episodes = json_decode((string) $item->episodes, true);
        if (!is_array($episodes)) {
            $episodes = [];
        }

        $shortId = (int) $db->table('lp_short_item')->insertGetId([
            'title'          => mb_substr((string) $item->persona_name, 0, 160),
            'description'    => (string) $item->description,
            'free_episodes'  => max(0, (int) $item->free_episodes),
            'category'       => mb_substr((string) $item->tags, 0, 128),
            'character_type' => 'creator', // Candy Creators：创作者投稿
            'poster'         => mb_substr((string) $item->cover_url, 0, 500),
            'video_url'      => '',
            'href'           => '',
            'section'        => 'explore', // 与普通短剧同区
            'rank'           => 0,
            'progress'       => 0,
            'spicy'          => (int) $item->is_adult === 1 ? 1 : 0,
            'featured'       => '',
            'new_episodes'   => 1,
            'weigh'          => 100, // 新上架给一点权重，避免排在分区末尾
            'status'         => 1,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        $seen = [];
        foreach ($episodes as $ep) {
            if (!is_array($ep)) {
                continue;
            }
            $no = max(1, (int) ($ep['episode_no'] ?? 0));
            if (isset($seen[$no])) {
                continue; // 表上 (short_id, episode_no) 唯一
            }
            $seen[$no] = true;

            $db->table('lp_short_episode')->insert([
                'short_id'   => $shortId,
                'episode_no' => $no,
                'title'      => mb_substr((string) ($ep['title'] ?? ''), 0, 160),
                'poster'     => mb_substr((string) ($ep['poster'] ?? ''), 0, 500),
                'video_url'  => mb_substr((string) ($ep['video_url'] ?? ''), 0, 500),
                'duration'   => mb_substr((string) ($ep['duration'] ?? ''), 0, 32),
                'price'      => max(0, (int) ($ep['price'] ?? 0)),
                'weigh'      => 0,
                'status'     => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $shortId;
    }

    /**
     * 补齐房间可用配置：绑定（默认流模板）+ 播单模板；已存在则保持不动
     */
    private function ensureRoomBinding(int $roomId, string $roomTitle, string $personaName): void
    {
        $db = Db::connect('live_mysql');

        $binding = $db->table('lp_room_binding')->where('room_id', $roomId)->find();

        $streamTemplateId = $binding ? (int) $binding['stream_template_id'] : 0;
        if ($streamTemplateId <= 0) {
            $streamTemplateId = (int) $db->table('lp_stream_template')->where('status', 1)->order('id', 'asc')->value('id');
        }
        if ($streamTemplateId <= 0) {
            throw new BusinessException(ResultCode::SERVER_ERROR, '未找到可用流模板，请先在后台初始化默认流模板');
        }

        $playlistId = $binding ? (int) ($binding['playlist_template_id'] ?? 0) : 0;
        if ($playlistId <= 0) {
            $playlistId = (int) $db->table('lp_playlist_template')
                ->where('template_code', 'room_playlist_' . $roomId)->value('id');
        }
        if ($playlistId <= 0) {
            $playlistId = (int) $db->table('lp_playlist_template')->insertGetId([
                'template_code' => 'room_playlist_' . $roomId,
                'name'          => mb_substr($roomTitle, 0, 120) . '播单',
                'mode'          => 'public',
                'status'        => 1,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        }

        $data = [
            'stream_template_id'   => $streamTemplateId,
            'playlist_template_id' => $playlistId,
            'persona'              => mb_substr($personaName, 0, 64),
        ];
        if ($binding) {
            $db->table('lp_room_binding')->where('room_id', $roomId)->update($data);
        } else {
            $db->table('lp_room_binding')->insert($data + ['room_id' => $roomId, 'room_group_id' => null]);
        }
    }

    /**
     * 首页卡片一句话简介：优先作者填的亮点，其次描述前 160 字
     */
    private function taglineOf(CreatorSubmission $item): string
    {
        $text = trim((string) $item->highlights);
        if ($text === '') {
            $text = trim((string) $item->description);
        }
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        return mb_substr($text, 0, 160);
    }

    /**
     * 作品落到首页哪个分类：男→guys，二次元风格→anime，其余→girls
     */
    private function homeCategory(CreatorSubmission $item): string
    {
        if ((string) $item->gender === 'male') {
            return 'guys';
        }
        if ((string) $item->style === 'anime') {
            return 'anime';
        }
        return 'girls';
    }

    /**
     * 收入排行榜：按平台用户累计到手收益降序（公开数据，用于创作中心展示）
     * 数据源为钱包流水 gift_income（礼物分成后角色归属人实际入账的代币）
     */
    public function leaderboard(int $limit = 10): array
    {
        $limit = max(1, min($limit, 50));

        $rows = Db::connect('live_mysql')
            ->table('lp_wallet_ledger l')
            ->join('lp_user u', 'u.id = l.user_id')
            ->where('l.biz_type', 'gift_income')
            ->where('l.direction', 1)
            ->where('l.user_id', '>', 0)
            ->field([
                'l.user_id',
                'u.user_no',
                'u.nickname',
                'u.avatar',
                'SUM(l.amount) AS income',
            ])
            ->group('l.user_id')
            ->order('income', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        $list = [];
        $rank = 1;
        foreach ($rows as $row) {
            $list[] = [
                'rank'     => $rank++,
                'user_id'  => (int) $row['user_id'],
                'user_no'  => (string) $row['user_no'],
                'nickname' => (string) $row['nickname'],
                'avatar'   => (string) ($row['avatar'] ?? ''),
                'income'   => (float) $row['income'],
            ];
        }
        return $list;
    }

    /**
     * 逗号分隔字符串 -> 数组
     */
    private static function splitList(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn($s) => $s !== ''));
    }
}
