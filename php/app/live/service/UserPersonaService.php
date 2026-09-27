<?php
declare(strict_types=1);

namespace app\live\service;

use app\live\model\UserPersona;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;

/**
 * 用户人格设定（设置页「Your personas」弹窗）
 *
 * 一个用户一条记录，字段与弹窗表单 user_persona[...] 一一对应。
 * 聊天时由 Live::chat() 调用 chatPromptRules() 注入 system 提示词，
 * 让 AI 角色知道「对面这个人是谁」，从而做出更贴合的回应。
 */
final class UserPersonaService
{
    /** 单值字段 -> 最大长度（0 表示不截断） */
    private const STRING_FIELDS = [
        'name'        => 15,   // 表单 maxlength=15
        'age_bucket'  => 16,
        'gender'      => 32,
        'location'    => 64,
        'occupation'  => 64,
        'body_type'   => 32,
        'ethnicity'   => 64,
        'hair_color'  => 32,
        'hair_length' => 32,
    ];

    /** 多值字段（JSON 数组存储） */
    private const ARRAY_FIELDS = ['genders_liked', 'looking_for', 'hobby_types'];

    /** 多值字段单项最大长度与最多条数 */
    private const ARRAY_ITEM_MAX  = 64;
    private const ARRAY_COUNT_MAX = 50;

    /** 读取人格设定；未创建时返回 null */
    public function get(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $row = UserPersona::where('user_id', $userId)->find();
        if (!$row) {
            return null;
        }

        return $this->format($row->toArray());
    }

    /**
     * 保存人格设定（整表覆盖式保存，未传的字段保持原值）
     *
     * @return array 保存后的完整设定
     */
    public function save(int $userId, array $data): array
    {
        $save = [];

        foreach (self::STRING_FIELDS as $field => $max) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = trim((string) $data[$field]);
            if ($max > 0 && mb_strlen($value) > $max) {
                throw new BusinessException(ResultCode::PARAM_ERROR, $field . ' 超出长度限制');
            }
            $save[$field] = $value;
        }

        if (array_key_exists('name', $save) && $save['name'] === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, '请填写 Your name');
        }

        if (array_key_exists('height_cm', $data)) {
            $height = (int) $data['height_cm'];
            if ($height < 0 || $height > 300) {
                throw new BusinessException(ResultCode::PARAM_ERROR, '身高不合法');
            }
            $save['height_cm'] = $height;
        }

        if (array_key_exists('active', $data)) {
            $save['active'] = !empty($data['active']) ? 1 : 0;
        }

        foreach (self::ARRAY_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $save[$field] = json_encode($this->normalizeList($data[$field]), JSON_UNESCAPED_UNICODE);
        }

        $row = UserPersona::where('user_id', $userId)->find();
        if (!$row) {
            $row = new UserPersona();
            $row->user_id = $userId;
            // 未传的字段用默认值，避免 NOT NULL 报错
            foreach (array_keys(self::STRING_FIELDS) as $field) {
                $row->$field = '';
            }
            foreach (self::ARRAY_FIELDS as $field) {
                $row->$field = '[]';
            }
        }
        foreach ($save as $field => $value) {
            $row->$field = $value;
        }
        $row->save();

        return $this->format(UserPersona::where('user_id', $userId)->find()->toArray());
    }

    /**
     * 人格设定 -> 追加到系统提示词的规则文本（未创建或未启用时返回空字符串）
     */
    public function chatPromptRules(?array $persona): string
    {
        if (!$persona || (int) ($persona['active'] ?? 0) !== 1) {
            return '';
        }

        $parts = [];

        $labels = [
            'name'        => '称呼',
            'age_bucket'  => '年龄',
            'gender'      => '性别',
            'location'    => '所在地',
            'occupation'  => '职业',
            'body_type'   => '体型',
            'ethnicity'   => '种族',
            'hair_color'  => '发色',
            'hair_length' => '发型',
        ];
        foreach ($labels as $field => $label) {
            $value = trim((string) ($persona[$field] ?? ''));
            if ($value !== '') {
                $parts[] = $label . '：' . $value;
            }
        }

        if ((int) ($persona['height_cm'] ?? 0) > 0) {
            $parts[] = '身高：' . (int) $persona['height_cm'] . 'cm';
        }

        $listLabels = [
            'genders_liked' => '喜欢的性别',
            'looking_for'   => '期望的关系',
            'hobby_types'   => '兴趣爱好',
        ];
        foreach ($listLabels as $field => $label) {
            $list = array_filter(array_map('strval', (array) ($persona[$field] ?? [])));
            if ($list) {
                $parts[] = $label . '：' . implode('、', $list);
            }
        }

        if (!$parts) {
            return '';
        }

        return '关于和你聊天的这个人（用户本人）的设定：' . implode('；', $parts)
            . '。请自然地记住并运用这些信息，让回应更贴合他/她的真实情况，'
            . '但不要生硬地逐条复述这些设定。';
    }

    /** 数据库行 -> 对外结构（多值字段转数组） */
    private function format(array $row): array
    {
        $out = [
            'id'         => (int) $row['id'],
            'name'       => (string) $row['name'],
            'age_bucket' => (string) $row['age_bucket'],
            'gender'     => (string) $row['gender'],
            'location'   => (string) $row['location'],
            'occupation' => (string) $row['occupation'],
            'height_cm'  => (int) $row['height_cm'],
            'body_type'  => (string) $row['body_type'],
            'ethnicity'  => (string) $row['ethnicity'],
            'hair_color' => (string) $row['hair_color'],
            'hair_length' => (string) $row['hair_length'],
            'active'     => (int) $row['active'],
        ];

        foreach (self::ARRAY_FIELDS as $field) {
            $decoded = json_decode((string) $row[$field], true);
            $out[$field] = is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
        }

        return $out;
    }

    /** 多值字段归一化：去空、去重、限长限量 */
    private function normalizeList(mixed $value): array
    {
        if (is_string($value)) {
            $value = $value === '' ? [] : explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $item = trim((string) $item);
            if ($item === '' || mb_strlen($item) > self::ARRAY_ITEM_MAX) {
                continue;
            }
            if (!in_array($item, $out, true)) {
                $out[] = $item;
            }
            if (count($out) >= self::ARRAY_COUNT_MAX) {
                break;
            }
        }

        return $out;
    }
}
