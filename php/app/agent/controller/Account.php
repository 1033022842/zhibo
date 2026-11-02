<?php
declare(strict_types=1);

namespace app\agent\controller;

use app\agent\middleware\Auth as AuthMiddleware;
use app\agent\service\AgentView;
use app\common\facade\Token;
use app\live\service\AgentService;
use think\facade\Db;

/**
 * 代理账号设置：资料展示 + 修改密码
 */
final class Account extends Base
{
    public function index(): void
    {
        $this->requirePerm('account');

        $agent  = $this->agent();
        $notice = (string) ($this->request->param('ok') ?? '') === '1'
            ? '<div class="alert ok">密码修改成功，其他设备上的登录已失效。</div>'
            : '';

        $name    = AgentView::h((string) $agent['name']);
        $no      = AgentView::h((string) $agent['agent_no']);
        $user    = AgentView::h((string) $agent['username']);
        $contact = AgentView::h((string) ($agent['contact'] ?? ''));
        $last    = AgentView::h((string) ($agent['last_login_at'] ?? '—'));
        $csrf    = AgentView::h($this->csrf());

        $permLabels = [];
        foreach (AgentService::PERMS as $key => $label) {
            if (AgentService::hasPerm($agent, $key)) {
                $permLabels[] = $label;
            }
        }
        $permText = $permLabels === [] ? '—' : implode('、', $permLabels);

        $content = <<<HTML
<div class="head">
  <div>
    <h1>账号设置</h1>
    <div class="sub">代理账号由总部创建，如需调整权限、域名或停用账号，请联系总部管理员。</div>
  </div>
</div>
{$notice}
<div class="card">
  <h2>账号信息</h2>
  <div class="table-wrap"><table><tbody>
    <tr><th style="width:160px">代理编号</th><td>{$no}</td></tr>
    <tr><th>代理名称</th><td>{$name}</td></tr>
    <tr><th>登录账号</th><td>{$user}</td></tr>
    <tr><th>联系方式</th><td>{$contact}</td></tr>
    <tr><th>当前权限</th><td>{$permText}</td></tr>
    <tr><th>最后登录</th><td>{$last}</td></tr>
  </tbody></table></div>
</div>
<div class="card">
  <h2>修改密码</h2>
  <form class="frm" method="post" action="/agent/account/password">
    <input type="hidden" name="_csrf" value="{$csrf}">
    <div class="fld"><label>当前密码</label><input type="password" name="old_password" maxlength="64" required autocomplete="current-password"></div>
    <div class="fld"><label>新密码（6-32 位）</label><input type="password" name="new_password" maxlength="32" required autocomplete="new-password"></div>
    <div class="fld"><label>确认新密码</label><input type="password" name="confirm_password" maxlength="32" required autocomplete="new-password"></div>
    <div class="actions"><button class="btn" type="submit">保存新密码</button></div>
  </form>
  <div class="tip">修改成功后所有已登录设备会被强制退出，需要用新密码重新登录。</div>
</div>
HTML;

        $this->render('account', '账号设置', $content);
    }

    public function password(): void
    {
        $this->requirePerm('account');
        $this->verifyCsrf();

        $agent = $this->agent();
        $old   = (string) ($this->request->post('old_password') ?? '');
        $new   = (string) ($this->request->post('new_password') ?? '');
        $again = (string) ($this->request->post('confirm_password') ?? '');

        if (!verify_password($old, (string) ($agent['password_hash'] ?? ''))) {
            $this->fail('当前密码不正确');
        }
        $len = mb_strlen($new);
        if ($len < 6 || $len > 32) {
            $this->fail('新密码长度需为 6-32 位');
        }
        if ($new !== $again) {
            $this->fail('两次输入的新密码不一致');
        }

        Db::connect('live_mysql')->table('lp_agent')->where('id', $this->agentId())->update([
            'password_hash' => hash_password($new),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        try {
            Token::clear(AuthMiddleware::TYPE, $this->agentId());
        } catch (\Throwable) {
            // 忽略
        }
        $this->clearCookie();
        $this->redirect('/agent/auth/login?changed=1');
    }

    private function fail(string $message): void
    {
        $notice = '<div class="alert err">' . AgentView::h($message) . '</div>';
        $agent  = $this->agent();
        $csrf   = AgentView::h($this->csrf());
        $content = $notice . '<div class="card"><h2>修改密码</h2>'
            . '<form class="frm" method="post" action="/agent/account/password">'
            . '<input type="hidden" name="_csrf" value="' . $csrf . '">'
            . '<div class="fld"><label>当前密码</label><input type="password" name="old_password" maxlength="64" required></div>'
            . '<div class="fld"><label>新密码（6-32 位）</label><input type="password" name="new_password" maxlength="32" required></div>'
            . '<div class="fld"><label>确认新密码</label><input type="password" name="confirm_password" maxlength="32" required></div>'
            . '<div class="actions"><button class="btn" type="submit">保存新密码</button>'
            . '<a class="btn-line" href="/agent/account/index">返回</a></div></form></div>';
        $this->render('account', '账号设置', $content, 422);
    }

    private function clearCookie(): void
    {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        header(
            'Set-Cookie: ' . AuthMiddleware::COOKIE . '=; Path=/agent; Max-Age=0; HttpOnly; SameSite=Lax'
            . ($secure ? '; Secure' : ''),
            false
        );
    }
}
