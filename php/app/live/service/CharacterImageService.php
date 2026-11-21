<?php
declare(strict_types=1);

namespace app\live\service;

use app\ai\model\AiTask;
use app\common\enums\TaskStatus;
use app\common\util\StrHelper;
use think\facade\Db;
use think\facade\Log;

/**
 * 角色形象图生成（candy.ai characters_v2 同款流程）
 *
 * customRoleCreate 落库后调用 enqueue()：
 *   - 按角色属性渲染英文正向 prompt（realistic → Qwen-Image t2i / anime → Illustrious-XL）
 *   - 入队 lp_ai_task（task_type=character_image，新角色首图免费，不扣额度/代币）
 *   - GPU worker（v6.2+）按 kind=character 拉取（style 选 workflow），出图后走
 *     /customVideo/uploadVideo + /aiTask/complete；completeTask 负责回写 lp_persona.cover_url
 *
 * 失败不退款（没扣过费）；任务卡死由 AiTaskService::reapStuckCustomTasks 回收（免费重试）。
 */
final class CharacterImageService
{
    /** 形象图任务排队上限（分钟）：与 CustomVideo::TASK_TTL_MIN 同口径 */
    private const TASK_TTL_MIN = 90;

    /** 形象图负向词：worker 覆盖 workflow 节点 77 */
    private const NEGATIVE_REALISTIC = 'blurry, low quality, deformed, distorted, bad anatomy, extra fingers, watermark, text, cartoon';
    private const NEGATIVE_ANIME = 'lowres, worst quality, low quality, bad anatomy, bad hands, extra digits, fewer digits, watermark, signature, username, text, jpeg artifacts';

    /** 旧向导数字编码 → 英文（新向导直接送英文文案值，走 word 分支） */
    private const LOOKUPS = [
        'race'      => [1 => 'Caucasian', 2 => 'Latina', 3 => 'Asian', 4 => 'Arab', 5 => 'Black'],
        'eye'       => [1 => 'brown eyes', 2 => 'blue eyes', 3 => 'green eyes', 4 => 'red eyes', 5 => 'yellow eyes'],
        'hair'      => [1 => 'blonde', 2 => 'brunette', 3 => 'black', 4 => 'red', 5 => 'pink', 6 => 'white', 7 => 'blue', 8 => 'green', 9 => 'purple', 10 => 'multicolor'],
        'hairstyle' => [1 => 'straight hair', 2 => 'braids', 3 => 'bangs', 4 => 'curly hair', 5 => 'hair bun', 6 => 'short hair', 7 => 'long hair', 8 => 'ponytail', 9 => 'pigtails'],
        'body'      => [1 => 'petite body', 2 => 'slim body', 3 => 'athletic body', 4 => 'voluptuous body', 5 => 'curvy body'],
        'breast'    => [1 => 'small breasts', 2 => 'medium breasts', 3 => 'large breasts', 4 => 'extra large breasts', 5 => 'huge breasts'],
    ];

    /** 新向导英文文案 → prompt 片段（只列需要变形的，其余原样小写） */
    private const WORDS = [
        'breast' => [
            'Small' => 'small breasts', 'Medium' => 'medium breasts',
            'Large' => 'large breasts', 'Extra Large' => 'extra large breasts',
        ],
        'hairstyle' => [
            'Straight' => 'straight hair', 'Bangs' => 'bangs', 'Curly' => 'curly hair',
            'Bun' => 'hair bun', 'Short' => 'short hair', 'Ponytail' => 'ponytail',
            'Braids' => 'braids', 'Long' => 'long hair', 'Pigtails' => 'pigtails',
        ],
        'eye' => ['Brown' => 'brown eyes', 'Blue' => 'blue eyes', 'Green' => 'green eyes', 'Red' => 'red eyes', 'Yellow' => 'yellow eyes'],
        'body' => [
            'Skinny' => 'skinny body', 'Athletic' => 'athletic body', 'Average' => 'average body',
            'Curvy' => 'curvy body', 'BBW' => 'BBW body',
            'Petite' => 'petite body', 'Slim' => 'slim body', 'Voluptuous' => 'voluptuous body',
        ],
    ];

