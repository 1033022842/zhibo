<?php
declare(strict_types=1);

namespace app\agent\controller;

use app\agent\middleware\Auth as AuthMiddleware;
use app\agent\service\AgentView;
use app\common\facade\Token;
use app\live\service\AgentService;
use think\facade\Db;

/**
 * 子代理管理（代理端）
 *
 * 每个代理只能管理自己的「直属下级」；下级拥有的权限必须是上级权限的子集，
 * 因此可以一级级往下开，但不会出现下级权限超过上级的情况。
 */
final class Subagent extends Base
{
    public function index(): void
    {
        $this->requirePerm('sub_agent');

        if (!AgentService::ready()) {
            $this->render('sub_agent', '子代理', '<div class="card"><h2>子代理</h2><div class="tip">代理系统尚未初始化，请联系总部管理员执行升级脚本。</div></div>');
        }

        $parentId  = $this->agentId();
        $children  = AgentService::children($parentId);
        $grantable = AgentService::grantablePerms($this->agent());

        $editId = (int) ($this->request->param('id') ?? 0);
        $edit   = null;
        if ($editId > 0) {
            foreach ($children as $child) {
                if ((int) $child['id'] === $editId) {
                    $edit = $child;
                    break;
                }
            }
            if ($edit === null) {
                $this->deny('只能管理自己的直属下级代理。', 403);
            }
        }

        $sumRegs = $sumBuyers = $sumSubs = 0;
        $sumAmount = $sumSubAmt = 0.0;
        $teamTotal = count(AgentService::teamIds($parentId));

        $rows = '';
        foreach ($children as $child) {
            $id       = (int) $child['id'];
            $teamIds  = AgentService::teamIds($id);
            $stats    = AgentService::stats($teamIds);
            $teamSize = count($teamIds) - 1;
            $status   = (int) $child['status'] === 1;
            $depth    = AgentService::depth($id);
            $initial  = mb_substr((string) $child['name'], 0, 1);

            $sumRegs   += (int) $stats['regs'];
            $sumBuyers += (int) $stats['buyers'];
            $sumSubs   += (int) $stats['subs'];
            $sumAmount += (float) $stats['amount'];
            $sumSubAmt += (float) $stats['sub_amount'];

            $perms    = AgentService::parsePerms($child['perms'] ?? null);
            $permFull = implode('、', array_map(
                static fn(string $k): string => AgentService::PERMS[$k] ?? $k,
                $perms
            ));
            $chips = '';
            foreach (array_slice($perms, 0, 3) as $key) {
                $chips .= '<span class="mini-chip" title="' . AgentView::h(AgentService::PERMS[$key] ?? $key) . '">'
                    . AgentView::h(AgentService::PERM_SHORT[$key] ?? $key) . '</span>';
            }
            if (count($perms) > 3) {
                $chips .= '<span class="mini-chip more" title="' . AgentView::h($permFull) . '">+' . (count($perms) - 3) . '</span>';
            }
            if ($chips === '') {
                $chips = '<span class="muted">—</span>';
            }

            $rows .= '<tr>'
                . '<td><div class="who-cell"><div class="avatar-sm">' . AgentView::h($initial) . '</div><div>'
                . '<div class="nm">' . AgentView::h((string) $child['name']) . '</div>'
                . '<div class="sub">' . AgentView::h((string) $child['agent_no'])
                . '<span class="lv">L' . $depth . '</span>'
                . ($teamSize > 0 ? '<span class="sep">·</span>含 ' . $teamSize . ' 个下级' : '') . '</div>'
                . '</div></div></td>'
                . '<td>' . AgentView::h((string) $child['username']) . '</td>'
                . '<td class="num">' . number_format((int) $stats['regs']) . '</td>'
                . '<td class="num"><b>$' . number_format((float) $stats['amount'], 2) . '</b>'
                . '<div class="muted" style="font-size:11.5px;margin-top:2px">' . (int) $stats['buyers'] . ' 人</div></td>'
                . '<td class="num"><b>$' . number_format((float) $stats['sub_amount'], 2) . '</b>'
                . '<div class="muted" style="font-size:11.5px;margin-top:2px">' . (int) $stats['subs'] . ' 人</div></td>'
                . '<td><div class="chips">' . $chips . '</div></td>'
                . '<td>' . ($status ? '<span class="chip on">启用</span>' : '<span class="chip off">停用</span>') . '</td>'
                . '<td class="ops">'
                . '<a href="/agent/subagent/index?id=' . $id . '#sub-form">编辑</a>'
                . '<a href="/agent/subagent/toggle?id=' . $id . '&_csrf=' . urlencode($this->csrf()) . '"'
                . ' onclick="return confirm(\'' . ($status ? '确认停用该子代理？停用后立即下线。' : '确认启用该子代理？') . '\')">'
                . ($status ? '停用' : '启用') . '</a>'
                . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="8"><div class="blank">'
                . '<div class="blank-ico"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg></div>'
                . '<div class="blank-t">还没有子代理</div>'
                . '<div class="blank-d">在下方创建第一个，子代理用同一入口登录，数据单独归因。</div>'
                . '<a class="btn-line" href="#sub-form">立即创建</a>'
                . '</div></td></tr>';
        }

        // 表单
        $eId      = $edit ? (int) $edit['id'] : 0;
        $eName    = $edit ? AgentView::h((string) $edit['name']) : '';
        $eUser    = $edit ? AgentView::h((string) $edit['username']) : '';
        $eContact = $edit ? AgentView::h((string) ($edit['contact'] ?? '')) : '';
        $eRemark  = $edit ? AgentView::h((string) ($edit['remark'] ?? '')) : '';
        $eRate    = $edit ? number_format((float) ($edit['rate'] ?? 0), 2, '.', '') : '0.00';
        $eStatus  = !$edit || (int) $edit['status'] === 1 ? ' checked' : '';
        $ePerms   = $edit ? AgentService::parsePerms($edit['perms'] ?? null) : ['overview', 'link', 'link_create', 'member', 'account'];

        $permBoxes = '';
        foreach ($grantable as $key) {
            $label = AgentView::h(AgentService::PERMS[$key] ?? $key);
            $desc  = AgentView::h(AgentService::PERM_DESCS[$key] ?? '');
            if (in_array($key, AgentService::ALWAYS_PERMS, true)) {
                $permBoxes .= '<label class="perm is-fixed"><input type="checkbox" checked disabled>'
                    . '<span><b>' . $label . '</b><small>始终可用，不可关闭</small></span></label>';
                continue;
            }
            $checked = in_array($key, $ePerms, true) ? ' checked' : '';
            $permBoxes .= '<label class="perm"><input type="checkbox" name="perms[]" value="' . $key . '"' . $checked . '>'
                . '<span><b>' . $label . '</b><small>' . $desc . '</small></span></label>';
        }

        // 这里展示的是「该子代理将继承到的资源」：只读，且只能用于其推广链接，不能在创建时单独分配
        $domains = AgentService::effectiveDomains($this->agentId(), true);
        $pixels  = AgentService::effectivePixels($this->agentId(), true);

        $domTags = '';
        foreach ($domains as $d) {
            $label = (string) $d['domain'] . (!empty($d['inherited']) ? '（继承自上级）' : '（本人）');
            $domTags .= '<span class="tag">' . AgentView::h($label) . '</span>';
        }
        if ($domTags === '') {
            $domTags = '<span class="muted">当前没有可用的推广域名；请先在「专属域名」添加，子代理才能生成推广链接。</span>';
        } else {
            $domTags .= '<span class="note">只读：子代理只能在推广链接里选用这些域名；访客直接访问域名时，业绩归域名所属代理，不归子代理。</span>';
        }
        $pxTags = '';
        foreach ($pixels as $p) {
            $label = (string) ($p['name'] !== '' ? $p['name'] : ('渠道#' . $p['id']));
            $pxTags .= '<span class="tag alt">' . AgentView::h($label) . '</span>';
        }
        if ($pxTags === '') {
            $pxTags = '<span class="muted">当前没有可用的像素渠道；子代理仍可用推广链接做归因，只是无法绑定像素。</span>';
        } else {
            $pxTags .= '<span class="note">只读：子代理只能在推广链接里绑定这些渠道，不能查看或修改像素配置。</span>';
        }

        $formTitle = $eId > 0 ? '编辑子代理 · ' . $eName : '新建子代理';
        $formHint  = $eId > 0
            ? '修改后立即生效；改密码或停用会强制该子代理重新登录。'
            : '子代理账号独立于总部后台，只能登录代理中心。';
        $cancel    = $eId > 0 ? '<a class="btn-line" href="/agent/subagent/index">取消编辑</a>' : '';
        $pwdHint   = $eId > 0 ? '留空表示不修改密码' : '至少 6 位';
        $csrf      = AgentView::h($this->csrf());
        $okText    = (string) ($this->request->param('ok') ?? '');
        $alert     = $okText !== '' ? '<div class="alert ok">' . AgentView::h($okText) . '</div>' : '';

        $childCount = number_format(count($children));
        $regsFmt    = number_format($sumRegs);
        $amountFmt  = number_format($sumAmount, 2);
        $subAmtFmt  = number_format($sumSubAmt, 2);

        $content = <<<HTML
{$alert}
<div class="head">
  <div>
    <h1>子代理</h1>
    <div class="sub">创建并管理你的直属下级。子代理用同一入口登录，只能看到你勾选的模块，注册与付费单独归因。</div>
  </div>
  <a class="btn" href="#sub-form">新建子代理</a>
</div>
<div class="stats">
  <div class="stat"><div class="ico indigo"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg></div><div><div class="k">直属子代理</div><div class="v">{$childCount}</div><div class="d">团队共 {$teamTotal} 个代理</div></div></div>
  <div class="stat"><div class="ico violet"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div><div><div class="k">团队注册</div><div class="v">{$regsFmt}</div><div class="d">下级累计</div></div></div>
  <div class="stat"><div class="ico emerald"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></div><div><div class="k">团队充值</div><div class="v">{$amountFmt}</div><div class="d">USD · 付费 {$sumBuyers} 人</div></div></div>
  <div class="stat"><div class="ico amber"><svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg></div><div><div class="k">团队订阅</div><div class="v">{$subAmtFmt}</div><div class="d">USD · 订阅 {$sumSubs} 人</div></div></div>
</div>
<div class="card">
  <div class="card-head">
    <div><h2>子代理列表</h2><div class="hint">注册、充值、订阅均为「含下级」的团队合计</div></div>
    <span class="muted" style="font-size:12px">共 {$childCount} 个</span>
  </div>
  <div class="table-wrap"><table class="tbl-subs"><thead><tr>
    <th>子代理</th><th>账号</th><th class="num">注册</th>
    <th class="num">充值</th><th class="num">订阅</th><th>权限</th><th>状态</th><th>操作</th>
  </tr></thead><tbody>{$rows}</tbody></table></div>
</div>
<div class="card" id="sub-form">
  <div class="card-head">
    <div><h2>{$formTitle}</h2><div class="hint">{$formHint}</div></div>
    {$cancel}
  </div>
  <form method="post" action="/agent/subagent/save">
    <input type="hidden" name="_csrf" value="{$csrf}">
    <input type="hidden" name="id" value="{$eId}">
    <div class="frm">
      <div class="fld"><label>代理名称（必填）</label><input type="text" name="name" value="{$eName}" maxlength="64" placeholder="如 Boby" required></div>
      <div class="fld"><label>登录账号（必填，唯一）</label><input type="text" name="username" value="{$eUser}" maxlength="64" placeholder="字母/数字/._-" required></div>
      <div class="fld"><label>登录密码</label><input type="password" name="password" maxlength="32" autocomplete="new-password" placeholder="{$pwdHint}"></div>
      <div class="fld"><label>联系方式</label><input type="text" name="contact" value="{$eContact}" maxlength="128" placeholder="Telegram / 邮箱"></div>
      <div class="fld"><label>结算比例 %（预留）</label><input type="number" step="0.01" min="0" max="100" name="rate" value="{$eRate}"></div>
      <div class="fld"><label>备注</label><input type="text" name="remark" value="{$eRemark}" maxlength="255" placeholder="如 投东南亚"></div>
    </div>
    <div class="divider"></div>
    <div class="section">
      <div class="section-head"><h3>可授予的权限</h3><span class="hint">只能勾选你自己拥有的权限，不能越权下放</span></div>
      <div class="perm-grid">{$permBoxes}</div>
    </div>
    <div class="divider"></div>
    <div class="section">
      <div class="section-head"><h3>该子代理自动继承的推广资源</h3><span class="hint">只读展示，不能在创建时分配</span></div>
      <div class="res-row"><span class="k">推广域名</span><span class="v">{$domTags}</span></div>
      <div class="res-row"><span class="k">像素渠道</span><span class="v">{$pxTags}</span></div>
      <div class="tip" style="margin-top:8px">子代理不单独维护域名和像素，统一继承当前账号（含上级）的资源；一个域名只能归属一个代理，因此不提供单独分配。</div>
    </div>
    <div class="form-actions">
      <button class="btn" type="submit">保存</button>{$cancel}
      <label class="switch"><input type="checkbox" name="status" value="1"{$eStatus}> 启用该子代理</label>
    </div>
  </form>
  <div class="tip">子代理编号自动生成（如 A0001-01），其推广链接名称会带上该编号，保证全局唯一。</div>
</div>
HTML;

        $this->render('sub_agent', '子代理', $content);
    }

