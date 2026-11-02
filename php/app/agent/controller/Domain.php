<?php
declare(strict_types=1);

namespace app\agent\controller;

use app\agent\service\AgentView;
use app\live\service\AgentService;

/**
 * 代理专属域名（只读，分配权在总部）
 */
final class Domain extends Base
{
    public function index(): void
    {
        $this->requirePerm('domain');

        $domains = AgentService::domains($this->agentId());

        $rows = '';
        foreach ($domains as $d) {
            $status = (int) $d['status'] === 1
                ? '<span class="chip on">启用</span>'
                : '<span class="chip off">停用</span>';
            $primary = (int) $d['is_primary'] === 1 ? '<span class="chip on">主域名</span>' : '<span class="muted">—</span>';
            $rows .= '<tr>'
                . '<td>' . AgentView::h((string) $d['domain']) . '</td>'
                . '<td>' . $primary . '</td>'
                . '<td>' . $status . '</td>'
                . '<td>' . AgentView::h((string) ($d['created_at'] ?? '')) . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="4" class="empty">总部还没有给你分配专属域名</td></tr>';
        }

        $content = <<<HTML
<div class="head">
  <div>
    <h1>专属域名</h1>
    <div class="sub">专属域名由总部统一分配与解析。域名下的直接访问会归入你的代理数据，无需带任何参数。</div>
  </div>
</div>
<div class="card">
  <h2>我的域名</h2>
  <table><thead><tr><th>域名</th><th>类型</th><th>状态</th><th>分配时间</th></tr></thead>
  <tbody>{$rows}</tbody></table>
  <div class="tip">如需新增或更换域名，请联系总部管理员。域名需要先完成 DNS 解析与 HTTPS 证书配置，添加后立即生效。</div>
</div>
HTML;

        $this->render('domain', '专属域名', $content);
    }
}
