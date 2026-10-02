<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\BaseController;
use app\common\web\ResultCode;
use app\live\service\CreatorService;

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
        $params = $this->request->post();

        $personaName = trim((string) ($params['persona_name'] ?? ''));
        if ($personaName === '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请输入角色名称');
        }

        $description = trim((string) ($params['description'] ?? ''));
        if (mb_strlen($description, 'UTF-8') < 200) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '角色描述不能少于200字');
        }

        $tags = $this->normalizeList((string) ($params['tags'] ?? ''));
        if ($tags === '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请至少填写一个标签');
        }
        if (count(explode(',', $tags)) > 10) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '标签最多填写10个');
        }

        $style = trim((string) ($params['style'] ?? ''));
        if (!isset(self::STYLE_MAP[$style])) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请选择角色风格');
        }

        $gender = trim((string) ($params['gender'] ?? ''));
        if ($gender !== '' && !isset(self::GENDER_MAP[$gender])) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '角色性别不合法');
        }

        $ageRange = trim((string) ($params['age_range'] ?? ''));
        if ($ageRange !== '' && !isset(self::AGE_MAP[$ageRange])) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '年龄段不合法');
        }

        $language = trim((string) ($params['language'] ?? ''));
        if ($language !== '' && !isset(self::LANGUAGE_MAP[$language])) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '语种不合法');
        }

        $voiceStyle = trim((string) ($params['voice_style'] ?? ''));
        if ($voiceStyle !== '' && !isset(self::VOICE_MAP[$voiceStyle])) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '语音风格不合法');
        }

        // 发布类型：必填且单选
        $publishType = trim((string) ($params['publish_type'] ?? ''));
        if (!isset(self::PUBLISH_MAP[$publishType])) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请选择发布类型');
        }

        // 是否 18+ 内容：必填 0/1
        $isAdultRaw = $params['is_adult'] ?? '';
        if ($isAdultRaw === '' || $isAdultRaw === null) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请选择是否为18+内容');
        }
        $isAdult = in_array((string) $isAdultRaw, ['1', 'true', 'on'], true) ? 1 : 0;

        $referenceUrl = trim((string) ($params['reference_url'] ?? ''));
        if ($referenceUrl !== '' && !preg_match('/^https?:\/\//i', $referenceUrl)) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '参考链接需以 http(s):// 开头');
        }

        // 资料：前端已开放上传，至少一份
        $materials = $this->parseMaterials($params['materials'] ?? []);
        if (count($materials) === 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请至少上传一份角色资料');
        }

        $item = $this->service->submit($userId, [
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
        ]);

        return $this->jsonSuccess([
            'id'     => (int) $item->id,
            'status' => (int) $item->status,
        ], '已提交审核');
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
     * 收入排行榜（公开，无需登录）
     */
    public function leaderboard()
    {
        $limit = (int) $this->request->get('limit/d', 10);
        return $this->jsonSuccess([
            'list' => $this->service->leaderboard($limit),
        ]);
    }

    /**
     * 资料参数归一化：[{name,url}]
     */
    private function parseMaterials(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $url  = trim($item);
                $name = '';
            } elseif (is_array($item)) {
                $url  = trim((string) ($item['url'] ?? ''));
                $name = trim((string) ($item['name'] ?? ''));
            } else {
                continue;
            }
            if ($url === '') {
                continue;
            }
            $result[] = ['name' => $name, 'url' => $url];
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
