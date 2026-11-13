<?php
declare(strict_types=1);

namespace app\agent\controller;

use app\agent\service\AgentView;
use app\live\service\AgentService;
use app\live\service\PromoService;
use think\facade\Db;

/**
 * 代理专属域名
 * - 代理可自助添加 / 停用 / 删除自己的推广域名（与总后台「分配域名」同一张表）
 * - 子代理不单独维护，直接继承上级的域名
 * - 域名直访会自动归因到该代理
 */
final class Domain extends Base
{
    /** 单个代理最多自建域名数 */
    private const MAX_OWN = 20;

    public function index(): void
    {
        $agentId  = $this->agentId();
        $topLevel = $this->isTopLevel();
        $domains  = AgentService::effectiveDomains($agentId, false);
        $ownCount = 0;

        $rows = '';
        foreach ($domains as $d) {
            $id        = (int) $d['id'];
            $own       = (int) ($d['agent_id'] ?? 0) === $agentId;
            $status    = (int) $d['status'] === 1;
            $primary   = (int) $d['is_primary'] === 1;
            $inherited = !empty($d['inherited']);
            if ($own) {
                $ownCount++;
            }

            $statusHtml = $status
                ? '<span class="chip on">启用</span>'
                : '<span class="chip off">停用</span>';
            $primaryHtml = $primary ? '<span class="chip on">主域名</span>' : '<span class="muted">—</span>';
            $fromHtml = $inherited
                ? '<span class="muted">继承自 ' . AgentView::h(AgentService::agentName((int) ($d['owner_id'] ?? 0)) ?: '上级代理') . '</span>'
                : '<span class="muted">本人</span>';
            $ops = $own
                ? '<a href="/agent/domain/toggle?id=' . $id . '&_csrf=' . urlencode($this->csrf()) . '">' . ($status ? '停用' : '启用') . '</a>'
                    . '<a class="danger" href="/agent/domain/del?id=' . $id . '&_csrf=' . urlencode($this->csrf()) . '" onclick="return confirm(\'确认删除该域名？删除后该域名将不再归属你。\')">删除</a>'
                : '<span class="muted">—</span>';

            $rows .= '<tr>'
                . '<td>' . AgentView::h((string) $d['domain']) . '</td>'
                . '<td>' . $fromHtml . '</td>'
                . '<td>' . $primaryHtml . '</td>'
                . '<td>' . $statusHtml . '</td>'
                . '<td class="nowrap">' . AgentView::h((string) ($d['created_at'] ?? '') ?: '—') . '</td>'
                . '<td class="ops">' . $ops . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="empty">还没有推广域名，在下方添加一个</td></tr>';
        }

        $tip = $topLevel && $ownCount >= self::MAX_OWN
            ? '<div class="alert err">已达到上限（' . self::MAX_OWN . ' 个），请先删除不用的域名。</div>'
            : '';
        $csrf = $this->csrf();

        if ($topLevel) {
            $formCard = <<<HTML
<div class="card">
  <h2>添加推广域名</h2>
  <form class="frm" method="post" action="/agent/domain/save">
    <input type="hidden" name="_csrf" value="{$csrf}">
    <div class="fld"><label>域名（必填，不用带 https://）</label>
      <input type="text" name="domain" maxlength="120" placeholder="如 go.example.com" required></div>
    <div class="actions"><button class="btn" type="submit">添加域名</button></div>
  </form>
  <div class="tip">添加前请先把域名解析到本站服务器并配置好 HTTPS 证书，否则访问会报错；平台主域名不能绑定到代理。</div>
</div>
HTML;
        } else {
            $formCard = '<div class="card"><h2>添加推广域名</h2><div class="tip">你是子代理，自动继承上级的推广域名，无需单独添加。</div></div>';
        }

        $content = <<<HTML
<div class="head">
  <div>
    <h1>专属域名</h1>
    <div class="sub">在这里添加自己的推广域名。访客直接访问该域名时，注册与充值会自动归入你的代理数据，无需带任何参数。子代理会自动继承上级的域名。</div>
  </div>
</div>
{$tip}
{$formCard}
<div class="card">
  <h2>可用域名</h2>
  <div class="table-wrap"><table><thead><tr>
    <th>域名</th><th>归属</th><th>类型</th><th>状态</th><th>添加时间</th><th>操作</th>
  </tr></thead><tbody>{$rows}</tbody></table></div>
</div>
HTML;

        $this->render('domain', '专属域名', $content);
    }

