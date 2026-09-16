<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\BaseController;
use app\common\exception\BusinessException;
use app\common\service\LlmService;
use app\common\web\ResultCode;
use app\live\service\AffectionService;
use app\live\service\UserService;
use app\live\service\PersonaService;
use app\live\service\WalletService;
use app\live\validate\UserValidate;

final class Live extends BaseController
{
    protected array $middleware = [
        \app\live\middleware\Auth::class => ['only' => ['logout', 'profile', 'userInfo', 'updateProfile', 'customRoleOne', 'customOneList', 'upload', 'uploadMediaAsset', 'mediaAssetList', 'mediaAssetEdit', 'mediaAssetDelete', 'replayClips', 'affection', 'buyAffection', 'unlockVideo', 'unlockChatMedia']],
    ];

    private UserService $userService;
    private PersonaService $personaService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->userService = new UserService();
        $this->personaService = new PersonaService();
    }

    public function register()
    {
        $params = $this->request->post();
        $this->validate($params, UserValidate::class . '.register');
        $ip = $this->request->ip();
        $this->userService->register($params['email'], $params['code'], $params['password'], $params['nickname'], $ip);
        return $this->jsonSuccess(null, '注册成功');
    }

    /**
     * AI 前端注册（无需邮箱验证码）
     */
    public function registerFromAi()
    {
        $params = $this->request->post();
        $this->validate($params, UserValidate::class . '.registerAi');
        $ip = $this->request->ip();
        $user = $this->userService->registerFromAi($params['username'], $params['email'], $params['password'], $ip);
        // 注册成功后自动登录
        $result = $this->userService->login($params['email'], $params['password'], $ip);
        return $this->jsonSuccess($result);
    }

    public function login()
    {
        $params = $this->request->post();
        // 兼容 AI 前端的 account 字段名
        if (!empty($params['account']) && empty($params['username'])) {
            $params['username'] = $params['account'];
        }
        // 根据账号格式选择验证场景
        $isEmail = str_contains($params['username'] ?? '', '@');
        if ($isEmail) {
            $params['email'] = $params['username'];
            $this->validate($params, UserValidate::class . '.emailLogin');
        } else {
            $this->validate($params, UserValidate::class . '.usernameLogin');
        }
        $ip = $this->request->ip();
        $deviceId = $params['device_id'] ?? '';
        $result = $this->userService->login($params['username'], $params['password'], $ip, $deviceId);
        return $this->jsonSuccess($result);
    }

    public function refreshToken()
    {
        $refreshToken = $this->request->post('refresh_token', '');
        if (empty($refreshToken)) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'refresh_token不能为空');
        }
        $result = $this->userService->refreshToken($refreshToken);
        return $this->jsonSuccess($result);
    }

    public function logout()
    {
        $accessToken = str_replace('Bearer ', '', $this->request->header('Authorization', ''));
        $refreshToken = $this->request->post('refresh_token', '');
        $userId = $this->getAuthUserId();
        $this->userService->logout($userId, $accessToken, $refreshToken);
        return $this->jsonSuccess(null, '已退出');
    }

    public function profile()
    {
        $userId = $this->getAuthUserId();
        $result = $this->userService->profile($userId);
        return $this->jsonSuccess($result);
    }

    /**
     * AI 前端：获取用户信息（兼容 AI 前端字段名）
     */
    public function userInfo()
    {
        $userId = $this->getAuthUserId();
        $profile = $this->userService->profile($userId);

        // 获取 email 和 username（从 auth 表）
        $auths = \app\live\model\UserAuth::where('user_id', $userId)->select()->toArray();
        $email = '';
        foreach ($auths as $auth) {
            if ($auth['auth_type'] === 'email') {
                $email = $auth['auth_key'];
                break;
            }
        }

        // 获取钻石余额（直播端和AI女友端共用 lp_wallet_account）
        $wallet = \think\facade\Db::connect('live_mysql')
            ->table('lp_wallet_account')
            ->where('user_id', $userId)
            ->find();
        $diamondBalance = $wallet ? (float)$wallet['diamond_balance'] : 0.00;

        return $this->jsonSuccess([
            'id'       => $profile['id'],
            'username' => $profile['nickname'],
            'email'    => $email,
            'avatar'   => $profile['avatar'],
            'token'    => $this->request->header('Authorization'),
            'money'    => $diamondBalance,
        ]);
    }

    public function updateProfile()
    {
        $userId = $this->getAuthUserId();
        $params = $this->request->only(['nickname', 'gender', 'bio']);
        $this->validate($params, UserValidate::class . '.updateProfile');
        $this->userService->updateProfile($userId, $params);
        return $this->jsonSuccess(null, '更新成功');
    }

    /**
     * AI 前端：创建角色（需商家认证通过）
     */
    public function customRoleOne()
    {
        $userId = $this->getAuthUserId();
        $this->checkMerchantCertified($userId);
        $data = $this->request->post();
        $persona = $this->personaService->createFromAi($userId, $data);
        return $this->jsonSuccess($persona);
    }

    /**
     * AI 前端：创建聊天伴侣角色（免商家认证、不开直播间，角色属于当前用户）
     */
    public function customRoleCreate()
    {
        $userId = $this->getAuthUserId();
        $data = $this->request->post();
        $persona = $this->personaService->createFromAi($userId, $data, false);
        return $this->jsonSuccess([
            'id'     => (int) $persona['id'],
            'name'   => $persona['name'],
            'status' => (int) $persona['status'],
        ]);
    }

    /**
     * AI 前端：我的角色列表（含关联直播间信息）
     */
    public function customOneList()
    {
        $userId = $this->getAuthUserId();
        $list = $this->personaService->listByUser($userId);
        return $this->jsonSuccess($list);
    }

    /**
     * AI 前端：文件上传（角色封面图）
     */
    public function upload()
    {
        $file = $this->request->file('file');
        if (!$file) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请选择要上传的文件');
        }

        $upload = new \app\common\library\Upload($file);
        $upload->setTopic('ai');
        $userId = $this->getAuthUserId();
        $attachment = $upload->upload(null, 0, $userId);
        $url = $attachment['url'];
        // 拼接完整URL，方便跨端口前端直接加载图片
        if (!str_starts_with($url, 'http')) {
            $url = rtrim($this->request->domain(), '/') . '/' . ltrim($url, '/');
        }
        return $this->jsonSuccess([
            'fullurl' => $url,
            'url'     => $url,
        ]);
    }

    /**
     * AI 女友端：上传直播素材（写入 lp_media_asset 素材池）
     */
    public function uploadMediaAsset()
    {
        $userId = $this->getAuthUserId();
        $file = $this->request->file('file');
        if (!$file) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '请选择要上传的素材文件');
        }

        $upload = new \app\common\library\Upload($file);
        $upload->setTopic('media');
        $attachment = $upload->upload(null, 0, $userId);
        $fileUrl = trim((string) ($attachment['url'] ?? ''));
        if ($fileUrl === '') {
            return $this->jsonFail(ResultCode::FAIL, '文件上传失败');
        }

        // 标题为空时从文件名推导
        $title = trim((string) $this->request->post('title', ''));
        if ($title === '') {
            $originName = (string) ($attachment['name'] ?? '素材');
            $title = pathinfo($originName, PATHINFO_FILENAME) ?: '未命名素材';
        }

        $assetType = trim((string) $this->request->post('asset_type', 'video'));
        if (!in_array($assetType, ['video', 'image', 'audio', 'subtitle'], true)) {
            $assetType = 'video';
        }

        $sceneType = trim((string) $this->request->post('scene_type', 'public'));
        if (!in_array($sceneType, ['public', 'privilege', 'interaction', 'cover', 'gift_effect'], true)) {
            $sceneType = 'public';
        }

        $persona = $this->resolveMediaPersona($userId);
        $keywords = $this->normalizeMediaKeywords((string) $this->request->post('keywords', ''));
        $weight = max(1, (int) $this->request->post('weight/d', 1));
        $durationMs = max(0, (int) $this->request->post('duration_ms/d', 0));
        $checksum = (string) ($attachment['sha1'] ?? '');
        $assetCode = 'u' . $userId . '_' . substr(md5($fileUrl . '|' . $checksum), 0, 10);

        // 详细素材信息
        $detail = $this->normalizeMediaDetail($this->request->post(), []);
        if ($detail['error'] !== '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, $detail['error']);
        }

        try {
            $query = \think\facade\Db::connect('live_mysql')->table('lp_media_asset');
            $query->insert([
                'asset_code'  => $assetCode,
                'asset_type'  => $assetType,
                'asset_role'  => '',
                'scene_type'  => $sceneType,
                'keywords'    => $keywords,
                'persona'     => $persona,
                'weight'      => $weight,
                'title'       => $title,
                'file_url'    => $fileUrl,
                'duration_ms' => $durationMs,
                'checksum'    => $checksum,
                'status'      => 1,
                'source'      => 'admin',
                'machine_id'  => '',
                'remote_path' => '',
                'created_at'  => date('Y-m-d H:i:s'),
                'description' => $detail['description'],
                'cover_url'   => $this->normalizeMediaUrl((string) $this->request->post('cover_url', '')),
                'tags'        => $detail['tags'],
                'style'       => $detail['style'],
                'mood'        => $detail['mood'],
                'resolution'  => $detail['resolution'],
                'is_adult'    => $detail['is_adult'],
            ]);
            $id = (int) $query->getLastInsID();
        } catch (\Throwable $e) {
            return $this->jsonFail(ResultCode::SERVER_ERROR, '素材保存失败：' . $e->getMessage());
        }

        return $this->jsonSuccess([
            'id'          => $id,
            'title'       => $title,
            'file_url'    => $fileUrl,
            'asset_type'  => $assetType,
            'scene_type'  => $sceneType,
            'persona'     => $persona,
            'keywords'    => $keywords,
            'description' => $detail['description'],
            'tags'        => $detail['tags'],
            'style'       => $detail['style'],
            'mood'        => $detail['mood'],
            'resolution'  => $detail['resolution'],
            'is_adult'    => $detail['is_adult'],
        ], '素材上传成功');
    }

    /**
     * 素材详细字段白名单
     */
    private const MEDIA_STYLE_MAP = [
        'realistic' => '写实',
        'anime'     => '二次元',
        '3d'        => '3D',
        'cyberpunk' => '赛博朋克',
        'chinese'   => '古风',
        'korean'    => '韩系',
        'western'   => '欧美',
    ];

    private const MEDIA_MOOD_MAP = [
        'happy'   => '开心',
        'cute'    => '撒娇',
        'shy'     => '害羞',
        'cold'    => '高冷',
        'sexy'    => '性感',
        'healing' => '治愈',
        'funny'   => '搞笑',
        'serious' => '认真',
    ];

    private const MEDIA_RESOLUTION_MAP = [
        '720P'  => '720P',
        '1080P' => '1080P',
        '2K'    => '2K',
        '4K'    => '4K',
    ];

    /**
     * 整理并校验素材详细字段
     *
     * @param array $post     当前提交数据
     * @param array $existing 已有记录（编辑时用于回退）
     * @return array{description:string,tags:string,style:string,mood:string,resolution:string,is_adult:int,error:string}
     */
    private function normalizeMediaDetail(array $post, array $existing): array
    {
        $has = static fn(string $key): bool => array_key_exists($key, $post);

        // 描述：必填，不少于 20 字
        $description = trim((string) ($post['description'] ?? ($existing['description'] ?? '')));
        if ($description === '') {
            return ['description' => '', 'tags' => '', 'style' => '', 'mood' => '', 'resolution' => '', 'is_adult' => 0, 'error' => '请输入素材描述'];
        }
        if (mb_strlen($description, 'UTF-8') < 20) {
            return ['description' => '', 'tags' => '', 'style' => '', 'mood' => '', 'resolution' => '', 'is_adult' => 0, 'error' => '素材描述不能少于20字'];
        }

        // 标签：必填，最多 10 个
        $tags = $this->normalizeMediaKeywords((string) ($post['tags'] ?? ($existing['tags'] ?? '')));
        if ($tags === '') {
            return ['description' => '', 'tags' => '', 'style' => '', 'mood' => '', 'resolution' => '', 'is_adult' => 0, 'error' => '请至少填写一个标签'];
        }
        if (count(explode(',', $tags)) > 10) {
            return ['description' => '', 'tags' => '', 'style' => '', 'mood' => '', 'resolution' => '', 'is_adult' => 0, 'error' => '标签最多填写10个'];
        }

        // 风格：必填
        $style = trim((string) ($post['style'] ?? ($existing['style'] ?? '')));
        if ($has('style') || !isset($existing['style'])) {
            if (!isset(self::MEDIA_STYLE_MAP[$style])) {
                return ['description' => '', 'tags' => '', 'style' => '', 'mood' => '', 'resolution' => '', 'is_adult' => 0, 'error' => '请选择素材风格'];
            }
        }

        // 情绪 / 清晰度：可选，但选值必须在白名单内
        $mood = trim((string) ($post['mood'] ?? ($existing['mood'] ?? '')));
        if ($mood !== '' && !isset(self::MEDIA_MOOD_MAP[$mood])) {
            return ['description' => '', 'tags' => '', 'style' => '', 'mood' => '', 'resolution' => '', 'is_adult' => 0, 'error' => '情绪氛围不合法'];
        }

        $resolution = trim((string) ($post['resolution'] ?? ($existing['resolution'] ?? '')));
        if ($resolution !== '' && !isset(self::MEDIA_RESOLUTION_MAP[$resolution])) {
            return ['description' => '', 'tags' => '', 'style' => '', 'mood' => '', 'resolution' => '', 'is_adult' => 0, 'error' => '清晰度不合法'];
        }

        // 是否 18+：必填
        $isAdultRaw = $post['is_adult'] ?? ($existing['is_adult'] ?? '');
        if ($isAdultRaw === '' || $isAdultRaw === null) {
            return ['description' => '', 'tags' => '', 'style' => '', 'mood' => '', 'resolution' => '', 'is_adult' => 0, 'error' => '请选择是否为18+内容'];
        }
        $isAdult = in_array((string) $isAdultRaw, ['1', 'true', 'on'], true) ? 1 : 0;

        return [
            'description' => $description,
            'tags'        => $tags,
            'style'       => $style,
            'mood'        => $mood,
            'resolution'  => $resolution,
            'is_adult'    => $isAdult,
            'error'       => '',
        ];
    }

    /**
     * 素材地址：相对路径补全为完整 URL
     */
    private function normalizeMediaUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }
        return rtrim($this->request->domain(), '/') . '/' . ltrim($url, '/');
    }

    /**
     * AI 女友端：当前用户上传的直播素材列表
     */
    public function mediaAssetList()
    {
        $userId = $this->getAuthUserId();
        $prefix = 'u' . $userId . '_';

        $rows = \think\facade\Db::connect('live_mysql')
            ->table('lp_media_asset')
            ->where('asset_code', 'like', $prefix . '%')
            ->order('id', 'desc')
            ->limit(100)
            ->select()
            ->toArray();

        $domain = rtrim($this->request->domain(), '/');
        $list = array_map(function ($row) use ($domain): array {
            $fileUrl = (string) ($row['file_url'] ?? '');
            if ($fileUrl !== '' && !preg_match('/^https?:\/\//i', $fileUrl)) {
                $fileUrl = $domain . '/' . ltrim($fileUrl, '/');
            }
            $coverUrl = (string) ($row['cover_url'] ?? '');
            if ($coverUrl !== '' && !preg_match('/^https?:\/\//i', $coverUrl)) {
                $coverUrl = $domain . '/' . ltrim($coverUrl, '/');
            }
            return [
                'id'          => (int) $row['id'],
                'title'       => (string) ($row['title'] ?? ''),
                'file_url'    => $fileUrl,
                'cover_url'   => $coverUrl,
                'asset_type'  => (string) ($row['asset_type'] ?? ''),
                'scene_type'  => (string) ($row['scene_type'] ?? ''),
                'persona'     => (string) ($row['persona'] ?? ''),
                'keywords'    => (string) ($row['keywords'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'tags'        => (string) ($row['tags'] ?? ''),
                'style'       => (string) ($row['style'] ?? ''),
                'mood'        => (string) ($row['mood'] ?? ''),
                'resolution'  => (string) ($row['resolution'] ?? ''),
                'is_adult'    => (int) ($row['is_adult'] ?? 0),
                'weight'      => (int) ($row['weight'] ?? 1),
                'duration_ms' => (int) ($row['duration_ms'] ?? 0),
                'created_at'  => (string) ($row['created_at'] ?? ''),
            ];
        }, $rows);

        return $this->jsonSuccess(['list' => $list]);
    }

    /**
     * AI 女友端：编辑当前用户上传的直播素材元数据
     */
    public function mediaAssetEdit()
    {
        $userId = $this->getAuthUserId();
        $id = (int) $this->request->post('id/d', 0);
        if ($id <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '素材ID无效');
        }

        $prefix = 'u' . $userId . '_';
        $row = \think\facade\Db::connect('live_mysql')
            ->table('lp_media_asset')
            ->where('id', $id)
            ->where('asset_code', 'like', $prefix . '%')
            ->find();
        if (!$row) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, '素材不存在或无权限');
        }

        $assetType = trim((string) $this->request->post('asset_type', (string) ($row['asset_type'] ?? 'video')));
        if (!in_array($assetType, ['video', 'image', 'audio', 'subtitle'], true)) {
            $assetType = (string) ($row['asset_type'] ?? 'video');
        }

        $sceneType = trim((string) $this->request->post('scene_type', (string) ($row['scene_type'] ?? 'public')));
        if (!in_array($sceneType, ['public', 'privilege', 'interaction', 'cover', 'gift_effect'], true)) {
            $sceneType = (string) ($row['scene_type'] ?? 'public');
        }

        $title = trim((string) $this->request->post('title', ''));
        if ($title === '') {
            $title = (string) ($row['title'] ?? '未命名素材');
        }

        $persona = $this->resolveMediaPersona($userId);
        if ($persona === '') {
            $persona = (string) ($row['persona'] ?? '');
        }

        $keywords = $this->normalizeMediaKeywords((string) $this->request->post('keywords', (string) ($row['keywords'] ?? '')));
        $weight = max(1, (int) $this->request->post('weight/d', (int) ($row['weight'] ?? 1)));
        $durationMs = max(0, (int) $this->request->post('duration_ms/d', (int) ($row['duration_ms'] ?? 0)));

        // 可选：替换素材文件（视频/图片/音频）
        $fileUrl = (string) ($row['file_url'] ?? '');
        $checksum = (string) ($row['checksum'] ?? '');
        $file = $this->request->file('file');
        if ($file) {
            $upload = new \app\common\library\Upload($file);
            $upload->setTopic('media');
            $attachment = $upload->upload(null, 0, $userId);
            $fileUrl = trim((string) ($attachment['url'] ?? ''));
            if ($fileUrl === '') {
                return $this->jsonFail(ResultCode::FAIL, '文件上传失败');
            }
            $checksum = (string) ($attachment['sha1'] ?? '');

            // 根据文件类型自动更新素材类型
            $mime = (string) ($attachment['mimetype'] ?? '');
            if (str_starts_with($mime, 'image/')) {
                $assetType = 'image';
            } elseif (str_starts_with($mime, 'audio/')) {
                $assetType = 'audio';
            } elseif (str_starts_with($mime, 'video/')) {
                $assetType = 'video';
            }
        }

        // 封面图：优先新上传，其次表单传入，最后保留原值
        $coverUrl = (string) ($row['cover_url'] ?? '');
        $coverFile = $this->request->file('cover_file');
        if ($coverFile) {
            $coverUpload = new \app\common\library\Upload($coverFile);
            $coverUpload->setTopic('media');
            $coverAttachment = $coverUpload->upload(null, 0, $userId);
            $newCover = trim((string) ($coverAttachment['url'] ?? ''));
            if ($newCover !== '') {
                $coverUrl = $newCover;
            }
        } elseif (array_key_exists('cover_url', $this->request->post())) {
            $coverUrl = (string) $this->request->post('cover_url', '');
        }
        $coverUrl = $this->normalizeMediaUrl($coverUrl);

        // 详细素材信息（编辑时以提交值为准，未提交则保留原值）
        $detail = $this->normalizeMediaDetail($this->request->post(), $row);
        if ($detail['error'] !== '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, $detail['error']);
        }

        $data = [
            'title'       => $title,
            'asset_type'  => $assetType,
            'scene_type'  => $sceneType,
            'persona'     => $persona,
            'keywords'    => $keywords,
            'weight'      => $weight,
            'duration_ms' => $durationMs,
            'file_url'    => $fileUrl,
            'cover_url'   => $coverUrl,
            'description' => $detail['description'],
            'tags'        => $detail['tags'],
            'style'       => $detail['style'],
            'mood'        => $detail['mood'],
            'resolution'  => $detail['resolution'],
            'is_adult'    => $detail['is_adult'],
            'checksum'    => $checksum,
        ];

        \think\facade\Db::connect('live_mysql')->table('lp_media_asset')->where('id', $id)->update($data);

        return $this->jsonSuccess($data, '更新成功');
    }

    /**
     * AI 女友端：删除当前用户上传的直播素材
     */
    public function mediaAssetDelete()
    {
        $userId = $this->getAuthUserId();
        $id = (int) $this->request->post('id/d', 0);
        if ($id <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '素材ID无效');
        }

        $prefix = 'u' . $userId . '_';
        $exists = \think\facade\Db::connect('live_mysql')
            ->table('lp_media_asset')
            ->where('id', $id)
            ->where('asset_code', 'like', $prefix . '%')
            ->find();
        if (!$exists) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, '素材不存在或无权限');
        }

        \think\facade\Db::connect('live_mysql')->table('lp_media_asset')->where('id', $id)->delete();

        return $this->jsonSuccess(null, '删除成功');
    }

    /**
     * 解析上传素材所属人设名称（优先 persona_id，其次自由文本 persona）
     */
    private function resolveMediaPersona(int $userId): string
    {
        $personaId = (int) $this->request->post('persona_id/d', 0);
        if ($personaId > 0) {
            $name = \think\facade\Db::connect('live_mysql')
                ->table('lp_persona')
                ->where('id', $personaId)
                ->where('user_id', $userId)
                ->value('name');
            if ($name !== null && $name !== '') {
                return (string) $name;
            }
        }
        return trim((string) $this->request->post('persona', ''));
    }

    /**
     * 关键词：逗号分隔或 JSON 数组 -> 逗号分隔字符串
     */
    private function normalizeMediaKeywords(string $keywords): string
    {
        $keywords = trim($keywords);
        if ($keywords === '') {
            return '';
        }
        if (str_contains($keywords, '[')) {
            $parts = json_decode($keywords, true);
            if (!is_array($parts)) {
                $parts = [$keywords];
            }
        } else {
            $parts = explode(',', $keywords);
        }
        $parts = array_filter(array_map('trim', $parts), static fn($s) => $s !== '');
        return implode(',', array_unique($parts));
    }

    /**
     * AI 前端：角色创建价格查询（目前免费）
     */
    public function customPrice()
    {
        return $this->jsonSuccess([
            'customoneprice' => 0,
            'customtwoprice' => 0,
            'customtwoyp1'   => '',
            'customtwoyp2'   => '',
            'customtwoyp3'   => '',
        ]);
    }

    /**
     * AI 前端：获取角色历史切片列表
     */
    public function replayClips()
    {
        $personaId = $this->request->param('persona_id/d', 0);
        if ($personaId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'persona_id 无效');
        }

        $clips = \think\facade\Db::connect('live_mysql')
            ->table('lp_replay_clip')
            ->where('persona_id', $personaId)
            ->where('status', 1)
            ->field(['id', 'title', 'video_url', 'cover_url', 'duration', 'live_date'])
            ->order('live_date', 'desc')
            ->select()
            ->toArray();

        return $this->jsonSuccess($clips);
    }

    /**
     * AI 前端兼容：频道类型（返回空，仅供旧页面兼容）
     */
    public function channelType()
    {
        return $this->jsonSuccess([]);
    }

    /**
     * AI 前端：内容列表（ai_web 首页列表，数据来自 lp_ai_content）
     */
    public function pagelist()
    {
        $rows = \think\facade\Db::connect('live_mysql')
            ->table('lp_ai_content')
            ->where('status', 1)
            ->where('is_public', 1)
            ->field(['id', 'title', 'category', 'cover_url', 'video_url', 'description', 'personality', 'weigh', 'created_at', '(SELECT COUNT(*) FROM lp_ai_collect c WHERE c.content_id = lp_ai_content.id) AS collect_count'])
            ->order('weigh', 'desc')
            ->order('id', 'desc')
            ->select()
            ->toArray();

        // 登录用户返回收藏状态
        $userId = $this->optionalAuthUserId();
        $collectIds = [];
        if ($userId > 0) {
            $collectIds = \think\facade\Db::connect('live_mysql')
                ->table('lp_ai_collect')
                ->where('user_id', $userId)
                ->column('content_id');
        }

        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['weigh'] = (int) ($row['weigh'] ?? 0);
            $row['collect_count'] = (int) ($row['collect_count'] ?? 0);
            $row['video_url'] = (string) ($row['video_url'] ?? '');
            $row['is_collect'] = in_array((int) $row['id'], $collectIds, true);
            $row['personality'] = $row['personality'] ? json_decode($row['personality'], true) : [];
            if (!is_array($row['personality'])) {
                $row['personality'] = [];
            }
        }
        unset($row);

        return $this->jsonSuccess([
            'list'  => $rows,
            'total' => count($rows),
        ]);
    }

    /**
     * AI 前端：角色详情（左侧资料卡）
     * GET { content_id }
     */
    public function roleDetail()
    {
        $contentId = (int) $this->request->get('content_id', 0);
        if ($contentId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'content_id 无效');
        }

        $content = \think\facade\Db::connect('live_mysql')
            ->table('lp_ai_content')
            ->where('id', $contentId)
            ->find();

        if (!$content) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, '角色不存在');
        }

        $personality = $content['personality'] ? json_decode($content['personality'], true) : [];
        if (!is_array($personality)) {
            $personality = [];
        }

        return $this->jsonSuccess([
            'id'          => (int) $content['id'],
            'title'       => (string) $content['title'],
            'category'    => (string) $content['category'],
            'cover_url'   => (string) $content['cover_url'],
            'description' => (string) $content['description'],
            'personality' => $personality,
        ]);
    }

    /**
     * AI 前端：聊天（接入 LLM）
     * POST { content_id, message, history: [{role, content}] }
     */
    public function chat()
    {
        $contentId = (int) $this->request->post('content_id', 0);
        $message   = trim((string) $this->request->post('message', ''));
        $lang      = (string) $this->request->post('lang', '');
        $deviceId  = trim((string) $this->request->post('device_id', ''));
        $voice     = (int) $this->request->post('voice', 0) === 1;
        $history   = $this->request->post('history', []);
        if (!is_array($history)) {
            $history = [];
        }
        // 非平台角色（首页推荐/自建角色）用 role_key 归档聊天记录，如 home-4
        $roleKey = $this->normalizeRoleKey((string) $this->request->post('role_key', ''));
        // 无平台内容 id 的角色（如首页推荐角色）允许前端直接传人设
        $customPersonaName = trim((string) $this->request->post('persona_name', ''));
        $customPersonaDesc = trim((string) $this->request->post('persona_desc', ''));

        if ($message === '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '消息不能为空');
        }

        // 聊天记录只属于登录用户：未登录不允许聊天，也不会产生任何记录
        $userId = $this->optionalAuthUserId();
        if ($userId <= 0) {
            return $this->jsonFail(ResultCode::ACCESS_TOKEN_INVALID, '请先登录');
        }

        $affectionData = null;
        if ($userId > 0 && $contentId > 0) {
            $affectionService = new AffectionService();
            $affectionService->addDailyChat($userId, $contentId);
            $affectionData = $affectionService->get($userId, $contentId);
        }

        // 关键词触发素材（语音/视频，后台配置关键词）：命中则直接返回素材，不再调用 AI 文本回复
        $triggerMedia = null;
        if (!$voice && $contentId > 0) {
            $triggerMedia = $this->matchTriggerMedia($contentId, $message, $userId);
        }

        $reply = '';
        if ($triggerMedia === null) {
            // 读取历史上下文（只含当前登录用户自己的记录）
            if ($contentId > 0 || $roleKey !== '') {
                $history = $this->loadChatHistory($userId, $contentId, 24, $roleKey);
            }

            // 取内容人设
            [$personaName, $personaDesc] = $this->loadPersonaPrompt($contentId);
            if ($contentId <= 0) {
                if ($customPersonaName !== '') {
                    $personaName = $customPersonaName;
                }
                $personaDesc = $customPersonaDesc;
            }

            // 构造 OpenAI messages（sugus.ai 风格：代入角色、口语化、带 emoji）
            $system = '你是「' . $personaName . '」，一个真实、鲜活的 AI 虚拟伴侣。';
            $system .= '请完全代入这个角色，用第一人称、口语化、自然的方式和对方聊天，像真人发消息一样。';
            $system .= '回复要简短（通常 1~3 句话），主动延续话题、适当调情暧昧，但保持自然不油腻。';
            $system .= '可以适当使用 emoji 表达情绪，语气贴合角色人设，避免机械、模板化。';
            $langRule = $this->langRule($lang);
            if ($langRule !== '') {
                $system .= $langRule;
            }
            if ($personaDesc !== '') {
                $system .= "\n\n角色人设：" . $personaDesc;
            }

            $messages = [['role' => 'system', 'content' => $system]];
            foreach ($history as $item) {
                if (is_array($item) && isset($item['role'], $item['content'])) {
                    $role = $item['role'] === 'assistant' ? 'assistant' : 'user';
                    $messages[] = ['role' => $role, 'content' => (string) $item['content']];
                }
            }
            $messages[] = ['role' => 'user', 'content' => $message];

            try {
                $reply = (new LlmService())->chat($messages);
            } catch (BusinessException $e) {
                return $this->jsonFail($e->resultCode, $e->getMessage());
            }
        }

        // 保存本轮对话（用户消息 + AI 回复；触发素材时保存媒体消息）
        if ($contentId > 0 || $roleKey !== '') {
            $this->saveChatMessage($userId, $deviceId, $contentId, 'user', $message, [], $voice, $roleKey);
            if ($reply !== '') {
                $this->saveChatMessage($userId, $deviceId, $contentId, 'assistant', $reply, [], $voice, $roleKey);
            } elseif ($triggerMedia !== null) {
                $mediaMsgId = $this->saveChatMessage($userId, $deviceId, $contentId, 'assistant', '', [
                    'kind'         => $triggerMedia['kind'],
                    'url'          => $triggerMedia['url'],
                    'title'        => $triggerMedia['title'],
                    'id'           => (int) $triggerMedia['id'],
                    'unlock_price' => (int) $triggerMedia['unlock_price'],
                ], $voice, $roleKey);
                $triggerMedia['message_id'] = $mediaMsgId;
            }
        }

        $result = [];
        if ($reply !== '') {
            $result['reply'] = $reply;
        }
        if ($triggerMedia !== null) {
            $result['media'] = $triggerMedia;
        }

        // 好感度返回给前端，便于实时刷新进度条
        if ($affectionData) {
            $result['affection'] = $affectionData;
        }

        // 特殊视频命令：消息含命令词 → 返回随机「已解锁」的特殊视频（好感度满100 或已花钻石解锁）
        if (!$voice && $userId > 0 && $contentId > 0 && $this->isSpecialCommand($message)) {
            $specialVideo = $this->pickSpecialVideo($contentId, $userId);
            if ($specialVideo !== '') {
                $result['special_video'] = $specialVideo;
            }
        }

        return $this->jsonSuccess($result);
    }

    /**
     * AI 前端：获取聊天历史（只读当前登录用户自己的记录）
     * GET { content_id, limit }
     */
    public function chatHistory()
    {
        $userId = $this->optionalAuthUserId();
        $contentId = (int) $this->request->get('content_id', 0);
        // 非平台角色用 role_key（如 home-4）取历史
        $roleKey = $this->normalizeRoleKey((string) $this->request->get('role_key', ''));
        if ($contentId <= 0 && $roleKey === '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'content_id 无效');
        }

        // 聊天记录只属于登录用户：未登录不返回任何历史
        if ($userId <= 0) {
            return $this->jsonSuccess([]);
        }

        $limit = (int) $this->request->get('limit', 50);
        $limit = max(1, min(200, $limit));

        return $this->jsonSuccess($this->loadChatHistory($userId, $contentId, $limit, $roleKey));
    }

    /**
     * AI 前端：会话列表（左侧聊天历史，按角色去重取最新一条）
     * 只返回当前登录用户自己的会话，未登录返回空
     */
    public function chatConversations()
    {
        $userId = $this->optionalAuthUserId();
        if ($userId <= 0) {
            return $this->jsonSuccess([]);
        }

        $db = \think\facade\Db::connect('live_mysql');
        // 平台角色按 content_id 归档，首页/自建角色按 role_key 归档，两者都要出现在历史里
        $query = $db->table('lp_ai_chat_message')
            ->where('user_id', $userId)
            ->where(function ($q) {
                $q->where('content_id', '>', 0)->whereOr('role_key', '<>', '');
            });

        // 按会话分组取最新一条，避免单个会话的消息把其它会话挤出 limit
        $groups = $query->field('content_id, role_key, MAX(id) AS last_id')
            ->group('content_id, role_key')
            ->order('last_id', 'desc')
            ->limit(100)
            ->select()
            ->toArray();

        $lastIds = [];
        foreach ($groups as $g) {
            $lastIds[] = (int) $g['last_id'];
        }
        $lastMap = [];
        if (!empty($lastIds)) {
            $rows = $db->table('lp_ai_chat_message')
                ->whereIn('id', $lastIds)
                ->field(['id', 'content', 'media_kind', 'created_at'])
                ->select()
                ->toArray();
            foreach ($rows as $r) {
                $lastMap[(int) $r['id']] = $r;
            }
        }

        $list = [];
        foreach ($groups as $g) {
            $cid = (int) $g['content_id'];
            $roleKey = (string) ($g['role_key'] ?? '');
            $last = $lastMap[(int) $g['last_id']] ?? [];

            $lastMessage = (string) ($last['content'] ?? '');
            if ($lastMessage === '') {
                $kind = (string) ($last['media_kind'] ?? '');
                $lastMessage = $kind === 'voice' ? '[语音]' : ($kind === 'video' ? '[视频]' : '');
            }

            // 首页角色的展示信息：home-N 对应内容表 id=N；自建角色 my-N 由前端用本地人设缓存补
            $title = '';
            $cover = '';
            $lookupId = $cid;
            if ($lookupId <= 0 && preg_match('/^home-(\d+)$/', $roleKey, $m) === 1) {
                $lookupId = (int) $m[1];
            }
            if ($lookupId > 0) {
                $content = $db->table('lp_ai_content')
                    ->where('id', $lookupId)
                    ->field(['title', 'cover_url'])
                    ->find();
                $title = (string) ($content['title'] ?? '');
                $cover = $this->fullUrl((string) ($content['cover_url'] ?? ''));
            }

            $list[] = [
                'content_id'   => $cid,
                'role_key'     => $roleKey,
                'title'        => $title,
                'cover_url'    => $cover,
                'last_message' => $lastMessage,
                'time'         => (string) ($last['created_at'] ?? ''),
            ];
        }

        return $this->jsonSuccess($list);
    }

    /**
     * AI 前端：获取角色视频素材（Call Me 随机播放 / 特殊视频）
     * GET { content_id, type: normal|special }
     */
    public function roleMedia()
    {
        $contentId = (int) $this->request->get('content_id', 0);
        $type = (string) $this->request->get('type', 'normal');
        if ($contentId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'content_id 无效');
        }
        if (!in_array($type, ['normal', 'special'], true)) {
            $type = 'normal';
        }

        $scene = $type === 'special' ? 'affection' : 'binge';

        $rows = \think\facade\Db::connect('live_mysql')
            ->table('lp_ai_media')
            ->where('content_id', $contentId)
            ->where('scene_type', $scene)
            ->where('status', 1)
            ->field(['id', 'title', 'video_url', 'cover_url', 'media_type', 'media_kind', 'unlock_price'])
            ->order('weigh', 'desc')
            ->order('unlock_price', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        // 付费剧集/特殊视频：判断是否已解锁（好感度满100仅对特殊视频生效，追剧只看是否已花钻石）
        $userId = $this->optionalAuthUserId();
        $affectionUnlocked = false;
        $unlockedMediaIds = [];
        if ($userId > 0 && $contentId > 0) {
            if ($type === 'special') {
                $affectionUnlocked = (new AffectionService())->get($userId, $contentId)['unlocked'] ?? false;
            }
            $unlockedMediaIds = \think\facade\Db::connect('live_mysql')->table('lp_ai_media_unlock')
                ->where('user_id', $userId)
                ->where('content_id', $contentId)
                ->column('media_id');
        }

        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['unlock_price'] = (int) $row['unlock_price'];
            $row['media_kind'] = (string) ($row['media_kind'] ?? 'video') ?: 'video';
            $row['video_url'] = $this->fullUrl($row['video_url']);
            $row['cover_url'] = $this->fullUrl($row['cover_url']);

            $price = (int) $row['unlock_price'];
            if ($type === 'special') {
                $unlocked = $price <= 0 || $affectionUnlocked || in_array($row['id'], $unlockedMediaIds, true);
            } else {
                $unlocked = $price <= 0 || in_array($row['id'], $unlockedMediaIds, true);
            }
            $row['unlocked'] = $unlocked;
            if (!$unlocked) {
                $row['video_url'] = '';
            }
        }
        unset($row);

        return $this->jsonSuccess($rows);
    }

    /**
     * AI 前端：收藏 / 取消收藏角色
     * POST { content_id, action: collect|cancel }
     */
    public function collect()
    {
        $userId = $this->optionalAuthUserId();
        if ($userId <= 0) {
            return $this->jsonFail(ResultCode::AUTH_FAILED, '请先登录');
        }

        $contentId = (int) $this->request->post('content_id', 0);
        $action    = (string) $this->request->post('action', 'collect');
        if ($contentId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'content_id 无效');
        }

        $db = \think\facade\Db::connect('live_mysql');
        $exists = $db->table('lp_ai_content')->where('id', $contentId)->find();
        if (!$exists) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, '内容不存在');
        }

        $row = $db->table('lp_ai_collect')
            ->where('user_id', $userId)
            ->where('content_id', $contentId)
            ->find();

        if ($action === 'cancel') {
            if ($row) {
                $db->table('lp_ai_collect')->where('id', $row['id'])->delete();
            }
            return $this->jsonSuccess(['collected' => false]);
        }

        if (!$row) {
            $db->table('lp_ai_collect')->insert([
                'user_id'    => $userId,
                'content_id' => $contentId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return $this->jsonSuccess(['collected' => true]);
    }

    /**
     * AI 前端：我的收藏列表
     */
    public function collectList()
    {
        $userId = $this->optionalAuthUserId();
        if ($userId <= 0) {
            return $this->jsonFail(ResultCode::AUTH_FAILED, '请先登录');
        }

        $rows = \think\facade\Db::connect('live_mysql')
            ->table('lp_ai_collect')
            ->alias('c')
            ->join('lp_ai_content a', 'a.id = c.content_id')
            ->where('c.user_id', $userId)
            ->where('a.status', 1)
            ->field(['a.id', 'a.title', 'a.category', 'a.cover_url', 'a.description', 'c.created_at'])
            ->order('c.id', 'desc')
            ->select()
            ->toArray();

        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['is_collect'] = true;
        }
        unset($row);

        return $this->jsonSuccess(['list' => $rows]);
    }

    /**
     * AI 前端：查询角色好感度（需登录）
     */
    public function affection()
    {
        $userId = $this->getAuthUserId();
        $contentId = (int) $this->request->get('content_id', 0);
        if ($contentId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'content_id 无效');
        }

        $data = (new AffectionService())->get($userId, $contentId);
        return $this->jsonSuccess($data);
    }

    /**
     * AI 前端：钻石购买好感度（1:1，需登录）
     * POST { content_id, amount }
     */
    public function buyAffection()
    {
        $userId = $this->getAuthUserId();
        $contentId = (int) $this->request->post('content_id', 0);
        $amount = (int) $this->request->post('amount', 0);
        if ($contentId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'content_id 无效');
        }

        try {
            $result = (new AffectionService())->buy($userId, $contentId, $amount);
        } catch (BusinessException $e) {
            return $this->jsonFail($e->resultCode, $e->getMessage());
        }

        return $this->jsonSuccess($result);
    }

    /**
     * AI 前端：花钻石解锁特殊视频（需登录）
     * POST { media_id }
     */
    public function unlockVideo()
    {
        $userId = $this->getAuthUserId();
        $mediaId = (int) $this->request->post('media_id', 0);
        if ($mediaId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'media_id 无效');
        }

        $media = \think\facade\Db::connect('live_mysql')->table('lp_ai_media')->where('id', $mediaId)->find();
        if (!$media || (int) $media['status'] !== 1) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, '视频不存在');
        }
        $contentId = (int) $media['content_id'];
        $price = (int) $media['unlock_price'];
        $videoUrl = $this->fullUrl((string) $media['video_url']);
        if ($price <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '该视频无需解锁');
        }

        // 已花钻石解锁
        $exists = \think\facade\Db::connect('live_mysql')->table('lp_ai_media_unlock')
            ->where('user_id', $userId)->where('media_id', $mediaId)->find();
        if ($exists) {
            return $this->jsonSuccess(['unlocked' => true, 'balance' => (new WalletService())->balance($userId), 'video_url' => $videoUrl]);
        }

        // 扣钻石并记录解锁
        try {
            $debit = (new WalletService())->debit($userId, (float) $price, 'media_unlock', $mediaId, '解锁AI视频');
        } catch (BusinessException $e) {
            return $this->jsonFail($e->resultCode, $e->getMessage());
        }

        \think\facade\Db::connect('live_mysql')->table('lp_ai_media_unlock')->insert([
            'user_id'    => $userId,
            'media_id'   => $mediaId,
            'content_id' => $contentId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->jsonSuccess(['unlocked' => true, 'balance' => $debit['balance_after'], 'video_url' => $videoUrl]);
    }

    /**
     * AI 前端：花钻石解锁单条聊天媒体消息（按消息，不按素材）
     * POST { message_id }
     */
    public function unlockChatMedia()
    {
        $userId = $this->getAuthUserId();
        $messageId = (int) $this->request->post('message_id', 0);
        if ($messageId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'message_id 无效');
        }

        $db = \think\facade\Db::connect('live_mysql');
        $msg = $db->table('lp_ai_chat_message')->where('id', $messageId)->find();
        if (!$msg) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, '消息不存在');
        }
        if ((int) $msg['user_id'] !== $userId) {
            return $this->jsonFail(ResultCode::AUTH_FAILED, '无权解锁该消息');
        }

        $mediaId = (int) $msg['media_id'];
        if ($mediaId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '该消息无需解锁');
        }
        if ((int) $msg['unlocked'] === 1 || (string) $msg['media_url'] !== '') {
            return $this->jsonSuccess(['unlocked' => true, 'balance' => (new WalletService())->balance($userId), 'video_url' => $this->fullUrl((string) $msg['media_url'])]);
        }

        $media = $db->table('lp_ai_media')->where('id', $mediaId)->find();
        if (!$media || (int) $media['status'] !== 1) {
            return $this->jsonFail(ResultCode::RECORD_NOT_FOUND, '视频不存在');
        }

        $price = (int) $msg['unlock_price'];
        if ($price <= 0) {
            $price = (int) $media['unlock_price'];
        }
        if ($price <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '该视频无需解锁');
        }

        $videoUrl = $this->fullUrl((string) $media['video_url']);

        try {
            $debit = (new WalletService())->debit($userId, (float) $price, 'media_unlock', $mediaId, '解锁AI视频');
        } catch (BusinessException $e) {
            return $this->jsonFail($e->resultCode, $e->getMessage());
        }

        $db->table('lp_ai_chat_message')->where('id', $messageId)->update([
            'unlocked'  => 1,
            'media_url' => (string) $media['video_url'],
        ]);

        return $this->jsonSuccess(['unlocked' => true, 'balance' => $debit['balance_after'], 'video_url' => $videoUrl]);
    }

    /**
     * 判断是否触发特殊视频的命令词
     */
    private function isSpecialCommand(string $message): bool
    {
        $keywords = ['特殊视频', '解锁视频', '解锁', '惊喜', 'special', 'secret', 'unlock'];
        foreach ($keywords as $kw) {
            if (stripos($message, $kw) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 随机取一个「已解锁」的特殊视频 URL
     * 好感度满 100 视为全部解锁，否则只取已花钻石解锁或免费的
     */
    private function pickSpecialVideo(int $contentId, int $userId): string
    {
        $db = \think\facade\Db::connect('live_mysql');

        $affectionUnlocked = (new AffectionService())->get($userId, $contentId)['unlocked'] ?? false;

        $query = $db->table('lp_ai_media')
            ->where('content_id', $contentId)
            ->where('scene_type', 'affection')
            ->where('status', 1);

        if (!$affectionUnlocked) {
            $unlockedIds = $db->table('lp_ai_media_unlock')
                ->where('user_id', $userId)
                ->where('content_id', $contentId)
                ->column('media_id');

            $query->where(function ($q) use ($unlockedIds) {
                $q->where('unlock_price', 0);
                if (!empty($unlockedIds)) {
                    $q->whereOr('id', 'in', $unlockedIds);
                }
            });
        }

        $row = $query->field(['video_url'])->orderRaw('RAND()')->limit(1)->find();

        return $row ? $this->fullUrl($row['video_url']) : '';
    }

    /**
     * 根据后台配置的关键词匹配该角色要触发的语音/视频素材
     * 返回第一个命中的素材信息，未命中返回 null
     */
    private function matchTriggerMedia(int $contentId, string $message, int $userId = 0): ?array
    {
        if ($contentId <= 0 || $message === '') {
            return null;
        }

        $rows = \think\facade\Db::connect('live_mysql')
            ->table('lp_ai_media')
            ->where('content_id', $contentId)
            ->where('status', 1)
            ->where('scene_type', 'chat')
            ->where('keywords', '<>', '')
            ->field(['id', 'title', 'media_kind', 'video_url', 'media_type', 'unlock_price', 'keywords'])
            ->order('weigh', 'desc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        foreach ($rows as $row) {
            $keywords = (string) ($row['keywords'] ?? '');
            if ($keywords === '') {
                continue;
            }
            foreach (explode(',', $keywords) as $kw) {
                $kw = trim($kw);
                if ($kw === '' || stripos($message, $kw) === false) {
                    continue;
                }

                $kind  = (string) ($row['media_kind'] ?? 'video') ?: 'video';
                $url   = (string) ($row['video_url'] ?? '');
                $price = (int) ($row['unlock_price'] ?? 0);
                $unlocked = $price <= 0;

                return [
                    'id'           => (int) $row['id'],
                    'kind'         => $kind,
                    'title'        => (string) ($row['title'] ?? ''),
                    'url'          => $unlocked ? $this->fullUrl($url) : '',
                    'unlock_price' => $price,
                    'media_type'   => (string) ($row['media_type'] ?? 'normal'),
                    'unlocked'     => $unlocked,
                ];
            }
        }

        return null;
    }

    /**
     * 拼接完整 URL（相对路径转绝对）
     */
    private function fullUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }
        // 相对路径（如 /storage/xxx）优先拼接到资源基础地址，否则回退到请求域名
        $base = \think\facade\Config::get('ai.resource_base_url');
        if ($base === '' || $base === null) {
            $base = $this->request->domain();
        }
        return rtrim((string) $base, '/') . '/' . ltrim($url, '/');
    }

    /**
     * 根据前端语言切换生成回复语言指令
     */
    private function langRule(string $lang): string
    {
        $map = [
            'zh' => '请始终使用中文回复。',
            'en' => 'Please always reply in English.',
            'ja' => '必ず日本語で返信してください。',
        ];
        return $map[$lang] ?? '';
    }

    /**
     * 读取聊天历史（按时间正序，最多 limit 条）
     * 只读当前登录用户自己的记录
     */
    private function loadChatHistory(int $userId, int $contentId, int $limit, string $roleKey = ''): array
    {
        $query = \think\facade\Db::connect('live_mysql')
            ->table('lp_ai_chat_message')
            ->where('user_id', $userId);

        if ($contentId > 0) {
            $query->where('content_id', $contentId);
        } else {
            // 非平台角色：按 role_key 归档
            $query->where('content_id', 0)->where('role_key', $roleKey);
        }

        $rows = $query->field(['id', 'role', 'content', 'media_kind', 'media_url', 'media_title', 'media_id', 'unlock_price', 'unlocked'])
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        $rows = array_reverse($rows);
        $list = [];
        foreach ($rows as $row) {
            $item = [
                'role'    => $row['role'],
                'content' => (string) $row['content'],
            ];

            $kind        = (string) ($row['media_kind'] ?? '');
            $url         = (string) ($row['media_url'] ?? '');
            $mediaId     = (int) ($row['media_id'] ?? 0);
            $unlockPrice = (int) ($row['unlock_price'] ?? 0);
            $messageId   = (int) ($row['id'] ?? 0);
            $unlocked    = (int) ($row['unlocked'] ?? 0) === 1;

            // 语音通话文本消息（无媒体地址、无素材ID）打语音标识，供前端展示
            if ($kind === 'voice' && $url === '' && $mediaId <= 0) {
                $item['voice'] = true;
            }

            if ($kind !== '' && ($url !== '' || $unlocked)) {
                $realUrl = $url;
                if ($realUrl === '' && $mediaId > 0) {
                    $realUrl = (string) \think\facade\Db::connect('live_mysql')->table('lp_ai_media')->where('id', $mediaId)->value('video_url');
                }
                $item['media'] = [
                    'kind'         => $kind,
                    'url'          => $this->fullUrl($realUrl),
                    'title'        => (string) ($row['media_title'] ?? ''),
                    'id'           => $mediaId,
                    'message_id'   => $messageId,
                    'unlock_price' => $unlockPrice,
                    'unlocked'     => true,
                ];
            } elseif ($kind !== '' && $mediaId > 0) {
                // 未解锁的媒体消息：按「消息」独立解锁
                $item['media'] = [
                    'kind'         => $kind,
                    'url'          => '',
                    'title'        => (string) ($row['media_title'] ?? ''),
                    'id'           => $mediaId,
                    'message_id'   => $messageId,
                    'unlock_price' => $unlockPrice,
                    'unlocked'     => false,
                ];
            }

            $list[] = $item;
        }
        return $list;
    }

    /**
     * 非平台角色标识过滤（只允许字母数字下划线短横），非法一律按空处理
     */
    private function normalizeRoleKey(string $key): string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 64) {
            return '';
        }
        return preg_match('/^[A-Za-z0-9_\-]+$/', $key) === 1 ? $key : '';
    }

    /**
     * 保存一条聊天记录
     */
    private function saveChatMessage(int $userId, string $deviceId, int $contentId, string $role, string $content, array $media = [], bool $voice = false, string $roleKey = ''): int
    {
        $data = [
            'user_id'    => $userId,
            'device_id'  => $deviceId,
            'content_id' => $contentId,
            'role_key'   => $roleKey,
            'role'       => $role,
            'content'    => $content,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if ($voice) {
            // 语音通话文本消息：以 media_kind=voice 标识（无媒体地址）
            $data['media_kind']  = 'voice';
            $data['media_url']   = '';
            $data['media_title'] = '';
        } elseif ($media) {
            $data['media_kind']    = (string) ($media['kind'] ?? '');
            $data['media_url']     = (string) ($media['url'] ?? '');
            $data['media_title']   = (string) ($media['title'] ?? '');
            $data['media_id']      = (int) ($media['id'] ?? 0);
            $data['unlock_price']  = (int) ($media['unlock_price'] ?? 0);
        }

        $query = \think\facade\Db::connect('live_mysql')->table('lp_ai_chat_message');
        $query->insert($data);
        return (int) $query->getLastInsID();
    }

    /**
     * 加载内容的人设信息，用于构造聊天 system prompt
     */
    private function loadPersonaPrompt(int $contentId): array
    {
        $name = 'AI 伴侣';
        $desc = '';

        if ($contentId > 0) {
            $content = \think\facade\Db::connect('live_mysql')
                ->table('lp_ai_content')
                ->where('id', $contentId)
                ->find();

            if ($content) {
                $name = trim((string) ($content['title'] ?? '')) ?: $name;
                if (trim((string) ($content['description'] ?? '')) !== '') {
                    $desc .= '简介：' . trim((string) $content['description']);
                }

                $personality = $content['personality'] ? json_decode($content['personality'], true) : [];
                if (is_array($personality) && !empty($personality)) {
                    $parts = [];
                    foreach ($personality as $k => $v) {
                        if (is_string($v) && $v !== '') {
                            $parts[] = $k . '：' . $v;
                        }
                    }
                    if ($parts) {
                        $desc = $desc !== '' ? $desc . '；' . implode('；', $parts) : implode('；', $parts);
                    }
                }
            }
        }

        return [$name, $desc];
    }

    /**
     * 校验用户是否已通过商家认证
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
                '请先通过商家认证后再创建AI角色'
            );
        }
    }
}
