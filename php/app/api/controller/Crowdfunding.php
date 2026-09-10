<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use think\facade\Db;
use app\BaseController;
use app\common\web\ResultCode;
use app\live\service\CrowdfundingService;
use app\api\controller\Live as LiveController;

final class Crowdfunding extends BaseController
{
    protected array $middleware = [
        \app\live\middleware\Auth::class => ['only' => [
            'initiate', 'pledge', 'myProjects', 'myPledges', 'linkPersona', 'checkActive', 'balance'
        ]],
    ];

    private CrowdfundingService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new CrowdfundingService();
    }

    /**
     * 发起众筹（需商家认证）
     */
    public function initiate()
    {
        $userId = $this->getAuthUserId();
        $this->checkMerchantCertified($userId);

        $params = $this->request->post();

        // 基础校验
        if (empty($params['title'])) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请输入项目标题');
        }
        if (empty($params['persona_name'])) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请输入角色名称');
        }

        // 角色描述不少于 200 字
        $description = trim((string) ($params['description'] ?? ''));
        if (mb_strlen($description, 'UTF-8') < 200) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '角色描述不能少于200字');
        }

        if (empty($params['target_amount']) || (float)$params['target_amount'] <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '目标金额必须大于0');
        }
        if (empty($params['deadline'])) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请选择截止时间');
        }
        if (strtotime($params['deadline']) <= time()) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '截止时间必须在当前时间之后');
        }

        // 详细资料校验
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

        $deliverables = $this->normalizeList($this->listParam($params['deliverables'] ?? ''));
        if ($deliverables === '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请至少选择一项交付内容');
        }
        foreach (explode(',', $deliverables) as $item) {
            if (!isset(self::DELIVERABLE_MAP[$item])) {
                return $this->jsonFail(ResultCode::PARAM_ERROR, '交付内容不合法');
            }
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

        $params['description']  = $description;
        $params['tags']         = $tags;
        $params['personality']  = $this->normalizeList((string) ($params['personality'] ?? ''));
        $params['deliverables'] = $deliverables;
        $params['is_adult']     = $isAdult;
        $params['highlights']   = trim((string) ($params['highlights'] ?? ''));
        $params['reference_url'] = $referenceUrl;

        $project = $this->service->initiate($userId, $params);
        return $this->jsonSuccess($project->toArray(), '众筹项目已发起');
    }

    /**
     * 支持众筹
     */
    public function pledge()
    {
        $userId = $this->getAuthUserId();
        $projectId = $this->request->post('project_id/d', 0);
        $amount = $this->request->post('amount/f', 0);

        if ($projectId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '项目ID无效');
        }
        if ($amount <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '支持金额必须大于0');
        }

        $this->service->pledge($userId, $projectId, $amount);
        return $this->jsonSuccess(null, '支持成功');
    }

    /**
     * 众筹列表（进行中+已完成，无需登录）
     */
    public function list()
    {
        $page = $this->request->param('page/d', 1);
        $pageSize = $this->request->param('page_size/d', 15);

        $result = $this->service->listAll($page, $pageSize);
        return $this->jsonSuccess($result);
    }

    /**
     * 众筹详情（无需登录）
     */
    public function detail()
    {
        $id = $this->request->param('id/d', 0);
        if ($id <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '项目ID无效');
        }

        $detail = $this->service->detail($id);
        if (!$detail) {
            return $this->jsonFail(ResultCode::CROWDFUNDING_NOT_FOUND);
        }

        return $this->jsonSuccess($detail);
    }

    /**
     * 我的发起（需登录+商家认证）
     */
    public function myProjects()
    {
        $userId = $this->getAuthUserId();
        $list = $this->service->myProjects($userId);
        return $this->jsonSuccess($list);
    }

    /**
     * 我的支持（需登录）
     */
    public function myPledges()
    {
        $userId = $this->getAuthUserId();
        $list = $this->service->myPledges($userId);
        return $this->jsonSuccess($list);
    }

    /**
     * 关联角色（需登录+商家认证）
     */
    public function linkPersona()
    {
        $userId = $this->getAuthUserId();
        $this->checkMerchantCertified($userId);

        $projectId = $this->request->post('project_id/d', 0);
        $personaId = $this->request->post('persona_id/d', 0);

        if ($projectId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '项目ID无效');
        }
        if ($personaId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '角色ID无效');
        }

        $this->service->linkPersona($userId, $projectId, $personaId);
        return $this->jsonSuccess(null, '关联成功');
    }

    /**
     * 检查当前用户是否有进行中的众筹
     */
    public function checkActive()
    {
        $userId = $this->getAuthUserId();
        $hasActive = $this->service->hasActiveProject($userId);
        return $this->jsonSuccess(['has_active' => $hasActive]);
    }


    /**
     * 查询当前用户钻石余额
     */
    public function balance()
    {
        $userId = $this->getAuthUserId();
        $wallet = Db::connect('live_mysql')
            ->table('lp_wallet_account')
            ->where('user_id', $userId)
            ->find();

        $balance = $wallet ? (float)$wallet['diamond_balance'] : 0.00;

        return $this->jsonSuccess([
            'balance' => $balance,
            'status'  => $wallet ? (int)$wallet['status'] : 1,
        ]);
    }

    // ==================== 可选值白名单 ====================

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
        'sweet'   => '甜美',
        'mature'  => '御姐',
        'magnetic' => '磁性',
        'loli'    => '萝莉',
        'cold'    => '冷艳',
        'gentle'  => '温柔',
        'none'    => '不涉及语音',
    ];

    private const DELIVERABLE_MAP = [
        'portrait' => '立绘',
        'voice'    => '语音',
        'video'    => '短视频',
        'live'     => '直播',
        'chat'     => 'AI聊天',
    ];

    /**
     * 数组或逗号分隔字符串 -> 去重后的逗号分隔字符串
     */
    private function listParam(mixed $value): string
    {
        if (is_array($value)) {
            return implode(',', array_map('strval', $value));
        }
        return (string) $value;
    }

    /**
     * 逗号分隔字符串 -> 去重、去空、trim 后的逗号分隔字符串
     */
    private function normalizeList(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $parts = array_filter(array_map('trim', explode(',', $value)), static fn($s) => $s !== '');
        return implode(',', array_unique($parts));
    }

    /**
     * 商家认证校验
     */
    private function checkMerchantCertified(int $userId): void
    {
        $cert = \think\facade\Db::connect('live_mysql')
            ->table('lp_merchant_certification')
            ->where('user_id', $userId)
            ->find();

        if (!$cert || (int)$cert['status'] !== 1) {
            throw new \app\common\exception\BusinessException(
                \app\common\web\ResultCode::CERTIFICATION_NOT_FOUND,
                '请先通过商家认证后再发起众筹'
            );
        }
    }
}
