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
     * AI 前端创建角色 → 写入 lp_persona（status=1 准备中）+ 自动创建维护态房间
     */
    public function createFromAi(int $userId, array $data): array
    {
        $persona = new Persona();
        $persona->code = StrHelper::orderNo('P');
        $persona->name = $data['characterName'] ?? ($data['name'] ?? '未命名角色');
        $persona->cover_url = $data['characterImageUrl'] ?? ($data['photo'] ?? '');
        $persona->status = 1; // 1=准备中
        $persona->user_id = $userId;

        // AI 原始属性存 JSON
        $persona->source_fields = $this->extractAiFields($data);
        $persona->created_at = date('Y-m-d H:i:s');
        $persona->updated_at = date('Y-m-d H:i:s');
        $persona->save();

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

        return $persona->toArray();
    }

    /**
     * AI 前端获取用户角色列表（含关联直播间状态）
     */
    public function listByUser(int $userId): array
    {
        $personas = Persona::where('user_id', $userId)
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
        $aiKeys = ['type', 'race', 'age', 'eye', 'hair', 'hairstyle',
                   'body', 'breast', 'hip', 'personality', 'profession',
                   'hobby', 'relation', 'clothing'];

        $fields = [];
        foreach ($aiKeys as $key) {
            if (isset($data[$key]) && $data[$key] !== '' && $data[$key] !== null) {
                $fields[$key] = $data[$key];
            }
        }

        return empty($fields) ? null : json_encode($fields, JSON_UNESCAPED_UNICODE);
    }
}
