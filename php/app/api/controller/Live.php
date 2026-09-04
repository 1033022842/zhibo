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
        \app\live\middleware\Auth::class => ['only' => ['logout', 'profile', 'userInfo', 'updateProfile', 'customRoleOne', 'customOneList', 'upload', 'replayClips', 'affection', 'buyAffection', 'unlockVideo']],
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
            ->field(['id', 'title', 'category', 'cover_url', 'description', 'personality'])
            ->order('weigh', 'desc')
            ->order('id', 'desc')
            ->select()
            ->toArray();

        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
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

        if ($message === '') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '消息不能为空');
        }

        // 登录用户：每天首次对话好感度 +1（一天最多 +1），并用数据库历史作为上下文
        $userId = $this->optionalAuthUserId();
        $affectionData = null;
        if ($userId > 0 && $contentId > 0) {
            $affectionService = new AffectionService();
            $affectionService->addDailyChat($userId, $contentId);
            $affectionData = $affectionService->get($userId, $contentId);
        }

        // 关键词触发素材（语音/视频，后台配置关键词）：命中则直接返回素材，不再调用 AI 文本回复
        $triggerMedia = null;
        if (!$voice && $contentId > 0) {
            $triggerMedia = $this->matchTriggerMedia($contentId, $message);
        }

        $reply = '';
        if ($triggerMedia === null) {
            // 读取历史上下文：登录用户按 user_id，游客按 device_id
            if ($contentId > 0 && ($userId > 0 || $deviceId !== '')) {
                $history = $this->loadChatHistory($userId, $deviceId, $contentId, 24);
            }

            // 取内容人设
            [$personaName, $personaDesc] = $this->loadPersonaPrompt($contentId);

            // 构造 OpenAI messages（candy.ai 风格：代入角色、口语化、带 emoji）
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
        if ($contentId > 0 && ($userId > 0 || $deviceId !== '')) {
            $this->saveChatMessage($userId, $deviceId, $contentId, 'user', $message, [], $voice);
            if ($reply !== '') {
                $this->saveChatMessage($userId, $deviceId, $contentId, 'assistant', $reply, [], $voice);
            } elseif ($triggerMedia !== null) {
                $this->saveChatMessage($userId, $deviceId, $contentId, 'assistant', '', [
                    'kind'  => $triggerMedia['kind'],
                    'url'   => $triggerMedia['url'],
                    'title' => $triggerMedia['title'],
                ], $voice);
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
     * AI 前端：获取聊天历史（登录用户按 user_id，游客按 device_id）
     * GET { content_id, device_id, limit }
     */
    public function chatHistory()
    {
        $userId = $this->optionalAuthUserId();
        $deviceId = trim((string) $this->request->get('device_id', ''));
        $contentId = (int) $this->request->get('content_id', 0);
        if ($contentId <= 0) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'content_id 无效');
        }

        if ($userId <= 0 && $deviceId === '') {
            return $this->jsonSuccess([]);
        }

        $limit = (int) $this->request->get('limit', 50);
        $limit = max(1, min(200, $limit));

        return $this->jsonSuccess($this->loadChatHistory($userId, $deviceId, $contentId, $limit));
    }

    /**
     * AI 前端：会话列表（左侧聊天历史，按角色去重取最新一条）
     * GET { device_id }
     */
    public function chatConversations()
    {
        $userId = $this->optionalAuthUserId();
        $deviceId = trim((string) $this->request->get('device_id', ''));
        if ($userId <= 0 && $deviceId === '') {
            return $this->jsonSuccess([]);
        }

        $db = \think\facade\Db::connect('live_mysql');
        $query = $db->table('lp_ai_chat_message')->where('content_id', '>', 0);
        if ($userId > 0) {
            $query->where('user_id', $userId);
        } else {
            $query->where('device_id', $deviceId);
        }

        $rows = $query->field(['content_id', 'content', 'media_kind', 'created_at'])
            ->order('id', 'desc')
            ->limit(500)
            ->select()
            ->toArray();

        $seen = [];
        $list = [];
        foreach ($rows as $r) {
            $cid = (int) $r['content_id'];
            if (isset($seen[$cid])) {
                continue;
            }
            $seen[$cid] = true;

            $content = $db->table('lp_ai_content')
                ->where('id', $cid)
                ->field(['title', 'cover_url'])
                ->find();

            $lastMessage = (string) $r['content'];
            if ($lastMessage === '') {
                $kind = (string) ($r['media_kind'] ?? '');
                $lastMessage = $kind === 'voice' ? '[语音]' : ($kind === 'video' ? '[视频]' : '');
            }

            $list[] = [
                'content_id'   => $cid,
                'title'        => $content['title'] ?? '',
                'cover_url'    => $content['cover_url'] ?? '',
                'last_message' => $lastMessage,
                'time'         => $r['created_at'],
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

        $rows = \think\facade\Db::connect('live_mysql')
            ->table('lp_ai_media')
            ->where('content_id', $contentId)
            ->where('media_type', $type)
            ->where('keywords', '=', '')
            ->where('status', 1)
            ->field(['id', 'title', 'video_url', 'cover_url', 'media_type', 'media_kind', 'unlock_price'])
            ->order('weigh', 'desc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        // 特殊视频：判断是否已解锁（好感度满 100 或已花钻石解锁）
        $userId = $this->optionalAuthUserId();
        $affectionUnlocked = false;
        $unlockedMediaIds = [];
        if ($type === 'special' && $userId > 0 && $contentId > 0) {
            $affectionUnlocked = (new AffectionService())->get($userId, $contentId)['unlocked'] ?? false;
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

            if ($type === 'special') {
                $price = (int) $row['unlock_price'];
                $unlocked = $price <= 0 || $affectionUnlocked || in_array($row['id'], $unlockedMediaIds, true);
                $row['unlocked'] = $unlocked;
                if (!$unlocked) {
                    $row['video_url'] = '';
                }
            } else {
                $row['unlocked'] = true;
                $row['unlock_price'] = 0;
            }
        }
        unset($row);

        return $this->jsonSuccess($rows);
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
        if ((string) $media['media_type'] !== 'special') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, '该视频无需解锁');
        }

        $contentId = (int) $media['content_id'];
        $price = (int) $media['unlock_price'];
        $videoUrl = $this->fullUrl((string) $media['video_url']);
        if ($price <= 0) {
            return $this->jsonSuccess(['unlocked' => true, 'balance' => (new WalletService())->balance($userId), 'video_url' => $videoUrl]);
        }

        // 已解锁
        $exists = \think\facade\Db::connect('live_mysql')->table('lp_ai_media_unlock')
            ->where('user_id', $userId)->where('media_id', $mediaId)->find();
        if ($exists) {
            return $this->jsonSuccess(['unlocked' => true, 'balance' => (new WalletService())->balance($userId), 'video_url' => $videoUrl]);
        }

        // 好感度满 100 视为已解锁
        $affectionUnlocked = (new AffectionService())->get($userId, $contentId)['unlocked'] ?? false;
        if ($affectionUnlocked) {
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
            ->where('media_type', 'special')
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
    private function matchTriggerMedia(int $contentId, string $message): ?array
    {
        if ($contentId <= 0 || $message === '') {
            return null;
        }

        $rows = \think\facade\Db::connect('live_mysql')
            ->table('lp_ai_media')
            ->where('content_id', $contentId)
            ->where('status', 1)
            ->where('keywords', '<>', '')
            ->field(['id', 'title', 'media_kind', 'video_url', 'keywords'])
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

                $kind = (string) ($row['media_kind'] ?? 'video') ?: 'video';
                $url  = (string) ($row['video_url'] ?? '');

                return [
                    'id'    => (int) $row['id'],
                    'kind'  => $kind,
                    'title' => (string) ($row['title'] ?? ''),
                    'url'   => $this->fullUrl($url),
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
     * 可选登录态：有合法 token 则返回 user_id，否则返回 0（不抛异常）
     * 用于 chat 等公开接口，登录用户每天对话 +1 / 特殊视频判定
     */
    private function optionalAuthUserId(): int
    {
        $token = str_replace('Bearer ', '', (string) $this->request->header('Authorization', ''));
        if ($token === '') {
            $token = (string) $this->request->header('token', '');
        }
        if ($token === '') {
            return 0;
        }

        $payload = \app\live\service\JwtService::parseToken($token);
        if (!$payload || ($payload['type'] ?? '') !== 'access') {
            return 0;
        }
        return (int) ($payload['sub'] ?? 0);
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
     * 登录用户按 user_id，游客按 device_id
     */
    private function loadChatHistory(int $userId, string $deviceId, int $contentId, int $limit): array
    {
        $query = \think\facade\Db::connect('live_mysql')
            ->table('lp_ai_chat_message')
            ->where('content_id', $contentId);

        if ($userId > 0) {
            $query->where('user_id', $userId);
        } else {
            $query->where('device_id', $deviceId);
        }

        $rows = $query->field(['role', 'content', 'media_kind', 'media_url', 'media_title'])
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

            $kind = (string) ($row['media_kind'] ?? '');
            $url  = (string) ($row['media_url'] ?? '');
            // 语音通话文本消息（无媒体地址）打语音标识，供前端展示
            if ($kind === 'voice' && $url === '') {
                $item['voice'] = true;
            }
            if ($kind !== '' && $url !== '') {
                $item['media'] = [
                    'kind'  => $kind,
                    'url'   => $this->fullUrl($url),
                    'title' => (string) ($row['media_title'] ?? ''),
                ];
            }

            $list[] = $item;
        }
        return $list;
    }

    /**
     * 保存一条聊天记录
     */
    private function saveChatMessage(int $userId, string $deviceId, int $contentId, string $role, string $content, array $media = [], bool $voice = false): void
    {
        $data = [
            'user_id'    => $userId,
            'device_id'  => $deviceId,
            'content_id' => $contentId,
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
            $data['media_kind']  = (string) ($media['kind'] ?? '');
            $data['media_url']   = (string) ($media['url'] ?? '');
            $data['media_title'] = (string) ($media['title'] ?? '');
        }

        \think\facade\Db::connect('live_mysql')->table('lp_ai_chat_message')->insert($data);
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
