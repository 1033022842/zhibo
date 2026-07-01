<?php

namespace app\admin\controller\routine;

use Throwable;
use app\common\controller\Backend;
use app\common\library\Sms;

class SmsConfig extends Backend
{
    protected array $noNeedPermission = ['testSend'];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function testSend(): void
    {
        $data = $this->request->post();
        if (empty($data['test_mobile'])) {
            $this->error('请输入测试手机号');
        }
        if (empty($data['sms_api_url']) || empty($data['sms_api_key'])
            || empty($data['sms_sign_id']) || empty($data['sms_template_id'])) {
            $this->error('请填写完整的短信配置参数');
        }

        $sms    = new Sms();
        $result = $sms->testSend(
            $data['test_mobile'],
            $data['sms_api_url'],
            $data['sms_api_key'],
            $data['sms_sign_id'],
            $data['sms_template_id']
        );

        if ($result['success']) {
            $this->success('测试短信发送成功');
        } else {
            $this->error($result['message']);
        }
    }

    public function encryptApiKey(): void
    {
        $plain = $this->request->post('api_key', '');
        if (empty($plain)) {
            $this->error('请输入需要加密的API Key');
        }
        $sms       = new Sms();
        $encrypted = $sms->encryptValue($plain);
        $this->success('', ['encrypted' => $encrypted]);
    }
}
