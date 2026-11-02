<?php
declare(strict_types=1);

namespace app\agent\controller;

use app\agent\service\AgentView;
use app\BaseController;
use app\live\service\AgentService;

/**
 * 代理门户控制器基类：登录守卫、权限、CSRF、页面输出
 */
abstract class Base extends BaseController
{
    protected array $middleware = [
        \app\agent\middleware\Auth::class,
    ];

    protected function agent(): array
    {
        return (array) ($this->request->agent ?? []);
    }

    protected function agentId(): int
    {
        return (int) ($this->agent()['id'] ?? 0);
    }

    protected function agentToken(): string
    {
        return (string) ($this->request->agentToken ?? '');
    }

    /** 权限校验：不通过直接 403 页面 */
    protected function requirePerm(string $key): void
    {
        if (!AgentService::hasPerm($this->agent(), $key)) {
            $this->deny('当前账号没有访问该功能的权限，请联系总部管理员开通。', 403);
        }
    }

    protected function csrf(): string
    {
        return hash_hmac(
            'sha256',
            'agent-csrf|' . $this->agentId() . '|' . $this->agentToken(),
            (string) config('buildadmin.token.key')
        );
    }

    protected function verifyCsrf(): void
    {
        $given = (string) ($this->request->post('_csrf') ?? '');
        if ($given === '' || !hash_equals($this->csrf(), $given)) {
            $this->deny('请求已失效，请刷新页面后重试。', 419);
        }
    }

    protected function render(string $active, string $title, string $content, int $status = 200): void
    {
        http_response_code($status);
        $html = AgentView::page($this->agent(), $active, $title, $content, $this->csrf());
        response($html)->send();
        exit;
    }

    protected function deny(string $message, int $status = 403): void
    {
        $title   = $status === 419 ? '请求已失效' : '无权访问';
        $content = '<div class="card"><h2>' . AgentView::h($title) . '</h2>'
            . '<div class="tip" style="font-size:13px;color:#5b6072">' . AgentView::h($message) . '</div>'
            . '<div class="actions"><a class="btn-line" href="/agent">返回概览</a></div></div>';
        $this->render('', $title, $content, $status);
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
