<?php
declare (strict_types=1);

namespace app\admin\controller;

use Throwable;
use ba\ClickCaptcha;
use think\facade\Config;
use think\facade\Validate;
use app\common\facade\Token;
use app\admin\model\AdminLog;
use app\common\controller\Backend;

class Index extends Backend
{
    protected array $noNeedLogin      = ['logout', 'login'];
    protected array $noNeedPermission = ['index'];

    /**
     * 后台初始化请求
     * @return void
     * @throws Throwable
     */
    public function index(): void
    {
        $adminInfo          = $this->auth->getInfo();
        $adminInfo['super'] = $this->auth->isSuperAdmin();
        unset($adminInfo['token'], $adminInfo['refresh_token']);

        $menus = $this->auth->getMenus();
        if (!$menus) {
            $this->error(__('No background menu, please contact super administrator!'));
        }
        $this->success('', [
            'adminInfo'  => $adminInfo,
            'menus'      => $menus,
            'siteConfig' => [
                'siteName'     => get_sys_config('site_name'),
                'version'      => get_sys_config('version'),
                'apiUrl'       => Config::get('buildadmin.api_url'),
                'upload'       => keys_to_camel_case(get_upload_config(), ['max_size', 'save_name', 'allowed_suffixes', 'allowed_mime_types']),
                'cdnUrl'       => full_url(),
                'cdnUrlParams' => Config::get('buildadmin.cdn_url_params'),
            ],
            'terminal'   => [
                'phpDevelopmentServer' => str_contains($_SERVER['SERVER_SOFTWARE'], 'Development Server'),
                'npmPackageManager'    => Config::get('terminal.npm_package_manager'),
            ]
        ]);
    }

    /**
     * 管理员登录
     * @return void
     * @throws Throwable
     */
    public function login(): void
    {
        // 检查登录态
        if ($this->auth->isLogin()) {
            $this->success(__('You have already logged in. There is no need to log in again~'), [
                'type' => $this->auth::LOGGED_IN
            ], $this->auth::LOGIN_RESPONSE_CODE);
        }

        $captchaSwitch = Config::get('buildadmin.admin_login_captcha');

        // 检查提交
        if ($this->request->isPost()) {
            $username = $this->request->post('username');
            $password = $this->request->post('password');
            $keep     = $this->request->post('keep');

            $rule = [
                'username|' . __('Username') => 'require|length:3,30',
                'password|' . __('Password') => 'require|regex:^(?!.*[&<>"\'\n\r]).{6,32}$',
            ];
            $data = [
                'username' => $username,
                'password' => $password,
            ];
            if ($captchaSwitch) {
                $rule['captchaId|' . __('CaptchaId')] = 'require';
                $rule['captchaInfo|' . __('Captcha')] = 'require';

                $data['captchaId']   = $this->request->post('captchaId');
                $data['captchaInfo'] = $this->request->post('captchaInfo');
            }
            $validate = Validate::rule($rule);
            if (!$validate->check($data)) {
                $this->error($validate->getError());
            }

            if ($captchaSwitch) {
                $captchaObj = new ClickCaptcha();
                if (!$captchaObj->check($data['captchaId'], $data['captchaInfo'])) {
                    $this->error(__('Captcha error'));
                }
            }

            AdminLog::instance()->setTitle(__('Login'));

            $res = $this->auth->login($username, $password, (bool)$keep, (string)$this->request->post('totp', ''));
            if ($res === true) {
                $this->success(__('Login succeeded!'), [
                    'userInfo' => $this->auth->getInfo()
                ]);
            } else {
                $msg = $this->auth->getError();
                // 2FA 相关错误：透出明确标记，前端据此提示/显示验证码框
                if ($msg === 'TOTP_REQUIRED') {
                    $this->error(__('Please enter the 6-digit code from your authenticator app'), [
                        'totp_required' => true,
                    ], 0);
                }
                if ($msg === 'TOTP_INVALID') {
                    $this->error(__('Invalid authenticator code'), [
                        'totp_required' => true,
                    ], 0);
                }
                $msg = $msg ?: __('Incorrect user name or password!');
                $this->error($msg);
            }
        }

        $this->success('', [
            'captcha' => $captchaSwitch,
            // 是否已有账号启用 2FA（前端据此决定是否显示「动态验证码」输入框）
            'totp_required' => $this->anyTotpEnabled(),
        ]);
    }

    /**
     * 是否有管理员启用了 2FA（且全局开关打开）
     */
    private function anyTotpEnabled(): bool
    {
        try {
            $on = trim((string) \think\facade\Db::connect('live_mysql')->table('lp_platform_config')
                ->where('key', 'admin_totp_enabled')->value('value'));
            if ($on !== '1') {
                return false;
            }
            return (int) \app\admin\model\Admin::where('totp_enabled', 1)
                ->where('totp_secret', '<>', '')->count() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 管理员注销
     * @return void
     */
    public function logout(): void
    {
        if ($this->request->isPost()) {
            $refreshToken = $this->request->post('refreshToken', '');
            if ($refreshToken) Token::delete((string)$refreshToken);
            $this->auth->logout();
            $this->success();
        }
    }
}
