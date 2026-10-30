<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\BaseController;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\live\service\CreatorService;
use think\facade\Db;

final class Creator extends BaseController
{
    private const STYLE_MAP = [
        'realistic' => '写实',
        'anime'     => '二次元',
        '3d'        => '3D',
        'cyberpunk' => '赛博朋克',
        'chinese'   => '古风',
        'korean'    => '韩系',
        'western'   => '欧美',
    ];

    private const GENDER_MAP = [
        'female' => '女性',
        'male'   => '男性',
        'other'  => '其他',
    ];

    private const AGE_MAP = [
        '18-22' => '18-22岁',
        '23-27' => '23-27岁',
        '28-35' => '28-35岁',
        '36-45' => '36-45岁',
        '45+'   => '45岁以上',
    ];

    private const LANGUAGE_MAP = [
        'zh-CN' => '中文',
        'en-US' => '英文',
        'ja-JP' => '日文',
        'ms-MY' => '马来语',
        'multi' => '多语言',
    ];

    private const VOICE_MAP = [
        'sweet'    => '甜美',
        'mature'   => '御姐',
        'magnetic' => '磁性',
        'loli'     => '萝莉',
        'cold'     => '冷艳',
        'gentle'   => '温柔',
        'none'     => '不涉及语音',
    ];

    // 发布类型：单选
    private const PUBLISH_MAP = [
        'live'  => '直播角色',
        'chat'  => '普通对话角色',
        'drama' => '短剧',
    ];