    public function save(): void
    {
        $this->requirePerm('sub_agent');
        $this->verifyCsrf();

        $parentId = $this->agentId();
        $agent    = $this->agent();
        $db       = Db::connect('live_mysql');

        $id       = (int) ($this->request->post('id') ?? 0);
        $name     = mb_substr(trim((string) ($this->request->post('name') ?? '')), 0, 64);
        $username = mb_substr(trim((string) ($this->request->post('username') ?? '')), 0, 64);
        $password = (string) ($this->request->post('password') ?? '');
        $contact  = mb_substr(trim((string) ($this->request->post('contact') ?? '')), 0, 128);
        $rate     = max(0, min(100, (float) ($this->request->post('rate') ?? 0)));
        $remark   = mb_substr(trim((string) ($this->request->post('remark') ?? '')), 0, 255);
        $status   = (string) ($this->request->post('status') ?? '') === '1' ? 1 : 0;
        $perms    = AgentService::restrictPerms(
            (array) ($this->request->post('perms') ?? []),
            AgentService::grantablePerms($agent)
        );

        if ($name === '') {
            $this->fail('代理名称必填');
        }
        if (!preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username)) {
            $this->fail('登录账号只能使用字母、数字、点、下划线、短横线，长度 3-64');
        }
        if ($id === 0 && mb_strlen($password) < 6) {
            $this->fail('新建子代理必须设置至少 6 位的登录密码');
        }
        if ($password !== '' && (mb_strlen($password) < 6 || mb_strlen($password) > 32)) {
            $this->fail('密码长度需为 6-32 位');
        }

