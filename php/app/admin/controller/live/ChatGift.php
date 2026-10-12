<?php
/**
 * 聊天礼物配置
 * 访问: /admin/live.ChatGift/index?_key=<ADMIN_FLOAT_KEY>
 *
 * 礼物列表：名称/图标/价格/排序/启停，保存即生效（前台 chatGifts 接口直读库）。
 * 收礼素材目录：/storage/chat_gifts/<role_key>/<gift_key>.mp4（角色素材清单只读展示）。
 */
declare(strict_types=1);

namespace app\admin\controller\live;

use app\common\controller\Backend;
use think\facade\Db;

final class ChatGift extends Backend
{
    protected array $noNeedLogin = ['index', 'save'];

    protected array $middleware = [
        \app\admin\middleware\FloatAuth::class,
    ];

    public function index(): void
    {
        $db = Db::connect('live_mysql');

        // 保存（价格/名称/启停）
        if ($this->request->isPost()) {
            $id    = (int) $this->request->post('id', 0);
            $field = (string) $this->request->post('field', '');
            $value = (string) $this->request->post('value', '');
            if ($id > 0 && in_array($field, ['name', 'price', 'sort', 'status'], true)) {
                $data = [];
                if ($field === 'price' || $field === 'sort') {
                    $data[$field] = max(0, (int) $value);
                } elseif ($field === 'status') {
                    $data[$field] = $value === '1' ? 1 : 0;
                } else {
                    $data[$field] = mb_substr(trim($value), 0, 60);
                }
                $db->table('lp_chat_gift')->where('id', $id)->update($data);
                echo json_encode(['code' => 1, 'msg' => 'ok']);
                exit;
            }
            echo json_encode(['code' => 0, 'msg' => 'bad params']);
            exit;
        }

        // 礼物列表
        $gifts = $db->table('lp_chat_gift')->order('sort', 'asc')->select()->toArray();
        $rows  = '';
        foreach ($gifts as $g) {
            $id   = (int) $g['id'];
            $icon = htmlspecialchars((string) $g['icon'], ENT_QUOTES);
            $name = htmlspecialchars((string) $g['name'], ENT_QUOTES);
            $price = (int) $g['price'];
            $sort = (int) $g['sort'];
            $st  = (int) $g['status'] === 1;
            $rows .= <<<ROW
            <tr data-id="{$id}">
                <td>{$id}</td>
                <td><img src="{$icon}" style="width:44px;height:44px;object-fit:cover;border-radius:8px" onerror="this.style.opacity=.2"></td>
                <td><input class="ed" data-field="name" value="{$name}" style="width:110px"></td>
                <td><input class="ed num" data-field="price" value="{$price}" style="width:70px"></td>
                <td><input class="ed num" data-field="sort" value="{$sort}" style="width:60px"></td>
                <td><button class="tg btn-mini" data-on="{$st}">{$st}</button></td>
            </tr>
ROW;
        }

        // 角色素材清单（只读）
        $roles = $db->table('lp_chat_gift_role')->where('status', 1)->order('role_key', 'asc')->select()->toArray();
        $roleRows = '';
        foreach ($roles as $r) {
            $rk = htmlspecialchars((string) $r['role_key'], ENT_QUOTES);
            $rn = htmlspecialchars((string) $r['role_name'], ENT_QUOTES);
            $roleRows .= "<tr><td>{$rk}</td><td>{$rn}</td></tr>";
        }

        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>聊天礼物配置</title>
<style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:-apple-system,sans-serif;background:#f5f7fa;color:#333;padding:20px;font-size:14px}
    .container{max-width:860px;margin:0 auto}
    .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:18px}
    h2{font-size:17px;margin-bottom:14px;display:flex;align-items:center;gap:8px}
    h2 .dot{width:6px;height:18px;background:#409eff;border-radius:3px}
    table{width:100%;border-collapse:collapse;font-size:13px}
    th,td{padding:9px 8px;border-bottom:1px solid #eef0f3;text-align:left}
    th{color:#888;font-weight:600;background:#fafbfc}
    input{padding:6px 8px;border:1px solid #ddd;border-radius:6px;font-size:13px}
    .btn-mini{padding:4px 12px;font-size:12px;border:none;border-radius:6px;cursor:pointer;background:#eef4ff;color:#409eff;font-weight:600}
    .tg.off{background:#f2f3f5;color:#999}
    .tip{font-size:12px;color:#999;margin-top:10px;line-height:1.6}
    .ok-toast{position:fixed;top:18px;right:18px;background:#00a870;color:#fff;padding:9px 18px;border-radius:8px;font-size:13px;display:none}
</style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2><span class="dot"></span>礼物列表（改价格/名称后回车或点保存；价格单位：代币）</h2>
        <table>
            <thead><tr><th>ID</th><th>图标</th><th>名称</th><th>价格</th><th>排序</th><th>启用</th></tr></thead>
            <tbody>{$rows}</tbody>
        </table>
        <div class="tip">素材目录约定：/storage/chat_gifts/&lt;角色键&gt;/&lt;gift_key&gt;.mp4；送礼会按「价格」扣代币并写入钱包流水（chat_gift）。</div>
    </div>
    <div class="card">
        <h2><span class="dot"></span>有收礼素材的角色（只读清单）</h2>
        <table>
            <thead><tr><th>角色键</th><th>角色名</th></tr></thead>
            <tbody>{$roleRows}</tbody>
        </table>
        <div class="tip">新增角色素材：把 6 个礼物 mp4 + 人设图放到 /storage/chat_gifts/&lt;home-N&gt;/，然后在 lp_chat_gift_role 插一行。</div>
    </div>
</div>
<div class="ok-toast" id="toast">已保存</div>
<script>
(function () {
    var toast = document.getElementById('toast');
    function flash() { toast.style.display = 'block'; setTimeout(function () { toast.style.display = 'none' }, 1200) }
    function save(id, field, value) {
        var fd = new FormData();
        fd.append('id', id); fd.append('field', field); fd.append('value', value);
        fetch(location.pathname + '/save' + location.search, { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) { if (d.code === 1) flash() })
            .catch(function () {});
    }
    document.querySelectorAll('tr[data-id]').forEach(function (tr) {
        var id = tr.getAttribute('data-id');
        tr.querySelectorAll('input.ed').forEach(function (inp) {
            inp.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') save(id, inp.getAttribute('data-field'), inp.value);
            });
            inp.addEventListener('change', function () { save(id, inp.getAttribute('data-field'), inp.value) });
        });
        var tg = tr.querySelector('.tg');
        if (tg) tg.addEventListener('click', function () {
            var on = tg.getAttribute('data-on') === '1' ? '0' : '1';
            save(id, 'status', on);
            tg.setAttribute('data-on', on);
            tg.textContent = on;
            tg.className = 'tg btn-mini' + (on === '1' ? '' : ' off');
        });
    });
})();
</script>
</body>
</html>
HTML;
        exit;
    }
}
