<?php

namespace app\admin\controller\user;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\live\LiveUser as LiveUserModel;

class LiveUser extends Backend
{
    /**
     * @var object
     * @phpstan-var LiveUserModel
     */
    protected object $model;

    /**
     * 手动充值/扣减代币、开通会员无需单独配置权限节点（登录鉴权仍生效）
     */
    protected array $noNeedPermission = ['adjustDiamond', 'vipPlans', 'grantVip', 'detail'];

    protected array $withJoinTable = [];

    protected string|array $preExcludeFields = [];

    protected string|array $quickSearchField = ['nickname', 'id', 'user_no', 'email', 'reg_domain', 'reg_channel', 'reg_link_name', 'reg_platform'];

    protected string|array $defaultSortField = ['id' => 'desc'];

    protected bool $modelValidate = false;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new LiveUserModel();
    }

    public function index(): void
    {
        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $res = $this->model
            ->field('
                live_user.id, live_user.user_no, live_user.nickname, live_user.avatar,
                live_user.email, live_user.status, live_user.level, live_user.vip_expire_at, live_user.created_at,
                live_user.reg_domain, live_user.reg_channel, live_user.reg_link_name, live_user.reg_platform, live_user.reg_px,
                IFNULL(wallet.diamond_balance, 0) as diamond_balance,
                IFNULL((SELECT au.auth_key FROM lp_user_auth au WHERE au.user_id = live_user.id ORDER BY au.id ASC LIMIT 1), \'\') as auth_account,
                IFNULL((SELECT au.auth_type FROM lp_user_auth au WHERE au.user_id = live_user.id ORDER BY au.id ASC LIMIT 1), \'\') as auth_type,
                IFNULL(lp_user_profile.last_login_ip, \'\') as last_login_ip,
                IFNULL(lp_user_profile.last_login_at, NULL) as last_login_at,
                IFNULL(cert.status, -1) as cert_status,
                IFNULL(cert.id, 0) as cert_id
            ')
            ->alias($alias)
            ->leftJoin('lp_wallet_account wallet', 'wallet.user_id = live_user.id')
            ->leftJoin('lp_user_profile', 'lp_user_profile.user_id = live_user.id')
            ->leftJoin('lp_merchant_certification cert', 'cert.user_id = live_user.id')
            ->where($where)
            ->order($order)
            ->paginate($limit);

        // 渠道名映射：reg_px 存的是 lp_promo_pixel.id 或像素ID字符串，两种都映射一份；
        // 同时按渠道绑定的推广域名建立「域名 → 渠道名称」映射（老链接常不带 px/name）
        $pixelNames  = [];
        $domainNames = [];
        foreach (Db::connect('live_mysql')->table('lp_promo_pixel')->field('id,pixel_id,name,domains')->select()->toArray() as $p) {
            $pn = trim((string) $p['name']);
            if ($pn === '') {
                continue;
            }
            $pixelNames[(string) $p['id']] = $pn;
            if ((string) $p['pixel_id'] !== '') {
                $pixelNames[(string) $p['pixel_id']] = $pn;
            }
            foreach (preg_split('/[\s,]+/', (string) ($p['domains'] ?? '')) ?: [] as $d) {
                $d = strtolower(trim((string) $d));
                $d = (string) preg_replace('#^https?://#i', '', $d);
                $d = (string) preg_replace('#[/:].*$#', '', $d);
                if ($d !== '' && !isset($domainNames[$d])) {
                    $domainNames[$d] = $pn;
                }
            }
        }

        // 附加认证状态展示字段（给前端 tag 渲染用）
        $items = $res->items();

        // 会员身份：Free / Premium / Premium Plus
        // Premium Plus = 名下有生效中的 premium_plus 订单（其余有效会员归为 Premium）
        $plusUsers = [];
        $ids = array_map(static fn($r) => (int) $r['id'], $items);
        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $plusRows = Db::connect('live_mysql')->query(
                "SELECT DISTINCT o.user_id FROM lp_vip_order o
                 JOIN lp_vip_plan p ON p.id = o.plan_id
                 WHERE o.user_id IN ({$placeholders}) AND o.status = 1
                   AND o.expire_to >= ? AND p.level = 'premium_plus'",
                array_merge($ids, [date('Y-m-d H:i:s')])
            );
            foreach ($plusRows as $pr) {
                $plusUsers[(int) $pr['user_id']] = true;
            }
        }

        foreach ($items as &$row) {
            // 会员账号：邮箱优先，为空时取登录账号（用户名/手机号）
            $row['account_label'] = (string) ($row['email'] ?? '') !== ''
                ? (string) $row['email']
                : (string) ($row['auth_account'] ?? '');

            // 会员身份标签
            $vipExpire   = (string) ($row['vip_expire_at'] ?? '');
            $isVipActive = $vipExpire !== '' && strtotime($vipExpire) > time();
            if (!$isVipActive) {
                $row['vip_level_label'] = 'Free';
                $row['vip_level_type']  = 'info';
            } elseif (isset($plusUsers[(int) $row['id']])) {
                $row['vip_level_label'] = 'Premium Plus';
                $row['vip_level_type']  = 'warning';
            } else {
                $row['vip_level_label'] = 'Premium';
                $row['vip_level_type']  = 'primary';
            }

            $row['cert_status_text'] = match ((int)$row['cert_status']) {
                -1 => '未认证', 0 => '审核中', 1 => '已通过', 2 => '已拒绝', default => '未知'
            };
            $row['cert_tag_type'] = match ((int)$row['cert_status']) {
                -1 => 'info', 0 => 'warning', 1 => 'success', 2 => 'danger', default => 'info'
            };
            $row['cert_review_url'] = $row['cert_id'] > 0
                ? '/admin/user.LiveUser/detail?id=' . $row['id']
                : '';

            // 来源展示两列：
            //   注册来源 = 平台短名（FB / TT / GG）
            //   注册渠道 = 渠道名称 + 推广域名
            $regLink     = (string) ($row['reg_link_name'] ?? '');
            $regChannel  = (string) ($row['reg_channel'] ?? '');
            $regPlatform = (string) ($row['reg_platform'] ?? '');
            $regDomain   = (string) ($row['reg_domain'] ?? '');

            $pfShort = match ($regPlatform) {
                'facebook' => 'FB',
                'tiktok'   => 'TT',
                'google'   => 'GG',
                default    => '',
            };
            if ($pfShort === '') {
                // 老数据 platform 为空时，按渠道 / 链接名兜底识别
                $hint = $regChannel . ' ' . $regLink;
                if (preg_match('/facebook|meta|instagram|fb/i', $hint)) {
                    $pfShort = 'FB';
                } elseif (preg_match('/tiktok|douyin/i', $hint)) {
                    $pfShort = 'TT';
                } elseif (preg_match('/google|adwords|gdn|youtube/i', $hint)) {
                    $pfShort = 'GG';
                }
            }
            $row['reg_source_label'] = $pfShort;
            $row['reg_source_type']  = match ($pfShort) {
                'FB' => 'primary', 'TT' => 'danger', 'GG' => 'success', default => 'info',
            };

            // 注册渠道 = 渠道名称 + 推广域名
            // 渠道名称取数优先级：推广链接 name 参数 > reg_px 映射的渠道名 > 推广域名匹配的渠道名 > ch 参数
            $pxKey    = (string) ($row['reg_px'] ?? '');
            $pxName   = ($pxKey !== '' && isset($pixelNames[$pxKey])) ? $pixelNames[$pxKey] : '';
            $domKey   = strtolower(trim($regDomain));
            $domName  = ($domKey !== '' && isset($domainNames[$domKey])) ? $domainNames[$domKey] : '';
            $channelName = $regLink !== '' ? $regLink : ($pxName !== '' ? $pxName : ($domName !== '' ? $domName : $regChannel));
            $chParts = [];
            if ($channelName !== '') { $chParts[] = $channelName; }
            if ($regDomain !== '') { $chParts[] = $regDomain; }
            $row['reg_channel_label'] = implode(' · ', $chParts);

            // 悬浮明细
            $detail = [];
            if ($regDomain !== '')   { $detail[] = '域名：' . $regDomain; }
            if ($pfShort !== '')     { $detail[] = '平台：' . ($regPlatform !== '' ? $regPlatform : $pfShort); }
            if ($regChannel !== '')  { $detail[] = '渠道：' . $regChannel; }
            if ($pxName !== '')      { $detail[] = '像素：' . $pxName; }
            elseif ($pxKey !== '')   { $detail[] = '像素：' . $pxKey; }
            if ($regLink !== '')     { $detail[] = '推广链接：' . $regLink; }
            $row['reg_source_detail'] = implode("\n", $detail);
        }
        unset($row);

        $this->success('', [
            'list'   => $items,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function add(): void
    {
        $this->error('直播平台用户不支持手动添加，请通过注册流程创建');
    }

    /**
     * 手动充值/扣减用户代币
     * POST /admin/user.LiveUser/adjustDiamond
     * @param int    user_id 用户ID
     * @param float  amount  代币数量（正数）
     * @param string type    credit=充值(增加) debit=扣减(减少)
     * @param string remark  备注（可选）
     */
    public function adjustDiamond(): void
    {
        $userId = $this->request->post('user_id/d', 0);
        $amount = (float) $this->request->post('amount/f', 0);
        $type   = (string) $this->request->post('type/s', 'credit');
        $remark = trim((string) $this->request->post('remark/s', ''));

        if ($userId <= 0) {
            $this->error('请选择用户');
        }
        if ($amount <= 0) {
            $this->error('代币数量必须大于 0');
        }
        if (!in_array($type, ['credit', 'debit'], true)) {
            $this->error('操作类型无效');
        }
        if (!$this->model->find($userId)) {
            $this->error('用户不存在');
        }

        $adminName = (string) ($this->auth->nickname ?? '');
        $memo = ($type === 'debit' ? '管理员手动扣减' : '管理员手动充值')
            . ($adminName !== '' ? '（' . $adminName . '）' : '')
            . ($remark !== '' ? '：' . $remark : '');

        try {
            $service = new \app\live\service\WalletService();
            $result  = $type === 'debit'
                ? $service->debit($userId, $amount, 'adjust', 0, mb_substr($memo, 0, 255))
                : $service->credit($userId, $amount, 'recharge', 0, mb_substr($memo, 0, 255));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return;
        }

        // 注意：success() 内部通过抛 HttpResponseException 输出响应，必须放在 try/catch 之外，
        // 否则会被 catch (\Throwable) 捕获而误报为失败（代币已入账但前端收到错误提示）。
        $this->success($type === 'debit' ? '扣减成功' : '充值成功', $result);
    }

    /**
     * 可开通的会员等级包（Premium / Premium Plus 各档，公开套餐表 status=1）
     * POST /admin/user.LiveUser/vipPlans
     */
    public function vipPlans(): void
    {
        $plans = (new \app\live\service\VipService())->plans();
        $this->success('', $plans);
    }

    /**
     * 给用户开通/顺延会员等级包（后台手动，等效于用户购买：无扣费、到期顺延）
     * POST /admin/user.LiveUser/grantVip
     * @param int user_id 用户ID
     * @param int plan_id 套餐ID
     */
    public function grantVip(): void
    {
        $userId = $this->request->post('user_id/d', 0);
        $planId = $this->request->post('plan_id/d', 0);

        if ($userId <= 0) {
            $this->error('请选择用户');
        }
        if ($planId <= 0) {
            $this->error('请选择要开通的等级包');
        }
        if (!$this->model->find($userId)) {
            $this->error('用户不存在');
        }

        // 后台开通单号前缀 ADMIN，便于与用户自助购买区分
        $orderNo = 'ADMIN' . date('YmdHis') . $userId;

        try {
            $result = (new \app\live\service\VipService())->activate($userId, $planId, $orderNo, 0);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return;
        }

        $this->success('已开通「' . $result['plan_name'] . '」，到期时间 ' . $result['expire_to'], $result);
    }

    public function del(): void
    {
        $pk  = $this->model->getPk();
        $ids = $this->request->param($pk);
        if (!$ids) {
            $this->error(__('Parameter error'));
        }

        $where = [];
        if (str_contains($ids, ',')) {
            $where[] = [$pk, 'in', $ids];
        } else {
            $where[] = [$pk, '=', $ids];
        }

        $this->model->startTrans();
        try {
            foreach ($this->model->where($where)->select() as $user) {
                $user->profile()->delete();
                $user->auth()->delete();
                $user->delete();
            }
            $this->model->commit();
        } catch (Throwable $e) {
            $this->model->rollback();
            $this->error($e->getMessage());
        }

        $this->success(__('Deleted successfully'));
    }

    public function quickSearch(): void
    {
        $search = $this->request->get('search', '');
        $users  = $this->model
            ->field('id, user_no, nickname')
            ->where('nickname', 'like', "%{$search}%")
            ->whereOr('user_no', 'like', "%{$search}%")
            ->limit(20)
            ->select();

        $list = [];
        foreach ($users as $user) {
            $list[] = [
                'id'       => $user->id,
                'user_no'  => $user->user_no,
                'nickname' => $user->nickname,
                'label'    => $user->nickname . '(ID:' . $user->id . ')',
            ];
        }

        $this->success('', ['list' => $list]);
    }

    /**
     * 用户详情页（含商家认证审核）
     */
    public function detail(): void
    {
        $userId = $this->request->param('id/d', 0);
        $user = $this->model->find($userId);
        if (!$user) {
            response('用户不存在')->send();
            exit;
        }

        // 认证信息
        $cert = \app\admin\model\live\MerchantCertification::where('user_id', $userId)->find();
        $certStatus = -1;
        $certId = 0;
        $certEmail = '';
        $certFront = '';
        $certBack = '';
        $certReason = '';
        $certTime = '';
        if ($cert) {
            $certStatus = (int)$cert->status;
            $certId = (int)$cert->id;
            $certEmail = htmlspecialchars($cert->email);
            $certFront = htmlspecialchars($cert->id_card_front);
            $certBack = htmlspecialchars($cert->id_card_back);
            $certReason = htmlspecialchars($cert->reject_reason);
            $certTime = $cert->created_at;
        }
        $statusMap = [-1 => '未认证', 0 => '审核中', 1 => '已通过', 2 => '已拒绝'];
        $badgeCls = [-1 => 'gray', 0 => 'yellow', 1 => 'green', 2 => 'red'][$certStatus] ?? 'gray';
        $certStatusText = $statusMap[$certStatus] ?? '未知';
        $frontHtml = $certFront ? '<img class="img-preview" src="' . $certFront . '" />' : '-';
        $backHtml  = $certBack  ? '<img class="img-preview" src="' . $certBack . '" />' : '-';
        $certTime  = $certTime ?: '-';
        $certEmail = $certEmail ?: '-';
        $certReason = $certReason ?: '-';

        $nickname = htmlspecialchars((string) $user->nickname);
        $userNo   = htmlspecialchars((string) $user->user_no);
        $userStatus = (int) $user->status === 1 ? '正常' : '禁用';
        $createdAt  = (string) $user->created_at;

        // 会员账号：邮箱优先，无邮箱时取登录账号（用户名/手机号）
        $auth = Db::connect('live_mysql')->table('lp_user_auth')
            ->where('user_id', $userId)->order('id', 'asc')->find();
        $authTypeRaw  = (string) ($auth['auth_type'] ?? '');
        $authKeyRaw   = (string) ($auth['auth_key'] ?? '');
        $emailRaw     = (string) ($user->email ?? '');
        $accountRaw   = $emailRaw !== '' ? $emailRaw : $authKeyRaw;
        $accountText  = htmlspecialchars($accountRaw !== '' ? $accountRaw : '-');
        $authTypeText = match ($authTypeRaw) {
            'email' => '邮箱', 'username' => '用户名', 'mobile' => '手机号', default => '',
        };
        $authSuffix = $authTypeText !== '' ? ' <span style="color:#999">（' . $authTypeText . '）</span>' : '';

        // 会员身份（Free / Premium / Premium Plus）与到期
        $vipExpireRaw = (string) ($user->vip_expire_at ?? '');
        $isVipActive  = $vipExpireRaw !== '' && strtotime($vipExpireRaw) > time();
        $vipLabel     = 'Free';
        if ($isVipActive) {
            $hasPlus = Db::connect('live_mysql')->table('lp_vip_order')->alias('o')
                ->join('lp_vip_plan p', 'p.id = o.plan_id')
                ->where('o.user_id', $userId)->where('o.status', 1)
                ->where('o.expire_to', '>=', date('Y-m-d H:i:s'))
                ->where('p.level', 'premium_plus')->count() > 0;
            $vipLabel = $hasPlus ? 'Premium Plus' : 'Premium';
        }
        $vipBadgeCls   = match ($vipLabel) {
            'Premium Plus' => 'purple', 'Premium' => 'blue', default => 'gray',
        };
        $vipExpireText = $isVipActive ? htmlspecialchars($vipExpireRaw) : '-';

        // 代币余额
        $balanceVal = Db::connect('live_mysql')->table('lp_wallet_account')
            ->where('user_id', $userId)->value('diamond_balance');
        $balance = number_format((float) ($balanceVal ?? 0), 2, '.', '');

        // 最后登录
        $profile = Db::connect('live_mysql')->table('lp_user_profile')->where('user_id', $userId)->find();
        $lastIp  = (string) ($profile['last_login_ip'] ?? '');
        $lastAt  = (string) ($profile['last_login_at'] ?? '');
        $lastLoginText = $lastAt !== '' ? htmlspecialchars($lastAt) : '-';
        if ($lastIp !== '') {
            $lastLoginText .= ' <span style="color:#999">（' . htmlspecialchars($lastIp) . '）</span>';
        }

        // 推广来源
        $regDomainRaw   = (string) ($user->reg_domain ?? '');
        $regLinkRaw     = (string) ($user->reg_link_name ?? '');
        $regPlatformRaw = (string) ($user->reg_platform ?? '');
        $regPxRaw       = (string) ($user->reg_px ?? '');

        // 渠道名称：优先 reg_px 映射，其次按推广域名匹配渠道配置
        $channelName = '';
        if ($regPxRaw !== '') {
            $channelName = trim((string) Db::connect('live_mysql')->table('lp_promo_pixel')
                ->where('id', $regPxRaw)->value('name'));
            if ($channelName === '') {
                $channelName = trim((string) Db::connect('live_mysql')->table('lp_promo_pixel')
                    ->where('pixel_id', $regPxRaw)->value('name'));
            }
        }
        if ($channelName === '' && $regDomainRaw !== '') {
            $channelName = trim((string) Db::connect('live_mysql')->table('lp_promo_pixel')
                ->whereLike('domains', '%' . $regDomainRaw . '%')->value('name'));
        }
        $pfShort = match (strtolower($regPlatformRaw)) {
            'facebook' => 'FB', 'tiktok' => 'TT', 'google' => 'GG', default => '',
        };
        $chParts = [];
        if ($channelName !== '') { $chParts[] = htmlspecialchars($channelName); }
        if ($regDomainRaw !== '') { $chParts[] = htmlspecialchars($regDomainRaw); }
        $channelDisplay = $chParts !== [] ? implode(' · ', $chParts) : '-';

        // 推广来源明细：只展示有值的项，避免一排 "-"
        $srcRows = '<div class="row"><span class="label">注册渠道</span><span class="value">' . $channelDisplay . '</span></div>';
        if ($pfShort !== '' || $regPlatformRaw !== '') {
            $srcRows .= '<div class="row"><span class="label">注册来源</span><span class="value">'
                . htmlspecialchars($pfShort !== '' ? $pfShort : $regPlatformRaw) . '</span></div>';
        }
        if ($regLinkRaw !== '') {
            $srcRows .= '<div class="row"><span class="label">推广链接</span><span class="value">' . htmlspecialchars($regLinkRaw) . '</span></div>';
        }
        if ($regPxRaw !== '') {
            $srcRows .= '<div class="row"><span class="label">推广像素</span><span class="value">' . htmlspecialchars($regPxRaw) . '</span></div>';
        }

        // 审核按钮
        $auditBtns = '';
        if ($certStatus === 0) {
            $auditBtns = <<<BTN
            <button class="btn btn-success" onclick="doApprove({$certId})">通过</button>
            <button class="btn btn-danger" onclick="showReject({$certId})">拒绝</button>
            BTN;
        } elseif ($certStatus === -1) {
            $auditBtns = '<span style="color:#999">该用户尚未提交认证</span>';
        }

        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户详情 - {$nickname}</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f5f7fa;color:#333;font-size:14px}
        .container{max-width:900px;margin:0 auto;padding:24px}
        h1{font-size:20px;margin-bottom:20px}
        .card{background:#fff;border-radius:10px;padding:24px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
        .card h2{font-size:16px;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #eee}
        .row{display:flex;margin-bottom:12px}
        .row .label{width:100px;color:#888;flex-shrink:0}
        .row .value{flex:1}
        .img-preview{max-width:320px;max-height:200px;border-radius:6px;border:1px solid #eee;display:block;margin-top:6px}
        .btn{padding:8px 20px;border:none;border-radius:6px;cursor:pointer;font-size:14px;font-weight:500;margin-right:8px}
        .btn-success{background:#00d4aa;color:#fff}
        .btn-danger{background:#ff2d55;color:#fff}
        .btn-back{background:#eee;color:#333}
        .btn:hover{opacity:.85}
        .badge{display:inline-block;padding:3px 12px;border-radius:12px;font-size:12px;font-weight:600}
        .badge-gray{background:#eee;color:#666}
        .badge-yellow{background:rgba(245,158,11,.12);color:#f59e0b}
        .badge-green{background:rgba(0,212,170,.12);color:#00d4aa}
        .badge-red{background:rgba(255,45,85,.12);color:#ff2d55}
        .badge-blue{background:rgba(59,130,246,.12);color:#3b82f6}
        .badge-purple{background:rgba(139,92,246,.12);color:#8b5cf6}
        .reason{color:#ff2d55;font-size:13px;margin-top:8px}
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;align-items:center;justify-content:center}
        .modal-overlay.active{display:flex}
        .modal{background:#fff;border-radius:12px;padding:24px;width:400px}
        .modal h3{margin-bottom:16px}
        .modal textarea{width:100%;height:80px;padding:10px;border:1px solid #ddd;border-radius:6px;resize:vertical;font-size:14px}
        .modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:16px}
        .toast{position:fixed;top:20px;left:50%;transform:translateX(-50%);padding:10px 24px;border-radius:8px;color:#fff;z-index:9999;display:none}
        .toast.success{background:#00d4aa}
        .toast.error{background:#ff2d55}
    </style>
</head>
<body>
<div class="container">
    <h1>用户详情</h1>

    <div class="card">
        <h2>基本信息</h2>
        <div class="row"><span class="label">用户ID</span><span class="value">{$userId}</span></div>
        <div class="row"><span class="label">用户编号</span><span class="value">{$userNo}</span></div>
        <div class="row"><span class="label">昵称</span><span class="value">{$nickname}</span></div>
        <div class="row"><span class="label">会员账号</span><span class="value">{$accountText}{$authSuffix}</span></div>
        <div class="row"><span class="label">会员身份</span><span class="value"><span class="badge badge-{$vipBadgeCls}">{$vipLabel}</span></span></div>
        <div class="row"><span class="label">会员到期</span><span class="value">{$vipExpireText}</span></div>
        <div class="row"><span class="label">代币余额</span><span class="value">{$balance}</span></div>
        <div class="row"><span class="label">状态</span><span class="value">{$userStatus}</span></div>
        <div class="row"><span class="label">注册时间</span><span class="value">{$createdAt}</span></div>
        <div class="row"><span class="label">最后登录</span><span class="value">{$lastLoginText}</span></div>
    </div>

    <div class="card">
        <h2>推广来源</h2>
        {$srcRows}
    </div>

    <div class="card">
        <h2>商家认证</h2>
        <div class="row"><span class="label">状态</span><span class="value"><span class="badge badge-{$badgeCls}">{$certStatusText}</span></span></div>
        <div class="row"><span class="label">认证邮箱</span><span class="value">{$certEmail}</span></div>
        <div class="row"><span class="label">提交时间</span><span class="value">{$certTime}</span></div>
        <div class="row"><span class="label">身份证正面</span><span class="value">{$frontHtml}</span></div>
        <div class="row"><span class="label">身份证背面</span><span class="value">{$backHtml}</span></div>
        <div class="row"><span class="label">拒绝原因</span><span class="value">{$certReason}</span></div>
        <div class="row"><span class="label">操作</span><span class="value">{$auditBtns}</span></div>
    </div>

    <button class="btn btn-back" onclick="history.back()">返回</button>
</div>

<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <h3>拒绝原因</h3>
        <textarea id="rejectReason" placeholder="请输入拒绝原因"></textarea>
        <input type="hidden" id="rejectCertId">
        <div class="modal-actions">
            <button class="btn btn-back" onclick="closeReject()">取消</button>
            <button class="btn btn-danger" onclick="doReject()">确认拒绝</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
function showReject(id) {
    document.getElementById('rejectCertId').value = id;
    document.getElementById('rejectReason').value = '';
    document.getElementById('rejectModal').classList.add('active');
}
function closeReject() {
    document.getElementById('rejectModal').classList.remove('active');
}
function toast(msg, type) {
    var el = document.getElementById('toast');
    el.textContent = msg;
    el.className = 'toast ' + type;
    el.style.display = 'block';
    setTimeout(function(){ el.style.display = 'none'; }, 2000);
}
function doApprove(id) {
    if (!confirm('确认审核通过？')) return;
    fetch('/admin/live.MerchantCertification/approve?id=' + id, { method: 'POST', headers: { 'Accept': 'application/json' } })
    .then(r => r.json()).then(d => {
        if (d.code === 1) { toast('审核通过', 'success'); setTimeout(function(){ location.reload(); }, 800); }
        else { toast(d.msg || '操作失败', 'error'); }
    });
}
function doReject() {
    var id = document.getElementById('rejectCertId').value;
    var reason = document.getElementById('rejectReason').value.trim();
    if (!reason) { toast('请填写拒绝原因', 'error'); return; }
    fetch('/admin/live.MerchantCertification/reject', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
        body: 'id=' + id + '&reason=' + encodeURIComponent(reason)
    }).then(r => r.json()).then(d => {
        if (d.code === 1) { toast('已拒绝', 'success'); setTimeout(function(){ location.reload(); }, 800); }
        else { toast(d.msg || '操作失败', 'error'); }
    });
}
</script>
</body>
</html>
HTML;
        exit;
    }
}
