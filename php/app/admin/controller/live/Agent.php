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
                ''
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

        $rows = '';
        foreach ($agents as $a) {
            $id      = (int) $a['id'];
            $status  = (int) $a['status'] === 1;
            $perms   = AgentService::parsePerms($a['perms'] ?? null);
            $permTxt = [];
            foreach ($perms as $p) {
                $permTxt[] = AgentService::PERMS[$p] ?? $p;
            }
            $domains = array_map(static fn(array $d): string => (string) $d['domain'], $domainsByAgent[$id] ?? []);
            $pixels  = array_map(static fn(array $p): string => (string) ($p['name'] !== '' ? $p['name'] : ('渠道#' . $p['id'])), $pixelsByAgent[$id] ?? []);

            $rows .= '<tr>'
                . '<td>' . $id . '</td>'
                . '<td><b>' . $this->e((string) $a['agent_no']) . '</b><div class="muted">' . $this->e((string) $a['name']) . '</div></td>'
                . '<td>' . $this->e((string) $a['username']) . '</td>'
                . '<td class="wrap">' . ($domains === [] ? '<span class="muted">未分配</span>' : $this->e(implode('、', $domains))) . '</td>'
                . '<td class="wrap">' . ($pixels === [] ? '<span class="muted">未分配</span>' : $this->e(implode('、', $pixels))) . '</td>'
                . '<td class="wrap muted">' . ($permTxt === [] ? '—' : $this->e(implode('、', $permTxt))) . '</td>'
                . '<td class="num">' . (int) $a['regs'] . '</td>'
                . '<td class="num">' . (int) $a['buyers'] . ' / ' . number_format((float) $a['amount'], 2) . '</td>'
                . '<td>' . ($status ? '<span class="chip on">启用</span>' : '<span class="chip off">停用</span>') . '</td>'
                . '<td>' . $this->e((string) ($a['last_login_at'] ?? '—')) . '</td>'
                . '<td class="ops">'
                . '<a href="/admin/live.Agent/index?id=' . $id . $this->keyQuery() . '">编辑</a>'
                . '<a href="/admin/live.Agent/toggle?id=' . $id . $this->keyQuery() . '">' . ($status ? '停用' : '启用') . '</a>'
                . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="11" class="empty">还没有代理，先在下面创建</td></tr>';
        }

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
            $checked = in_array($key, $ePerms, true) ? ' checked' : '';
            $disabled = in_array($key, AgentService::ALWAYS_PERMS, true) ? ' disabled' : '';
            $permBoxes .= '<label class="chk"><input type="checkbox" name="perms[]" value="' . $key . '"' . $checked . $disabled . '> ' . $this->e($label) . '</label>';
        }

        $pixelBoxes = '';
        foreach ($allPixels as $p) {
            $pid   = (int) $p['id'];
            $owner = (int) $p['agent_id'];
            $pf    = PromoService::PIXEL_PLATFORMS[(string) $p['platform']] ?? (string) $p['platform'];
            $pn    = trim((string) $p['name']);
            $label = $pf . ($pn !== '' ? ' · ' . $pn : '') . '（#' . $pid . '）';
            if ($owner > 0 && $owner !== $eId) {
                $label .= ' — 已属于 ' . ($agentNames[$owner] ?? ('代理#' . $owner));
                $pixelBoxes .= '<label class="chk is-disabled"><input type="checkbox" disabled> ' . $this->e($label) . '</label>';
            } else {
                $checked = in_array($pid, $ePixels, true) ? ' checked' : '';
                $pixelBoxes .= '<label class="chk"><input type="checkbox" name="pixels[]" value="' . $pid . '"' . $checked . '> ' . $this->e($label) . '</label>';
            }
        }
        if ($pixelBoxes === '') {
            $pixelBoxes = '<div class="muted">还没有渠道，请先到「推广与像素」创建。</div>';
        }

        $ok = (string) $this->request->param('ok', '');
        $alert = $ok !== '' ? '<div class="alert ok">' . $this->e($ok) . '</div>' : '';
        $formTitle = $eId > 0 ? '编辑代理 #' . $eId : '新建代理';
        $cancel = $eId > 0 ? '<a class="btn-line" href="/admin/live.Agent/index' . $this->keyQuery() . '">取消编辑</a>' : '';
        $pwdHint = $eId > 0 ? '留空表示不修改密码' : '新建代理必填，至少 6 位';
        $eDomainsEsc = $this->e($eDomains);
        $saveUrl     = '/admin/live.Agent/save' . $this->keyQuery();

        $content = <<<HTML
