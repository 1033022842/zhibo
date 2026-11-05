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

        $domains = AgentService::effectiveDomains($this->agentId(), false);

        $rows = '';
        foreach ($domains as $d) {
            $status = (int) $d['status'] === 1
                ? '<span class="chip on">启用</span>'
                : '<span class="chip off">停用</span>';
            $primary = (int) $d['is_primary'] === 1 ? '<span class="chip on">主域名</span>' : '<span class="muted">—</span>';
            $inherited = !empty($d['inherited'])
                ? '继承自 ' . AgentView::h(AgentService::agentName((int) ($d['owner_id'] ?? 0)) ?: '上级代理')
                : '<span class="muted">本人</span>';
            $rows .= '<tr>'
                . '<td>' . AgentView::h((string) $d['domain']) . '</td>'
                . '<td>' . $inherited . '</td>'
                . '<td>' . $primary . '</td>'
                . '<td>' . $status . '</td>'
                . '<td>' . AgentView::h((string) ($d['created_at'] ?? '')) . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="empty">总部或上级还没有分配推广域名</td></tr>';
        }

        $content = <<<HTML
<div class="head">
  <div>
    <h1>专属域名</h1>
    <div class="sub">专属域名由总部或上级统一分配与解析。域名下的直接访问会归入对应代理的数据，无需带任何参数。</div>
  </div>
</div>
<div class="card">
  <h2>可用域名</h2>
  <div class="table-wrap"><table><thead><tr><th>域名</th><th>归属</th><th>类型</th><th>状态</th><th>分配时间</th></tr></thead>
  <tbody>{$rows}</tbody></table></div>
  <div class="tip">自己没有独立域名时会自动继承上级的域名。如需新增或更换域名，请联系上级代理或总部管理员。</div>
</div>
HTML;

        $this->render('domain', '专属域名', $content);
    }
}
