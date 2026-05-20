<?php
declare(strict_types=1);

namespace app\live\controller;

use think\App;
use app\BaseController;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\live\service\UserService;
use app\live\validate\UserValidate;

final class UserController extends BaseController
{
    private UserService $userService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->userService = new UserService();
    }

    public function register()
    {
        $params = $this->request->post();
        $this->validate($params, UserValidate::class, 'register');

        $ip = $this->request->ip();
        $this->userService->register($params['username'], $params['password'], $params['nickname'], $ip);

        return $this->jsonSuccess(null, '注册成功');
    }

    public function login()
    {
        $params = $this->request->post();
        $this->validate($params, UserValidate::class, 'login');

        $ip       = $this->request->ip();
        $deviceId = $params['device_id'] ?? '';

        $result = $this->userService->login($params['username'], $params['password'], $ip, $deviceId);

        return $this->jsonSuccess($result);
    }

    public function refreshToken()
    {
        $refreshToken = $this->request->post('refresh_token', '');

        if (empty($refreshToken)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'refresh_token不能为空');
        }

        $result = $this->userService->refreshToken($refreshToken);

        return $this->jsonSuccess($result);
    }

    public function logout()
    {
        $accessToken  = $this->request->header('Authorization', '');
        $accessToken  = str_replace('Bearer ', '', $accessToken);
        $refreshToken = $this->request->post('refresh_token', '');
        $userId       = $this->getAuthUserId();

        $this->userService->logout($userId, $accessToken, $refreshToken);

        return $this->jsonSuccess(null, '已退出');
    }

    public function profile()
    {
        $userId = $this->getAuthUserId();
        $result = $this->userService->profile($userId);

        return $this->jsonSuccess($result);
    }

    public function updateProfile()
    {
        $userId = $this->getAuthUserId();
        $params = $this->request->only(['nickname', 'gender', 'bio']);
        $this->validate($params, UserValidate::class, 'updateProfile');

        $this->userService->updateProfile($userId, $params);

        return $this->jsonSuccess(null, '更新成功');
    }
}
