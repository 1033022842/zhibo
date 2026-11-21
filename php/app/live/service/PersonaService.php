<?php
declare(strict_types=1);

namespace app\live\service;

use app\room\model\Persona;
use app\room\model\Room;
use app\common\exception\BusinessException;
use app\common\util\StrHelper;
use app\common\web\ResultCode;

final class PersonaService
{
    /**
     * AI 前端创建角色 → 写入 lp_persona
     * $withRoom=true：自动创建维护态房间（开播商户流程）
     * $withRoom=false：纯聊天伴侣角色。新版向导流程下 status=4（AI 草稿）——
     *                  免费首图预览满意后由 customRoleSave 扣费转正（status=2），
     *                  草稿不出现在 My AI / 不可聊天（listByUser 过滤）
     */
    public function createFromAi(int $userId, array $data, bool $withRoom = true): array
    {
        $persona = new Persona();
        $persona->code = StrHelper::orderNo('P');
        $persona->name = $data['characterName'] ?? ($data['name'] ?? '未命名角色');
        $persona->cover_url = $data['characterImageUrl'] ?? ($data['photo'] ?? '');
        $persona->status = $withRoom ? 1 : 4; // 4=AI 草稿（未保存，不可聊/不进列表）
        $persona->user_id = $userId;

        // AI 原始属性存 JSON
        $persona->source_fields = $this->extractAiFields($data);
        $persona->created_at = date('Y-m-d H:i:s');
        $persona->updated_at = date('Y-m-d H:i:s');
        $persona->save();

        if ($withRoom) {
            // 自动创建维护态房间，等待运营上传素材
            $room = new Room();
            $room->room_no     = StrHelper::orderNo('R');
            $room->title       = $persona->name . '的直播间';
            $room->persona_id  = $persona->id;
            $room->room_type   = 'live';
            $room->status      = 2; // 2=维护中（等待运营上传素材）
            $room->cover_url   = $persona->cover_url;
            $room->created_at  = date('Y-m-d H:i:s');
            $room->updated_at  = date('Y-m-d H:i:s');
            $room->save();

            // 人设已被房间绑定 → 正在使用
            $persona->status = 2;
            $persona->save();
        }

        return $persona->toArray();
    }

    /**
     * AI 前端获取用户角色列表（含关联直播间状态）
     */
    public function listByUser(int $userId): array
    {
        // AI 草稿（status=4）不出现在 My AI：未扣费保存的角色不可见/不可聊
        $personas = Persona::where('user_id', $userId)
            ->where('status', '<>', 4)
            ->order('id', 'desc')
            ->select()
            ->toArray();

        $roomMap = $this->loadRoomMap(array_column($personas, 'id'));

        return array_map(function (array $p) use ($roomMap): array {
            $room = $roomMap[$p['id']] ?? null;
            return [
                'id'           => (int)$p['id'],
                'name'         => $p['name'],
                'photo'        => $p['cover_url'],
                'status'       => (int)$p['status'],
                'source_fields' => $p['source_fields'] ? json_decode($p['source_fields'], true) : [],
                'room'         => $room ? [
                    'id'     => (int)$room['id'],
                    'status' => (int)$room['status'],
                    'title'  => $room['title'],
                ] : null,
            ];
        }, $personas);
    }

    private function loadRoomMap(array $personaIds): array
    {
        if (empty($personaIds)) {
            return [];
        }

        $rooms = Room::whereIn('persona_id', $personaIds)
            ->field(['id', 'persona_id', 'status', 'title'])
            ->select()
            ->toArray();

        $map = [];
        foreach ($rooms as $room) {
            $map[(int)$room['persona_id']] = $room;
        }
        return $map;
    }

    private function extractAiFields(array $data): ?string
    {
        // 新版向导（createCharacter）直接送英文文案值（如 race=Caucasian、body=Athletic、
        // age=精确整数），聊天侧 persona_desc 可直接拼接；旧向导的数字编码照旧兼容。
        $data = CharacterFields::withLegacyAliases($data);

        // chest 是男性独立词表（前端 v8 男分支），与 breast 并列落库，互不翻译
        $aiKeys = ['type', 'style', 'race', 'age', 'eye', 'hair', 'hairstyle',
                   'body', 'breast', 'chest', 'hip', 'personality', 'profession',
                   'hobby', 'relation', 'clothing', 'voice', 'kinks'];

        $fields = [];
        foreach ($aiKeys as $key) {
            if (!isset($data[$key]) || $data[$key] === '' || $data[$key] === null) {
                continue;
            }
            if (is_array($data[$key]) || is_bool($data[$key])) {
                continue; // public API 异常入参：不落库、不拼接
            }
            $fields[$key] = $data[$key];
        }

        // 新版向导字段：gender / appearance_description / personality_description /
        // ethnicity / butt_size / age_range / chest（归一后与旧字段 race/hip/age 并存，
        // 读值时新字段优先、旧字段回退，见 CharacterFields）
        $fields = array_merge($fields, CharacterFields::normalize($data));

        // 偏好标签：数组或逗号分隔字符串，逐条清理去重
        $preferences = $this->normalizePreferences($data['preferences'] ?? null);
        if ($preferences !== []) {
            $fields['preferences'] = $preferences;
        }

        return empty($fields) ? null : json_encode($fields, JSON_UNESCAPED_UNICODE);
    }

    /** 偏好标签：最多 20 条、每条 60 字符，去重保序；非数组/空值返回 [] */
    private function normalizePreferences($value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,，;；\n]+/u', $value) ?: [];
        }
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if ($item === null || is_array($item) || is_bool($item)) {
                continue;
            }
            $s = CharacterFields::sanitizeDescription((string) $item);
            $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
            if ($s === '') {
                continue;
            }
            if (mb_strlen($s, 'UTF-8') > CharacterFields::MAX_PREFERENCE_LEN) {
                $s = mb_substr($s, 0, CharacterFields::MAX_PREFERENCE_LEN, 'UTF-8');
            }
            $out[$s] = true;
            if (count($out) >= CharacterFields::MAX_PREFERENCES) {
                break;
            }
        }
        return array_keys($out);
    }
}