    public function buildPrompt(array $fields, string $category): string
    {
        // 新字段（gender/ethnicity/butt_size/age_range/appearance_description）与旧字段
        // 同一口径：buildPrompt 是首创与再生成的唯一 prompt 出口，不区分调用方。
        $fields = CharacterFields::withLegacyAliases($fields);
        $male = CharacterFields::isMale($fields);

        $parts = [];
        // 年龄：整数 age 优先，否则 age_range 折算代表年龄（只影响出图，不回写 lp_persona.age）
        $parts[] = $this->agePart(CharacterFields::representativeAge($fields));
        // 族裔：ethnicity 优先，race 回退（数字旧编码仍走 LOOKUPS）
        $ethnicity = CharacterFields::ethnicity($fields);
        $parts[] = $ethnicity !== '' ? mb_strtolower($ethnicity) : $this->humanize('race', $fields['race'] ?? null);
        $hair = $this->hairPart($fields['hairstyle'] ?? null, $fields['hair'] ?? null);
        if ($hair !== '') {
            $parts[] = $hair;
        }
        $parts[] = $this->humanize('eye', $fields['eye'] ?? null);
        $parts[] = $this->humanize('body', $fields['body'] ?? null);
        // 胸部：男用独立键 chest（Slim/Toned/Muscular/Broad），女用旧键 breast；
        // 男请求即使收到 breast 也绝不出女性词（历史女角色口径不参与，男角色是新增分支）
        if ($male) {
            $parts[] = $this->chestPart($fields);
        } else {
            $parts[] = $this->humanize('breast', $fields['breast'] ?? null);
        }
        // 臀围只对女性出词：男性 screen3 已隐藏 Butt 卡（前端不发），但历史/异常数据若带来
        // butt_size 也不能给男角色拼 "big butt"；女性路径与旧口径完全一致（旧请求无此键 → 空）
        if (!$male) {
            $parts[] = $this->buttPart($fields);
        }

        // 新版 Outfit 固定 Bikini（candy 同款）；旧向导送 clothing 编码时不猜，忽略。
        // 男性不出 bikini（前端 v8 的 clothing 缺省值仍是 'Bikini'，这里按性别兜住）
        $clothing = $this->scalar($fields['clothing'] ?? '');
        $clothingPart = (!$male && strcasecmp(trim($clothing), 'Bikini') === 0) ? 'wearing a bikini' : '';

        // 自由外貌描述：只取 appearance_description，清理成单行短语后进 prompt；
        // personality_description 只做聊天人设，绝不进画面
        $look = CharacterFields::promptDescription(CharacterFields::appearanceDescription($fields));

        $traits = implode(', ', array_filter(array_map('trim', $parts)));
        if ($category === 'anime') {
            $prompt = ($male ? '1boy' : '1girl') . ', solo, ' . $traits
                . ($clothingPart !== '' ? ', ' . $clothingPart : '')
                . ($look !== '' ? ', ' . $look : '')
                . ', looking at viewer, masterpiece, best quality, highly detailed, detailed face, anime artwork';
        } else {
            // 发色短语前置并加 dyed 强调：asian + 粉/蓝等非自然色的模型先验很弱，
            // 不强调会被「黑发」先验吞掉（生产实测 pink 生成出来是黑发）
            $hairColor = strtolower(trim($this->scalar($fields['hair'] ?? '')));
            $prompt = 'Photorealistic portrait photo of a ' . ($male ? 'handsome' : 'beautiful') . ' ' . $traits . ' ' . ($male ? 'man' : 'woman')
                . ($clothingPart !== '' ? ', ' . $clothingPart : '')
                . '. ' . ($look !== '' ? 'Physical details: ' . $look . '. ' : '')
                . ($hairColor !== '' && $hairColor !== 'black'
                    ? ($male ? 'His' : 'Her') . ' hair is dyed exactly ' . $hairColor . ', vivid and clearly visible. '
                    : '')
                . 'Soft studio lighting, detailed skin texture, professional photography, 85mm lens, high quality, 4k';
        }
        return $prompt;
    }

    /**
     * 池是否可用：预生成池是「旧向导口径的共用库存」（fields_hash 由改动前的 poolHash 公式生成），
     * 只承接旧向导能完整表达的请求；任何新向导键出现都走实时生成。
     *  - gender / style / ethnicity / butt_size / age_range / appearance_description / chest
     *    任一出现（非空）→ 不可用池：新维度值不在旧库存词表内，领到就是错图
     *  - Male → 不可用池（池图女主脸）
     * personality_description 不进画面，因此不影响池适用性；preferences 同理。
     * 说明：这个判定只看「键是否存在」，不看值是否与旧口径等价——旧库存一旦错领无法回收，
     * 宁可多走一次实时生成。
     */
    public function poolEligible(array $fields): bool
    {
        if (CharacterFields::isMale($fields)) {
            return false;
        }
        foreach (self::NEW_WIZARD_KEYS as $key) {
            if ($key === 'gender') {
                // 显式 female 与历史缺省完全等价（出图女性分支、不进 key），可以吃旧库存；
                // 只有 male 不可用，已被上面的 isMale 拦下。这样「领图」与「补池」对同一份
                // 请求的判定永远一致，旧流量既能领也持续贡献需求。
                continue;
            }
            $value = $fields[$key] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            if (is_array($value) || is_scalar($value)) {
                return false; // 出现了新键（含异常类型）即视为新向导请求
            }
        }
        return true;
    }

