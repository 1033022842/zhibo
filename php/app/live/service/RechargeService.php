<?php
declare(strict_types=1);

namespace app\live\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\live\model\RechargeChannel;
use app\common\util\StrHelper;
use think\facade\Db;
use think\facade\Log;

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
        // status=3 允许放行：链上转账迟到、订单已被 expireOrders() 关掉时，
        // 运营核对到账后仍要能人工补单，否则用户的钱就白付了
        if (!in_array((int)$order['status'], [0, 3], true)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '订单状态不允许此操作');
        }

        $db->startTrans();
        try {
            $now = date('Y-m-d H:i:s');
            // 人工审核通过 = 人工已核对到账，支付状态一并置为已支付
            $db->table('lp_recharge_order')->where('id', $orderId)->update([
                'status'         => 1,
                'paid_at'        => $now,
                'reviewed_at'    => $now,
                'pay_state'      => 1,
                'pay_checked_at' => $now,
                'admin_remark'   => $remark,
                'updated_at'     => $now,
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
        if (!in_array((int)$order['status'], [0, 3], true)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '订单状态不允许此操作');
        }

        Db::connect('live_mysql')->table('lp_recharge_order')
            ->where('id', $orderId)->update([
                'status'         => 2,
                'reviewed_at'    => date('Y-m-d H:i:s'),
                'admin_remark'   => $remark,
                // 人工拒绝通常就是「没收到钱/凭证不符」：把支付状态明确成未支付，
                // 避免它一直挂在「未确认」上看不出结论
                'pay_state'      => 2,
                'pay_checked_at' => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
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
                'status'         => 1,
                'paid_at'        => $now,
                'reviewed_at'    => $now,
                'pay_state'      => 1,
                'pay_checked_at' => $now,
                'admin_remark'   => '链上自动确认 tx=' . $txId,
                'updated_at'     => $now,
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
     * star-pay / FlowPay 异步通知确认入账（幂等）
     *
     * 允许两种状态入账：
     *   status=0 待支付 —— 正常路径
     *   status=3 已关闭 —— 用户在有效期最后时刻付款、或回调/查单迟到时，订单已被
     *                     expireOrders() 关闭；网关既然确认收到钱就必须补上，
     *                     否则就是「用户付了钱不到账」
     * status=1（已入账）幂等返回；status=2（人工拒绝）不放行。
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

            $status = (int) $order['status'];
            if ($status === 1) {
                $db->commit();
                return $order; // 已入账，幂等返回
            }
            if ($status !== 0 && $status !== 3) {
                $db->commit();
                return $order; // 已拒绝等状态不自动放行
            }

            $now    = date('Y-m-d H:i:s');
            $isVip  = (string) ($order['biz_type'] ?? 'coin') === 'vip';
            $late   = $status === 3 ? ' [订单已关闭后补单]' : '';
            $remark = ($isVip ? $gateway . ' 订阅到账 system_ref=' : $gateway . ' 自动到账 system_ref=')
                . $systemRef . ($productRef !== '' ? ' product_ref=' . $productRef : '') . $late;

            $db->table('lp_recharge_order')->where('id', $order['id'])->update([
                'status'         => 1,
                'paid_at'        => $now,
                'reviewed_at'    => $now,
                'pay_state'      => 1,
                'pay_checked_at' => $now,
                'system_ref'     => $systemRef !== '' ? $systemRef : (string) ($order['system_ref'] ?? ''),
                'admin_remark'   => $remark,
                'updated_at'     => $now,
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

    /**
     * 网关订单查单补单：回调丢失时的兜底
     *
     * 回调URL不通、网关重试耗尽、用户在收银台付完直接关页面……都会让订单停在待支付，
     * 最后被 expireOrders() 关掉，用户钱付了不到账。这里周期性主动找网关对账：
     * 网关确认已付款就补入账（confirmByGateway 幂等），否则什么都不做。
     *
     * 由 recharge:scan 每分钟调用。
     *
     * 顺带把网关查单结果写进 pay_state（1 已支付 / 2 未支付），
     * 让后台能分清「未支付过期」和「已支付但没入账」。
     *
     * @return array{checked:int, recovered:int, failed:int, skipped:int, unpaid:int}
     */
    public function reconcileGateway(int $staleMinutes = 5, int $limit = 50): array
    {
        $db  = Db::connect('live_mysql');
        $now = time();

        $rows = $db->table('lp_recharge_order')
            ->whereIn('pay_channel', ['flowpay', 'starpay'])
            ->whereIn('status', [0, 3])
            // 刚下完单还没付款的不用查；超过 7 天的直接放弃，避免每分钟白查历史单
            ->where('created_at', '<', date('Y-m-d H:i:s', $now - max(1, $staleMinutes) * 60))
            ->where('created_at', '>', date('Y-m-d H:i:s', $now - 7 * 86400))
            ->order('id', 'asc')
            ->limit($limit)
            ->select()->toArray();

        $stat = ['checked' => 0, 'recovered' => 0, 'failed' => 0, 'skipped' => 0, 'unpaid' => 0];
        if (!$rows) {
            return $stat;
        }

        // 下单就被网关拒掉的单（remark 里记了失败原因）不必再查
        $rows = array_values(array_filter($rows, static function (array $r): bool {
            return !str_starts_with((string) ($r['admin_remark'] ?? ''), '网关下单失败');
        }));
        if (!$rows) {
            return $stat;
        }

        // star-pay 支持批量查单（一次最多 20 笔），先一次性拿回状态
        // $starStates[order_no] = ['paid' => bool, 'system_ref' => string]
        $starStates = [];
        $starRefs   = [];
        foreach ($rows as $r) {
            if ((string) $r['pay_channel'] === 'starpay') {
                $starRefs[] = (string) $r['order_no'];
            }
        }
        if ($starRefs) {
            foreach (array_chunk($starRefs, 20) as $chunk) {
                try {
                    foreach ((new StarPayService())->queryOrders($chunk) as $item) {
                        $ref = (string) ($item['merchant_ref'] ?? '');
                        if ($ref === '') {
                            continue;
                        }
                        $starStates[$ref] = [
                            'paid'       => (int) ($item['status'] ?? 0) === 1,
                            'system_ref' => (string) ($item['system_ref'] ?? ''),
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::error('[recharge:reconcile] star-pay 批量查单失败：' . $e->getMessage());
                }
            }
        }

        /** @var FlowPayService $flowPay */
        $flowPay = null;
        foreach ($rows as $r) {
            $stat['checked']++;
            $orderNo = (string) $r['order_no'];

            try {
                if ((string) $r['pay_channel'] === 'starpay') {
                    if (!isset($starStates[$orderNo])) {
                        continue; // 网关没返回这笔，支付状态仍未知，不动
                    }
                    if (!$starStates[$orderNo]['paid']) {
                        $this->markPayChecked((int) $r['id'], 2);
                        $stat['unpaid']++;
                        continue;
                    }
                    // 先把支付状态落成「已支付」再补单：万一补单抛错，
                    // 后台也能看到「已支付但订单没入账」，而不是什么都查不到
                    $this->markPayChecked((int) $r['id'], 1);
                    $this->confirmByGateway($orderNo, $starStates[$orderNo]['system_ref'], '', 'star-pay(查单补单)');
                    $stat['recovered']++;
                    Log::info('[recharge:reconcile] star-pay 补单成功 ' . $orderNo);
                    continue;
                }

                $flowPay = $flowPay ?: new FlowPayService();
                if (!$flowPay->enabled()) {
                    $stat['skipped']++;
                    continue;
                }
                $res   = $flowPay->queryOrder($orderNo);
                $state = (string) ($res['trade_state'] ?? '');
                if ($state === 'notpay') {
                    $this->markPayChecked((int) $r['id'], 2);
                    $stat['unpaid']++;
                    continue;
                }
                if ($state !== 'success') {
                    continue; // 网关没给出明确结论，保持未知
                }
                $this->markPayChecked((int) $r['id'], 1);
                $this->confirmByGateway($orderNo, (string) ($res['transaction_id'] ?? ''), '', 'FlowPay(查单补单)');
                $stat['recovered']++;
                Log::info('[recharge:reconcile] FlowPay 补单成功 ' . $orderNo);
            } catch (\Throwable $e) {
                $stat['failed']++;
                Log::error('[recharge:reconcile] ' . $orderNo . ' 查单失败：' . $e->getMessage());
            }
        }

        return $stat;
    }

    /**
     * 记录支付状态（0 未确认 / 1 已支付 / 2 未支付）
     *
     * 已是「已支付」的不允许被后续查单覆盖回未支付（钱到账是终态）。
     */
    public function markPayChecked(int $orderId, int $payState): void
    {
        $db    = Db::connect('live_mysql');
        $order = $db->table('lp_recharge_order')->where('id', $orderId)->field('id, pay_state')->find();
        if (!$order) {
            return;
        }
        if ((int) $order['pay_state'] === 1 && $payState !== 1) {
            return;
        }

        $db->table('lp_recharge_order')->where('id', $orderId)->update([
            'pay_state'      => $payState,
            'pay_checked_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 未匹配（待人工处理）的链上转账
     */
    public function unmatchedCallbacks(int $limit = 50): array
    {
        return Db::connect('live_mysql')->table('lp_payment_callback_log')
            ->where('verify_status', 0)
            ->order('id', 'desc')
            ->limit($limit)
            ->select()->toArray();
    }

    /**
     * 把某条链上回调标记为已人工处理（verify_status 2=已忽略）
     */
    public function markCallbackHandled(int $id): void
    {
        Db::connect('live_mysql')->table('lp_payment_callback_log')
            ->where('id', $id)
            ->update(['verify_status' => 2]);
    }

    /**
     * 为一条未匹配的链上转账找「可能对应的订单」
     *
     * 匹配口径：同一渠道 + 金额完全相同 + 下单时间落在到账前 2 小时 ~ 到账后 1 小时，
     * 且订单是待审核或已关闭（已关闭说明过期才到账，正是要补单的典型场景）。
     * 只用于给运营提示，不做任何自动入账。
     */
    public function callbackCandidates(array $callback, int $limit = 3): array
    {
        $gateway = (string) ($callback['gateway'] ?? '');
        $amount  = (string) ($callback['amount'] ?? '');
        $ts      = (int) ($callback['block_timestamp'] ?? 0);
        if ($gateway === '' || $amount === '' || $ts <= 0) {
            return [];
        }

        $db = Db::connect('live_mysql');
        $channelIds = $db->table('lp_recharge_channel')->where('type', $gateway)->column('id');
        if (!$channelIds) {
            return [];
        }

        return $db->table('lp_recharge_order')
            ->whereIn('channel_id', $channelIds)
            ->where('pay_amount', $amount)
            ->whereIn('status', [0, 3])
            ->where('created_at', '>=', date('Y-m-d H:i:s', $ts - 7200))
            ->where('created_at', '<=', date('Y-m-d H:i:s', $ts + 3600))
            ->field('id, order_no, user_id, status, pay_amount, diamond_amount, created_at')
            ->order('id', 'asc')
            ->limit($limit)
            ->select()->toArray();
    }

    /**
     * 收款地址合法性检查，返回错误原因（'' 表示合法）
     *
     * 线上就踩过这个坑：USDT-TRC20 渠道填了一个 base58check 校验和不通过的地址，
     * 钱包会直接拒绝转账，链上扫链接口也一律报「not a valid address」，
     * 但因为异常被吞掉，表面看不出问题——所以这里做前置校验。
     */
    public static function addressError(string $type, string $address): string
    {
        $addr = trim($address);
        if ($addr === '') {
            return '未填写收款地址';
        }

        if ($type === 'eth') {
            if (!preg_match('/^0x[0-9a-fA-F]{40}$/', $addr)) {
                return 'ETH 地址格式不对（应为 0x 开头的 42 位十六进制）';
            }
            if (preg_match('/^0x0{40}$/i', $addr)) {
                return '不能使用全 0 占位地址';
            }
            return '';
        }

        if ($type === 'usdt_trc20') {
            if (!str_starts_with($addr, 'T') || strlen($addr) !== 34) {
                return 'TRON 地址应以 T 开头且共 34 位';
            }
            return self::base58checkValid($addr)
                ? ''
                : 'TRON 地址 base58 校验和不通过（地址被改动过），钱包会拒绝转账，请核对后重填';
        }

        return '';
    }

    /**
     * TRON base58check 地址校验（25 字节：0x41 版本 + 20 字节地址 + 4 字节校验和）
     */
    private static function base58checkValid(string $s): bool
    {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $num      = '0';
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $pos = strpos($alphabet, $s[$i]);
            if ($pos === false) {
                return false;
            }
            $num = bcadd(bcmul($num, '58'), (string) $pos);
        }

        $bin = '';
        while (bccomp($num, '0') > 0) {
            $bin = chr((int) bcmod($num, '256')) . $bin;
            $num = bcdiv($num, '256', 0);
        }
        for ($i = 0, $n = strlen($s); $i < $n && $s[$i] === '1'; $i++) {
            $bin = "\x00" . $bin;
        }

        if (strlen($bin) !== 25 || $bin[0] !== "\x41") {
            return false;
        }
        $expect = substr(hash('sha256', hash('sha256', substr($bin, 0, 21), true), true), 0, 4);

        return hash_equals($expect, substr($bin, 21));
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
