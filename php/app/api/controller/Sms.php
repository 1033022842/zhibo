<?php

namespace app\api\controller;

use Throwable;
use ba\Captcha;
use ba\ClickCaptcha;
use think\facade\Validate;
use app\common\controller\Frontend;
use app\common\library\Sms as SmsLibrary;

class Sms extends Frontend
{
    protected array $noNeedLogin = ['send'];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function send(): void
    {
        $params = $this->request->post(['mobile', 'event', 'captchaId', 'captchaInfo']);

        $validate = Validate::rule([
            'mobile'      => 'require|mobile',
            'event'       => 'require',
            'captchaId'   => 'require',
            'captchaInfo' => 'require'
        ])->message([
            'mobile'      => 'mobile format error',
            'event'       => 'Parameter error',
            'captchaId'   => 'Captcha error',
            'captchaInfo' => 'Captcha error'
        ]);
        if (!$validate->check($params)) {
            $this->error(__($validate->getError()));
        }

        $clickCaptcha = new ClickCaptcha();
        if (!$clickCaptcha->check($params['captchaId'], $params['captchaInfo'])) {
            $this->error(__('Captcha error'));
        }

        $captcha  = new Captcha();
        $capData  = $captcha->getCaptchaData($params['mobile'] . $params['event']);
        if ($capData && time() - $capData['create_time'] < 60) {
            $this->error(__('Frequent SMS sending'));
        }

        $sms = new SmsLibrary();
        if (!$sms->configured) {
            $this->error(__('SMS sending service unavailable'));
        }

        $code = $captcha->create($params['mobile'] . $params['event']);

        $sent = $sms->send($params['mobile'], $code);
        if (!$sent) {
            $this->error(__('SMS sending failed'));
        }

        $this->success(__('SMS sent successfully~'));
    }
}