    private CreatorService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new CreatorService();
    }

    /**
     * 提交独立完成的角色（需登录，提交后由后台审核）
     */
    public function independent()
    {
        $userId = $this->getAuthUserId();
        $built  = $this->buildPayload($this->request->post());
        if ($built['error'] !== '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, $built['error']);
        }

        try {
            $item = $this->service->submit($userId, $built['payload']);
        } catch (BusinessException $e) {
            return $this->jsonFail($e->resultCode, $e->getMessage());
        }

        return $this->jsonSuccess([
            'id'     => (int) $item->id,
            'status' => (int) $item->status,
        ], '已提交审核');
    }

    /**
     * 我的创作详情（编辑回填，需登录且仅限本人）
     */
    public function detail()
    {
        $userId = $this->getAuthUserId();
        $id     = (int) $this->request->get('id/d', 0);
        if ($id <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '缺少作品ID');
        }

        try {
            return $this->jsonSuccess($this->service->detail($userId, $id));
        } catch (BusinessException $e) {
            return $this->jsonFail($e->resultCode, $e->getMessage());
        }
    }

    /**
     * 修改自己的创作（仅待审核 / 已驳回可改，改后重新进入待审核）
     */
    public function update()
    {
        $userId = $this->getAuthUserId();
        $params = $this->request->post();
        $id     = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '缺少作品ID');
        }

        $built = $this->buildPayload($params);
        if ($built['error'] !== '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, $built['error']);
        }

        try {
            $item = $this->service->update($userId, $id, $built['payload']);
        } catch (BusinessException $e) {
            return $this->jsonFail($e->resultCode, $e->getMessage());
        }

        return $this->jsonSuccess([
            'id'     => (int) $item->id,
            'status' => (int) $item->status,
        ], '已重新提交审核');
    }

    /**
     * 删除自己的创作（需登录且仅限本人）
     */
    public function remove()
    {
        $userId = $this->getAuthUserId();
        $id     = (int) $this->request->post('id/d', 0);
        if ($id <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '缺少作品ID');
        }

        try {
            $this->service->remove($userId, $id);
        } catch (BusinessException $e) {
            return $this->jsonFail($e->resultCode, $e->getMessage());
        }

        return $this->jsonSuccess(null, '已删除');
    }

    /**
     * 校验 + 归一化提交参数（independent / update 共用）
     * 返回 ['payload' => array, 'error' => string]
     */
    private function buildPayload(array $params): array
    {
        $fail = static fn(string $msg): array => ['payload' => [], 'error' => $msg];

        $personaName = trim((string) ($params['persona_name'] ?? ''));
        if ($personaName === '') {
            return $fail('请输入角色名称');
        }

        $description = trim((string) ($params['description'] ?? ''));
        if (mb_strlen($description, 'UTF-8') < 200) {
            return $fail('角色描述不能少于200字');
        }

        $tags = $this->normalizeList((string) ($params['tags'] ?? ''));
        if ($tags === '') {
            return $fail('请至少填写一个标签');
        }
        if (count(explode(',', $tags)) > 10) {
            return $fail('标签最多填写10个');
        }

        $style = trim((string) ($params['style'] ?? ''));
        if (!isset(self::STYLE_MAP[$style])) {
            return $fail('请选择角色风格');
        }

        $gender = trim((string) ($params['gender'] ?? ''));
        if ($gender !== '' && !isset(self::GENDER_MAP[$gender])) {
            return $fail('角色性别不合法');
        }

        $ageRange = trim((string) ($params['age_range'] ?? ''));
        if ($ageRange !== '' && !isset(self::AGE_MAP[$ageRange])) {
            return $fail('年龄段不合法');
        }

        $language = trim((string) ($params['language'] ?? ''));
        if ($language !== '' && !isset(self::LANGUAGE_MAP[$language])) {
            return $fail('语种不合法');
        }

        $voiceStyle = trim((string) ($params['voice_style'] ?? ''));
        if ($voiceStyle !== '' && !isset(self::VOICE_MAP[$voiceStyle])) {
            return $fail('语音风格不合法');
        }

        // 发布类型：必填且单选
        $publishType = trim((string) ($params['publish_type'] ?? ''));
        if (!isset(self::PUBLISH_MAP[$publishType])) {
            return $fail('请选择发布类型');
        }

        // 是否 18+ 内容：必填 0/1
        $isAdultRaw = $params['is_adult'] ?? '';
        if ($isAdultRaw === '' || $isAdultRaw === null) {
            return $fail('请选择是否为18+内容');
        }
        $isAdult = in_array((string) $isAdultRaw, ['1', 'true', 'on'], true) ? 1 : 0;

        $referenceUrl = trim((string) ($params['reference_url'] ?? ''));
        if ($referenceUrl !== '' && !preg_match('/^https?:\/\//i', $referenceUrl)) {
            return $fail('参考链接需以 http(s):// 开头');
        }

        // 资料：前端已开放上传，至少一份（短剧以剧集为准，资料可选）
        $parsedMaterials = $this->parseMaterials($params['materials'] ?? [], $publishType);
        if ($parsedMaterials['error'] !== '') {
            return $fail($parsedMaterials['error']);
        }
        $materials = $parsedMaterials['items'];
        if (count($materials) === 0 && $publishType !== 'drama') {
            return $fail('请至少上传一份角色资料');
        }
        // 直播角色：资源按用途分类，至少一个主视频（可多个）
        if ($publishType === 'live') {
            $hasMainVideo = false;
            foreach ($materials as $mat) {
                if (($mat['type'] ?? '') === 'main_video') {
                    $hasMainVideo = true;
                    break;
                }
            }
            if (!$hasMainVideo) {
                return $fail('直播角色需要上传主视频');
            }
        }

        // 短剧：多集结构（与后台 lp_short_episode 一致），非短剧则忽略
        $episodes     = $this->parseEpisodes($params['episodes'] ?? []);
        $freeEpisodes = max(0, (int) ($params['free_episodes'] ?? 0));
        if ($publishType === 'drama') {
            if (count($episodes) === 0) {
                return $fail('短剧至少需要上传 1 集');
            }
            foreach ($episodes as $i => $ep) {
                if ($ep['video_url'] === '') {
                    return $fail('第 ' . ($i + 1) . ' 集请上传视频');
                }
            }
            if ($freeEpisodes > count($episodes)) {
                $freeEpisodes = count($episodes);
            }
        } else {
            $episodes     = [];
            $freeEpisodes = 0;
        }

        return [
            'error'   => '',
            'payload' => [
                'persona_name'  => $personaName,
                'description'   => $description,
                'tags'          => $tags,
                'style'         => $style,
                'gender'        => $gender,
                'age_range'     => $ageRange,
                'language'      => $language,
                'personality'   => $this->normalizeList((string) ($params['personality'] ?? '')),
                'voice_style'   => $voiceStyle,
                'publish_type'  => $publishType,
                'is_adult'      => $isAdult,
                'highlights'    => trim((string) ($params['highlights'] ?? '')),
                'reference_url' => $referenceUrl,
                'cover_url'     => trim((string) ($params['cover_url'] ?? '')),
                'materials'     => $materials,
                'episodes'      => $episodes,
                'free_episodes' => $freeEpisodes,
            ],
        ];
    }

    /**
     * 我的创作列表（需登录）
     */
    public function myCreations()
    {
        $userId = $this->getAuthUserId();
        return $this->jsonSuccess($this->service->myCreations($userId));
    }

    /**
     * 收入排行榜：按平台用户累计到手收益降序（公开，无需登录）
     */
    public function leaderboard()
    {
        $limit = (int) $this->request->get('limit/d', 10);
        return $this->jsonSuccess([
            'list' => $this->service->leaderboard($limit),
        ]);
    }

    /**
     * 可选礼物列表（公开发布表单用：直播角色的「礼物视频」需绑定具体礼物）
     */
    public function gifts()
    {
        $rows = Db::connect('live_mysql')
            ->table('lp_gift')
            ->where('status', 1)
            ->order('price_diamond', 'asc')
            ->order('id', 'asc')
            ->field(['id', 'name', 'price_diamond'])
            ->limit(200)
            ->select()
            ->toArray();

        return $this->jsonSuccess([
            'list' => array_map(static fn(array $row): array => [
                'id'    => (int) $row['id'],
                'name'  => (string) $row['name'],
                'price' => (float) $row['price_diamond'],
            ], $rows),
        ]);
    }

    /**
     * 资料参数归一化：[{name,url,type,gift_id,gift_name}]
     * type 仅对直播角色生效：main_video=主视频（可多个）
     *   gift_image=礼物人设图（每个礼物 1 张）/ gift_video=礼物中间视频（可多个）/ gift_end_video=礼物收尾视频（每个礼物 1 个）
     *   other=其它资料
     * 返回 ['items' => array, 'error' => string]
     */
    private function parseMaterials(mixed $raw, string $publishType): array
    {
        $fail = static fn(string $msg): array => ['items' => [], 'error' => $msg];

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return ['items' => [], 'error' => ''];
        }

        $isLive       = $publishType === 'live';
        $allowedType  = ['main_video', 'gift_video', 'gift_image', 'gift_end_video', 'other'];
        $giftTypes    = ['gift_video', 'gift_image', 'gift_end_video'];
        $items        = [];
        $giftIds      = [];

        foreach ($raw as $item) {
            if (is_string($item)) {
                $url  = trim($item);
                $name = '';
                $type = 'other';
                $giftId = 0;
                $giftName = '';
            } elseif (is_array($item)) {
                $url  = trim((string) ($item['url'] ?? ''));
                $name = trim((string) ($item['name'] ?? ''));
                $type = $isLive ? trim((string) ($item['type'] ?? '')) : 'other';
                if (!in_array($type, $allowedType, true)) {
                    $type = 'other';
                }
                $isGiftAsset = in_array($type, $giftTypes, true);
                $giftId   = $isGiftAsset ? (int) ($item['gift_id'] ?? 0) : 0;
                $giftName = $isGiftAsset ? trim((string) ($item['gift_name'] ?? '')) : '';
            } else {
                continue;
            }
            if ($url === '') {
                continue;
            }

            if ($type === 'main_video') {
                $items[] = ['type' => 'main_video', 'name' => $name, 'url' => $url];
                continue;
            }

            if (in_array($type, $giftTypes, true)) {
                if ($giftId <= 0) {
                    return $fail('礼物素材需要选择对应的礼物');
                }
                $giftIds[$giftId] = true;
                $items[] = [
                    'type'      => $type,
                    'gift_id'   => $giftId,
                    'gift_name' => $giftName,
                    'name'      => $name,
                    'url'       => $url,
                ];
                continue;
            }

            $items[] = ['type' => 'other', 'name' => $name, 'url' => $url];
        }

        // 礼物名称以库里的配置为准，避免前端传错/礼物改名后文案不一致
        if ($giftIds) {
            $names = Db::connect('live_mysql')
                ->table('lp_gift')
                ->whereIn('id', array_keys($giftIds))
                ->column('name', 'id');
            foreach ($items as &$row) {
                if (!in_array(($row['type'] ?? ''), $giftTypes, true)) {
                    continue;
                }
                $gid = (int) $row['gift_id'];
                if (!isset($names[$gid])) {
                    return $fail('所选礼物不存在，请刷新页面后重试');
                }
                $row['gift_name'] = (string) $names[$gid];
            }
            unset($row);
        }

        return ['items' => $items, 'error' => ''];
    }

    /**
     * 短剧剧集归一化：[{episode_no,title,poster,video_url,duration,price}]
     * 集号由服务端按顺序重排，避免前端乱序；标题留空时按「第N集」兜底
     */
    private function parseEpisodes(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        $no     = 1;
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = trim((string) ($item['title'] ?? ''));
            $video = trim((string) ($item['video_url'] ?? ''));
            if ($title === '' && $video === '') {
                continue; // 空行跳过
            }
            $result[] = [
                'episode_no' => $no,
                'title'      => $title !== '' ? $title : ('第' . $no . '集'),
                'poster'     => trim((string) ($item['poster'] ?? '')),
                'video_url'  => $video,
                'duration'   => trim((string) ($item['duration'] ?? '')),
                'price'      => max(0, (int) ($item['price'] ?? 0)),
            ];
            $no++;
        }
        return $result;
    }

    /**
     * 逗号分隔字符串 -> 去重、去空
     */
    private function normalizeList(string $value): string
    {
        $value = trim(str_replace('，', ',', $value));
        if ($value === '') {
            return '';
        }
        $parts = array_filter(array_map('trim', explode(',', $value)), static fn($s) => $s !== '');
        return implode(',', array_unique($parts));
    }
}