        if ($id > 0 && !AgentService::isDirectChild($parentId, $id)) {
            $this->deny('只能管理自己的直属下级代理。', 403);
        }

        $dup = $db->table('lp_agent')->where('username', $username)->where('id', '<>', $id)->find();
        if ($dup) {
            $this->fail('登录账号「' . $username . '」已被使用');
        }

        $now = date('Y-m-d H:i:s');
        $db->startTrans();
        try {
            $data = [
                'name' => $name, 'username' => $username, 'contact' => $contact,
                'rate' => $rate, 'status' => $status, 'remark' => $remark,
                'perms' => AgentService::permsJson($perms), 'updated_at' => $now,
            ];
            if ($password !== '') {
                $data['password_hash'] = hash_password($password);
            }

            if ($id > 0) {
                $db->table('lp_agent')->where('id', $id)->update($data);
            } else {
                $data['agent_no']   = AgentService::nextSubAgentNo($parentId);
                $data['parent_id']  = $parentId;
                $data['created_at'] = $now;
                $id = (int) $db->table('lp_agent')->insertGetId($data);
            }

            if ($password !== '' || $status === 0) {
                $this->clearTokens($id);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            $this->fail('保存失败：' . $e->getMessage());
        }

        // 子代理的「总后台」沿用上级已开放的后台菜单（只读）
        try {
            $row = $db->table('lp_agent')->where('id', $id)->find();
            if ($row) {
                AgentService::syncAdminAccount($row, AgentService::adminMenuNames($agent['admin_rules'] ?? null));
            }
        } catch (\Throwable) {
            // 后台账号同步失败不影响子代理保存
        }

        $this->redirect('/agent/subagent/index?ok=' . urlencode('子代理已保存'));
    }

