<?php
declare(strict_types=1);

namespace app\api\controller;

use think\App;
use app\BaseController;
use app\common\web\ResultCode;
use app\live\service\UserService;
use app\live\service\PersonaService;
use app\live\validate\UserValidate;

final class Live extends BaseController
{
    protected array $middleware = [
        \app\live\middleware\Auth::class => ['only' => ['logout', 'profile', 'userInfo', 'updateProfile', 'customRoleOne', 'customOneList', 'upload', 'replayClips']],
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

        return $this->jsonSuccess([
            'id'       => $profile['id'],
            'username' => $profile['nickname'],
            'email'    => $email,
            'avatar'   => $profile['avatar'],
            'token'    => $this->request->header('Authorization'),
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
     * AI 前端：创建角色
     */
    public function customRoleOne()
    {
        $userId = $this->getAuthUserId();
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

        return $this->jsonSuccess([
            'fullurl' => $attachment['url'],
            'url'     => $attachment['url'],
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
     * AI 前端兼容：浏览列表（返回空）
     */
    public function pagelist()
    {
        return $this->jsonSuccess(['pagelist' => ['data' => [], 'total' => 0]]);
    }
}
