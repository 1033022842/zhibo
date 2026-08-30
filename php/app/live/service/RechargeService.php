<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\live\model\RechargeChannel;
use app\common\util\StrHelper;
use think\facade\Db;

final class RechargeService
{
    /**
     * 可用渠道列表
     */
    public function channels(): array
    {
        return RechargeChannel::where('status', 1)
            ->order('sort', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 提交充值订单
     */
    public function submitOrder(int $userId, int $channelId, float $amount, string $proofImage = ''): array
    {
        $channel = RechargeChannel::find($channelId);
        if (!$channel || $channel->status != 1) {
            throw new BusinessException(ResultCode::RECORD_NOT_FOUND, '充值渠道不可用');
        }
        if ($amount < (float)$channel->min_amount) {
            throw new BusinessException(ResultCode::PARAM_ERROR, "最低充值 {$channel->min_amount} USDT");
        }

        $orderNo = StrHelper::orderNo('RC');
        // 钻石按用户输入的整数基数计算（随机尾数仅作链上匹配标识）
        $diamondAmount = bcmul((string)floor($amount), (string)$channel->diamond_rate, 2);

        $confirmMode = (string)($channel->confirm_mode ?? 'auto');
        if ($confirmMode === 'auto') {
            $payAmount = $this->uniquePayAmount(floor($amount), $channelId);
            $expireAt = date('Y-m-d H:i:s', time() + 7200);
        } else {
            $payAmount = number_format($amount, 2, '.', '');
            $expireAt = date('Y-m-d H:i:s', time() + 86400);
        }

        $order = [
            'order_no'       => $orderNo,
            'user_id'        => $userId,
            'pay_channel'    => $channel->type,
            'channel_id'     => $channelId,
            'chain_type'     => 'TRC20',
            'pay_amount'     => $payAmount,
            'diamond_amount' => $diamondAmount,
            'proof_image'    => $proofImage,
            'status'         => 0,
            'expire_at'      => $expireAt,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        Db::connect('live_mysql')->table('lp_recharge_order')->insert($order);

        return array_merge($order, ['channel' => $channel->toArray()]);
    }

    /**
     * 审核通过
     */
    public function approve(int $orderId, string $remark = ''): void
    {
        $db = Db::connect('live_mysql');
        $order = $db->table('lp_recharge_order')->where('id', $orderId)->find();
        if (!$order) throw new BusinessException(ResultCode::ORDER_NOT_FOUND);
        if ((int)$order['status'] !== 0) throw new BusinessException(ResultCode::PARAM_ERROR, '订单状态不允许此操作');

        $db->startTrans();
        try {
            $now = date('Y-m-d H:i:s');
            // 更新订单状态
            $db->table('lp_recharge_order')->where('id', $orderId)->update([
                'status'       => 1,
                'paid_at'      => $now,
                'reviewed_at'  => $now,
                'admin_remark' => $remark,
                'updated_at'   => $now,
            ]);

            // 给用户加钻
            $userId = (int)$order['user_id'];
            $amount = (float)$order['diamond_amount'];
            $this->creditDiamond($db, $userId, $amount, (int)$order['id']);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * 审核拒绝
     */
    public function reject(int $orderId, string $remark): void
    {
        $order = Db::connect('live_mysql')->table('lp_recharge_order')
            ->where('id', $orderId)->find();
        if (!$order) throw new BusinessException(ResultCode::ORDER_NOT_FOUND);
        if ((int)$order['status'] !== 0) throw new BusinessException(ResultCode::PARAM_ERROR, '订单状态不允许此操作');

        Db::connect('live_mysql')->table('lp_recharge_order')
            ->where('id', $orderId)->update([
                'status'       => 2,
                'reviewed_at'  => date('Y-m-d H:i:s'),
                'admin_remark' => $remark,
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 用户充值记录
     */
    public function orderList(int $userId, int $page = 1, int $pageSize = 20): array
    {
        $query = Db::connect('live_mysql')->table('lp_recharge_order')
            ->where('user_id', $userId)
            ->order('id', 'desc');

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        $statusMap = [0 => '待审核', 1 => '已通过', 2 => '已拒绝', 3 => '已关闭'];
        foreach ($list as &$row) {
            $row['status_text'] = $statusMap[(int)$row['status']] ?? '未知';
        }

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 生成链上唯一应付金额：整数部分 + 随机 0.01~0.99 尾数（多用户并发充值区分标识）
     */
    private function uniquePayAmount(float $base, int $channelId): string
    {
        $db = Db::connect('live_mysql');
        for ($i = 0; $i < 30; $i++) {
            $cents = random_int(1, 99);
            $amt = bcadd((string)(int)$base, sprintf('0.%02d', $cents), 2);
            $exists = $db->table('lp_recharge_order')
                ->where('channel_id', $channelId)
                ->where('pay_amount', $amt)
                ->where('status', 0)
                ->where('expire_at', '>', date('Y-m-d H:i:s'))
                ->count();
            if (!$exists) {
                return $amt;
            }
        }
        throw new BusinessException(ResultCode::SERVER_ERROR, '下单繁忙请稍后再试');
    }

    /**
     * 链上自动确认入账（由 recharge:scan 扫描任务调用，幂等）
     */
    public function confirmByChain(int $orderId, array $tx): void
    {
        $db = Db::connect('live_mysql');
        $txId = (string)($tx['transaction_id'] ?? '');

        $db->startTrans();
        try {
            $order = $db->table('lp_recharge_order')->where('id', $orderId)->lock(true)->find();
            if (!$order || (int)$order['status'] !== 0) {
                $db->commit();
                return; // 已处理或不存在，幂等返回
            }

            $now = date('Y-m-d H:i:s');
            $db->table('lp_recharge_order')->where('id', $orderId)->update([
                'status'       => 1,
                'paid_at'      => $now,
                'reviewed_at'  => $now,
                'admin_remark' => '链上自动确认 tx=' . $txId,
                'updated_at'   => $now,
            ]);

            $this->creditDiamond($db, (int)$order['user_id'], (float)$order['diamond_amount'], (int)$order['id'], 'USDT链上自动充值');

            $db->commit();
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * 过期未支付订单关闭（status 0 -> 3）
     */
    public function expireOrders(): int
    {
        return Db::connect('live_mysql')->table('lp_recharge_order')
            ->where('status', 0)
            ->where('expire_at', '<', date('Y-m-d H:i:s'))
            ->update(['status' => 3, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    private function creditDiamond($db, int $userId, float $amount, int $bizId, string $remark = '管理员审核充值'): void
    {
        $wallet = $db->table('lp_wallet_account')
            ->where('user_id', $userId)->lock(true)->find();

        if (!$wallet) {
            $db->table('lp_wallet_account')->insert([
                'user_id' => $userId, 'diamond_balance' => 0.00, 'status' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $balanceBefore = 0.00;
        } else {
            $balanceBefore = (float)$wallet['diamond_balance'];
        }

        $balanceAfter = bcadd((string)$balanceBefore, (string)$amount, 2);
        $db->table('lp_wallet_account')
            ->where('user_id', $userId)->update([
                'diamond_balance' => $balanceAfter, 'updated_at' => date('Y-m-d H:i:s'),
            ]);

        $db->table('lp_wallet_ledger')->insert([
            'user_id' => $userId, 'biz_type' => 'recharge', 'direction' => 1,
            'asset_type' => 'diamond', 'amount' => $amount,
            'balance_before' => $balanceBefore, 'balance_after' => $balanceAfter,
            'biz_id' => $bizId, 'remark' => $remark, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