    /**
     * 补池用：一条历史 source_fields 是否构成可补的池需求（ai:pool:cron 唯一判定入口）
     * 返回 null = 不进池，否则返回 ['category' => realistic|anime, 'hash' => 池 key]。
     * 保留改动前的旧前置条件：type 与 race 都必须有（没有 type 无法定分类，没有 race 出图无族裔）。
     */
    public function poolDemand(array $fields): ?array
    {
        if (empty($fields['type']) || empty($fields['race'])) {
            return null;
        }
        if (!$this->poolEligible($fields)) {
            return null;
        }
        return [
            'category' => $this->category($fields['type']),
            'hash'     => $this->poolHash($fields, $this->category($fields['type'])),
        ];
    }

    /** 男性胸部 → prompt 片段（独立键 chest；收到breast 旧别名时也不套用女性词表） */
    private function chestPart(array $fields): string
    {
        $chest = CharacterFields::chest($fields);
        if ($chest === '') {
            return '';
        }
        $map = [
            'Slim' => 'slim chest', 'Toned' => 'toned chest', 'Muscular' => 'muscular chest',
            'Broad' => 'broad chest', 'Average' => 'average chest', 'Athletic' => 'athletic chest',
        ];
        return $map[$chest] ?? '';
    }

    /** 臀围 → prompt 片段（新字段 butt_size；旧 hip 在历史口径里从不参与出图，这里也不回退） */
    private function buttPart(array $fields): string
    {
        $butt = CharacterFields::buttSize($fields);
        if ($butt === '') {
            return '';
        }
        $map = [
            'Small' => 'small butt', 'Slim' => 'slim butt', 'Athletic' => 'athletic butt',
            'Average' => 'average butt', 'Big' => 'big butt', 'Large' => 'large butt',
            'Extra Large' => 'extra large butt',
        ];
        return $map[$butt] ?? '';
    }

    /**
     * 负面词反先验：用户选了非黑发/非棕眼时，把默认发色/瞳色加进负面，
     * 压掉模型的「asian=黑发」「深色眼睛」先验（配合正向 dyed 强调）
     */
    public function buildNegative(string $category, array $fields): string
    {
        $neg = $category === 'anime' ? self::NEGATIVE_ANIME : self::NEGATIVE_REALISTIC;
        $hair = strtolower(trim($this->scalar($fields['hair'] ?? '')));
        $eye = strtolower(trim($this->scalar($fields['eye'] ?? '')));
        if ($hair !== '' && $hair !== 'black') {
            $neg .= ', black hair, dark hair';
        }
        if ($eye !== '' && $eye !== 'brown') {
            $neg .= ', brown eyes';
        }
        return $neg;
    }

    /**
     * 落库成功后入队形象图任务
     * @param array $persona createFromAi 返回的数组（须含 id）
     * @param array $fields 送进 extractAiFields 的原始属性（type/race/age/...）
     */
    public function enqueue(int $userId, array $persona, array $fields): ?AiTask
    {
        $personaId = (int) ($persona['id'] ?? 0);
        if ($personaId <= 0) {
            return null;
        }
        $category = $this->categoryOf($fields);

        try {
            $task = new AiTask();
            $task->task_no       = StrHelper::orderNo('CR');
            $task->room_id       = 0;
            $task->task_type     = 'character_image';
            $task->priority      = 8; // 用户正盯着向导页等图，插队到视频任务前面
            $task->source_type   = 'character_image';
            $task->source_ref_id = $userId;
            $task->persona_id    = $personaId;
            $task->content       = json_encode([
                'style'           => $category,
                'label'           => 'Character',
                'prompt'          => $this->buildPrompt($fields, $category),
                'negative_prompt' => $this->buildNegative($category, $fields),
                'retry'           => 0,
            ], JSON_UNESCAPED_UNICODE);
            $task->callback_mode = 'none';
            $task->status        = TaskStatus::PENDING->value;
            $task->deadline_at   = date('Y-m-d H:i:s', time() + self::TASK_TTL_MIN * 60);
            $task->created_at    = date('Y-m-d H:i:s');
            $task->updated_at    = date('Y-m-d H:i:s');
            $task->save();
            return $task;
        } catch (\Throwable $e) {
            // 入队失败不阻断建角色：角色已存在，前端按无图展示，运营可补素材
            Log::error('[characterImage] enqueue failed: ' . $e->getMessage());
            return null;
        }
    }

