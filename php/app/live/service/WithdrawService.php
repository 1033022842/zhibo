<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\common\util\StrHelper;
use think\facade\Db;

/**
 * 提现服务
 *
 * 主流程：申请冻结代币 → 后台审核 → 链上自动打款（或人工打款登记 TxHash）→ 链上确认 → 完成
 * 拒绝：退回冻结的代币
 *
 * 打款子状态（lp_withdrawal.pay_state）：
 *   0 待打款 / 1 已广播待确认 / 2 已确认到账 / 3 打款失败 / 4 打款处理中
 * 单据状态（lp_withdrawal.status）：
 *   0 待审核(含打款中) / 1 打款完成 / 2 已拒绝
 */
final class WithdrawService
{
    public const PAY_PENDING    = 0;
    public const PAY_BROADCAST  = 1;
    public const PAY_CONFIRMED  = 2;
    public const PAY_FAILED     = 3;
    public const PAY_PROCESSING = 4;

    private const PAY_STATE_TEXT = [
        self::PAY_PENDING    => 'Pending payout',
        self::PAY_BROADCAST  => 'Broadcasting',
        self::PAY_CONFIRMED  => 'Paid',
        self::PAY_FAILED     => 'Payout failed',
        self::PAY_PROCESSING => 'Processing',
    ];

    public function chain(): ChainPayoutService
    {
        return new ChainPayoutService();
    }

    /**
     * 查/绑收款账户
     */
    public function account(int $userId): array
    {
        $row = Db::connect('live_mysql')->table('lp_payout_account')
            ->where('user_id', $userId)->find();
        return $row
            ? ['network' => (string)$row['network'], 'address' => (string)$row['address']]
            : ['network' => 'TRC20', 'address' => ''];
    }

    public function saveAccount(int $userId, string $network, string $address): void
    {
        $network = in_array($network, ['TRC20', 'ERC20', 'BEP20'], true) ? $network : 'TRC20';
        $address = trim($address);

        $err = ChainPayoutService::addressError($network, $address);
        if ($err !== '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, $err);
        }

        // 一个用户一条收款账户记录：存在则更新，不存在则插入
        // （think-orm 的 replace() 是「改用 REPLACE INTO」的布尔开关，不能直接塞数据）
        $db  = Db::connect('live_mysql');
        $now = date('Y-m-d H:i:s');
        $row = $db->table('lp_payout_account')->where('user_id', $userId)->find();
        if ($row) {
            $db->table('lp_payout_account')->where('user_id', $userId)
                ->update(['network' => $network, 'address' => $address, 'updated_at' => $now]);
        } else {
            $db->table('lp_payout_account')->insert([
                'user_id' => $userId, 'network' => $network, 'address' => $address, 'updated_at' => $now,
            ]);
        }
    }

    /**
     * 提现试算
     */
    public function preview(int $userId, float $diamonds): array
    {
        $rate = (float)WalletService::config('diamond_to_usdt_rate', '0.01');
        $feeRate = (float)WalletService::config('withdraw_fee_rate', '0.01');
        $min = (float)WalletService::config('min_withdraw_diamond', '1000');

        $usdt = round($diamonds * $rate, 6);
        $fee = round($usdt * $feeRate, 6);
        return [
            'diamonds'      => $diamonds,
            'usdt'          => $usdt,
            'fee'           => $fee,
            'actual'        => round($usdt - $fee, 6),
            'rate'          => $rate,
            'fee_rate'      => $feeRate,
            'min_diamonds'  => $min,
            'balance'       => (new WalletService())->balance($userId),
        ];
    }

    /**
     * 发起提现：扣钻冻结 + 建单待审
     */
    public function apply(int $userId, float $diamonds): array
    {
        $p = $this->preview($userId, $diamonds);
        if ($diamonds < $p['min_diamonds']) {
            throw new BusinessException(ResultCode::PARAM_ERROR, "最低提现 {$p['min_diamonds']} 代币");
        }
        if ($diamonds > $p['balance']) {
            throw new BusinessException(ResultCode::BALANCE_NOT_ENOUGH, '可提余额不足');
        }

        $account = $this->account($userId);
        if ($account['address'] === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, '请先绑定收款地址');
        }
        $addrErr = ChainPayoutService::addressError((string) $account['network'], (string) $account['address']);
        if ($addrErr !== '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, '收款地址无效：' . $addrErr);
        }

