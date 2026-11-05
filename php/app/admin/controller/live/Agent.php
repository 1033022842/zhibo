<?php
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use app\live\service\AgentService;
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
        // 代理账号进来：只看自己团队，且只能维护直属子代理
        $selfId  = $this->actingAgentId();
        $isAgent = $selfId > 0;
        $teamIds = $isAgent ? AgentService::teamIds($selfId) : [];
        $selfAgentRow = $isAgent ? ($this->actingAgent() ?: []) : [];

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
            'parent_id' => 0, 'admin_rules' => null,
        ];
        if ($editId > 0) {
            $row = $db->table('lp_agent')->where('id', $editId)->find();
            // 代理账号只能编辑自己的直属子代理，其余一律按新建处理
            if ($row && (!$isAgent || AgentService::isDirectChild($selfId, $editId))) {
                $edit = array_merge($edit, $row);
            } else {
                $editId = 0;
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
        if ($isAgent) {
            $agents = array_values(array_filter(
                $agents,
                static fn(array $a): bool => in_array((int) $a['id'], $teamIds, true)
            ));
        }
        // 后台登录账号（代理用它在后台登录页登录）
        $adminNames = AgentService::adminUsernames(array_column($agents, 'admin_id'));

        // 像素 / 域名归属（只读展示）
        $domainsByAgent = [];
        foreach ($db->table('lp_agent_domain')->order('is_primary', 'desc')->order('id', 'asc')->select()->toArray() as $d) {
            $domainsByAgent[(int) $d['agent_id']][] = $d;
        }
        $pixelsByAgent = [];
        foreach ($db->table('lp_promo_pixel')->order('sort', 'asc')->order('id', 'asc')->select()->toArray() as $p) {
            if ((int) $p['agent_id'] > 0) {
                $pixelsByAgent[(int) $p['agent_id']][] = $p;
            }
        }
        $agentNames = [];
        foreach ($agents as $a) {
            $agentNames[(int) $a['id']] = (string) $a['name'];
        }

        // 上级代理下拉（编辑时排除自己与自己的下级，避免成环）
        $excludeIds   = $editId > 0 ? array_merge([$editId], AgentService::descendantIds($editId)) : [];
        $parentSorted = $agents;
        usort($parentSorted, static fn(array $x, array $y): int => AgentService::depth((int) $x['id']) <=> AgentService::depth((int) $y['id']));
        $parentOptions = '<option value="0">无（总部直属）</option>';
        $parentId      = (int) ($edit['parent_id'] ?? 0);
        foreach ($parentSorted as $a) {
            $pid = (int) $a['id'];
            if (in_array($pid, $excludeIds, true)) {
                continue;
            }
            $depth = AgentService::depth($pid);
            $label = str_repeat('— ', max(0, $depth - 1)) . (string) $a['agent_no'] . ' · ' . (string) $a['name'];
            $parentOptions .= '<option value="' . $pid . '"' . ($pid === $parentId ? ' selected' : '') . '>'
                . $this->e($label) . '</option>';
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
            $parentOf   = (int) ($a['parent_id'] ?? 0);
            $parentText = $parentOf > 0
                ? '上级：' . ($agentNames[$parentOf] ?? ('代理#' . $parentOf))
                : '总部直属';

            $adminName = (string) ($adminNames[(int) ($a['admin_id'] ?? 0)] ?? '');
            $canEdit   = !$isAgent || AgentService::isDirectChild($selfId, $id);

            $rows .= '<tr>'
                . '<td><div class="agent-name">' . $this->e((string) $a['name']) . '</div>'
                . '<div class="agent-no">' . $this->e((string) $a['agent_no']) . ' · ' . $this->e($parentText) . '</div></td>'
                . '<td><div>' . $this->e((string) $a['username']) . '</div>'
                . '<div class="agent-no">后台：' . ($adminName !== '' ? $this->e($adminName) : '<span class="muted">保存后生成</span>') . '</div></td>'
                . '<td><div>' . $tagList($pixels, 'alt') . '</div><div style="margin-top:4px">' . $tagList($domains) . '</div></td>'
                . '<td><div class="mini-chips">' . $permChips . '</div></td>'
                . '<td class="num"><div>注册 ' . (int) $a['regs'] . '</div>'
                . '<div class="muted" style="font-size:11.5px;margin-top:2px">'
                . (int) $a['buyers'] . ' 人 / $' . number_format((float) $a['amount'], 2) . '</div></td>'
                . '<td>' . ($status ? '<span class="chip on">启用</span>' : '<span class="chip off">停用</span>') . '</td>'
                . '<td>' . $lastLogin . '</td>'
                . '<td class="ops">'
                . ($canEdit
                    ? '<a href="/admin/live.Agent/index?id=' . $id . $this->keyQuery() . '">编辑</a>'
                      . '<a href="/admin/live.Agent/toggle?id=' . $id . $this->keyQuery() . '">' . ($status ? '停用' : '启用') . '</a>'
                    : '<span class="muted">—</span>')
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

        // 只读展示：该代理当前自建的像素 / 独有域名（不再由总部分配）
        $ownPixelNames = array_map(
            static fn(array $p): string => (string) ($p['name'] !== '' ? $p['name'] : ('渠道#' . $p['id'])),
            $pixelsByAgent[$eId] ?? []
        );
        $ownDomainNames = array_map(static fn(array $d): string => (string) $d['domain'], $domainsByAgent[$eId] ?? []);

        $permBoxes = '';
        foreach (AgentService::PERMS as $key => $label) {
            $checked  = in_array($key, $ePerms, true) ? ' checked' : '';
            $disabled = in_array($key, AgentService::ALWAYS_PERMS, true) ? ' disabled' : '';
            $desc     = AgentService::PERM_DESCS[$key] ?? '';
            $permBoxes .= '<label class="perm"><input type="checkbox" name="perms[]" value="' . $key . '"' . $checked . $disabled . '>'
                . '<span><b>' . $this->e($label) . '</b><small>' . $this->e($desc) . '</small></span></label>';
        }

        // 代理可用的总后台菜单（菜单级权限，只读）
        $adminReady  = AgentService::hasColumn('lp_agent', 'admin_id');
        $eAdminMenus = $adminReady
            ? ($eId > 0 ? AgentService::adminMenuNames($edit['admin_rules'] ?? null) : array_keys(AgentService::ADMIN_MENUS))
            : [];
        $adminMenuBoxes = '';
        foreach (AgentService::ADMIN_MENUS as $menuName => $menuLabel) {
            $checked = in_array($menuName, $eAdminMenus, true) ? ' checked' : '';
            $adminMenuBoxes .= '<label class="perm"><input type="checkbox" name="admin_menus[]" value="' . $menuName . '"' . $checked . '>'
                . '<span><b>' . $this->e($menuLabel) . '</b><small>' . $this->e($menuName) . '</small></span></label>';
        }
        if (!$adminReady) {
            $adminMenuBoxes = '<div class="muted" style="font-size:12.5px">请先执行 <code>php/sql/upgrade_agent.sql</code> 升级脚本。</div>';
        }
        if ($isAgent) {
            // 子代理沿用上级已开放的后台菜单，不在本表单单独设置
            $adminMenuBoxes = '<div class="muted" style="font-size:12.5px">子代理自动继承上级已开放的后台菜单（只读），无需单独勾选。</div>';
        }

        $ok = (string) $this->request->param('ok', '');
        $alert = $ok !== '' ? '<div class="alert ok">' . $this->e($ok) . '</div>' : '';
        $formTitle = $eId > 0 ? '编辑代理 · ' . $eName : '新建代理';
        $cancel = $eId > 0 ? '<a class="btn-line" href="/admin/live.Agent/index' . $this->keyQuery() . '">取消编辑</a>' : '';
        $pwdHint = $eId > 0 ? '留空表示不修改密码' : '新建代理必填，至少 6 位';
        $saveUrl     = '/admin/live.Agent/save' . $this->keyQuery();
        $newUrl      = '/admin/live.Agent/index' . $this->keyQuery();
        $formHint    = $eId > 0
            ? '修改后立即生效；改密码或停用会强制该代理重新登录。'
            : ($isAgent
                ? '创建的是你自己的子代理，共用平台统一域名，数据按各自的 ?px= 推广链接区分。'
                : '代理用下面的「后台账号」在主站后台登录页登录，只能看到勾选的菜单与自己团队的数据。');
        $headActions = '<a class="btn-line" href="' . $newUrl . '">新建代理</a>';

        $parentField = $isAgent
            ? '<input type="hidden" name="parent_id" value="' . $selfId . '">'
                . '<input type="text" value="' . $this->e((string) ($selfAgentRow['name'] ?? '')) . '（当前代理）" disabled>'
            : '<select name="parent_id">' . $parentOptions . '</select>';
        $resourceSection = $isAgent
            ? '<div class="divider"></div><div class="hint">子代理不用单独分配域名和像素：共用平台统一域名，代理自己在代理后台「推广与像素」创建像素、复制带 <b>?px=</b> 的链接，数据按像素区分。</div>'
            : '<div class="divider"></div><div class="two-col">'
                . '<div class="field-block"><h3>像素渠道（只读）</h3><div>' . $tagList($ownPixelNames, 'alt') . '</div>'
                . '<div class="hint">像素由代理在代理后台「推广与像素」里自行创建；总部不再分配像素，避免把平台流量算到代理名下。</div></div>'
                . '<div class="field-block"><h3>专属域名（只读）</h3><div>' . $tagList($ownDomainNames) . '</div>'
                . '<div class="hint">默认共用平台统一域名，归因靠代理自己的 <b>?px=</b> 链接；只有单独给该代理配了域名时，域名直访才会归到他。</div></div>'
                . '</div>';

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
      <div class="fld"><label>上级代理（决定层级与编号）</label>{$parentField}</div>
      <div class="fld"><label>代理名称（必填）</label><input type="text" name="name" value="{$eName}" maxlength="64" required></div>
      <div class="fld"><label>登录账号（必填，唯一）</label><input type="text" name="username" value="{$eUser}" maxlength="64" required></div>
      <div class="fld"><label>登录密码</label><input type="password" name="password" maxlength="32" autocomplete="new-password" placeholder="{$pwdHint}"></div>
      <div class="fld"><label>联系方式</label><input type="text" name="contact" value="{$eContact}" maxlength="128"></div>
      <div class="fld"><label>结算比例 %（预留，仅记录）</label><input type="number" step="0.01" min="0" max="100" name="rate" value="{$eRate}"></div>
      <div class="fld"><label>备注</label><input type="text" name="remark" value="{$eRemark}" maxlength="255"></div>
    </div>
    <div class="divider"></div>
    <div class="section-head"><h3>代理端权限</h3><span class="hint">勾选后代理登录后台只能看到对应模块，未勾选的菜单和接口都会被拒绝</span></div>
    <div class="perm-grid">{$permBoxes}</div>
    <div class="divider"></div>
    <div class="section-head"><h3>后台菜单权限</h3><span class="hint">勾选后代理在后台登录页登录即可看到对应菜单，只读且只能看自己团队的数据</span></div>
    <div class="perm-grid">{$adminMenuBoxes}</div>
    {$resourceSection}
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
        <th>代理</th><th>账号</th><th>像素 / 域名</th><th>权限</th>
        <th class="num">注册 / 充值</th><th>状态</th><th>最后登录</th><th>操作</th>
      </tr></thead>
      <tbody>{$rows}</tbody>
    </table>
  </div>
</div>
HTML;

        $this->renderPage(
            $content,
            '代理管理',
            $isAgent
                ? '只显示你与你的下级代理；你可以新建/编辑自己的直属子代理。'
                : '创建代理账号、分配后台菜单权限。代理用「后台账号」在主站后台登录页登录，只读且只看自己团队的数据。',
            $headActions
        );
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
            $parentId = AgentService::hasColumn('lp_agent', 'parent_id')
                ? max(0, (int) $this->request->post('parent_id', 0))
                : 0;
            $perms    = AgentService::permsJson((array) $this->request->post('perms', []));

            // 代理账号维护子代理：只能建在自己下面、只能编辑直属子代理、
            // 只能授予自己有的代理端权限
            $selfAgent = $this->actingAgent();
            if ($selfAgent) {
                $selfAgentId = (int) $selfAgent['id'];
                if ($id > 0 && !AgentService::isDirectChild($selfAgentId, $id)) {
                    throw new \InvalidArgumentException('只能管理自己的直属子代理');
                }
                $parentId = $selfAgentId;
                $perms    = AgentService::permsJson(AgentService::restrictPerms(
                    (array) $this->request->post('perms', []),
                    AgentService::grantablePerms($selfAgent)
                ));
            }

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

            $db  = Db::connect('live_mysql');
            $now = date('Y-m-d H:i:s');

            $dup = $db->table('lp_agent')->where('username', $username)->where('id', '<>', $id)->find();
            if ($dup) {
                throw new \InvalidArgumentException('登录账号「' . $username . '」已被使用');
            }

            if ($parentId > 0) {
                if (!$db->table('lp_agent')->where('id', $parentId)->find()) {
                    throw new \InvalidArgumentException('上级代理不存在');
                }
                if ($id > 0 && !AgentService::canSetParent($id, $parentId)) {
                    throw new \InvalidArgumentException('不能把代理挂到自己或自己的下级下面');
                }
            }

            $db->startTrans();
            try {
                $data = [
                    'name' => $name, 'username' => $username, 'contact' => $contact,
                    'rate' => $rate, 'status' => $status, 'remark' => $remark,
                    'perms' => $perms, 'updated_at' => $now,
                ];
                if (AgentService::hasColumn('lp_agent', 'parent_id')) {
                    $data['parent_id'] = $parentId;
                }
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
                    $agentNo = $parentId > 0
                        ? AgentService::nextSubAgentNo($parentId)
                        : AgentService::nextAgentNo();
                    $data['agent_no']    = $agentNo;
                    $data['created_at']  = $now;
                    $id = (int) $db->table('lp_agent')->insertGetId($data);
                }

                // 域名与像素不再由总部在代理管理里分配：
                // 代理共用平台统一域名，像素由代理在代理后台「推广与像素」自行创建，数据按 ?px= 归因

                // 同步总后台账号与菜单权限（复用 BuildAdmin 界面）
                if (AgentService::hasColumn('lp_agent', 'admin_id')) {
                    $menus = $selfAgent
                        ? AgentService::adminMenuNames($selfAgent['admin_rules'] ?? null)
                        : array_values(array_intersect(
                            (array) $this->request->post('admin_menus', []),
                            array_keys(AgentService::ADMIN_MENUS)
                        ));
                    $fresh = $db->table('lp_agent')->where('id', $id)->find();
                    $sync  = AgentService::syncAdminAccount($fresh ?: [], $menus);
                    if (empty($sync['ok'])) {
                        throw new \InvalidArgumentException($sync['msg'] ?? '同步后台账号失败');
                    }
                    // 直属子代理沿用同一套后台菜单，跟着上级一起刷新
                    foreach ($db->table('lp_agent')->where('parent_id', $id)->select()->toArray() as $child) {
                        AgentService::syncAdminAccount($child, $menus);
                    }
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
        // 代理账号只能启停自己的直属子代理
        $acting = $this->actingAgent();
        if ($acting && ($id <= 0 || !AgentService::isDirectChild((int) $acting['id'], $id))) {
            $this->renderPage(
                '<div class="card"><h2>没有权限</h2><div class="tip">只能启停自己的直属子代理。</div>'
                . '<div class="actions"><a class="btn-line" href="/admin/live.Agent/index' . $this->keyQuery() . '">返回</a></div></div>',
                '没有权限',
                '',
                '',
                403
            );
            return;
        }
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
                if (AgentService::hasColumn('lp_agent', 'admin_id')) {
                    $fresh = $db->table('lp_agent')->where('id', $id)->find();
                    if ($fresh) {
                        AgentService::syncAdminAccount($fresh, AgentService::adminMenuNames($fresh['admin_rules'] ?? null));
                    }
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

    /**
     * 当前操作人若是代理，返回其 lp_agent 行
     * 后台接口请求带 batoken；iframe 直连页（本控制器）没有 Token，用签名 Cookie 识别
     */
    private function actingAgent(): ?array
    {
        if ($this->agentAccount) {
            return $this->agentAccount;
        }
        $id = AgentService::scopeAgentId();
        if ($id <= 0) {
            return null;
        }
        try {
            return Db::connect('live_mysql')->table('lp_agent')->where('id', $id)->find() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function actingAgentId(): int
    {
        $agent = $this->actingAgent();
        return $agent ? (int) $agent['id'] : 0;
    }

    private function renderPage(string $content, string $title = '代理管理', string $subtitle = '', string $actions = '', int $status = 200): void
    {
        $html     = \app\live\service\AgentAdminView::page($title, $subtitle, $content, $actions);
        $response = response($html);
        if ($status !== 200) {
            $response->code($status);
        }
        $response->send();
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