    public function toggle(): void
    {
        $this->requirePerm('sub_agent');
        $this->verifyCsrfParam();

        $id = (int) ($this->request->param('id') ?? 0);
        if ($id > 0 && AgentService::isDirectChild($this->agentId(), $id)) {
            $db  = Db::connect('live_mysql');
            $row = $db->table('lp_agent')->where('id', $id)->find();
            if ($row) {
                $status = (int) $row['status'] === 1 ? 0 : 1;
                $db->table('lp_agent')->where('id', $id)->update([
                    'status' => $status, 'updated_at' => date('Y-m-d H:i:s'),
                ]);
                if ($status === 0) {
                    $this->clearTokens($id);
                }
                // 同步总后台账号状态（停用时后台也登不进去）
                try {
                    $row['status'] = $status;
                    AgentService::syncAdminAccount($row, AgentService::adminMenuNames($row['admin_rules'] ?? null));
                } catch (\Throwable) {
                    // 忽略
                }
            }
        }
        $this->redirect('/agent/subagent/index');
    }

    private function clearTokens(int $agentId): void
    {
        try {
            Token::clear(AuthMiddleware::TYPE, $agentId);
        } catch (\Throwable) {
            // 忽略
        }
    }

    /** 链接类操作走 GET，CSRF 放在 query 里 */
    private function verifyCsrfParam(): void
    {
        $given = (string) ($this->request->param('_csrf') ?? '');
        if ($given === '' || !hash_equals($this->csrf(), $given)) {
            $this->deny('请求已失效，请刷新页面后重试。', 419);
        }
    }

    private function fail(string $message): void
    {
        $content = '<div class="card"><h2>操作失败</h2><div class="tip" style="font-size:13px;color:#d61f4a">'
            . AgentView::h($message) . '</div><div class="actions">'
            . '<a class="btn-line" href="/agent/subagent/index">返回子代理</a></div></div>';
        $this->render('sub_agent', '操作失败', $content, 422);
    }
}