    /** 添加自己的推广域名 */
    public function save(): void
    {
        $this->requireTopLevel();
        $this->verifyCsrf();

        $agentId = $this->agentId();
        $db      = Db::connect('live_mysql');

        $raw    = (string) $this->request->post('domain', '');
        $domain = PromoService::normalizeDomain($raw);
        if ($domain === '' || !preg_match('/^(?=.{4,120}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $domain)) {
            $this->flashError('请填写正确的域名，例如 go.example.com');
        }

        // 平台主域名不能绑定到代理，避免把官方流量归到代理名下
        if (in_array($domain, (new PromoService())->siteDomains(), true)) {
            $this->flashError('「' . $domain . '」是平台主域名，不能绑定到代理，请使用你自己的域名');
        }

        $exists = $db->table('lp_agent_domain')->where('domain', $domain)->find();
        if ($exists) {
            $this->flashError((int) $exists['agent_id'] === $agentId
                ? '你已经添加过该域名'
                : '该域名已被占用，请联系总部处理');
        }

        if (count(AgentService::domains($agentId, false)) >= self::MAX_OWN) {
            $this->flashError('最多只能添加 ' . self::MAX_OWN . ' 个域名，请先删除不用的');
        }

        $now      = date('Y-m-d H:i:s');
        $hasOwned = count(AgentService::domains($agentId, false)) > 0;
        $db->table('lp_agent_domain')->insert([
            'agent_id'   => $agentId,
            'domain'     => $domain,
            'is_primary' => $hasOwned ? 0 : 1,
            'status'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->redirect('/agent/domain/index');
    }

    /** 启用 / 停用自己的域名 */
    public function toggle(): void
    {
        $this->requireTopLevel();
        $this->verifyCsrfParam();

        $id = (int) ($this->request->param('id') ?? 0);
        $db = Db::connect('live_mysql');
        $row = $id > 0
            ? $db->table('lp_agent_domain')->where('id', $id)->where('agent_id', $this->agentId())->find()
            : null;
        if ($row) {
            $db->table('lp_agent_domain')->where('id', $id)->update([
                'status'     => (int) $row['status'] === 1 ? 0 : 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->redirect('/agent/domain/index');
    }

    /** 删除自己的域名（继承来的不能删） */
    public function del(): void
    {
        $this->requireTopLevel();
        $this->verifyCsrfParam();

        $id = (int) ($this->request->param('id') ?? 0);
        if ($id > 0) {
            Db::connect('live_mysql')->table('lp_agent_domain')
                ->where('id', $id)->where('agent_id', $this->agentId())->delete();
        }
        $this->redirect('/agent/domain/index');
    }

    /**
     * 顶级代理可自助添加域名，无需总部先分配
     * （子代理不单独维护，直接继承上级的域名）
     */
    private function isTopLevel(): bool
    {
        return (int) ($this->agent()['parent_id'] ?? 0) === 0;
    }

    private function requireTopLevel(): void
    {
        if (!$this->isTopLevel()) {
            $this->deny('子代理自动继承上级的推广域名，无需单独添加。', 403);
        }
    }

    /** 域名类操作走 GET，CSRF 放在 query 里 */
    private function verifyCsrfParam(): void
    {
        $given = (string) ($this->request->param('_csrf') ?? '');
        if ($given === '' || !hash_equals($this->csrf(), $given)) {
            $this->deny('请求已失效，请刷新页面后重试。', 419);
        }
    }

    private function flashError(string $message): void
    {
        $content = '<div class="card"><h2>操作失败</h2><div class="tip" style="font-size:13px;color:#d61f4a">'
            . AgentView::h($message) . '</div><div class="actions">'
            . '<a class="btn-line" href="/agent/domain/index">返回专属域名</a></div></div>';
        $this->render('domain', '操作失败', $content, 422);
    }
}
