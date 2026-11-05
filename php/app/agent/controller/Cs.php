<?php
declare(strict_types=1);

namespace app\agent\controller;

use app\agent\service\AgentView;
use app\live\service\AgentService;
use app\live\service\PromoService;
use think\facade\Db;

/**
 * 代理独立客服：配置自己专属域名下的 Contact Us 渠道
 * 每个代理只维护自己的渠道（lp_cs_channel.agent_id = 自己），与总部客服互不影响
 */
final class Cs extends Base
{
    private const MAX_CHANNELS = 20;

    public function index(): void
    {
        $this->requirePerm('cs');

        $db      = Db::connect('live_mysql');
        $agentId = $this->agentId();
        $editId  = (int) ($this->request->param('id') ?? 0);

        $edit = ['id' => 0, 'type' => 'telegram', 'name' => '', 'url' => '', 'icon' => '', 'sort' => 0, 'status' => 1];
        if ($editId > 0) {
            $row = $db->table('lp_cs_channel')->where('id', $editId)->where('agent_id', $agentId)->find();
            if ($row) {
                $edit = $row;
            } else {
                $editId = 0;
            }
        }

        $list = $db->table('lp_cs_channel')->where('agent_id', $agentId)
            ->order('sort', 'asc')->order('id', 'asc')->select()->toArray();

        $rows = '';
        foreach ($list as $c) {
            $st   = (int) $c['status'] === 1
                ? '<span class="chip on">启用</span>'
                : '<span class="chip off">停用</span>';
            $type = (string) (PromoService::CHANNEL_TYPES[(string) $c['type']] ?? $c['type']);
            $rows .= '<tr>'
                . '<td>' . AgentView::h($type) . '</td>'
                . '<td>' . AgentView::h((string) $c['name']) . '</td>'
                . '<td><span class="mono">' . AgentView::h((string) $c['url']) . '</span></td>'
                . '<td class="num">' . (int) $c['clicks'] . '</td>'
                . '<td>' . $st . '</td>'
                . '<td class="ops">'
                . '<a href="/agent/cs/index?id=' . (int) $c['id'] . '">编辑</a>'
                . '<a href="/agent/cs/toggle?id=' . (int) $c['id'] . '&_csrf=' . urlencode($this->csrf()) . '">'
                . ((int) $c['status'] === 1 ? '停用' : '启用') . '</a>'
                . '<a class="danger" href="/agent/cs/del?id=' . (int) $c['id'] . '&_csrf=' . urlencode($this->csrf())
                . '" onclick="return confirm(\'确认删除这个客服渠道？\')">删除</a>'
                . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="empty">还没有配置客服渠道，使用下方表单添加一个</td></tr>';
        }

        $domains = AgentService::effectiveDomains($agentId, true);
        $domainText = $domains === []
            ? '你还没有专属推广域名，配置的渠道会在你获得专属域名后生效。'
            : '生效域名：' . implode('、', array_map(static fn(array $d): string => (string) $d['domain'], $domains));

        $typeOpts = '';
        foreach (PromoService::CHANNEL_TYPES as $key => $label) {
            $typeOpts .= '<option value="' . AgentView::h($key) . '"'
                . ((string) $edit['type'] === $key ? ' selected' : '') . '>' . AgentView::h($label) . '</option>';
        }

        $ok  = trim((string) ($this->request->param('ok') ?? ''));
        $err = trim((string) ($this->request->param('err') ?? ''));
        $alert = $ok !== '' ? '<div class="alert ok">' . AgentView::h($ok) . '</div>' : '';
        $alert .= $err !== '' ? '<div class="alert err">' . AgentView::h($err) . '</div>' : '';

        $eid    = (int) $edit['id'];
        $eName  = AgentView::h((string) $edit['name']);
        $eUrl   = AgentView::h((string) $edit['url']);
        $eIcon  = AgentView::h((string) ($edit['icon'] ?? ''));
        $eSort  = (int) $edit['sort'];
        $eOn    = (int) $edit['status'] === 1 ? ' checked' : '';
        $csrf   = AgentView::h($this->csrf());
        $formTitle = $eid > 0 ? '编辑客服渠道' : '新增客服渠道';
        $cancel = $eid > 0 ? '<a class="btn-line" href="/agent/cs/index">取消编辑</a>' : '';
        $canAdd = count($list) < self::MAX_CHANNELS || $eid > 0;
        $full   = $canAdd ? '' : '<div class="alert err">最多配置 ' . self::MAX_CHANNELS . ' 个渠道，请先删除不用的。</div>';

        $content = <<<HTML
{$alert}
<div class="head">
  <div>
    <h1>独立客服</h1>
    <div class="sub">这里的渠道只作用于你自己（含下级继承）的专属域名；总部客服配置不受影响。</div>
  </div>
</div>
<div class="card">
  <h2>我的客服渠道</h2>
  <div class="table-wrap"><table><thead><tr>
    <th>类型</th><th>展示名称</th><th>链接 / 地址</th><th class="num">点击</th><th>状态</th><th>操作</th>
  </tr></thead><tbody>{$rows}</tbody></table></div>
  <div class="tip">{$domainText}</div>
</div>
{$full}
<div class="card">
  <h2>{$formTitle}</h2>
  <form class="frm" method="post" action="/agent/cs/save">
    <input type="hidden" name="_csrf" value="{$csrf}">
    <input type="hidden" name="id" value="{$eid}">
    <div class="fld"><label>渠道类型</label><select name="type">{$typeOpts}</select></div>
    <div class="fld"><label>展示名称</label><input type="text" name="name" value="{$eName}" maxlength="50" placeholder="如 Telegram 客服"></div>
    <div class="fld"><label>链接 / 邮箱 / 嵌入地址</label><input type="text" name="url" value="{$eUrl}" maxlength="500" placeholder="https://t.me/xxx 或 support@example.com"></div>
    <div class="fld"><label>图标 URL（可留空）</label><input type="text" name="icon" value="{$eIcon}" maxlength="500" placeholder="https://…/icon.svg"></div>
    <div class="fld"><label>排序（越小越靠前）</label><input type="number" name="sort" value="{$eSort}" min="0" max="999"></div>
    <div class="fld"><label>状态</label>
      <label class="switch" style="height:38px"><input type="checkbox" name="status" value="1"{$eOn}> 启用</label>
    </div>
    <div class="actions">
      <button class="btn" type="submit"{$this->disabledAttr($canAdd)}>保存</button>
      {$cancel}
    </div>
  </form>
  <div class="tip">会员在你专属域名下点开 Contact Us 时，会看到这里的渠道；没有配置时自动回落到总部客服。</div>
</div>
HTML;

        $this->render('cs', '独立客服', $content);
    }

