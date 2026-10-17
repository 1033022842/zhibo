<?php

namespace app\admin\controller\user;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\live\LiveUser as LiveUserModel;
use app\live\service\UserService;

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
    protected array $noNeedPermission = ['adjustDiamond', 'vipPlans', 'grantVip', 'detail', 'invitees'];

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
                live_user.reg_domain, live_user.reg_channel, live_user.reg_link_name, live_user.reg_platform, live_user.reg_px, live_user.reg_channel_name,
                live_user.invite_code,
                IFNULL((SELECT ir.inviter_id FROM lp_invite_record ir WHERE ir.invitee_id = live_user.id LIMIT 1), 0) as inviter_id,
                IFNULL((SELECT iu.nickname FROM lp_invite_record ir JOIN lp_user iu ON iu.id = ir.inviter_id WHERE ir.invitee_id = live_user.id LIMIT 1), \'\') as inviter_nickname,
                IFNULL((SELECT iu.user_no FROM lp_invite_record ir JOIN lp_user iu ON iu.id = ir.inviter_id WHERE ir.invitee_id = live_user.id LIMIT 1), \'\') as inviter_user_no,
                IFNULL((SELECT ir.registered_at FROM lp_invite_record ir WHERE ir.invitee_id = live_user.id LIMIT 1), \'\') as inviter_bound_at,
                IFNULL((SELECT COUNT(*) FROM lp_invite_record ir2 WHERE ir2.inviter_id = live_user.id), 0) as invite_count,
                IFNULL((SELECT COUNT(*) FROM lp_invite_record ir3 WHERE ir3.inviter_id = live_user.id AND ir3.premium_at IS NOT NULL), 0) as invite_premium_count,
                IFNULL((SELECT SUM(ir4.reward_tokens) FROM lp_invite_record ir4 WHERE ir4.inviter_id = live_user.id), 0) as invite_reward_tokens,
                IFNULL(wallet.diamond_balance, 0) as diamond_balance,
                IFNULL((SELECT au.auth_key FROM lp_user_auth au WHERE au.user_id = live_user.id ORDER BY au.id ASC LIMIT 1), \'\') as auth_account,
                IFNULL((SELECT au.auth_type FROM lp_user_auth au WHERE au.user_id = live_user.id ORDER BY au.id ASC LIMIT 1), \'\') as auth_type,
                IFNULL(lp_user_profile.last_login_ip, \'\') as last_login_ip,
                IFNULL(lp_user_profile.last_login_at, NULL) as last_login_at,
                IFNULL(lp_user_profile.last_active_at, NULL) as last_active_at,
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

        // 像素名映射：reg_px 存的是 lp_promo_pixel.id 或像素ID字符串，两种都映射一份（仅用于悬浮明细展示）
        $pixelNames = [];
        foreach (Db::connect('live_mysql')->table('lp_promo_pixel')->field('id,pixel_id,name')->select()->toArray() as $p) {
            $pn = trim((string) $p['name']);
            if ($pn === '') {
                continue;
            }
            $pixelNames[(string) $p['id']] = $pn;
            if ((string) $p['pixel_id'] !== '') {
                $pixelNames[(string) $p['pixel_id']] = $pn;
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

            // 在线/离线：最近有过带 token 的接口请求（窗口见 UserService::ONLINE_WINDOW）
            $activeAt = (string) ($row['last_active_at'] ?? '');
            $row['online']       = $activeAt !== '' && (time() - strtotime($activeAt)) <= UserService::ONLINE_WINDOW;
            $row['online_label'] = $row['online'] ? '在线' : '离线';

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
            // 渠道名称取注册时的快照，渠道配置被改名/删除后历史会员归属不再变化
            $pxKey    = (string) ($row['reg_px'] ?? '');
            $pxName   = ($pxKey !== '' && isset($pixelNames[$pxKey])) ? $pixelNames[$pxKey] : '';
            $snapName = (string) ($row['reg_channel_name'] ?? '');
            $channelName = $snapName !== '' ? $snapName : ($regLink !== '' ? $regLink : ($pxName !== '' ? $pxName : $regChannel));
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

            // 上下级（邀请关系）
            //   上级 = 邀请我的人（lp_invite_record.inviter_id），无记录即自然注册
            //   下级 = 我邀请来的人（lp_invite_record.inviter_id = 我）
            $inviterId   = (int) ($row['inviter_id'] ?? 0);
            $inviterName = trim((string) ($row['inviter_nickname'] ?? ''));
            $inviterNo   = trim((string) ($row['inviter_user_no'] ?? ''));
            $inviterAt   = trim((string) ($row['inviter_bound_at'] ?? ''));
            if ($inviterId > 0) {
                $row['inviter_label'] = $inviterName !== '' ? $inviterName : ('用户#' . $inviterId);
                $it = ['用户ID：' . $inviterId];
                if ($inviterNo !== '') { $it[] = '编号：' . $inviterNo; }
                if ($inviterAt !== '') { $it[] = '邀请绑定：' . $inviterAt; }
                $row['inviter_detail'] = implode("\n", $it);
            } else {
                $row['inviter_label']  = '';
                $row['inviter_detail'] = '';
            }

            $inviteCount = (int) ($row['invite_count'] ?? 0);
            $row['invite_count']         = $inviteCount;
            $row['invite_premium_count'] = (int) ($row['invite_premium_count'] ?? 0);
            $row['invite_reward_tokens'] = (float) ($row['invite_reward_tokens'] ?? 0);
            $row['invite_code']          = trim((string) ($row['invite_code'] ?? ''));
            $it = ['下级总数：' . $inviteCount . ' 人', '其中已升级会员：' . $row['invite_premium_count'] . ' 人'];
            if ($row['invite_reward_tokens'] > 0) { $it[] = '累计邀请奖励：' . $row['invite_reward_tokens'] . ' 代币'; }
            if ($row['invite_code'] !== '') { $it[] = '我的邀请码：' . $row['invite_code']; }
            $row['invite_detail'] = implode("\n", $it);
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

    /**
     * 删除会员
     * 前端：DELETE /admin/user.LiveUser/del?ids[]=1&ids[]=2
     *
     * 删除账号与其个人数据；充值/会员订单、钱包流水、提现等财务记录保留，便于对账追溯。
     */
    public function del(): void
    {
        $ids = array_values(array_filter(
            array_map('intval', (array) $this->request->param('ids/a', [])),
            static fn($v) => $v > 0
        ));
        if ($ids === []) {
            $this->error(__('Parameter error'));
        }

        // 会员个人数据（均以 user_id 关联）
        $userTables = [
            'lp_user_profile', 'lp_user_auth', 'lp_user_setting', 'lp_user_device', 'lp_user_quota',
            'lp_user_item', 'lp_user_persona', 'lp_wallet_account', 'lp_merchant_certification',
            'lp_payout_account', 'lp_ai_affection', 'lp_ai_chat_message', 'lp_ai_collect',
            'lp_ai_media_unlock', 'lp_ai_task', 'lp_chat_message', 'lp_post_like',
            'lp_like_action_log', 'lp_creator_submission',
        ];

        $db = Db::connect('live_mysql');
        $db->startTrans();
        try {
            foreach ($userTables as $table) {
                $db->table($table)->whereIn('user_id', $ids)->delete();
            }
            // 邀请关系：他邀请的人 + 邀请他的人，两个方向都要清掉
            $db->table('lp_invite_record')
                ->where(function ($query) use ($ids) {
                    $query->whereIn('inviter_id', $ids)->whereOr('invitee_id', 'in', $ids);
                })
                ->delete();
            $count = $db->table('lp_user')->whereIn('id', $ids)->delete();
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            $this->error($e->getMessage());
            return;
        }

        if (!$count) {
            $this->error(__('No rows were deleted'));
        }
        $this->success('已删除 ' . $count . ' 个会员');
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
     * 某会员的「下级」列表（他邀请来的会员）
     * POST /admin/user.LiveUser/invitees
     * @param int id 上级用户ID
     */
    public function invitees(): void
    {
        $userId = $this->request->param('id/d', 0);
        if ($userId <= 0) {
            $this->error(__('Parameter error'));
        }

        $rows = Db::connect('live_mysql')->table('lp_invite_record')->alias('ir')
            ->join('lp_user u', 'u.id = ir.invitee_id')
            ->where('ir.inviter_id', $userId)
            ->field('ir.invitee_id, ir.invite_code, ir.registered_at, ir.premium_at, ir.reward_tokens,
                     u.nickname, u.user_no, u.vip_expire_at')
            ->order('ir.id', 'desc')
            ->limit(500)
            ->select()
            ->toArray();

        $now  = time();
        $list = [];
        foreach ($rows as $r) {
            $expire = (string) ($r['vip_expire_at'] ?? '');
            $isVip  = $expire !== '' && strtotime($expire) > $now;
            $list[] = [
                'user_id'       => (int) $r['invitee_id'],
                'nickname'      => (string) ($r['nickname'] ?? ''),
                'user_no'       => (string) ($r['user_no'] ?? ''),
                'invite_code'   => (string) ($r['invite_code'] ?? ''),
                'registered_at' => (string) ($r['registered_at'] ?? ''),
                'premium_at'    => (string) ($r['premium_at'] ?? ''),
                'reward_tokens' => (float) ($r['reward_tokens'] ?? 0),
                'vip_label'     => $isVip ? 'Premium' : 'Free',
            ];
        }

        $this->success('', ['list' => $list, 'total' => count($list)]);
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
        $lastIpText    = $lastIp !== '' ? htmlspecialchars($lastIp) : '-';

        // 推广来源
        $regDomainRaw   = (string) ($user->reg_domain ?? '');
        $regLinkRaw     = (string) ($user->reg_link_name ?? '');
        $regPlatformRaw = (string) ($user->reg_platform ?? '');
        $regPxRaw       = (string) ($user->reg_px ?? '');

        // 渠道名称：优先注册时快照，保证渠道配置被改名/删除后归属展示不变
        $channelName = trim((string) ($user->reg_channel_name ?? ''));
        if ($channelName === '') {
            $channelName = $regLinkRaw;
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

        // 订阅记录（lp_vip_order：代币购买 / 网关付款 / 后台手动开通 三类都在这里）
        $vipOrders = Db::connect('live_mysql')->table('lp_vip_order')->alias('o')
            ->join('lp_vip_plan p', 'p.id = o.plan_id', 'LEFT')
            ->where('o.user_id', $userId)
            ->field('o.order_no, o.diamond_amount, o.expire_from, o.expire_to, o.status, o.created_at, p.name as plan_name')
            ->order('o.id', 'desc')->limit(50)->select()->toArray();

        $vipAutoRenew    = (int) ($user->vip_auto_renew ?? 1) === 1;
        $vipCancelAt     = trim((string) ($user->vip_cancel_at ?? ''));
        $vipCancelReason = trim((string) ($user->vip_cancel_reason ?? ''));
        $vipCancelDesc   = trim((string) ($user->vip_cancel_description ?? ''));

        $vipBlock = '<div class="row"><span class="label">自动续订</span><span class="value">'
            . ($vipAutoRenew
                ? '<span class="badge badge-blue">是</span>'
                : '<span class="badge badge-gray">否（已取消续订）</span>')
            . '</span></div>';
        if ($vipCancelAt !== '') {
            $vipBlock .= '<div class="row"><span class="label">取消时间</span><span class="value">' . htmlspecialchars($vipCancelAt) . '</span></div>';
        }
        if ($vipCancelReason !== '') {
            $vipBlock .= '<div class="row"><span class="label">取消原因</span><span class="value">' . htmlspecialchars($vipCancelReason)
                . ($vipCancelDesc !== '' ? '：' . htmlspecialchars($vipCancelDesc) : '') . '</span></div>';
        }

        $srcOf = static function (string $no): string {
            if (str_starts_with($no, 'ADMIN')) { return '后台开通'; }
            if (str_starts_with($no, 'RC'))    { return '网关/链上'; }
            if (str_starts_with($no, 'VIP'))   { return '代币购买'; }
            return '其它';
        };
        $vipRows = '';
        foreach ($vipOrders as $vo) {
            $expTo = (string) ($vo['expire_to'] ?? '');
            $ok    = $expTo !== '' && strtotime($expTo) > time();
            $vipRows .= '<tr>'
                . '<td>' . htmlspecialchars((string) ($vo['created_at'] ?? '')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($vo['order_no'] ?? '')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($vo['plan_name'] ?? '-')) . '</td>'
                . '<td>' . number_format((float) ($vo['diamond_amount'] ?? 0), 2) . '</td>'
                . '<td>' . $srcOf((string) ($vo['order_no'] ?? '')) . '</td>'
                . '<td>' . htmlspecialchars($expTo) . '</td>'
                . '<td>' . ($ok ? '生效中' : '已到期') . '</td>'
                . '</tr>';
        }
        if ($vipRows === '') {
            $vipRows = '<tr><td colspan="7" style="text-align:center;color:#999;padding:16px">暂无订阅/开通记录</td></tr>';
        }
        $vipBlock .= '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:13px;margin-top:12px">'
            . '<thead><tr style="color:#888">'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #eee;font-weight:500">支付时间</th>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #eee;font-weight:500">订单号</th>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #eee;font-weight:500">套餐</th>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #eee;font-weight:500">实付代币</th>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #eee;font-weight:500">来源</th>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #eee;font-weight:500">到期时间</th>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #eee;font-weight:500">状态</th>'
            . '</tr></thead><tbody>' . $vipRows . '</tbody></table></div>';

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
        <div class="row"><span class="label">登入IP</span><span class="value" style="word-break:break-all">{$lastIpText}</span></div>
    </div>

    <div class="card">
        <h2>推广来源</h2>
        {$srcRows}
    </div>

    <div class="card">
        <h2>订阅记录（最近 50 条）</h2>
        {$vipBlock}
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