    /* ---------- 形象图预生成池（词条组合缓存） ---------- */

    /** 池深：每个词条组合库存几张（领走即减，不足由 ai:pool:cron 补） */
    public const POOL_DEPTH = 3;
    /** 池任务全局在制上限：ai:pool:cron 补池前检查 pending pool_image 数量 */
    public const POOL_MAX_INFLIGHT = 20;

    /**
     * 新向导（v8）独有的键：出现任意一个就说明这条请求用的是新词表，
     * 不能领/不能补旧口径库存（旧库存 key 与 prompt 都是旧词表口径）。
     * 例外：gender 只按 male 排除——显式 female 与历史缺省出图完全一致（见 poolEligible）。
     */
    private const NEW_WIZARD_KEYS = [
        'gender', 'style', 'ethnicity', 'butt_size', 'age_range',
        'appearance_description', 'chest',
    ];

    /** 年龄分箱：连续年龄(18-55) → 池 key 档位 + 出图代表年龄 */
    public function ageBin($age): array
    {
        $n = max(18, min(55, (int) $age));
        if ($n <= 19) {
            return ['bin' => '18-19', 'rep' => 19];
        }
        if ($n <= 24) {
            return ['bin' => '20-24', 'rep' => 22];
        }
        if ($n <= 29) {
            return ['bin' => '25-29', 'rep' => 27];
        }
        if ($n <= 39) {
            return ['bin' => '30-39', 'rep' => 34];
        }
        return ['bin' => '40-55', 'rep' => 45];
    }

    /**
     * 池缓存 key：与改动前 HEAD 的公式**逐字节一致**（category|race|hairstyle|hair|eye|body|breast|ageBin），
     * 因为池里已有库存就是用这个公式建的 key，多拼任何一段（gender/butt 等）都会让老库存全部失配，
     * 旧女性请求的「秒出图」会整体回退成实时生成。
     * 新维度不进 key 是安全的：带 gender/style/ethnicity/butt_size/age_range/appearance_description/chest
     * 的请求在 poolEligible() 已被挡在池外，永远不会用这个 key 去领旧库存。
     */
    public function poolHash(array $fields, string $category): string
    {
        $bin = $this->ageBin($fields['age'] ?? 0);
        $key = implode('|', [
            $category,
            $this->humanize('race', $fields['race'] ?? ''),
            $this->humanize('hairstyle', $fields['hairstyle'] ?? ''),
            $this->humanize('hair', $fields['hair'] ?? ''),
            $this->humanize('eye', $fields['eye'] ?? ''),
            $this->humanize('body', $fields['body'] ?? ''),
            $this->humanize('breast', $fields['breast'] ?? ''),
            $bin['bin'],
        ]);
        return md5($key);
    }

    /** 旧向导入口用：type 归一 + hash 一步到位（与改动前一致） */
    public function poolHashFor(array $fields): string
    {
        return $this->poolHash($fields, $this->category($fields['type'] ?? ''));
    }

