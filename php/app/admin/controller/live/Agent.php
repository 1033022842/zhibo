<?php
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\live\service\AgentService;
use app\live\service\PromoService;
use think\facade\Db;

/**
 * 代理管理（总后台）
 * 访问: /admin/live.Agent/index?_key=<ADMIN_FLOAT_KEY>
 *
 * - 创建/编辑代理账号
 * - 勾选代理端权限
 * - 分配专属推广域名
 * - 分配渠道（lp_promo_pixel.agent_id）
 */
final class Agent extends Backend
{
    protected array $noNeedLogin = ['index', 'save', 'toggle'];

    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    public function index(): void
    {
        if (!AgentService::ready()) {
            $this->renderPage(
                '<div class="card"><h2>代理系统尚未初始化</h2>'
                . '<div class="tip">请先在数据库执行 <code>php/sql/upgrade_agent.sql</code>，再刷新本页。</div></div>',
                '代理管理',
                '代理功能尚未初始化'
            );
            return;
        }

        $db = Db::connect('live_mysql');

        $editId = (int) $this->request->param('id', 0);
        $edit   = [
            'id' => 0, 'agent_no' => '', 'name' => '', 'username' => '', 'contact' => '',
            'rate' => 0, 'status' => 1, 'remark' => '', 'perms' => json_encode(AgentService::DEFAULT_PERMS),
        ];
        if ($editId > 0) {
            $row = $db->table('lp_agent')->where('id', $editId)->find();
            if ($row) {
                $edit = array_merge($edit, $row);
            }
        }

        // 列表 + 每个代理的累计数据
        $agents = $db->query(
            'SELECT a.*,
                    (SELECT COUNT(*) FROM lp_user u WHERE u.reg_agent_id = a.id) AS regs,
                    (SELECT COUNT(DISTINCT o.user_id) FROM lp_recharge_order o
                       JOIN lp_user u ON u.id = o.user_id
                      WHERE u.reg_agent_id = a.id AND o.status = 1 AND o.biz_type <> \'vip\') AS buyers,
                    (SELECT IFNULL(SUM(o.pay_amount),0) FROM lp_recharge_order o
                       JOIN lp_user u ON u.id = o.user_id
                      WHERE u.reg_agent_id = a.id AND o.status = 1 AND o.biz_type <> \'vip\') AS amount
             FROM lp_agent a ORDER BY a.id DESC LIMIT 500'
        );

        // 域名 / 渠道归属
        $domainsByAgent = [];
        foreach ($db->table('lp_agent_domain')->order('is_primary', 'desc')->order('id', 'asc')->select()->toArray() as $d) {
            $domainsByAgent[(int) $d['agent_id']][] = $d;
        }
        $pixelsByAgent = [];
        $allPixels = [];
        foreach ($db->table('lp_promo_pixel')->order('sort', 'asc')->order('id', 'asc')->select()->toArray() as $p) {
            $allPixels[] = $p;
            if ((int) $p['agent_id'] > 0) {
                $pixelsByAgent[(int) $p['agent_id']][] = $p;
            }
        }
        $agentNames = [];
        foreach ($agents as $a) {
            $agentNames[(int) $a['id']] = (string) $a['name'];
        }

        $tagList = static function (array $items, string $class = ''): string {
            if ($items === []) {
                return '<span class="muted">未分配</span>';
            }
            $html = '';
            foreach (array_slice($items, 0, 3) as $item) {
                $html .= '<span class="tag ' . $class . '" title="' . htmlspecialchars((string) $item, ENT_QUOTES) . '">'
                    . htmlspecialchars((string) $item, ENT_QUOTES) . '</span>';
            }
            if (count($items) > 3) {
                $html .= '<span class="tag more">+' . (count($items) - 3) . '</span>';
            }
            return $html;
        };

        $rows = '';
        foreach ($agents as $a) {
            $id      = (int) $a['id'];
            $status  = (int) $a['status'] === 1;
            $perms   = AgentService::parsePerms($a['perms'] ?? null);
            $domains = array_map(static fn(array $d): string => (string) $d['domain'], $domainsByAgent[$id] ?? []);
            $pixels  = array_map(static fn(array $p): string => (string) ($p['name'] !== '' ? $p['name'] : ('渠道#' . $p['id'])), $pixelsByAgent[$id] ?? []);

            $permChips = '';
            foreach (array_slice($perms, 0, 3) as $p) {
                $permChips .= '<span class="mini-chip">' . $this->e(AgentService::PERMS[$p] ?? $p) . '</span>';
            }
            if (count($perms) > 3) {
                $permChips .= '<span class="mini-chip">+' . (count($perms) - 3) . '</span>';
            }
            if ($permChips === '') {
                $permChips = '<span class="muted">—</span>';
            }

            $lastLogin = trim((string) ($a['last_login_at'] ?? '')) !== ''
                ? $this->e((string) $a['last_login_at'])
                : '<span class="muted">从未登录</span>';

            $rows .= '<tr>'
                . '<td><div class="agent-name">' . $this->e((string) $a['name']) . '</div>'
                . '<div class="agent-no">' . $this->e((string) $a['agent_no']) . '</div></td>'
                . '<td>' . $this->e((string) $a['username']) . '</td>'
                . '<td><div>' . $tagList($domains) . '</div><div style="margin-top:4px">' . $tagList($pixels, 'alt') . '</div></td>'
                . '<td><div class="mini-chips">' . $permChips . '</div></td>'
                . '<td class="num"><div>注册 ' . (int) $a['regs'] . '</div>'
                . '<div class="muted" style="font-size:11.5px;margin-top:2px">'
                . (int) $a['buyers'] . ' 人 / $' . number_format((float) $a['amount'], 2) . '</div></td>'
                . '<td>' . ($status ? '<span class="chip on">启用</span>' : '<span class="chip off">停用</span>') . '</td>'
                . '<td>' . $lastLogin . '</td>'
                . '<td class="ops">'
                . '<a href="/admin/live.Agent/index?id=' . $id . $this->keyQuery() . '">编辑</a>'
                . '<a href="/admin/live.Agent/toggle?id=' . $id . $this->keyQuery() . '">' . ($status ? '停用' : '启用') . '</a>'
                . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="8" class="empty">还没有代理，先在上方创建</td></tr>';
        }
        $agentCount = count($agents);

        // 表单预填
        $eId       = (int) $edit['id'];
        $eNo       = $this->e((string) $edit['agent_no']);
        $eName     = $this->e((string) $edit['name']);
        $eUser     = $this->e((string) $edit['username']);
        $eContact  = $this->e((string) $edit['contact']);
        $eRate     = number_format((float) $edit['rate'], 2, '.', '');
        $eRemark   = $this->e((string) ($edit['remark'] ?? ''));
        $eStatus   = (int) $edit['status'] === 1 ? ' checked' : '';
        $ePerms    = AgentService::parsePerms($edit['perms'] ?? null);
        $eDomains  = implode("\n", array_map(static fn(array $d): string => (string) $d['domain'], $domainsByAgent[$eId] ?? []));
        $ePixels   = array_map(static fn(array $p): int => (int) $p['id'], $pixelsByAgent[$eId] ?? []);

        $permBoxes = '';
        foreach (AgentService::PERMS as $key => $label) {
            $checked  = in_array($key, $ePerms, true) ? ' checked' : '';
            $disabled = in_array($key, AgentService::ALWAYS_PERMS, true) ? ' disabled' : '';
            $desc     = AgentService::PERM_DESCS[$key] ?? '';
            $permBoxes .= '<label class="perm"><input type="checkbox" name="perms[]" value="' . $key . '"' . $checked . $disabled . '>'
                . '<span><b>' . $this->e($label) . '</b><small>' . $this->e($desc) . '</small></span></label>';
        }

        $pixelBoxes = '';
        foreach ($allPixels as $p) {
            $pid   = (int) $p['id'];
            $owner = (int) $p['agent_id'];
            $pf    = PromoService::PIXEL_PLATFORMS[(string) $p['platform']] ?? (string) $p['platform'];
            $pn    = trim((string) $p['name']);
            $label = $pf . ($pn !== '' ? ' · ' . $pn : '');
            if ($owner > 0 && $owner !== $eId) {
                $ownerName = $agentNames[$owner] ?? ('代理#' . $owner);
                $pixelBoxes .= '<label class="perm is-disabled"><input type="checkbox" disabled>'
                    . '<span><b>' . $this->e($label) . '</b><small>已属于 ' . $this->e($ownerName) . '</small></span></label>';
            } else {
                $checked = in_array($pid, $ePixels, true) ? ' checked' : '';
                $pixelBoxes .= '<label class="perm"><input type="checkbox" name="pixels[]" value="' . $pid . '"' . $checked . '>'
                    . '<span><b>' . $this->e($label) . '</b><small>渠道 #' . $pid . '</small></span></label>';
            }
        }
        if ($pixelBoxes === '') {
            $pixelBoxes = '<div class="muted" style="font-size:12.5px">还没有渠道，请先到「推广与像素」创建。</div>';
        }

        $ok = (string) $this->request->param('ok', '');
        $alert = $ok !== '' ? '<div class="alert ok">' . $this->e($ok) . '</div>' : '';
        $formTitle = $eId > 0 ? '编辑代理 · ' . $eName : '新建代理';
        $cancel = $eId > 0 ? '<a class="btn-line" href="/admin/live.Agent/index' . $this->keyQuery() . '">取消编辑</a>' : '';
        $pwdHint = $eId > 0 ? '留空表示不修改密码' : '新建代理必填，至少 6 位';
        $eDomainsEsc = $this->e($eDomains);
        $saveUrl     = '/admin/live.Agent/save' . $this->keyQuery();
        $newUrl      = '/admin/live.Agent/index' . $this->keyQuery();
        $formHint    = $eId > 0 ? '修改后立即生效；改密码或停用会强制该代理重新登录。' : '代理账号独立于后台管理员，只能登录 /agent。';
        $headActions = '<a class="btn-line" href="' . $newUrl . '">新建代理</a>';

        $content = <<<HTML
{$alert}
<div class="card">
  <div class="card-head">
    <div><h2>{$formTitle}</h2><div class="hint">{$formHint}</div></div>
    {$cancel}
  </div>
  <form method="post" action="{$saveUrl}">
    <input type="hidden" name="id" value="{$eId}">
    <div class="frm">
      <div class="fld"><label>代理名称（必填）</label><input type="text" name="name" value="{$eName}" maxlength="64" required></div>
      <div class="fld"><label>登录账号（必填，唯一）</label><input type="text" name="username" value="{$eUser}" maxlength="64" required></div>
      <div class="fld"><label>登录密码</label><input type="password" name="password" maxlength="32" autocomplete="new-password" placeholder="{$pwdHint}"></div>
      <div class="fld"><label>联系方式</label><input type="text" name="contact" value="{$eContact}" maxlength="128"></div>
      <div class="fld"><label>结算比例 %（预留，仅记录）</label><input type="number" step="0.01" min="0" max="100" name="rate" value="{$eRate}"></div>
      <div class="fld"><label>备注</label><input type="text" name="remark" value="{$eRemark}" maxlength="255"></div>
    </div>
    <div class="divider"></div>
    <div class="section-head"><h3>代理端权限</h3><span class="hint">勾选后代理登录 /agent 只能看到对应模块，未勾选的菜单和接口都会被拒绝</span></div>
    <div class="perm-grid">{$permBoxes}</div>
    <div class="divider"></div>
    <div class="two-col">
      <div class="field-block">
        <h3>专属推广域名</h3>
        <textarea name="domains" rows="5" placeholder="agent1.example.com" style="width:100%;padding:10px 12px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;color:#1f2430;font-family:inherit;outline:none;resize:vertical">{$eDomainsEsc}</textarea>
        <div class="hint">一行一个。域名需先完成 DNS 解析和 HTTPS 证书配置；域名直访会自动归属该代理。</div>
      </div>
      <div class="field-block">
        <h3>分配渠道</h3>
        <div class="perm-grid compact">{$pixelBoxes}</div>
        <div class="hint">代理只能看到渠道名称，看不到像素 ID 和 CAPI Token。</div>
      </div>
    </div>
    <div class="form-actions">
      <button class="btn" type="submit">保存</button>{$cancel}
      <label class="switch"><input type="checkbox" name="status" value="1"{$eStatus}> 启用</label>
    </div>
  </form>
</div>
<div class="card">
  <div class="card-head">
    <div><h2>代理列表</h2><div class="hint">共 {$agentCount} 个代理 · 注册与充值按代理归因累计</div></div>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>代理</th><th>账号</th><th>域名 / 渠道</th><th>权限</th>
        <th class="num">注册 / 充值</th><th>状态</th><th>最后登录</th><th>操作</th>
      </tr></thead>
      <tbody>{$rows}</tbody>
    </table>
  </div>
</div>
HTML;

        $this->renderPage($content, '代理管理', '创建代理账号、勾选代理端权限、分配专属域名与渠道。代理登录入口 <b>/agent</b>。', $headActions);
    }

    public function save(): void
    {
        try {
            if (!AgentService::ready()) {
                throw new \InvalidArgumentException('代理系统尚未初始化，请先执行 upgrade_agent.sql');
            }

            $id       = (int) $this->request->post('id', 0);
            $name     = mb_substr(trim((string) $this->request->post('name', '')), 0, 64);
            $username = mb_substr(trim((string) $this->request->post('username', '')), 0, 64);
            $password = (string) $this->request->post('password', '');
            $contact  = mb_substr(trim((string) $this->request->post('contact', '')), 0, 128);
            $rate     = max(0, min(100, (float) $this->request->post('rate', 0)));
            $remark   = mb_substr(trim((string) $this->request->post('remark', '')), 0, 255);
            $status   = (string) $this->request->post('status', '') === '1' ? 1 : 0;
            $perms    = AgentService::permsJson((array) $this->request->post('perms', []));
            $domainsRaw = (string) $this->request->post('domains', '');
            $pixels   = array_values(array_unique(array_map('intval', (array) $this->request->post('pixels', []))));

            if ($name === '') {
                throw new \InvalidArgumentException('代理名称必填');
            }
            if (!preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username)) {
                throw new \InvalidArgumentException('登录账号只能使用字母、数字、点、下划线、短横线，长度 3-64');
            }
            if ($id === 0 && mb_strlen($password) < 6) {
                throw new \InvalidArgumentException('新建代理必须设置至少 6 位的登录密码');
            }
            if ($password !== '' && (mb_strlen($password) < 6 || mb_strlen($password) > 32)) {
                throw new \InvalidArgumentException('密码长度需为 6-32 位');
            }

            $domains = [];
            foreach (preg_split('/[\s,;]+/', $domainsRaw) ?: [] as $raw) {
                $d = PromoService::normalizeDomain($raw);
                if ($d !== '' && !in_array($d, $domains, true)) {
                    $domains[] = $d;
                }
            }

            $db  = Db::connect('live_mysql');
            $now = date('Y-m-d H:i:s');

            $dup = $db->table('lp_agent')->where('username', $username)->where('id', '<>', $id)->find();
            if ($dup) {
                throw new \InvalidArgumentException('登录账号「' . $username . '」已被使用');
            }

            $db->startTrans();
            try {
                $data = [
                    'name' => $name, 'username' => $username, 'contact' => $contact,
                    'rate' => $rate, 'status' => $status, 'remark' => $remark,
                    'perms' => $perms, 'updated_at' => $now,
                ];
                if ($password !== '') {
                    $data['password_hash'] = hash_password($password);
                }

                $agentNo = '';
                if ($id > 0) {
                    $row = $db->table('lp_agent')->where('id', $id)->find();
                    if (!$row) {
                        throw new \InvalidArgumentException('代理不存在');
                    }
                    $agentNo = (string) $row['agent_no'];
                    $db->table('lp_agent')->where('id', $id)->update($data);
                } else {
                    $agentNo = AgentService::nextAgentNo();
                    $data['agent_no']    = $agentNo;
                    $data['created_at']  = $now;
                    $id = (int) $db->table('lp_agent')->insertGetId($data);
                }

                // 域名同步：不在本次列表里的删除，新域名查重后插入
                $existing = $db->table('lp_agent_domain')->where('agent_id', $id)->select()->toArray();
                $keep = [];
                $hasPrimary = $existing !== [];
                foreach ($domains as $d) {
                    $other = $db->table('lp_agent_domain')->where('domain', $d)->where('agent_id', '<>', $id)->find();
                    if ($other) {
                        throw new \InvalidArgumentException('域名「' . $d . '」已分配给其他代理');
                    }
                    $hit = null;
                    foreach ($existing as $e) {
                        if ((string) $e['domain'] === $d) {
                            $hit = $e;
                            break;
                        }
                    }
                    if ($hit) {
                        $keep[] = (int) $hit['id'];
                        $db->table('lp_agent_domain')->where('id', (int) $hit['id'])->update([
                            'status' => 1, 'updated_at' => $now,
                        ]);
                    } else {
                        $db->table('lp_agent_domain')->insert([
                            'agent_id' => $id, 'domain' => $d, 'is_primary' => $hasPrimary ? 0 : 1,
                            'status' => 1, 'created_at' => $now, 'updated_at' => $now,
                        ]);
                        $hasPrimary = true;
                    }
                }
                foreach ($existing as $e) {
                    if (!in_array((int) $e['id'], $keep, true)) {
                        $db->table('lp_agent_domain')->where('id', (int) $e['id'])->delete();
                    }
                }

                // 渠道同步：先释放不再勾选的，再占用新勾选的
                $db->table('lp_promo_pixel')->where('agent_id', $id)->whereNotIn('id', $pixels ?: [0])
                    ->update(['agent_id' => 0, 'updated_at' => $now]);
                foreach ($pixels as $pid) {
                    $p = $db->table('lp_promo_pixel')->where('id', $pid)->find();
                    if (!$p) {
                        continue;
                    }
                    if ((int) $p['agent_id'] > 0 && (int) $p['agent_id'] !== $id) {
                        throw new \InvalidArgumentException('渠道 #' . $pid . ' 已分配给其他代理');
                    }
                    $db->table('lp_promo_pixel')->where('id', $pid)->update([
                        'agent_id' => $id, 'updated_at' => $now,
                    ]);
                }

                if ($password !== '' || $status === 0) {
                    $this->clearAgentTokens($id);
                }

                $db->commit();
                $this->redirect('/admin/live.Agent/index?ok=' . urlencode('代理已保存') . $this->keyQuery('&'));
            } catch (\Throwable $e) {
                $db->rollback();
                throw $e;
            }
        } catch (\Throwable $e) {
            $this->renderPage(
                '<div class="card"><h2>保存失败</h2><div class="tip" style="color:#d61f4a">'
                . $this->e($e->getMessage()) . '</div><div class="actions">'
                . '<a class="btn-line" href="/admin/live.Agent/index' . $this->keyQuery() . '">返回</a></div></div>',
                '保存失败',
                '请修正后重试',
                '',
                422
            );
        }
    }

    public function toggle(): void
    {
        $id = (int) $this->request->param('id', 0);
        if ($id > 0 && AgentService::ready()) {
            $db  = Db::connect('live_mysql');
            $row = $db->table('lp_agent')->where('id', $id)->find();
            if ($row) {
                $status = (int) $row['status'] === 1 ? 0 : 1;
                $db->table('lp_agent')->where('id', $id)->update([
                    'status' => $status, 'updated_at' => date('Y-m-d H:i:s'),
                ]);
                if ($status === 0) {
                    $this->clearAgentTokens($id);
                }
            }
        }
        $this->redirect('/admin/live.Agent/index' . $this->keyQuery());
    }

    private function clearAgentTokens(int $agentId): void
    {
        try {
            \app\common\facade\Token::clear(\app\agent\middleware\Auth::TYPE, $agentId);
        } catch (\Throwable) {
            // 忽略
        }
    }

    private function renderPage(string $content, string $title = '代理管理', string $subtitle = '', string $actions = '', int $status = 200): void
    {
        http_response_code($status);
        $html = \app\live\service\AgentAdminView::page($title, $subtitle, $content, $actions);
        response($html)->send();
        exit;
    }

    private function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /** 保留 _key，便于首次访问的飘页鉴权不断 */
    private function keyQuery(string $prefix = '?'): string
    {
        $key = (string) $this->request->param('_key', '');
        return $key !== '' ? $prefix . '_key=' . urlencode($key) : '';
    }

    private function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
