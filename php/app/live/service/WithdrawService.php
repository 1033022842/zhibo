<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\common\util\StrHelper;
use think\facade\Db;

/**
 * 提现服务（人工打款模式：申请冻结 → 后台审核 → 通过确认/拒绝退钻）
 */
final class WithdrawService
{
    /**
     * 查/绑收款账户（前置：商家认证通过）
     */
    public function account(int $userId): array
    {
        $this->assertMerchantCertified($userId);
        $row = Db::connect('live_mysql')->table('lp_payout_account')
            ->where('user_id', $userId)->find();
        return $row
            ? ['network' => (string)$row['network'], 'address' => (string)$row['address']]
            : ['network' => 'TRC20', 'address' => ''];
    }

    public function saveAccount(int $userId, string $network, string $address): void
    {
        $this->assertMerchantCertified($userId);
        $network = in_array($network, ['TRC20', 'ERC20', 'BEP20'], true) ? $network : 'TRC20';
        $address = trim($address);
        if (strlen($address) < 20 || strlen($address) > 100) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '收款地址格式不正确');
        }

        Db::connect('live_mysql')->table('lp_payout_account')
            ->replace(['user_id' => $userId, 'network' => $network, 'address' => $address, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * 提现试算
     */
    public function preview(int $userId, float $diamonds): array
    {
        $this->assertMerchantCertified($userId);
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
        $this->assertMerchantCertified($userId);

        $p = $this->preview($userId, $diamonds);
        if ($diamonds < $p['min_diamonds']) {
            throw new BusinessException(ResultCode::PARAM_ERROR, "最低提现 {$p['min_diamonds']} 钻");
        }
        if ($diamonds > $p['balance']) {
            throw new BusinessException(ResultCode::BALANCE_NOT_ENOUGH, '可提余额不足');
        }

        $account = $this->account($userId);
        if ($account['address'] === '') {
            throw new BusinessException(ResultCode::PARAM_ERROR, '请先绑定收款地址');
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
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            (new WalletService())->credit($userId, $diamonds, 'withdraw_refund', '提现下单失败退回');
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
            $r['status_text'] = $map[(int)$r['status']] ?? 'Unknown';
            $r['reject_reason'] = $r['reject_reason'] ?: '';
        }
        return ['list' => $list, 'total' => $total];
    }

    /**
     * 后台审核通过（人工打款后确认）
     */
    public function approve(int $id, string $txHash = ''): void
    {
        $db = Db::connect('live_mysql');
        $w = $db->table('lp_withdrawal')->where('id', $id)->find();
        if (!$w || (int)$w['status'] !== 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '记录不存在或已处理');
        }
        $db->table('lp_withdrawal')->where('id', $id)->update([
            'status'     => 1,
            'tx_hash'    => $txHash,
            'paid_at'    => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 后台拒绝 → 自动退钻
     */
    public function reject(int $id, string $reason): void
    {
        $db = Db::connect('live_mysql');
        $w = $db->table('lp_withdrawal')->where('id', $id)->find();
        if (!$w || (int)$w['status'] !== 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '记录不存在或已处理');
        }

        $db->table('lp_withdrawal')->where('id', $id)->update([
            'status'        => 2,
            'reject_reason' => $reason,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
        (new WalletService())->credit((int)$w['user_id'], (float)$w['diamond_amount'], 'withdraw_refund', '提现被拒绝退回');
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

    private function assertMerchantCertified(int $userId): void
    {
        $cert = Db::connect('live_mysql')->table('lp_merchant_certification')
            ->where('user_id', $userId)->find();
        if (!$cert || (int)$cert['status'] !== 1) {
            throw new BusinessException(
                ResultCode::CERTIFICATION_NOT_FOUND,
                '需先通过商家认证才能提现'
            );
        }
    }
}