<div class="head"><div><h1>代理管理</h1>
  <div class="sub">代理是独立账号，只能登录 <b>/agent</b>，只能看到分配给自己的域名、渠道、链接与会员数据。</div></div></div>
{$alert}
<div class="card">
  <h2>{$formTitle}</h2>
  <form class="frm" method="post" action="{$saveUrl}">
    <input type="hidden" name="id" value="{$eId}">
    <div class="fld"><label>代理名称（必填）</label><input type="text" name="name" value="{$eName}" maxlength="64" required></div>
    <div class="fld"><label>登录账号（必填，唯一）</label><input type="text" name="username" value="{$eUser}" maxlength="64" required></div>
    <div class="fld"><label>登录密码</label><input type="password" name="password" maxlength="32" autocomplete="new-password" placeholder="{$pwdHint}"></div>
    <div class="fld"><label>联系方式</label><input type="text" name="contact" value="{$eContact}" maxlength="128"></div>
    <div class="fld"><label>结算比例 %（预留，仅记录）</label><input type="number" step="0.01" min="0" max="100" name="rate" value="{$eRate}"></div>
    <div class="fld"><label>备注</label><input type="text" name="remark" value="{$eRemark}" maxlength="255"></div>
    <div class="fld full"><label>代理端权限</label><div class="chks">{$permBoxes}</div></div>
    <div class="fld full"><label>专属推广域名（一行一个，需先完成 DNS 解析与证书配置）</label>
      <textarea name="domains" rows="3" placeholder="agent1.example.com">{$eDomainsEsc}</textarea></div>
    <div class="fld full"><label>分配渠道（代理只能看到名称，看不到像素 ID / Token）</label><div class="chks">{$pixelBoxes}</div></div>
    <div class="actions">
      <button class="btn" type="submit">保存</button>{$cancel}
      <label class="muted" style="margin-left:auto"><input type="checkbox" name="status" value="1"{$eStatus}> 启用</label>
    </div>
  </form>
</div>
<div class="card">
  <h2>代理列表</h2>
  <table><thead><tr>
    <th>ID</th><th>代理</th><th>账号</th><th>域名</th><th>渠道</th><th>权限</th>
    <th class="num">注册</th><th class="num">充值人数/金额</th><th>状态</th><th>最后登录</th><th>操作</th>
  </tr></thead><tbody>{$rows}</tbody></table>
</div>
HTML;

        $this->renderPage($content, '代理管理');
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

    private function renderPage(string $content, string $sub, int $status = 200): void
    {
        http_response_code($status);
        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>代理管理</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;background:#f4f5fb;color:#1f2430;font-size:14px}
.container{max-width:1280px;margin:0 auto;padding:28px 24px 60px}
.head{margin-bottom:18px}
h1{font-size:23px;font-weight:700;color:#1a1f36}
.sub{margin-top:6px;font-size:13px;color:#8a90a3;line-height:1.7}
.card{background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:20px;margin-bottom:18px;box-shadow:0 1px 3px rgba(30,34,60,.04)}
.card h2{font-size:15px;font-weight:700;margin-bottom:14px;color:#1a1f36}
.frm{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.fld{display:flex;flex-direction:column;gap:6px}
.fld label{font-size:12px;color:#8a90a3}
.fld input,.fld textarea{padding:9px 12px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;color:#1f2430;background:#fff;outline:none;width:100%;font-family:inherit}
.fld input{height:38px}
.fld input:focus,.fld textarea:focus{border-color:#6366f1}
.fld.full{grid-column:1/-1}
.chks{display:flex;flex-wrap:wrap;gap:8px 18px;padding:8px 0}
.chk{display:flex;align-items:center;gap:6px;font-size:13px;color:#3a4051;cursor:pointer}
.chk.is-disabled{color:#b4b9c6;cursor:not-allowed}
.actions{grid-column:1/-1;display:flex;gap:10px;align-items:center}
.btn{height:38px;padding:0 20px;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff}
.btn-line{height:38px;line-height:36px;padding:0 16px;border:1px solid #e2e5ee;border-radius:10px;font-size:14px;color:#5b6072;text-decoration:none;background:#fff;display:inline-block}
table{width:100%;border-collapse:collapse}
th{background:#fafbfe;font-weight:600;font-size:12px;color:#8a90a3;text-align:left;padding:12px 12px;border-bottom:1px solid #eef0f6;white-space:nowrap}
td{padding:11px 12px;border-bottom:1px solid #f2f3f8;font-size:13px;color:#3a4051;vertical-align:top}
td.num,th.num{text-align:right;font-variant-numeric:tabular-nums}
tr:last-child td{border-bottom:none}
.empty{text-align:center;color:#9aa0b0;padding:26px 0}
.muted{color:#9aa0b0}
td.wrap{max-width:220px;word-break:break-all}
.chip{font-size:12px;padding:2px 9px;border-radius:999px;white-space:nowrap}
.chip.on{background:#e8f7ef;color:#00a870}
.chip.off{background:#f2f3f5;color:#999}
td.ops a{color:#6366f1;text-decoration:none;margin-right:12px;white-space:nowrap}
.tip{font-size:13px;color:#6b7080;line-height:1.8}
.alert{padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:16px}
.alert.ok{background:#e8f7ef;color:#00875a;border:1px solid #c7ebd8}
code{background:#f7f8fc;border:1px solid #eef0f6;border-radius:6px;padding:2px 6px;font-family:Consolas,Menlo,monospace;font-size:12.5px}
</style>
</head>
<body><div class="container">{$content}</div></body>
</html>
HTML;
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
