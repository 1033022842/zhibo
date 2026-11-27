<?php

declare(strict_types=1);

namespace app\live\service;

/**
 * 角色资料解析（纯逻辑，可脱离数据库做本地回归）。
 *
 * role_key 是两套 id 空间的联合键，同号大量冲突（线上实测 home-99 = Chelsea，
 * 而 lp_ai_content.id=99 = Rita），所以必须按前缀分表，禁止互相当同一个人：
 *   home-N → lp_home_character（首页推荐角色，与 Home::characters 同源）
 *   N      → lp_ai_content（平台内容角色，保留旧字段映射）
 *   my-N / 空 / 其它 → 无服务端资料（自建角色由前端本地人设缓存补）
 * home-N 查不到启用行时保持空身份，绝不回落到 lp_ai_content 同号内容。
 */
final class PersonaProfileService
{
    public const KIND_HOME    = 'home';
    public const KIND_CONTENT = 'content';
    public const KIND_NONE    = 'none';

    /**
     * @return array{kind:string, id:int}
     */
    public static function parseRoleKey(string $raw): array
    {
        $raw = trim($raw);
        if (preg_match('/^home-(\d+)$/', $raw, $m) === 1) {
            return ['kind' => self::KIND_HOME, 'id' => (int) $m[1]];
        }
        if (preg_match('/^\d+$/', $raw, $m) === 1) {
            return ['kind' => self::KIND_CONTENT, 'id' => (int) $m[0]];
        }
        return ['kind' => self::KIND_NONE, 'id' => 0];
    }

    /**
     * 填充首页推荐角色（lp_home_character）。
     *
     * @param array<string, mixed>       $out
     * @param array<string, mixed>|null  $row
     */
    public static function applyHomeRow(array &$out, ?array $row): void
    {
        // 缺失 / 已禁用：id 空间不同，同数字的 lp_ai_content 是另一个人，不能兜底
        if ($row === null) {
            return;
        }
        $name = (string) ($row['name'] ?? '');
        $out['name'] = $name;
        $out['age']  = (string) ($row['age'] ?? '');

        $desc = trim((string) ($row['description'] ?? ''));
        if ($desc === '') {
            $desc = trim((string) ($row['tagline'] ?? ''));
        }
        $out['description'] = $desc;
        $out['cover']       = (string) ($row['cover_url'] ?? '');

        // 素材命名约定与 Home::characters / chat-pfp-loop.js 一致：ai_media/<name>/loop.mp4
        if (trim($name) !== '') {
            $out['loop_url'] = '/storage/ai_media/' . $name . '/loop.mp4';
        }
        // lp_home_character 没有族裔 / 性格 / 爱好 / 关系 / 场景字段：
        // 不按同名或同号去借 lp_ai_content 的人设（会再次串成另一个角色）。
    }

    /**
     * 填充平台内容角色（lp_ai_content）。纯数字 role_key 的旧行为与字段保持不变。
     *
     * @param array<string, mixed>       $out
     * @param array<string, mixed>|null  $row
     */
    public static function applyContentRow(array &$out, ?array $row): void
    {
        if ($row === null) {
            return;
        }
        $out['name']        = (string) ($row['title'] ?? '');
        $out['description'] = (string) ($row['description'] ?? '');
        $out['cover']       = (string) ($row['cover_url'] ?? '');
        // 动态图（loop.mp4）：相对路径交由全局中间件转签名 URL
        $out['loop_url']    = '/storage/ai_media/' . $out['name'] . '/loop.mp4';

        $p = ($row['personality'] ?? null) ? json_decode((string) $row['personality'], true) : [];
        if (is_array($p)) {
            $map = [
                '年龄' => 'age',
                '族裔' => 'ethnicity',
                '性格' => 'personality',
                '爱好' => 'hobbies',
                '关系' => 'relationship',
                '场景' => 'scenario',
            ];
            foreach ($map as $k => $field) {
                if (isset($p[$k]) && is_string($p[$k]) && $p[$k] !== '') {
                    $out[$field] = $p[$k];
                }
            }
        }
    }

    /**
     * 空资料骨架（保持 API data 结构不变）。
     *
     * @return array<string, string>
     */
    public static function blankProfile(string $raw): array
    {
        return [
            'role_key'     => $raw,
            'name'         => '',
            'age'          => '',
            'ethnicity'    => '',
            'personality'  => '',
            'hobbies'      => '',
            'relationship' => '',
            'scenario'     => '',
            'description'  => '',
            'cover'        => '',
            'loop_url'     => '',
        ];
    }
}