    /**
     * 池命中：原子领取一张 ready 图（ready→served，领走即减），返回图 URL；未命中/异常返回 null
     * 池表未部署（静态已同步、后端未升级）时静默回退实时生成路径
     */
    public function claimFromPool(string $hash): ?string
    {
        try {
            $db = Db::connect('live_mysql');
            $now = date('Y-m-d H:i:s');
            $claimed = $db->table('lp_character_image_pool')
                ->where('fields_hash', $hash)
                ->where('status', 'ready')
                ->order('seq', 'asc')
                ->limit(1)
                ->update(['status' => 'served', 'served_at' => $now, 'updated_at' => $now]);
            if ($claimed <= 0) {
                return null;
            }
            $row = $db->table('lp_character_image_pool')
                ->where('fields_hash', $hash)
                ->where('status', 'served')
                ->order('served_at', 'desc')
                ->find();
            $url = trim((string) ($row['image_url'] ?? ''));
            return $url !== '' ? $url : null;
        } catch (\Throwable $e) {
            Log::warning('[characterPool] claim failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 补池：为词条组合入队一张预生成图（pool_image，priority=0 不抢占用户单）
     * $binFields = 原始词条且 age 已替换为分箱代表年龄；返回 task_id 或 null
     */
    public function enqueuePoolTask(string $hash, array $binFields, string $category, int $seq): ?int
    {
        try {
            $db = Db::connect('live_mysql');
            $now = date('Y-m-d H:i:s');
            $task = new AiTask();
            $task->task_no       = StrHelper::orderNo('CP');
            $task->room_id       = 0;
            $task->task_type     = 'pool_image';
            $task->priority      = 0; // 最低位：只在 GPU 空闲时段消化，用户单（5/8）永远优先
            $task->source_type   = 'character_pool';
            $task->source_ref_id = 0;
            $task->persona_id    = 0;
            $task->content       = json_encode([
                'style'           => $category,
                'label'           => 'Pool',
                'prompt'          => $this->buildPrompt($binFields, $category),
                'negative_prompt' => $this->buildNegative($category, $binFields),
                'retry'           => 0,
            ], JSON_UNESCAPED_UNICODE);
            $task->callback_mode = 'none';
            $task->status        = TaskStatus::PENDING->value;
            $task->deadline_at   = date('Y-m-d H:i:s', time() + self::TASK_TTL_MIN * 60);
            $task->created_at    = $now;
            $task->updated_at    = $now;
            $task->save();

            $db->table('lp_character_image_pool')->insert([
                'category'    => $category,
                'fields_hash' => $hash,
                'fields_json' => json_encode($binFields, JSON_UNESCAPED_UNICODE),
                'age_bin'     => $this->ageBin($binFields['age'] ?? 0)['bin'],
                'seq'         => $seq,
                'status'      => 'pending',
                'task_id'     => (int) $task->id,
                'persona_id'  => 0,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            return (int) $task->id;
        } catch (\Throwable $e) {
            Log::error('[characterPool] enqueuePoolTask failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 字段集 → workflow 分类：旧向导送 type，新向导送 style（Realistic|Anime）。
     * 两个键都在时以 style 为准（新字段优先），旧请求只有 type 时行为完全不变。
     */
    private function categoryOf(array $fields): string
    {
        foreach (['style', 'type'] as $key) {
            $value = trim($this->scalar($fields[$key] ?? ''));
            if ($value !== '') {
                return $this->category($value);
            }
        }
        return 'realistic';
    }

    /** 1/2（旧编码）或 Realistic/Anime（新文案）→ workflow 分类 */
    private function category($type): string
    {
        $t = is_string($type) ? strtolower(trim($type)) : $this->scalar($type);
        if ($t === '2' || $t === 'anime') {
            return 'anime';
        }
        return 'realistic';
    }

    private function agePart($age): string
    {
        if ($age === null || is_array($age) || is_bool($age)) {
            return '';
        }
        $n = (int) $age;
        if ($n < 18 || $n > 80) {
            return '';
        }
        return $n . ' year old';
    }

    private function hairPart($style, $color): string
    {
        $s = $this->humanize('hairstyle', $style);
        $c = $this->humanize('hair', $color);
        if ($s === '' && $c === '') {
            return '';
        }
        return trim($c . ' ' . $s);
    }

    /**
     * 属性值 → prompt 片段：数字走旧编码表，英文文案走 WORDS 映射（缺省原样小写）
     * 入参来自 public API，array/bool 一律当空值（避免 (string) 转数组的 warning 打进日志/
     * 破坏统一失败响应）；旧口径标量值的输出逐字节不变。
     */
    private function humanize(string $key, $value): string
    {
        if ($value === null || $value === '' || is_array($value) || is_bool($value) || is_object($value)) {
            return '';
        }
        if (is_numeric($value) && isset(self::LOOKUPS[$key][(int) $value])) {
            return self::LOOKUPS[$key][(int) $value];
        }
        $word = trim((string) $value);
        if (isset(self::WORDS[$key][$word])) {
            return self::WORDS[$key][$word];
        }
        return mb_strtolower($word);
    }

    /** 标量安全转字符串：array/bool/null 一律空串（public API 入参不做盲转） */
    private function scalar($value): string
    {
        if ($value === null || is_array($value) || is_bool($value) || is_object($value)) {
            return '';
        }
        return (string) $value;
    }
}
