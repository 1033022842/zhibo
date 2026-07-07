<?php

declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\admin\model\live\MaintenanceConfig as MaintenanceConfigModel;
use app\common\service\TgService;

final class MaintenanceConfig extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new MaintenanceConfigModel();
    }

    /**
     * 获取配置
     */
    public function index(): void
    {
        $config = $this->model->find(1);
        $this->success('', [
            'row' => $config ? $config->toArray() : null,
        ]);
    }

    /**
     * 保存配置
     */
    public function save(): void
    {
        if (!$this->request->isPost()) {
            $this->error(__('Parameter error'));
        }

        $payload = $this->request->post();
        $botToken = trim((string) ($payload['bot_token'] ?? ''));
        $chatId = trim((string) ($payload['chat_id'] ?? ''));

        if ($botToken === '' || $chatId === '') {
            $this->error('Bot Token 和 Chat ID 不能为空');
        }

        $config = $this->model->find(1);
        if (!$config) {
            $config = $this->model;
            $config->id = 1;
        }

        $config->bot_token = $botToken;
        $config->chat_id = $chatId;
        $config->save();

        $this->success('配置保存成功');
    }

    /**
     * 测试发送
     */
    public function test(): void
    {
        if (!$this->request->isPost()) {
            $this->error(__('Parameter error'));
        }

        $payload = $this->request->post();
        $botToken = trim((string) ($payload['bot_token'] ?? ''));
        $chatId = trim((string) ($payload['chat_id'] ?? ''));

        if ($botToken === '' || $chatId === '') {
            $this->error('请先填写 Bot Token 和 Chat ID');
        }

        $service = new TgService();
        $result = $service->testSend($botToken, $chatId);

        if ($result['ok']) {
            $this->success('测试消息发送成功');
        } else {
            $this->error('发送失败: ' . ($result['error'] ?? 'Unknown'));
        }
    }
}