        // 冻结扣钻
        (new WalletService())->debit($userId, $diamonds, 'withdraw', 0, '提现申请冻结');

        try {
            $orderNo = StrHelper::orderNo('WD');
            Db::connect('live_mysql')->table('lp_withdrawal')->insert([
                'order_no'       => $orderNo,
                'user_id'        => $userId,
                'diamond_amount' => $diamonds,
                'usdt_amount'    => $p['usdt'],
                'fee_usdt'       => $p['fee'],
                'actual_usdt'    => $p['actual'],
                'network'        => $account['network'],
                'address'        => $account['address'],
                'status'         => 0,
                'pay_state'      => self::PAY_PENDING,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            (new WalletService())->credit($userId, $diamonds, 'withdraw_refund', 0, '提现下单失败退回');
            throw new BusinessException(ResultCode::SERVER_ERROR, '提现申请失败: ' . $e->getMessage());
        }

        return [
            'order_no'    => $orderNo,
            'usdt'        => $p['usdt'],
            'fee'         => $p['fee'],
            'actual'      => $p['actual'],
            'status_text' => 'Under review',
        ];
    }

    /**
     * 我的提现记录
     */
    public function records(int $userId, int $page = 1, int $pageSize = 20): array
    {
        $query = Db::connect('live_mysql')->table('lp_withdrawal')
            ->where('user_id', $userId)->order('id', 'desc');
        $total = $query->count();
        $list = $query->page($page, $pageSize)->select()->toArray();

        $map = [0 => 'Under review', 1 => 'Approved', 2 => 'Rejected'];
        foreach ($list as &$r) {
            $payState = (int)($r['pay_state'] ?? 0);
            $r['status_text'] = $map[(int)$r['status']] ?? 'Unknown';
            $r['pay_state'] = $payState;
            $r['pay_state_text'] = self::PAY_STATE_TEXT[$payState] ?? 'Unknown';
            $r['reject_reason'] = $r['reject_reason'] ?: '';
            $r['tx_hash'] = (string)($r['tx_hash'] ?? '');
        }
        return ['list' => $list, 'total' => $total];
    }

    /**
     * 后台审核通过
     *
     * - 传了 $txHash：按人工打款处理，登记哈希并直接标记完成
     * - 未传：走链上自动打款（需 [PAYOUT] MODE=auto 且该网络已配置热钱包私钥）
     *
     * @return array 打款结果
     */
    public function approve(int $id, string $txHash = ''): array
    {
        $db = Db::connect('live_mysql');
        $w  = $db->table('lp_withdrawal')->where('id', $id)->find();
        if (!$w) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '提现记录不存在');
        }
        if ((int) $w['status'] !== 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '记录已处理');
        }

        if (trim($txHash) !== '') {
            $now = date('Y-m-d H:i:s');
            $db->table('lp_withdrawal')->where('id', $id)->update([
                'status'       => 1,
                'pay_state'    => self::PAY_CONFIRMED,
                'tx_hash'      => trim($txHash),
                'pay_error'    => '',
                'paid_at'      => $now,
                'confirmed_at' => $now,
                'updated_at'   => $now,
            ]);
            return ['id' => $id, 'pay_state' => self::PAY_CONFIRMED, 'tx_hash' => trim($txHash), 'mode' => 'manual'];
        }

        // 配置类问题（没开自动打款 / 没配私钥）先拦下来，不要动单据状态：
        // 否则运营只是漏填 TxHash，单据就被标成「打款失败」、「确认打款」按钮也跟着消失了
        $chain   = $this->chain();
        $network = (string) $w['network'];
        if (!$chain->isAutoEnabled()) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '未开启自动打款：请先人工链上转账并在弹窗里填写 TxHash，或在 .env [PAYOUT] 设置 MODE = auto');
        }
        if (!$chain->support($network)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, $network . ' 未配置热钱包私钥：请人工链上转账并在弹窗里填写 TxHash');
        }

        return $this->attemptPayout($id);
    }

    /**
     * 手动重试打款（打款失败后由运营确认热钱包无出账再点）
     */
    public function retry(int $id): array
    {
        $w = Db::connect('live_mysql')->table('lp_withdrawal')->where('id', $id)->find();
        if (!$w) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '提现记录不存在');
        }
        if ((int) $w['status'] !== 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '记录已处理');
        }
        if ((int) $w['pay_state'] === self::PAY_BROADCAST) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '该单已广播到链上，请等待确认，勿重复打款');
        }
        return $this->attemptPayout($id);
    }

    /**
     * 领单并执行一次链上打款
     *
     * 用「原子占位」把 pay_state 从 待打款/失败 改成 处理中，
     * 只有抢占成功的请求才会真正发起转账，避免并发重复打款。
     */
    private function attemptPayout(int $id): array
    {
        $db      = Db::connect('live_mysql');
        $chain   = $this->chain();
        $now     = date('Y-m-d H:i:s');

        $claimed = $db->table('lp_withdrawal')
            ->where('id', $id)
            ->where('status', 0)
            ->whereIn('pay_state', [self::PAY_PENDING, self::PAY_FAILED])
            ->update([
                'pay_state'    => self::PAY_PROCESSING,
                'pay_attempts' => Db::raw('pay_attempts + 1'),
                'updated_at'   => $now,
            ]);
        if ($claimed === 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '提现单不存在、已在处理中或已打款完成');
        }

        $w = $db->table('lp_withdrawal')->where('id', $id)->find();
        if (!$w) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '提现记录不存在');
        }
        if ((int) $w['pay_attempts'] > $chain->maxAttempts()) {
            $this->markPayFailed($id, '已超过最大自动打款次数（' . $chain->maxAttempts() . '），请人工核对');
            throw new BusinessException(ResultCode::PARAM_ERROR, '已超过最大自动打款次数');
        }

        $network = (string) $w['network'];
        $address = (string) $w['address'];
        $amount  = (string) $w['actual_usdt'];

        $addrErr = ChainPayoutService::addressError($network, $address);
        if ($addrErr !== '') {
            $this->markPayFailed($id, '收款地址无效：' . $addrErr);
            throw new BusinessException(ResultCode::PARAM_ERROR, $addrErr);
        }

        if (!$chain->isAutoEnabled()) {
            $this->markPayFailed($id, '自动打款未开启：请在 .env [PAYOUT] 设置 MODE = auto');
            throw new BusinessException(ResultCode::PARAM_ERROR, '自动打款未开启，请人工转账后在后台登记 TxHash');
        }
        if (!$chain->support($network)) {
            $this->markPayFailed($id, $network . ' 未配置热钱包私钥，无法自动打款');
            throw new BusinessException(ResultCode::PARAM_ERROR, $network . ' 未配置热钱包私钥');
        }

        try {
            $result = $chain->payout($network, $address, $amount);
        } catch (\Throwable $e) {
            $this->markPayFailed($id, $e->getMessage());
            throw $e;
        }

        $db->table('lp_withdrawal')->where('id', $id)->update([
            'pay_state'    => self::PAY_BROADCAST,
            'tx_hash'      => $result['tx_hash'],
            'raw_tx'       => $result['raw_tx'],
            'pay_error'    => '',
            'broadcast_at' => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        return [
            'id'          => $id,
            'pay_state'   => self::PAY_BROADCAST,
            'tx_hash'     => $result['tx_hash'],
            'status_text' => self::PAY_STATE_TEXT[self::PAY_BROADCAST],
        ];
    }

    /**
     * 轮询已广播的交易，确认到账后置为「打款完成」
     *
     * 长时间未确认只提示、不改状态：一旦误判失败，运营再点重试就会重复打款。
     */
    public function confirmBroadcast(int $limit = 30): array
    {
        $db = Db::connect('live_mysql');
        $rows = $db->table('lp_withdrawal')
            ->where('status', 0)
            ->where('pay_state', self::PAY_BROADCAST)
            ->where('tx_hash', '<>', '')
            ->where('broadcast_at', '<', date('Y-m-d H:i:s', time() - 20))
            ->order('id', 'asc')
            ->limit($limit)
            ->select()->toArray();

        $stat = ['checked' => 0, 'confirmed' => 0, 'failed' => 0, 'pending' => 0];
        $chain = $this->chain();

        foreach ($rows as $w) {
            $stat['checked']++;
            $id  = (int) $w['id'];
            $res = $chain->confirm((string) $w['network'], (string) $w['tx_hash']);

            if ($res['state'] === 'success') {
                $now = date('Y-m-d H:i:s');
                $db->table('lp_withdrawal')->where('id', $id)->update([
                    'status'       => 1,
                    'pay_state'    => self::PAY_CONFIRMED,
                    'pay_error'    => '',
                    'paid_at'      => $now,
                    'confirmed_at' => $now,
                    'updated_at'   => $now,
                ]);
                $stat['confirmed']++;
                continue;
            }

            if ($res['state'] === 'failed') {
                $this->markPayFailed($id, '链上交易失败：' . $res['message'] . '（TxHash ' . $w['tx_hash'] . '）');
                $stat['failed']++;
                continue;
            }

            if (strtotime((string) $w['broadcast_at']) < time() - 1800) {
                $db->table('lp_withdrawal')->where('id', $id)->update([
                    'pay_error'  => '链上超过 30 分钟未确认，请人工核对 TxHash ' . $w['tx_hash'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            $stat['pending']++;
        }

        return $stat;
    }

    /**
     * 回收卡在「处理中」的单据（进程被杀 / 超时）
     *
     * 只有确认没有广播过（无 tx_hash）才放回「打款失败」等人工重试；
     * 已广播过的一律转「已广播待确认」，以免重复打款。
     */
    public function reclaimStale(int $minutes = 10): int
    {
        $db    = Db::connect('live_mysql');
        $stale = $db->table('lp_withdrawal')
            ->where('status', 0)
            ->where('pay_state', self::PAY_PROCESSING)
            ->where('updated_at', '<', date('Y-m-d H:i:s', time() - $minutes * 60))
            ->select()->toArray();

        foreach ($stale as $w) {
            $now = date('Y-m-d H:i:s');
            if (trim((string) $w['tx_hash']) !== '') {
                $db->table('lp_withdrawal')->where('id', $w['id'])->update([
                    'pay_state'    => self::PAY_BROADCAST,
                    'broadcast_at' => $w['broadcast_at'] ?: $now,
                    'updated_at'   => $now,
                ]);
                continue;
            }
            $db->table('lp_withdrawal')->where('id', $w['id'])->update([
                'pay_state'  => self::PAY_FAILED,
                'pay_error'  => '打款流程被中断，请在热钱包核对无出账后再重试',
                'updated_at' => $now,
            ]);
        }

        return count($stale);
    }

    /**
     * 后台拒绝 → 自动退钻
     *
     * 已广播/已确认的单据不允许拒绝，否则会「既打款又退钻」。
     */
    public function reject(int $id, string $reason): void
    {
        $db = Db::connect('live_mysql');
        $w = $db->table('lp_withdrawal')->where('id', $id)->find();
        if (!$w || (int)$w['status'] !== 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '记录不存在或已处理');
        }
        if (in_array((int) ($w['pay_state'] ?? 0), [self::PAY_BROADCAST, self::PAY_PROCESSING], true)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '该单已进入链上打款流程，不能再拒绝，请先核对链上交易');
        }

        $db->table('lp_withdrawal')->where('id', $id)->update([
            'status'        => 2,
            'reject_reason' => $reason,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
        (new WalletService())->credit((int)$w['user_id'], (float)$w['diamond_amount'], 'withdraw_refund', 0, '提现被拒绝退回');
    }

    /**
     * 待审核列表（后台）
     */
    public function pendingList(string $keyword = '', int $status = -1): array
    {
        $query = Db::connect('live_mysql')->table('lp_withdrawal w')
            ->join('lp_user u', 'u.id = w.user_id', 'LEFT')
            ->field('w.*, u.nickname');
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('w.order_no', 'like', "%{$keyword}%")->whereOr('w.address', 'like', "%{$keyword}%");
            });
        }
        if ($status >= 0) {
            $query->where('w.status', $status);
        }
        return $query->order('w.id', 'desc')->paginate(15)->toArray();
    }

    private function markPayFailed(int $id, string $error): void
    {
        Db::connect('live_mysql')->table('lp_withdrawal')->where('id', $id)->update([
            'pay_state'  => self::PAY_FAILED,
            'pay_error'  => mb_substr($error, 0, 255),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
