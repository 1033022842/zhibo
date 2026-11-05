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
        $parts = [];
        $parts[] = $this->agePart($fields['age'] ?? null);
        $parts[] = $this->humanize('race', $fields['race'] ?? null);
        $hair = $this->hairPart($fields['hairstyle'] ?? null, $fields['hair'] ?? null);
        if ($hair !== '') {
            $parts[] = $hair;
        }
        $parts[] = $this->humanize('eye', $fields['eye'] ?? null);
        $parts[] = $this->humanize('body', $fields['body'] ?? null);
        $parts[] = $this->humanize('breast', $fields['breast'] ?? null);

        // 新版 Outfit 固定 Bikini（candy 同款）；旧向导送 clothing 编码时不猜，忽略
        $clothing = trim((string) ($fields['clothing'] ?? ''));
        $clothingPart = (strcasecmp($clothing, 'Bikini') === 0) ? 'wearing a bikini' : '';

        $traits = implode(', ', array_filter(array_map('trim', $parts)));
        if ($category === 'anime') {
            $prompt = '1girl, solo, ' . $traits
                . ($clothingPart !== '' ? ', ' . $clothingPart : '')
                . ', looking at viewer, masterpiece, best quality, highly detailed, detailed face, anime artwork';
        } else {
            // 发色短语前置并加 dyed 强调：asian + 粉/蓝等非自然色的模型先验很弱，
            // 不强调会被「黑发」先验吞掉（生产实测 pink 生成出来是黑发）
            $hairColor = strtolower(trim((string) ($fields['hair'] ?? '')));
            $prompt = 'Photorealistic portrait photo of a beautiful ' . $traits . ' woman'
                . ($clothingPart !== '' ? ', ' . $clothingPart : '')
                . '. ' . ($hairColor !== '' && $hairColor !== 'black' ? 'Her hair is dyed exactly ' . $hairColor . ', vivid and clearly visible. ' : '')
                . 'Soft studio lighting, detailed skin texture, professional photography, 85mm lens, high quality, 4k';
        }
        return $prompt;
    }

    /**
     * 负面词反先验：用户选了非黑发/非棕眼时，把默认发色/瞳色加进负面，
     * 压掉模型的「asian=黑发」「深色眼睛」先验（配合正向 dyed 强调）
     */
    public function buildNegative(string $category, array $fields): string
    {
        $neg = $category === 'anime' ? self::NEGATIVE_ANIME : self::NEGATIVE_REALISTIC;
        $hair = strtolower(trim((string) ($fields['hair'] ?? '')));
        $eye = strtolower(trim((string) ($fields['eye'] ?? '')));
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
        $category = $this->category($fields['type'] ?? '');

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

    /** 池缓存 key：与 buildPrompt 同字段口径，词条经 humanize 归一（旧编码/新文案等价），age 用分箱 */
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

    /** 新向导入口用：type 归一 + hash 一步到位 */
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

    /** 1/2（旧编码）或 Realistic/Anime（新文案）→ workflow 分类 */
    private function category($type): string
    {
        $t = is_string($type) ? strtolower(trim($type)) : (string) $type;
        if ($t === '2' || $t === 'anime') {
            return 'anime';
        }
        return 'realistic';
    }

    private function agePart($age): string
    {
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
     */
    private function humanize(string $key, $value): string
    {
        if ($value === null || $value === '') {
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
}
