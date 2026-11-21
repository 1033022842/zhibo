<?php
declare(strict_types=1);

namespace app\live\service;

/**
 * 角色创建字段的归一 + 校验（createCharacter 前后端契约的唯一实现处）
 *
 * 目标：
 *  - 新版向导首创（customRoleCreate）与再生成（customRoleRegenerate）走同一套字段口径：
 *    首创把归一结果写进 lp_persona.source_fields，再生成原样读回来，两条路径共用
 *    CharacterImageService::buildPrompt()，不会一边有 gender、另一边没有。
 *  - 新字段（gender / appearance_description / personality_description / ethnicity /
 *    butt_size / age_range）与旧字段（race / hip / 整数 age 及旧数字编码）并存：
 *    读值一律「新字段优先、旧字段回退」，写值只写新字段，旧字段原样保留。
 *  - 自由描述只落在 appearance_description / personality_description，绝不塞进
 *    race/hip/age/body 等枚举字段（那些字段会被拼进画面 prompt 或池 key）。
 *
 * 只做纯函数式计算，不碰 DB / 不依赖框架，便于 tests/create-character/backend 直接跑。
 */
final class CharacterFields
{
    /** 自由描述落库上限（与前端 0/1000 计数一致） */
    public const MAX_DESCRIPTION = 1000;

    /** 自由描述进画面 prompt 的截断长度：避免用户长文把画面主体挤掉 */
    public const MAX_PROMPT_DESCRIPTION = 200;

    /** 性别缺省：女性（历史女角色与预生成池都是女性口径） */
    public const DEFAULT_GENDER = 'female';

    public const GENDERS = ['female', 'male'];

    /**
     * 族裔：key 为归一后的输入（小写、单空格），value 为落库/prompt 用标准写法。
     * 兼容新旧两套选项（旧向导 Caucasian/Black / Afro/Arab；新向导 White/South Asian/Middle Eastern）。
     */
    private const ETHNICITY_MAP = [
        'white' => 'White',
        'caucasian' => 'White',
        'caucasian / white' => 'White',
        'caucasian/white' => 'White',
        'european' => 'White',
        'asian' => 'Asian',
        'east asian' => 'Asian',
        'east-asian' => 'Asian',
        'chinese' => 'Asian',
        'japanese' => 'Asian',
        'korean' => 'Asian',
        'south asian' => 'South Asian',
        'south-asian' => 'South Asian',
        'indian' => 'South Asian',
        'black' => 'Black',
        'black / afro' => 'Black',
        'black/afro' => 'Black',
        'afro' => 'Black',
        'african' => 'Black',
        'african american' => 'Black',
        'latina' => 'Latina',
        'latino' => 'Latina',
        'hispanic' => 'Latina',
        'arab' => 'Arab',
        'arabic' => 'Arab',
        'middle eastern' => 'Middle Eastern',
        'middle-eastern' => 'Middle Eastern',
        'middle east' => 'Middle Eastern',
        'mena' => 'Middle Eastern',
    ];

    /** 臀围：新向导 Small/Slim/Athletic/Big/Huge，另兼容 Average/Large/Extra Large */
    private const BUTT_MAP = [
        'small' => 'Small',
        'slim' => 'Slim',
        'athletic' => 'Athletic',
        'average' => 'Average',
        'medium' => 'Average',
        'big' => 'Big',
        'large' => 'Large',
        'extra large' => 'Extra Large',
        'xl' => 'Extra Large',
        'huge' => 'Extra Large',
    ];

    /**
     * 新的取值别名 → 旧字段（前端 v3 原型用过 hair_color/body_type 之类的键；
     * 正式契约是旧键 hair/body/breast/hairstyle，这里做兼容映射，不新增落库键）
     */
    private const LEGACY_ALIASES = [
        'hair_color'   => 'hair',
        'hair_style'   => 'hairstyle',
        'body_type'    => 'body',
        'breast_size'  => 'breast',
        'relationship' => 'relation',
        'occupation'   => 'profession',
    ];

    /**
     * 男性胸部：前端 v8 男分支送独立键 chest（Slim/Toned/Muscular/Broad），
     * 与女性 breast 是两套词表，绝不互相翻译（否则男角色会出现 "medium breasts"）。
     */
    private const CHEST_MAP = [
        'slim' => 'Slim',
        'toned' => 'Toned',
        'muscular' => 'Muscular',
        'broad' => 'Broad',
        'average' => 'Average',
        'athletic' => 'Athletic',
        'big' => 'Broad',
        'large' => 'Broad',
    ];

    /** 偏好标签：数组或逗号分隔字符串，落库前逐条清理 */
    public const MAX_PREFERENCES = 20;
    public const MAX_PREFERENCE_LEN = 60;

    /* ------------------------------------------------------------------ */
    /* 归一                                                                */
    /* ------------------------------------------------------------------ */