    public function save(): void
    {
        $this->requirePerm('cs');
        $this->verifyCsrf();

        $db      = Db::connect('live_mysql');
        $agentId = $this->agentId();
        $id      = (int) ($this->request->post('id') ?? 0);
        $type    = (string) ($this->request->post('type') ?? '');
        $name    = mb_substr(trim((string) ($this->request->post('name') ?? '')), 0, 50);
        $url     = mb_substr(trim((string) ($this->request->post('url') ?? '')), 0, 500);
        $icon    = mb_substr(trim((string) ($this->request->post('icon') ?? '')), 0, 500);
        $sort    = max(0, min(999, (int) ($this->request->post('sort') ?? 0)));
        $status  = (string) ($this->request->post('status') ?? '') === '1' ? 1 : 0;

        if (!isset(PromoService::CHANNEL_TYPES[$type])) {
            $this->back('err=' . urlencode('渠道类型无效'));
        }
        if ($name === '') {
            $this->back('err=' . urlencode('请填写展示名称'));
        }
        if ($url === '') {
            $this->back('err=' . urlencode('请填写链接或地址'));
        }
        if ($type === 'email') {
            if (!filter_var($url, FILTER_VALIDATE_EMAIL)) {
                $this->back('err=' . urlencode('邮件渠道请填写正确的邮箱地址'));
            }
        } elseif (!preg_match('#^https?://#i', $url)) {
            $this->back('err=' . urlencode('链接需以 http:// 或 https:// 开头'));
        }

        $now = date('Y-m-d H:i:s');
        if ($id > 0) {
            $row = $db->table('lp_cs_channel')->where('id', $id)->where('agent_id', $agentId)->find();
            if (!$row) {
                $this->back('err=' . urlencode('渠道不存在'));
            }
            $db->table('lp_cs_channel')->where('id', $id)->update([
                'type' => $type, 'name' => $name, 'url' => $url, 'icon' => $icon,
                'sort' => $sort, 'status' => $status, 'updated_at' => $now,
            ]);
            $this->back('ok=' . urlencode('客服渠道已更新'));
        }

        $count = (int) $db->table('lp_cs_channel')->where('agent_id', $agentId)->count();
        if ($count >= self::MAX_CHANNELS) {
            $this->back('err=' . urlencode('最多配置 ' . self::MAX_CHANNELS . ' 个渠道'));
        }
        $db->table('lp_cs_channel')->insert([
            'agent_id' => $agentId, 'type' => $type, 'name' => $name, 'url' => $url, 'icon' => $icon,
            'sort' => $sort, 'status' => $status, 'clicks' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->back('ok=' . urlencode('客服渠道已添加'));
    }

    public function toggle(): void
    {
        $this->requirePerm('cs');
        $this->verifyCsrfParam();

        $id  = (int) ($this->request->param('id') ?? 0);
        $db  = Db::connect('live_mysql');
        $row = $db->table('lp_cs_channel')->where('id', $id)->where('agent_id', $this->agentId())->find();
        if (!$row) {
            $this->back('err=' . urlencode('渠道不存在'));
        }
        $db->table('lp_cs_channel')->where('id', $id)->update([
            'status' => (int) $row['status'] === 1 ? 0 : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->back('ok=' . urlencode('状态已更新'));
    }

    public function del(): void
    {
        $this->requirePerm('cs');
        $this->verifyCsrfParam();

        $id = (int) ($this->request->param('id') ?? 0);
        Db::connect('live_mysql')->table('lp_cs_channel')
            ->where('id', $id)->where('agent_id', $this->agentId())->delete();
        $this->back('ok=' . urlencode('渠道已删除'));
    }

    private function back(string $query): void
    {
        $this->redirect('/agent/cs/index?' . $query);
    }

    private function disabledAttr(bool $enabled): string
    {
        return $enabled ? '' : ' disabled';
    }

    /** 链接类操作走 GET，CSRF 放在 query 里 */
    private function verifyCsrfParam(): void
    {
        $given = (string) ($this->request->param('_csrf') ?? '');
        if ($given === '' || !hash_equals($this->csrf(), $given)) {
            $this->deny('请求已失效，请刷新页面后重试。', 419);
        }
    }
}
