<?php
declare(strict_types=1);

namespace app\live\service;

use app\live\model\UserSetting;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;

/**
 * 用户偏好设置（对应设置页的 Global chat customization）
 *
 * 存储用 lp_user_setting 的 key/value，聊天时由 Live::chat() 读取并落进提示词：
 *   chat_mode     聊天模式  —— 影响回复风格
 *   reply_length  回复长度  —— 影响提示词里的篇幅要求与 max_tokens
 *   narration     动作旁白  —— 允许用 *星号* 描写动作/场景
 *   timezone      时区      —— 让角色能感知用户的本地时间
 *
 * 其中「强力模式 / 超长回复 / 时区感知」是 Premium Plus 专属，非会员保存时自动降级。
 */
final class UserSettingService
{
    /** 允许的聊天模式（与设置页 data-model-key 一致） */
    private const CHAT_MODES = ['default-1', 'balanced-v69-1', 'thoughtful-26b-1', 'powerful-26blong-1'];
    private const PREMIUM_MODE = 'powerful-26blong-1';
    private const PREMIUM_LENGTH = 4;

    /** 回复长度 -> 提示词要求 */
    private const LENGTH_RULE = [
        0 => '回复要非常简短，通常 1 句话就够。',
        1 => '回复要简短，通常 1~3 句话。',
        2 => '回复长度中等，通常 3~5 句话。',
        3 => '回复可以长一些，2~3 段，内容充实。',
        4 => '回复可以很长，3~4 段，细节丰富、有层次。',
    ];

    /** 回复长度 -> max_tokens 相对 llm.max_tokens 的倍数（索引 1 保持原样，不动现有默认行为） */
    private const LENGTH_RATIO = [0 => 0.3, 1 => 1.0, 2 => 1.4, 3 => 1.8, 4 => 2.5];

    /** 聊天模式 -> 风格补充 */
    private const MODE_RULE = [
        'default-1'          => '',
        'balanced-v69-1'     => '风格更直接、节奏更快，回答干脆利落。',
        'thoughtful-26b-1'   => '风格更细腻，擅长叙述和慢节奏的展开。',
        'powerful-26blong-1' => '表现力最强：主动记住并呼应之前的细节，回答最灵活、最长。',
    ];

    private const DEFAULTS = [
        'chat_mode'    => 'default-1',
        'reply_length' => 1,
        'narration'    => 0,
        'timezone'     => 'off',
    ];

    /** 读取全部设置（缺失项补默认值） */
    public function all(int $userId): array
    {
        $rows = [];
        if ($userId > 0) {
            $rows = UserSetting::where('user_id', $userId)->column('setting_value', 'setting_key');
        }

        $out = [
            'chat_mode'    => (string) ($rows['chat_mode'] ?? self::DEFAULTS['chat_mode']),
            'reply_length' => (int) ($rows['reply_length'] ?? self::DEFAULTS['reply_length']),
            'narration'    => (int) ($rows['narration'] ?? self::DEFAULTS['narration']),
            'timezone'     => (string) ($rows['timezone'] ?? self::DEFAULTS['timezone']),
        ];

        if (!in_array($out['chat_mode'], self::CHAT_MODES, true)) {
            $out['chat_mode'] = self::DEFAULTS['chat_mode'];
        }
        if ($out['reply_length'] < 0 || $out['reply_length'] > 4) {
            $out['reply_length'] = self::DEFAULTS['reply_length'];
        }
        $out['narration'] = $out['narration'] === 1 ? 1 : 0;

        return $out;
    }

    /**
     * 保存设置（只处理传入的键）
     *
     * @param array $entitlements VipService::entitlements() 的结果，用于 Premium Plus 判定
     */
    public function save(int $userId, array $data, array $entitlements = []): array
    {
        $isPlus = (string) ($entitlements['level'] ?? '') === 'premium_plus';
        $save   = [];

        if (array_key_exists('chat_mode', $data)) {
            $mode = trim((string) $data['chat_mode']);
            if (!in_array($mode, self::CHAT_MODES, true)) {
                throw new BusinessException(ResultCode::PARAM_ERROR, '聊天模式不合法');
            }
            if ($mode === self::PREMIUM_MODE && !$isPlus) {
                $mode = self::DEFAULTS['chat_mode'];
            }
            $save['chat_mode'] = $mode;
        }

        if (array_key_exists('reply_length', $data)) {
            $len = (int) $data['reply_length'];
            if ($len < 0 || $len > 4) {
                throw new BusinessException(ResultCode::PARAM_ERROR, '回复长度不合法');
            }
            if ($len === self::PREMIUM_LENGTH && !$isPlus) {
                $len = self::PREMIUM_LENGTH - 1;
            }
            $save['reply_length'] = (string) $len;
        }

        if (array_key_exists('narration', $data)) {
            $save['narration'] = !empty($data['narration']) ? '1' : '0';
        }

        if (array_key_exists('timezone', $data)) {
            $tz = trim((string) $data['timezone']);
            if ($tz !== '' && $tz !== 'off' && !in_array($tz, timezone_identifiers_list(), true)) {
                throw new BusinessException(ResultCode::PARAM_ERROR, '时区不合法');
            }
            if ($tz !== '' && $tz !== 'off' && !$isPlus) {
                $tz = 'off';
            }
            $save['timezone'] = $tz === '' ? 'off' : $tz;
        }

        foreach ($save as $key => $value) {
            $row = UserSetting::where('user_id', $userId)->where('setting_key', $key)->find();
            if (!$row) {
                $row = new UserSetting();
                $row->user_id = $userId;
                $row->setting_key = $key;
            }
            $row->setting_value = $value;
            $row->save();
        }

        return $this->all($userId);
    }

    /**
     * 设置 -> 追加到系统提示词的规则文本（空字符串表示不需要追加）
     */
    public function chatPromptRules(array $settings): string
    {
        $rules = '';

        $len = (int) ($settings['reply_length'] ?? 1);
        $rules .= (self::LENGTH_RULE[$len] ?? self::LENGTH_RULE[1]);

        $modeRule = self::MODE_RULE[(string) ($settings['chat_mode'] ?? '')] ?? '';
        if ($modeRule !== '') {
            $rules .= $modeRule;
        }

        if ((int) ($settings['narration'] ?? 0) === 1) {
            $rules .= '可以用 *星号* 包裹动作与场景描写来区分旁白，但不要每句都用。';
        }

        $tz = (string) ($settings['timezone'] ?? 'off');
        if ($tz !== '' && $tz !== 'off' && in_array($tz, timezone_identifiers_list(), true)) {
            try {
                $now = new \DateTime('now', new \DateTimeZone($tz));
                $rules .= '用户当前本地时间是 ' . $now->format('Y-m-d H:i') . '（' . $tz . '），'
                    . '请自然地感知时间（比如早上问早安、深夜提醒休息）。';
            } catch (\Exception $e) {
                // 时区异常时忽略这条规则，不影响聊天
            }
        }

        return $rules;
    }

    /** 设置 -> 本次请求的 max_tokens（以 llm.max_tokens 为基准按倍数缩放） */
    public function maxTokens(array $settings): int
    {
        $base  = (int) config('llm.max_tokens', 500);
        $len   = (int) ($settings['reply_length'] ?? 1);
        $ratio = self::LENGTH_RATIO[$len] ?? 1.0;

        return max(64, (int) round($base * $ratio));
    }
}
