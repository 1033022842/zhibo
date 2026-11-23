<?php

namespace app\live\service;

/**
 * 聊天「发张照片」动作路由（第一版，最小闭环）
 *
 * 职责只有一个：判断这句话是不是**高置信**的「想看我照片」请求，并排除
 * 否定（别发照片）、引用（"发张照片"英文怎么说）、用户上传意图（我发张照片给你）。
 * 真正的能力判定（有没有素材、要不要登录）留在控制器里做，因为只有那里
 * 才知道 role_key / token / 素材目录。
 *
 * 开关：lp_platform_config.chat_photo_cta = 0 时整条链路关闭（无需发版即可回退）。
 */
class ChatPhotoIntent
{
    /** 明确要照片的请求（中英常见高置信表达） */
    private const PHOTO_PATTERNS = [
        '/(?:发|给|看|来|传)\s*(?:我|他|她)?\s*(?:一张|张|些|点)?\s*(?:照片|相片|图片|自拍|照)/u',
        '/(?:照片|相片|图片|自拍)\s*(?:发|给|看|来|拿来)/u',
        '/看看你(?:现在)?(?:长什么样|的样子|嘛|吧)?/u',
        '/\b(?:send|show|see|got|have|got any)\b[^.!?\n]{0,24}\b(?:photo|photos|pic|pics|picture|pictures|selfie|selfies|image|images)\b/i',
        '/\b(?:photo|photos|pic|pics|picture|pictures|selfie|selfies)\b[^.!?\n]{0,12}\b(?:pls|please|now|of you|of yourself)\b/i',
    ];

    /** 否定：别发 / 不要照片 / stop sending pics */
    private const NEGATIVE_PATTERNS = [
        '/(?:别|不要|不用|不需要|不必|停止|不许|禁止|不准)\s*(?:再)?\s*(?:发|给|看|传)/u',
        '/(?:别|不要|不用|不需要|不必)\s*[^。！？\n]{0,6}(?:照片|相片|图片|图|自拍)/u',
        '/\b(?:don\'?t|do not|stop|no more|never)\b[^.!?\n]{0,20}\b(?:send|show|photo|photos|pic|pics|picture|pictures|selfie|image)\b/i',
    ];

    /** 引用 / 问怎么翻译：把「发张照片」当句子讨论，不是请求 */
    private const QUOTE_PATTERNS = [
        '/怎么说|什么意思|啥意思|怎么讲|怎么翻译|翻译一下|用英文|用中文|英文是/u',
        '/\bhow (?:do|to|would) (?:i|you|we) say\b|\bwhat does\b[^?\n]{0,24}\bmean\b|\btranslate\b|\bhow to say\b/i',
    ];

    /** 用户要上传/自己发图：方向相反，不触发角色发图 */
    private const UPLOAD_PATTERNS = [
        '/我\s*(?:来|先|也|这边)?\s*(?:发|传|拍|给)\s*[^。！？\n]{0,10}(?:照片|相片|图片|图|自拍)/u',
        '/\b(?:i|i\'ll|i will|let me|can i)\b[^.!?\n]{0,14}\b(?:send|share|upload|show)\b[^.!?\n]{0,14}\b(?:photo|photos|pic|picture|selfie)\b/i',
    ];

    /**
     * 返回 'photo' 表示这是一次明确且非否定的看照片请求；null 表示不处理。
     */
    public static function detect(string $message): ?string
    {
        $text = trim($message);
        if ($text === '' || mb_strlen($text) > 300) {
            return null;
        }

        // 否定 / 引用 / 上传意图优先判掉，宁可不出卡也不要发错
        foreach ([self::NEGATIVE_PATTERNS, self::QUOTE_PATTERNS, self::UPLOAD_PATTERNS] as $group) {
            foreach ($group as $re) {
                if (preg_match($re, $text)) {
                    return null;
                }
            }
        }

        foreach (self::PHOTO_PATTERNS as $re) {
            if (preg_match($re, $text)) {
                return 'photo';
            }
        }

        return null;
    }

    /** 后台开关，缺省开启 */
    public static function isEnabled(): bool
    {
        try {
            $value = \think\facade\Db::connect('live_mysql')
                ->table('lp_platform_config')
                ->where('key', 'chat_photo_cta')
                ->value('value');
            if ($value === null || $value === '') {
                return true;
            }
            return (string) $value !== '0';
        } catch (\Throwable $e) {
            return true;
        }
    }
}
