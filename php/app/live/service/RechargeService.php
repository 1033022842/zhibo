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
        $type  = (string) $channel->type;
        $isEth = $type === 'eth';
        if ($amount < (float)$channel->min_amount) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '最低充值 ' . $channel->min_amount . ($isEth ? ' USD' : ' USDT'));
        }

        $orderNo = StrHelper::orderNo('RC');
        // 钻石按用户输入的整数基数计算（随机尾数仅作链上匹配标识）
        $diamondAmount = bcmul((string)floor($amount), (string)$channel->diamond_rate, 2);

        $confirmMode = (string)($channel->confirm_mode ?? 'auto');
        if ($confirmMode === 'auto') {
            $payAmount = $isEth
                ? $this->uniqueEthPayAmount($amount, $channelId)
                : $this->uniquePayAmount(floor($amount), $channelId);
            $expireAt = date('Y-m-d H:i:s', time() + 7200);
        } else {
            // 人工审核：ETH 按当前汇率折算，不带识别尾数
            $payAmount = $isEth
                ? number_format((float)$this->usdToEth($amount), 8, '.', '')
                : number_format($amount, 2, '.', '');
            $expireAt = date('Y-m-d H:i:s', time() + 86400);
        }

        $order = [
            'order_no'       => $orderNo,
            'user_id'        => $userId,
            'pay_channel'    => $channel->type,
            'channel_id'     => $channelId,
            'chain_type'     => $isEth ? 'ETH' : 'TRC20',
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
     * ETH 兑 USD 汇率（后台 lp_platform_config.eth_usd_rate，缺省 3000）
     */
    private function ethRate(): string
    {
        $rate = (float) WalletService::config('eth_usd_rate', '3000');
        if ($rate <= 0) {
            $rate = 3000.0;
        }
        return (string) $rate;
    }

    /**
     * USD → ETH（保留 6 位小数，最后两位留给识别尾数）
     */
    private function usdToEth(float $usd): string
    {
        return bcdiv((string) $usd, $this->ethRate(), 6);
    }

    /**
     * ETH 渠道的链上唯一应付金额：USD 折算成 ETH + 第 7/8 位随机尾数作识别标识
     */
    private function uniqueEthPayAmount(float $usd, int $channelId): string
    {
        $db   = Db::connect('live_mysql');
        $base = $this->usdToEth($usd);

        for ($i = 0; $i < 30; $i++) {
            $amt = bcadd($base, sprintf('0.000000%02d', random_int(1, 99)), 8);
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
     * 链上自动确认入账（由 recharge:scan / recharge:scan-eth 扫描任务调用，幂等）
     */
    public function confirmByChain(int $orderId, array $tx, string $label = 'USDT链上自动充值'): void
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

            $this->creditDiamond($db, (int)$order['user_id'], (float)$order['diamond_amount'], (int)$order['id'], $label);

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

    /**
     * 充值套餐列表（后台可配，AI 女友端动态下发）
     */
    public function packages(): array
    {
        return Db::connect('live_mysql')->table('lp_recharge_package')
            ->where('status', 1)
            ->order('sort', 'asc')
            ->order('price', 'asc')
            ->select()->toArray();
    }

    /**
     * 网关下单（固定套餐）
     */
    public function submitPackageOrder(int $userId, int $packageId, string $product = '', string $gateway = 'starpay'): array
    {
        $pkg = Db::connect('live_mysql')->table('lp_recharge_package')
            ->where('id', $packageId)->where('status', 1)->find();
        if (!$pkg) {
            throw new BusinessException(ResultCode::RECORD_NOT_FOUND, '充值套餐不存在或已下架');
        }

        $tokens = (int) $pkg['tokens'];
        $amount = number_format((float) $pkg['price'], 2, '.', '');
        if ($gateway === 'flowpay') {
            return $this->createFlowPayOrder($userId, $tokens, $amount, (int) $pkg['id']);
        }

        return $this->createStarPayOrder($userId, $tokens, $amount, $product, (int) $pkg['id']);
    }

    /**
     * 自选点数报价：在相邻两档套餐之间线性插值（与前端算法一致）
     */
    public function quoteByTokens(int $tokens): array
    {
        $list = $this->packages();
        if (!$list) {
            throw new BusinessException(ResultCode::RECORD_NOT_FOUND, '充值套餐未配置');
        }
        usort($list, static fn($a, $b) => (int) $a['tokens'] <=> (int) $b['tokens']);

        $min = (int) $list[0]['tokens'];
        $max = (int) $list[count($list) - 1]['tokens'];
        if ($tokens < $min || $tokens > $max) {
            throw new BusinessException(ResultCode::PARAM_ERROR, "点数需在 {$min}~{$max} 之间");
        }

        $low  = $list[0];
        $high = $list[count($list) - 1];
        foreach ($list as $i => $row) {
            if ((int) $row['tokens'] <= $tokens && ($i + 1) < count($list)) {
                $low  = $row;
                $high = $list[$i + 1];
            }
        }

        $lowTokens  = (int) $low['tokens'];
        $highTokens = (int) $high['tokens'];
        $ratio      = $highTokens > $lowTokens ? ($tokens - $lowTokens) / ($highTokens - $lowTokens) : 0.0;
        $price      = (float) $low['price'] + $ratio * ((float) $high['price'] - (float) $low['price']);
        $bonus      = (int) round((int) $low['bonus_percent'] + $ratio * ((int) $high['bonus_percent'] - (int) $low['bonus_percent']));

        return [
            'tokens'        => $tokens,
            'price'         => number_format(round($price, 2), 2, '.', ''),
            'bonus_percent' => $bonus,
        ];
    }

    /**
     * 网关下单（自选点数）
     */
    public function submitCustomOrder(int $userId, int $tokens, string $product = '', string $gateway = 'starpay'): array
    {
        $quote = $this->quoteByTokens($tokens);

        if ($gateway === 'flowpay') {
            return $this->createFlowPayOrder($userId, (int) $quote['tokens'], $quote['price'], null);
        }

        return $this->createStarPayOrder($userId, (int) $quote['tokens'], $quote['price'], $product, null);
    }

    /**
     * 网关下单（会员订阅）
     *
     * 与充值共用 lp_recharge_order（biz_type=vip 标记），回调时开通会员而非加钻
     */
    public function submitVipOrder(int $userId, int $planId, string $product = '', string $gateway = 'starpay'): array
    {
        $plan = Db::connect('live_mysql')->table('lp_vip_plan')
            ->where('id', $planId)->where('status', 1)->find();
        if (!$plan) {
            throw new BusinessException(ResultCode::RECORD_NOT_FOUND, '会员套餐不存在或已下架');
        }

        $amount = number_format((float) $plan['usd_price'], 2, '.', '');
        $opt    = [
            'biz_type'    => 'vip',
            'vip_plan_id' => (int) $plan['id'],
        ];

        if ($gateway === 'flowpay') {
            return $this->createFlowPayOrder($userId, 0, $amount, null, $opt);
        }

        $min = (float) config('starpay.min_amount');
        if ((float) $amount < $min) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '该套餐金额低于网关最小支付金额 ' . number_format($min, 2));
        }
        $opt['return_url'] = (string) config('starpay.return_url_vip');

        return $this->createStarPayOrder($userId, 0, $amount, $product, null, $opt);
    }

    /**
     * 落库 + 调用网关下单，返回订单（含 pay_url）
     *
     * @param array $opt biz_type / vip_plan_id / return_url
     */
    private function createStarPayOrder(int $userId, int $tokens, string $amount, string $product, ?int $packageId, array $opt = []): array
    {
        $db      = Db::connect('live_mysql');
        $starPay = new StarPayService();
        $product = $product !== '' ? $product : (string) config('starpay.product');
        if (!$starPay->isProductEnabled($product)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '不支持的支付方式');
        }

        $now = date('Y-m-d H:i:s');
        $order = [
            'order_no'       => StrHelper::orderNo('RC'),
            'user_id'        => $userId,
            'pay_channel'    => 'starpay',
            'channel_id'     => 0,
            'package_id'     => $packageId,
            'biz_type'       => (string) ($opt['biz_type'] ?? 'coin'),
            'vip_plan_id'    => $opt['vip_plan_id'] ?? null,
            'chain_type'     => $product,
            'pay_amount'     => number_format((float) $amount, 2, '.', ''),
            'diamond_amount' => $tokens,
            'proof_image'    => '',
            'status'         => 0,
            'expire_at'      => date('Y-m-d H:i:s', time() + ((int) (config('starpay.expire_minutes') ?: 120)) * 60),
            'created_at'     => $now,
            'updated_at'     => $now,
        ];
        $db->table('lp_recharge_order')->insert($order);

        try {
            $gw = $starPay->createDeposit($order['order_no'], $order['pay_amount'], $product, [], (string) ($opt['return_url'] ?? ''));
        } catch (\Throwable $e) {
            $db->table('lp_recharge_order')->where('order_no', $order['order_no'])->update([
                'status'       => 3,
                'admin_remark' => '网关下单失败：' . $e->getMessage(),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
            throw $e;
        }

        $update = [
            'system_ref' => (string) ($gw['system_ref'] ?? ''),
            'pay_url'    => (string) ($gw['payurl'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $db->table('lp_recharge_order')->where('order_no', $order['order_no'])->update($update);

        return array_merge($order, $update, ['product' => $product]);
    }

    /**
     * FlowPay 网关下单（收银台模式，返回 pay_url）
     *
     * @param array $opt biz_type / vip_plan_id / return_url
     */
    private function createFlowPayOrder(int $userId, int $tokens, string $amount, ?int $packageId, array $opt = []): array
    {
        $db = Db::connect('live_mysql');
        $flowPay = new FlowPayService();
        if (!$flowPay->enabled()) {
            throw new BusinessException(ResultCode::PARAM_ERROR, 'FlowPay 支付方式未配置');
        }
        $min = (float) config('flowpay.min_amount');
        if ((float) $amount < $min) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '订单金额低于网关最小支付金额 ' . number_format($min, 2));
        }

        $now = date('Y-m-d H:i:s');
        $order = [
            'order_no'       => StrHelper::orderNo('RC'),
            'user_id'        => $userId,
            'pay_channel'    => 'flowpay',
            'channel_id'     => 0,
            'package_id'     => $packageId,
            'biz_type'       => (string) ($opt['biz_type'] ?? 'coin'),
            'vip_plan_id'    => $opt['vip_plan_id'] ?? null,
            'chain_type'     => 'flowpay',
            'pay_amount'     => number_format((float) $amount, 2, '.', ''),
            'diamond_amount' => $tokens,
            'proof_image'    => '',
            'status'         => 0,
            'expire_at'      => date('Y-m-d H:i:s', time() + ((int) (config('flowpay.expire_minutes') ?: 120)) * 60),
            'created_at'     => $now,
            'updated_at'     => $now,
        ];
        $db->table('lp_recharge_order')->insert($order);

        try {
            $gw = $flowPay->createOrder($order['order_no'], $order['pay_amount']);
        } catch (\Throwable $e) {
            $db->table('lp_recharge_order')->where('order_no', $order['order_no'])->update([
                'status'       => 3,
                'admin_remark' => '网关下单失败：' . $e->getMessage(),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
            throw $e;
        }

        $update = [
            'system_ref' => (string) ($gw['order_sn'] ?? ''),
            'pay_url'    => (string) ($gw['payurl'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $db->table('lp_recharge_order')->where('order_no', $order['order_no'])->update($update);

        return array_merge($order, $update, ['product' => 'flowpay']);
    }

    /**
     * star-pay 异步通知确认入账（幂等）
     */
    public function confirmByGateway(string $orderNo, string $systemRef = '', string $productRef = '', string $gateway = 'star-pay'): array
    {
        $db = Db::connect('live_mysql');

        $db->startTrans();
        try {
            $order = $db->table('lp_recharge_order')->where('order_no', $orderNo)->lock(true)->find();
            if (!$order) {
                $db->commit();
                throw new BusinessException(ResultCode::ORDER_NOT_FOUND);
            }
            if ((int) $order['status'] !== 0) {
                $db->commit();
                return $order; // 已处理，幂等返回
            }

            $now    = date('Y-m-d H:i:s');
            $isVip  = (string) ($order['biz_type'] ?? 'coin') === 'vip';
            $remark = ($isVip ? $gateway . ' 订阅到账 system_ref=' : $gateway . ' 自动到账 system_ref=')
                . $systemRef . ($productRef !== '' ? ' product_ref=' . $productRef : '');

            $db->table('lp_recharge_order')->where('id', $order['id'])->update([
                'status'       => 1,
                'paid_at'      => $now,
                'reviewed_at'  => $now,
                'system_ref'   => $systemRef !== '' ? $systemRef : (string) ($order['system_ref'] ?? ''),
                'admin_remark' => $remark,
                'updated_at'   => $now,
            ]);

            if ($isVip) {
                // 订阅订单：开通/顺延会员（同一订单号只生效一次）
                (new VipService())->activate(
                    (int) $order['user_id'],
                    (int) $order['vip_plan_id'],
                    (string) $order['order_no'],
                    (float) $order['pay_amount']
                );
            } else {
                $this->creditDiamond($db, (int) $order['user_id'], (float) $order['diamond_amount'], (int) $order['id'], 'star-pay充值');
            }

            $db->commit();
            $order['status'] = 1;
            return $order;
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
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