    /**
     * 把一次创建请求归一成「可落库的增量字段」（只含本次真正提供的键）
     *
     * @return array{gender:string,appearance_description?:string,personality_description?:string,
     *               ethnicity?:string,butt_size?:string,age_range?:string}
     */
    public static function normalize(array $data): array
    {
        // 性别只在**请求真的带了合法值**时才落库。
        // 历史请求没有 gender 键，落库结果必须与改动前逐字节一致，否则每条旧流量的
        // source_fields 都会多出 gender，补池需求被 poolEligible 全判成新向导 → 需求恒为 0，
        // 库存被领空后无法回补。读值路径仍由 gender() 对缺省兜底 female。
        $out = [];
        $gender = self::providedGender($data);
        if ($gender !== '') {
            $out['gender'] = $gender;
        }

        $appearance = self::sanitizeDescription($data['appearance_description'] ?? '');
        if ($appearance !== '') {
            $out['appearance_description'] = $appearance;
        }

        $personality = self::sanitizeDescription($data['personality_description'] ?? '');
        if ($personality !== '') {
            $out['personality_description'] = $personality;
        }

        $ethnicity = self::ethnicity($data);
        if ($ethnicity !== '') {
            $out['ethnicity'] = $ethnicity;
        }

        $butt = self::buttSize($data);
        if ($butt !== '') {
            $out['butt_size'] = $butt;
        }

        $ageRange = self::ageRange($data);
        if ($ageRange !== '') {
            $out['age_range'] = $ageRange;
        }

        // 男性胸部独立落库（不并进 breast）
        $chest = self::chest($data);
        if ($chest !== '') {
            $out['chest'] = $chest;
        }

        return $out;
    }

    /**
     * 旧字段补齐：请求只送了新别名（hair_color 等）时，补回旧键，供 worker v6.2 的
     * buildPrompt 老口径继续可用。已有旧键不覆盖。
     */
    public static function withLegacyAliases(array $data): array
    {
        foreach (self::LEGACY_ALIASES as $alias => $canonical) {
            if (!isset($data[$canonical]) || $data[$canonical] === '' || $data[$canonical] === null) {
                if (isset($data[$alias]) && $data[$alias] !== '' && $data[$alias] !== null) {
                    $data[$canonical] = $data[$alias];
                }
            }
        }
        return $data;
    }

    /* ------------------------------------------------------------------ */
    /* 读值（新字段优先、旧字段回退）                                      */
    /* ------------------------------------------------------------------ */

    /** 性别：female | male，缺省/非法一律 female（历史女角色口径） */
    public static function gender(array $data): string
    {
        $raw = self::key($data['gender'] ?? '');
        return in_array($raw, self::GENDERS, true) ? $raw : self::DEFAULT_GENDER;
    }

    public static function isMale(array $data): bool
    {
        return self::gender($data) === 'male';
    }

    /**
     * 请求里**显式给出**且合法的 gender（female|male）；未提供或非法一律返回 ''
     * 只用于落库判定：缺省不写键，避免把历史请求改写成「带新键」。
     */
    private static function providedGender(array $data): string
    {
        if (!array_key_exists('gender', $data)) {
            return '';
        }
        $raw = self::key($data['gender'] ?? '');
        return in_array($raw, self::GENDERS, true) ? $raw : '';
    }

    /**
     * 族裔：只读新键 ethnicity（前端同时送 race 兼容值，但那条旧路径继续走原 LOOKUPS/
     * humanize 口径，prompt 才能与改动前逐字节一致——旧请求的 race 在这里一律不读）。
     * 数字编码（旧向导 1..5）不在此翻译。
     */
    public static function ethnicity(array $data): string
    {
        foreach (['ethnicity'] as $key) {
            $raw = $data[$key] ?? '';
            if ($raw === '' || $raw === null || is_bool($raw) || is_array($raw)) {
                continue;
            }
            if (is_numeric($raw)) {
                // 旧数字编码：不在这里翻译，返回空串让 prompt 走 LOOKUPS
                return '';
            }
            $k = self::key($raw);
            if (isset(self::ETHNICITY_MAP[$k])) {
                return self::ETHNICITY_MAP[$k];
            }
        }
        return '';
    }

    /**
     * 臀围：只读新键 butt_size。旧键 hip 在历史 prompt 里从未参与出图（LOOKUPS/WORDS 都没有
     * hip 条目），为了不改变旧请求的出图结果，这里不做 hip 回退；hip 仍按原样落库。
     */
    public static function buttSize(array $data): string
    {
        foreach (['butt_size'] as $key) {
            $raw = $data[$key] ?? '';
            if ($raw === '' || $raw === null || is_bool($raw) || is_array($raw)) {
                continue;
            }
            if (is_numeric($raw)) {
                return '';
            }
            $k = self::key($raw);
            if (isset(self::BUTT_MAP[$k])) {
                return self::BUTT_MAP[$k];
            }
        }
        return '';
    }

