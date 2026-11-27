<?php

declare(strict_types=1);

/**
 * personaProfile 角色资料回归（本地，无需数据库）
 *
 * 覆盖线上真实冲突：lp_home_character.id=99 → Chelsea，lp_ai_content.id=99 → Rita，
 * 以及 home 行缺失/禁用时不得回落到同数字内容。
 *
 * 运行：php php/tests/persona_profile_regression.php
 */

require __DIR__ . '/../vendor/autoload.php';

use app\live\service\PersonaProfileService;

$fail = 0;
$pass = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $fail, $pass;
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
        return;
    }
    $fail++;
    echo "  FAIL {$label}" . ($detail !== '' ? "  ({$detail})" : '') . "\n";
}

function eq(string $label, $expected, $actual): void
{
    check($label, $expected === $actual, 'expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
}

/** 模拟 lp_home_character 行（Chelsea） */
$homeChelsea = [
    'id'          => 99,
    'name'        => 'Chelsea',
    'age'         => '23',
    'tagline'     => 'Sunlit sweetheart',
    'description' => 'Chelsea is the girl next door who remembers your coffee order.',
    'cover_url'   => '/storage/home_character/chelsea.jpg',
    'tags'        => 'Sweet,Girlfriend',
    'status'      => 1,
];

/** 模拟 lp_ai_content 行（Rita） */
$contentRita = [
    'id'          => 99,
    'title'       => 'Rita',
    'description' => 'Rita is a late-night radio host.',
    'cover_url'   => '/storage/ai_media/Rita/cover.jpg',
    'personality' => json_encode([
        '年龄' => '27',
        '族裔' => 'Latina',
        '性格' => 'Bold',
        '爱好' => 'Jazz',
        '关系' => 'Girlfriend',
        '场景' => 'Late night studio',
    ], JSON_UNESCAPED_UNICODE),
    'status'      => 1,
];

echo "[1] role_key 分表解析\n";
eq('home-99 走首页角色表', ['kind' => 'home', 'id' => 99], PersonaProfileService::parseRoleKey('home-99'));
eq('99 走平台内容表', ['kind' => 'content', 'id' => 99], PersonaProfileService::parseRoleKey('99'));
eq('home-099 归一到 99', ['kind' => 'home', 'id' => 99], PersonaProfileService::parseRoleKey('home-099'));
eq('my-279 无服务端资料', ['kind' => 'none', 'id' => 0], PersonaProfileService::parseRoleKey('my-279'));
eq('home-abc 不命中', ['kind' => 'none', 'id' => 0], PersonaProfileService::parseRoleKey('home-abc'));
eq('空串不命中', ['kind' => 'none', 'id' => 0], PersonaProfileService::parseRoleKey(''));

echo "[2] home-99 返回 Chelsea 而不是同号 Rita\n";
$profile = PersonaProfileService::blankProfile('home-99');
PersonaProfileService::applyHomeRow($profile, $homeChelsea);
eq('name', 'Chelsea', $profile['name']);
eq('age', '23', $profile['age']);
eq('description', $homeChelsea['description'], $profile['description']);
eq('cover', '/storage/home_character/chelsea.jpg', $profile['cover']);
eq('loop_url', '/storage/ai_media/Chelsea/loop.mp4', $profile['loop_url']);
eq('不带 Rita 的 scenario', '', $profile['scenario']);
eq('不带 Rita 的 personality', '', $profile['personality']);
check('绝不出现 Rita', strpos(json_encode($profile, JSON_UNESCAPED_UNICODE), 'Rita') === false);

$taglineOnly = $homeChelsea;
unset($taglineOnly['description']);
$profile2 = PersonaProfileService::blankProfile('home-99');
PersonaProfileService::applyHomeRow($profile2, $taglineOnly);
eq('description 为空时回退 tagline', 'Sunlit sweetheart', $profile2['description']);

echo "[3] home-99 缺行/禁用时不回落同号内容\n";
$missing = PersonaProfileService::blankProfile('home-99');
PersonaProfileService::applyHomeRow($missing, null);
eq('name 为空', '', $missing['name']);
eq('cover 为空', '', $missing['cover']);
eq('loop_url 为空', '', $missing['loop_url']);
eq('description 为空', '', $missing['description']);
check('缺行不会变成 Rita', $missing['name'] !== 'Rita');

echo "[4] 纯数字 99 保留旧 lp_ai_content 映射与字段\n";
$legacy = PersonaProfileService::blankProfile('99');
PersonaProfileService::applyContentRow($legacy, $contentRita);
eq('name = title', 'Rita', $legacy['name']);
eq('description', $contentRita['description'], $legacy['description']);
eq('cover', '/storage/ai_media/Rita/cover.jpg', $legacy['cover']);
eq('loop_url 沿用旧拼接', '/storage/ai_media/Rita/loop.mp4', $legacy['loop_url']);
eq('age 来自 personality.年龄', '27', $legacy['age']);
eq('ethnicity', 'Latina', $legacy['ethnicity']);
eq('personality', 'Bold', $legacy['personality']);
eq('hobbies', 'Jazz', $legacy['hobbies']);
eq('relationship', 'Girlfriend', $legacy['relationship']);
eq('scenario', 'Late night studio', $legacy['scenario']);

echo "[5] API data 结构保持不变\n";
$expectKeys = ['role_key', 'name', 'age', 'ethnicity', 'personality', 'hobbies', 'relationship', 'scenario', 'description', 'cover', 'loop_url'];
eq('字段与顺序', $expectKeys, array_keys(PersonaProfileService::blankProfile('home-99')));
eq('role_key 原样回显', 'home-99', PersonaProfileService::blankProfile('home-99')['role_key']);

echo "[6] 空 personality / 非法 JSON 不炸\n";
$noPersona = PersonaProfileService::blankProfile('7');
PersonaProfileService::applyContentRow($noPersona, ['title' => 'Neo', 'description' => '', 'cover_url' => '', 'personality' => null]);
eq('name', 'Neo', $noPersona['name']);
eq('scenario 留空', '', $noPersona['scenario']);
$badJson = PersonaProfileService::blankProfile('8');
PersonaProfileService::applyContentRow($badJson, ['title' => 'Bad', 'personality' => '{not json']);
eq('坏 JSON 不写入属性', '', $badJson['age']);

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