    /**
     * 男性胸部：只读新键 chest（前端 v8 男分支）。旧键 breast 在 male 请求里一律忽略，
     * 任何情况下都不把 breast 词表套到男性身上。
     */
    public static function chest(array $data): string
    {
        $raw = $data['chest'] ?? '';
        if ($raw === '' || $raw === null || is_bool($raw) || is_array($raw)) {
            return '';
        }
        if (is_numeric($raw)) {
            return '';
        }
        $k = self::key($raw);
        return self::CHEST_MAP[$k] ?? '';
    }

    /**
     * 年龄区间归一：只接受 18 岁及以上的写法
     *  - "18-24" / "25 – 34"  → "18-24"
     *  - "21+"                → "21+"
     *  - "30s"                → "30s"
     */
    public static function ageRange(array $data): string
    {
        $raw = self::key($data['age_range'] ?? '');
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^(\d{2})\s*[-–—~]\s*(\d{2})$/u', $raw, $m) === 1) {
            $lo = (int) $m[1];
            $hi = (int) $m[2];
            if ($lo >= 18 && $hi >= $lo && $hi <= 80) {
                return $lo . '-' . $hi;
            }
            return '';
        }

        if (preg_match('/^(\d{2})\s*\+$/', $raw, $m) === 1) {
            $n = (int) $m[1];
            return ($n >= 18 && $n <= 80) ? $n . '+' : '';
        }

        if (preg_match('/^(\d{2})\s*s$/', $raw, $m) === 1) {
            $n = (int) $m[1];
            return ($n >= 18 && $n <= 80) ? $n . 's' : '';
        }

        return '';
    }

    /**
     * 代表年龄：整数 age 优先（旧字段），否则用 age_range 折算一个代表值。
     * 仅供画面 prompt / 池分箱使用，不回写 lp_persona.age。
     */
    public static function representativeAge(array $data): ?int
    {
        $age = $data['age'] ?? null;
        if (is_numeric($age)) {
            $n = (int) $age;
            if ($n >= 18 && $n <= 80) {
                return $n;
            }
        }

        $range = self::ageRange($data);
        if ($range === '') {
            return null;
        }
        if (preg_match('/^(\d{2})-(\d{2})$/', $range, $m) === 1) {
            return (int) round(((int) $m[1] + (int) $m[2]) / 2);
        }
        if (preg_match('/^(\d{2})\+$/', $range, $m) === 1) {
            return min(80, (int) $m[1] + 2);
        }
        if (preg_match('/^(\d{2})s$/', $range, $m) === 1) {
            return min(80, (int) $m[1] + 4);
        }
        return null;
    }

    /* ------------------------------------------------------------------ */
    /* 自由描述                                                            */
    /* ------------------------------------------------------------------ */

    /** 外貌自由描述（进画面 prompt 的唯一来源） */
    public static function appearanceDescription(array $data): string
    {
        return self::sanitizeDescription($data['appearance_description'] ?? '');
    }

    /** 个性自由描述（只做聊天人设，不进画面 prompt） */
    public static function personalityDescription(array $data): string
    {
        return self::sanitizeDescription($data['personality_description'] ?? '');
    }

    /**
     * 落库用文本清理：去标签、去控制字符、按 1000 字符截断。
     * 非法 UTF-8 直接丢弃，返回空串（不抛异常，不影响建角色主流程）。
     */
    public static function sanitizeDescription($value): string
    {
        if ($value === null || is_array($value) || is_bool($value)) {
            return '';
        }
        $s = (string) $value;
        if ($s === '') {
            return '';
        }
        if (!mb_check_encoding($s, 'UTF-8')) {
            return '';
        }
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = strip_tags($s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
        $s = trim($s);
        if (mb_strlen($s, 'UTF-8') > self::MAX_DESCRIPTION) {
            $s = mb_substr($s, 0, self::MAX_DESCRIPTION, 'UTF-8');
        }
        return $s;
    }

    /**
     * 进 prompt 的文本：只留字母/数字/空格与少量标点，压成单行并截断，
     * 避免自由描述里的标点、换行、伪指令改写 prompt 结构。
     */
    public static function promptDescription($value): string
    {
        if (!is_string($value)) {
            $value = self::sanitizeDescription($value);
        }
        if ($value === '') {
            return '';
        }
        $s = preg_replace('/[^\p{L}\p{N} ,.\'-]/u', ' ', $value) ?? '';
        $s = preg_replace('/\s+/u', ' ', $s) ?? '';
        $s = trim($s);
        if ($s === '') {
            return '';
        }
        if (mb_strlen($s, 'UTF-8') > self::MAX_PROMPT_DESCRIPTION) {
            $s = mb_substr($s, 0, self::MAX_PROMPT_DESCRIPTION, 'UTF-8');
            $s = rtrim($s, " ,.'-");
        }
        return $s;
    }

    /* ------------------------------------------------------------------ */

    /** 归一 key：小写、去首尾空白、内部空白压成单空格 */
    private static function key($value): string
    {
        if ($value === null || is_array($value) || is_bool($value) || is_object($value)) {
            return '';
        }
        $s = strtolower(trim((string) $value));
        if ($s === '') {
            return '';
        }
        return preg_replace('/\s+/u', ' ', $s) ?? $s;
    }
}
