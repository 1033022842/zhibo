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
     * 支持的币种/链（后台「充值渠道」type 的可选值，全部走后台配置，不接三方加密货币收单）
     *
     *  - pegged=true ：1 币 ≈ 1 美元，应付金额直接等于 USD 金额（USDT/USDC/DAI/TUSD 这类稳定币）
     *  - pegged=false：按后台 lp_platform_config 的 <type>_usd_rate 折算，精度取 decimals
     *  - scan=true   ：已有链上自动到账任务 ——
     *                  recharge:scan（usdt_trc20）、recharge:scan-eth（eth）、recharge:scan-trx（trx）、
     *                  recharge:scan-alt（ERC-20 代币 / BEP-20 代币 / bnb / btc / ton / sol / xrp）；
     *                  其余币种没有扫链实现，即使后台勾了「自动」也会退化成人工审核，避免订单永远不到账
     */
    public const COINS = [
        // 稳定币 / 多链（1:1 美元）
        'usdt_trc20'    => ['label' => 'USDT · TRON (TRC-20)',    'unit' => 'USDT', 'decimals' => 2, 'pegged' => true,  'chain' => 'TRON (TRC-20)',   'scan' => true],
        'usdt_erc20'    => ['label' => 'USDT · Ethereum (ERC-20)', 'unit' => 'USDT', 'decimals' => 2, 'pegged' => true,  'chain' => 'Ethereum (ERC-20)', 'scan' => true],
        'usdt_bep20'    => ['label' => 'USDT · BNB Chain (BEP-20)', 'unit' => 'USDT', 'decimals' => 2, 'pegged' => true, 'chain' => 'BNB Chain (BEP-20)', 'scan' => true],
        'usdt_polygon'  => ['label' => 'USDT · Polygon',          'unit' => 'USDT', 'decimals' => 2, 'pegged' => true,  'chain' => 'Polygon',         'scan' => false],
        'usdt_sol'      => ['label' => 'USDT · Solana',           'unit' => 'USDT', 'decimals' => 2, 'pegged' => true,  'chain' => 'Solana',          'scan' => false],
        'usdc_erc20'    => ['label' => 'USDC · Ethereum (ERC-20)', 'unit' => 'USDC', 'decimals' => 2, 'pegged' => true, 'chain' => 'Ethereum (ERC-20)', 'scan' => true],
        'usdc_sol'      => ['label' => 'USDC · Solana',           'unit' => 'USDC', 'decimals' => 2, 'pegged' => true,  'chain' => 'Solana',          'scan' => false],
        'dai_erc20'     => ['label' => 'DAI · Ethereum (ERC-20)', 'unit' => 'DAI',  'decimals' => 2, 'pegged' => true,  'chain' => 'Ethereum (ERC-20)', 'scan' => true],
        'tusd_erc20'    => ['label' => 'TUSD · Ethereum (ERC-20)', 'unit' => 'TUSD', 'decimals' => 2, 'pegged' => true, 'chain' => 'Ethereum (ERC-20)', 'scan' => true],
        // 主流币（按后台汇率折算）
        'btc'           => ['label' => 'BTC · Bitcoin',           'unit' => 'BTC',  'decimals' => 8, 'pegged' => false, 'chain' => 'Bitcoin',         'scan' => true],
        'ltc'           => ['label' => 'LTC · Litecoin',          'unit' => 'LTC',  'decimals' => 8, 'pegged' => false, 'chain' => 'Litecoin',        'scan' => false],
        'bch'           => ['label' => 'BCH · Bitcoin Cash',      'unit' => 'BCH',  'decimals' => 8, 'pegged' => false, 'chain' => 'Bitcoin Cash',    'scan' => false],
        'doge'          => ['label' => 'DOGE · Dogecoin',         'unit' => 'DOGE', 'decimals' => 8, 'pegged' => false, 'chain' => 'Dogecoin',        'scan' => false],
        // 公链原生币
        'eth'           => ['label' => 'ETH · Ethereum',          'unit' => 'ETH',  'decimals' => 8, 'pegged' => false, 'chain' => 'Ethereum',        'scan' => true],
        'trx'           => ['label' => 'TRX · TRON',              'unit' => 'TRX',  'decimals' => 6, 'pegged' => false, 'chain' => 'TRON',            'scan' => true],
        'bnb'           => ['label' => 'BNB · BNB Chain',         'unit' => 'BNB',  'decimals' => 6, 'pegged' => false, 'chain' => 'BNB Chain',       'scan' => true],
        'sol'           => ['label' => 'SOL · Solana',            'unit' => 'SOL',  'decimals' => 6, 'pegged' => false, 'chain' => 'Solana',          'scan' => true],
        'ton'           => ['label' => 'TON · Toncoin',           'unit' => 'TON',  'decimals' => 6, 'pegged' => false, 'chain' => 'TON',             'scan' => true],
        'xrp'           => ['label' => 'XRP · XRP Ledger',        'unit' => 'XRP',  'decimals' => 6, 'pegged' => false, 'chain' => 'XRP Ledger',      'scan' => true],
    ];

    /**
     * 地址族：同族地址格式一致，校验规则共用（未登记的 type 不做格式校验）
     */
    private const ADDR_FAMILY = [
        'usdt_trc20' => 'tron',   'trx' => 'tron',
        'usdt_erc20' => 'evm',    'usdt_bep20' => 'evm', 'usdt_polygon' => 'evm',
        'usdc_erc20' => 'evm',    'dai_erc20' => 'evm',  'tusd_erc20' => 'evm',
        'eth' => 'evm',           'bnb' => 'evm',
        'usdt_sol' => 'solana',   'usdc_sol' => 'solana', 'sol' => 'solana',
        'btc' => 'bitcoin',       'ltc' => 'litecoin',    'bch' => 'bitcoincash',
        'doge' => 'dogecoin',     'xrp' => 'xrp',         'ton' => 'ton',
    ];

    /**
     * 币种规格：type 不在白名单里（后台选「其他」）时，用渠道名当币种代号，按稳定币处理
     *
     * @param array|object $channel 渠道数组或渠道模型（后台列表拿到的就是模型对象）
     */
    public static function specOf($channel): array
    {
        $channel = self::toRow($channel);
        $type = (string) ($channel['type'] ?? '');
        if (isset(self::COINS[$type])) {
            return self::COINS[$type];
        }

        $name = trim((string) ($channel['name'] ?? ''));
        // 渠道名形如「USDT (TRC-20)」，取第一个词当币种代号
        $unit = strtoupper((string) preg_split('/[\s(（·\/|-]+/u', $name)[0]);
        $unit = preg_replace('/[^A-Z0-9]/', '', $unit) ?: 'COIN';

        return [
            'label'    => $name !== '' ? $name : $type,
            'unit'     => $unit,
            'decimals' => 2,
            'pegged'   => true,
            'chain'    => $name !== '' ? $name : $type,
            'scan'     => false,
        ];
    }

    /**
     * 可用渠道列表（附币种规格，前端不再靠渠道名猜币种/网络）
     */
    public function channels(): array
    {
        $rows = RechargeChannel::where('status', 1)
            ->order('sort', 'asc')
            ->select()
            ->toArray();

        foreach ($rows as &$row) {
            $spec            = self::specOf($row);
            $row['unit']        = $spec['unit'];
            $row['chain_label'] = $spec['chain'];
            $row['decimals']    = $spec['decimals'];
            $row['pegged']      = $spec['pegged'];
            $row['scan']        = $spec['scan'];
        }
        unset($row);

        return $rows;
    }

    /**
     * 该渠道实际生效的确认方式：没扫链实现的币种一律按人工审核，
     * 否则用户付了钱订单永远停在「待支付」，只能过期。
     *
     * @param array|object $channel
     */
    public static function effectiveConfirmMode($channel): string
    {
        $channel = self::toRow($channel);
        $mode = (string) ($channel['confirm_mode'] ?? 'auto');
        if ($mode === 'auto' && !self::specOf($channel)['scan']) {
            return 'manual';
        }
        return $mode === 'manual' ? 'manual' : 'auto';
    }

    /** 渠道模型 / 数组统一成数组 */
    private static function toRow($channel): array
    {
        if (is_array($channel)) {
            return $channel;
        }
        if (is_object($channel) && method_exists($channel, 'toArray')) {
            return $channel->toArray();
        }
        return (array) $channel;
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
        $row = $channel->toArray();
        if ($amount < (float)$channel->min_amount) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '最低充值 ' . $channel->min_amount . ' USD');
        }

        $orderNo = StrHelper::orderNo('RC');
        // 代币按用户输入的整数基数计算（随机尾数仅作链上匹配标识）
        $diamondAmount = bcmul((string)floor($amount), (string)$channel->diamond_rate, 2);

        $confirmMode = self::effectiveConfirmMode($row);
        $payAmount   = $this->channelPayAmount($row, $amount, $confirmMode, $channelId);
        $expireAt    = date('Y-m-d H:i:s', time() + ($confirmMode === 'auto' ? 7200 : 86400));

        $order = [
            'order_no'       => $orderNo,
            'user_id'        => $userId,
            'pay_channel'    => $channel->type,
            'channel_id'     => $channelId,
            'chain_type'     => (string) $channel->type === 'eth' ? 'ETH' : ((string) $channel->type === 'usdt_trc20' ? 'TRC20' : strtoupper((string) $channel->type)),
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
     * 渠道应付金额
     *
     * 自动确认（已有扫链实现的币种）带唯一标识尾数，扫链按金额精确匹配：
     *   非稳定币先按后台汇率折算到该币精度，尾数加在末两位（价值守恒）；
     *   稳定币 1:1 锚定，按标价收，只有同渠道撞到相同待支付金额时才叠加 6 位小数微尾数。
     * 人工审核不带尾数：稳定币直接用 USD 金额，其它币按后台汇率折算到该币精度。
     */
    private function channelPayAmount(array $channel, float $usd, string $confirmMode, int $channelId): string
    {
        $spec = self::specOf($channel);
        $type = (string) ($channel['type'] ?? '');

        if ($confirmMode === 'auto') {
            if ($type === 'eth') {
                return $this->uniqueEthPayAmount($usd, $channelId);
            }
            // TRX 与 ETH 同属「非稳定币自动扫链」：先按汇率折成 TRX，再加随机尾数当匹配标识
            if ($type === 'trx') {
                return $this->uniqueTrxPayAmount($usd, $channelId);
            }
            // 其余已支持扫链的非稳定币（BTC / TON / SOL / XRP）：统一按汇率折算 + 精度尾数
            if ($spec['scan'] && !$spec['pegged']) {
                return $this->uniqueCoinPayAmount($usd, $type, $spec, $channelId);
            }
            // 稳定币（USDT/USDC/DAI/TUSD 各链版本）1:1 锚定，按标价收，撞车才加微尾数
            return $this->peggedPayAmount($usd, $channelId);
        }

        if ($spec['pegged']) {
            return number_format($usd, 2, '.', '');
        }

        return $this->usdToCoin($usd, $type, $spec);
    }

    /**
     * USD → 币：按后台 lp_platform_config 的 <type>_usd_rate 折算，保留该币精度
     */
    private function usdToCoin(float $usd, string $type, array $spec): string
    {
        $rate = (float) WalletService::config($type . '_usd_rate', '0');
        if ($rate <= 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '后台未配置 ' . $spec['unit'] . ' 兑 USD 价格，请联系客服');
        }
        return number_format($usd / $rate, (int) $spec['decimals'], '.', '');
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

            // 结算：订阅单开通/顺延会员，充值单加代币（与网关回调、链上自动确认同一套口径）
            $this->settleOrder($db, $order, $remark !== '' ? $remark : '管理员审核充值');

            $db->commit();
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * 订单结算：biz_type=vip 开通会员，否则加代币
     *
     * 人工审核 / 链上自动确认 / 网关回调三条入账路径共用，避免某条路径漏开会员。
     */
    private function settleOrder($db, array $order, string $remark): void
    {
        if ((string) ($order['biz_type'] ?? 'coin') === 'vip') {
            (new VipService())->activate(
                (int) $order['user_id'],
                (int) $order['vip_plan_id'],
                (string) $order['order_no'],
                (float) $order['pay_amount']
            );
            return;
        }

        $this->creditDiamond($db, (int) $order['user_id'], (float) $order['diamond_amount'], (int) $order['id'], $remark);
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
     * 稳定币（1:1 锚定）的链上收款金额：直接按标价收（金额本身就是识别标识），
     * 只有该渠道已有同金额的待支付单时才叠加 6 位小数微尾数——金额几乎不变，同时保持唯一可匹配。
     */
    private function peggedPayAmount(float $usd, int $channelId): string
    {
        $exact = number_format($usd, 2, '.', '');
        if (!$this->hasPendingAmount($channelId, $exact)) {
            return $exact;
        }
        for ($i = 0; $i < 99; $i++) {
            $amt = bcadd($exact, sprintf('0.0000%02d', random_int(1, 99)), 6);
            if (!$this->hasPendingAmount($channelId, $amt)) {
                return $amt;
            }
        }
        throw new BusinessException(ResultCode::SERVER_ERROR, '下单繁忙请稍后再试');
    }

    /** 该渠道是否已有相同金额的待支付订单（扫链按金额精确匹配，同金额必须区分开） */
    private function hasPendingAmount(int $channelId, string $amount): bool
    {
        return (bool) Db::connect('live_mysql')->table('lp_recharge_order')
            ->where('channel_id', $channelId)
            ->where('pay_amount', $amount)
            ->where('status', 0)
            ->where('expire_at', '>', date('Y-m-d H:i:s'))
            ->count();
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
     * TRX 自动扫链收款金额：USD → TRX（按 trx_usd_rate 折算，6 位精度），
     * 再往小数第 5/6 位加随机尾数当匹配标识（TRON 原生转账只有 6 位小数）
     */
    private function uniqueTrxPayAmount(float $usd, int $channelId): string
    {
        $db   = Db::connect('live_mysql');
        $spec = self::COINS['trx'];
        $base = $this->usdToCoin($usd, 'trx', $spec);

        for ($i = 0; $i < 30; $i++) {
            $amt = bcadd($base, sprintf('0.0000%02d', random_int(1, 99)), 6);
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
     * 通用「非稳定币」链上唯一应付金额（BTC / TON / SOL / XRP）：
     * USD 按 <type>_usd_rate 折算成币，再在末两位加随机尾数当匹配标识，精度随币种
     */
    private function uniqueCoinPayAmount(float $usd, string $type, array $spec, int $channelId): string
    {
        $db   = Db::connect('live_mysql');
        $dec  = max(2, (int) $spec['decimals']);
        $base = $this->usdToCoin($usd, $type, $spec);          // 例：BTC 8 位 / XRP 6 位
        $tail = '0.' . str_repeat('0', $dec - 2) . '%02d';     // 例：BTC '0.000000%02d'

        for ($i = 0; $i < 30; $i++) {
            $amt = bcadd($base, sprintf($tail, random_int(1, 99)), $dec);
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
     * 链上自动确认入账（由 recharge:scan / recharge:scan-eth / recharge:scan-trx / recharge:scan-alt 扫描任务调用，幂等）
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

            // 结算：订阅单开通/顺延会员，充值单加代币
            $this->settleOrder($db, $order, $label);

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
     *
     * @param string $gcode FlowPay 收单通道（用户在前台自选），非 FlowPay 忽略
     */
    public function submitPackageOrder(int $userId, int $packageId, string $product = '', string $gateway = 'starpay', string $gcode = ''): array
    {
        $pkg = Db::connect('live_mysql')->table('lp_recharge_package')
            ->where('id', $packageId)->where('status', 1)->find();
        if (!$pkg) {
            throw new BusinessException(ResultCode::RECORD_NOT_FOUND, '充值套餐不存在或已下架');
        }

        $tokens = (int) $pkg['tokens'];
        $amount = number_format((float) $pkg['price'], 2, '.', '');
        if ($gateway === 'flowpay') {
            return $this->createFlowPayOrder($userId, $tokens, $amount, (int) $pkg['id'], [], $gcode);
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
    public function submitCustomOrder(int $userId, int $tokens, string $product = '', string $gateway = 'starpay', string $gcode = ''): array
    {
        $quote = $this->quoteByTokens($tokens);

        if ($gateway === 'flowpay') {
            return $this->createFlowPayOrder($userId, (int) $quote['tokens'], $quote['price'], null, [], $gcode);
        }

        return $this->createStarPayOrder($userId, (int) $quote['tokens'], $quote['price'], $product, null);
    }

    /**
     * 网关下单（会员订阅）
     *
     * 与充值共用 lp_recharge_order（biz_type=vip 标记），回调时开通会员而非加代币
     */
    public function submitVipOrder(int $userId, int $planId, string $product = '', string $gateway = 'starpay', string $gcode = '', int $channelId = 0, int $fiatChannelId = 0): array
    {
        $plan = Db::connect('live_mysql')->table('lp_vip_plan')
            ->where('id', $planId)->where('status', 1)->find();
        if (!$plan) {
            throw new BusinessException(ResultCode::RECORD_NOT_FOUND, '会员套餐不存在或已下架');
        }

        // 法币通道（后台 lp_fiat_channel）：按后台汇率把套餐 USD 价折算成本币收款
        if ($fiatChannelId > 0) {
            return $this->submitFiatOrder($userId, $fiatChannelId, (float) $plan['usd_price'], [
                'biz_type'    => 'vip',
                'vip_plan_id' => (int) $plan['id'],
            ]);
        }

        // 链上收款渠道（后台 lp_recharge_channel）：站内收款页直接用渠道地址收款
        if ($channelId > 0) {
            return $this->submitVipOrderByChannel($userId, $plan, $channelId);
        }

        $amount = number_format((float) $plan['usd_price'], 2, '.', '');
        $opt    = [
            'biz_type'    => 'vip',
            'vip_plan_id' => (int) $plan['id'],
        ];

        if ($gateway === 'flowpay') {
            return $this->createFlowPayOrder($userId, 0, $amount, null, $opt, $gcode);
        }

        $min = (float) config('starpay.min_amount');
        if ((float) $amount < $min) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '该套餐金额低于网关最小支付金额 ' . number_format($min, 2));
        }
        $opt['return_url'] = (string) config('starpay.return_url_vip');

        return $this->createStarPayOrder($userId, 0, $amount, $product, null, $opt);
    }

    /**
     * 链上渠道下单（会员订阅）
     *
     * 与充值共用 lp_recharge_order（biz_type=vip）：扫链确认后开通会员而非加代币。
     * 金额识别尾数与充值渠道保持一致（自动模式带唯一尾数，人工审核按原价）。
     */
    private function submitVipOrderByChannel(int $userId, array $plan, int $channelId): array
    {
        $channel = RechargeChannel::find($channelId);
        if (!$channel || $channel->status != 1) {
            throw new BusinessException(ResultCode::RECORD_NOT_FOUND, '收款渠道不可用');
        }

        $amount = (float) $plan['usd_price'];
        if ($amount < (float) $channel->min_amount) {
            throw new BusinessException(
                ResultCode::PARAM_ERROR,
                '该套餐金额低于 ' . $channel->name . ' 的最低收款金额 ' . $channel->min_amount
            );
        }

        $row         = $channel->toArray();
        $type        = (string) $channel->type;
        $confirmMode = self::effectiveConfirmMode($row);
        $payAmount   = $this->channelPayAmount($row, $amount, $confirmMode, $channelId);
        $expireAt    = date('Y-m-d H:i:s', time() + ($confirmMode === 'auto' ? 7200 : 86400));

        $chainType = ['eth' => 'ETH', 'usdt_trc20' => 'TRC20'][$type] ?? strtoupper($type);
        $now       = date('Y-m-d H:i:s');
        $order     = [
            'order_no'       => StrHelper::orderNo('RC'),
            'user_id'        => $userId,
            'pay_channel'    => $type,
            'channel_id'     => $channelId,
            'biz_type'       => 'vip',
            'vip_plan_id'    => (int) $plan['id'],
            'chain_type'     => $chainType,
            'pay_amount'     => $payAmount,
            'diamond_amount' => 0,
            'proof_image'    => '',
            'status'         => 0,
            'expire_at'      => $expireAt,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];
        Db::connect('live_mysql')->table('lp_recharge_order')->insert($order);

        return array_merge($order, ['channel' => $channel->toArray()]);
    }

    /**
     * 法币通道下单（会员订阅 / 代币充值共用）
     *
     * 金额按后台配置的汇率在「下单这一刻」折算成本币并锁定：
     *   pay_amount = 本币实付（如 70.53）、usd_amount = 原始 USD 标价（如 15.00）、
     *   fx_rate    = 锁定的 1 USD = ? 本币。
     * 之后运营改汇率，已生成的订单金额不变。
     *
     * @param array $opt biz_type / vip_plan_id / diamond_amount / package_id / return_url
     */
    public function submitFiatOrder(int $userId, int $fiatChannelId, float $usd, array $opt = []): array
    {
        $channel = FiatChannelService::find($fiatChannelId);
        $fx      = FiatChannelService::convert($usd, $channel);
        $gateway = (string) $channel['gateway'];

        $db  = Db::connect('live_mysql');
        $now = date('Y-m-d H:i:s');
        $order = [
            'order_no'        => StrHelper::orderNo('RC'),
            'user_id'         => $userId,
            // 走网关的记网关 code，这样网关查单补单（reconcileGateway）能自动带上；
            // 纯人工通道记 'fiat'，订单直接进后台「充值审核」等运营核对
            'pay_channel'     => $gateway === 'starpay' ? 'starpay' : 'fiat',
            'channel_id'      => 0,
            'fiat_channel_id' => (int) $channel['id'],
            'package_id'      => $opt['package_id'] ?? null,
            'biz_type'        => (string) ($opt['biz_type'] ?? 'coin'),
            'vip_plan_id'     => $opt['vip_plan_id'] ?? null,
            'chain_type'      => strtoupper((string) $fx['currency']),
            'pay_amount'      => $fx['amount'],
            'pay_currency'    => $fx['currency'],
            'usd_amount'      => $fx['usd'],
            'fx_rate'         => $fx['rate'],
            'diamond_amount'  => (float) ($opt['diamond_amount'] ?? 0),
            'proof_image'     => '',
            'status'          => 0,
            'expire_at'       => date('Y-m-d H:i:s', time() + 7200),
            'created_at'      => $now,
            'updated_at'      => $now,
        ];
        $db->table('lp_recharge_order')->insert($order);

        $update = [];
        try {
            $update = $this->callFiatGateway($order, $channel, $fx, (string) ($opt['return_url'] ?? ''));
        } catch (\Throwable $e) {
            $db->table('lp_recharge_order')->where('order_no', $order['order_no'])->update([
                'status'       => 3,
                'admin_remark' => '网关下单失败：' . $e->getMessage(),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
            throw $e;
        }
        if ($update !== []) {
            $db->table('lp_recharge_order')->where('order_no', $order['order_no'])->update($update);
        }

        return array_merge($order, $update, ['fiat' => $fx, 'fiat_channel' => $channel]);
    }

    /**
     * 法币通道充值代币：到账代币按通道的 diamond_rate（每 1 USD）折算，
     * 与链上渠道 submitOrder 同一口径（取 USD 整数部分 × 单价）
     */
    public function submitFiatCoinOrder(int $userId, int $fiatChannelId, float $usd, ?int $packageId = null): array
    {
        $channel  = FiatChannelService::find($fiatChannelId);
        $diamonds = bcmul((string) floor($usd), (string) $channel['diamond_rate'], 2);

        return $this->submitFiatOrder($userId, $fiatChannelId, $usd, [
            'biz_type'       => 'coin',
            'diamond_amount' => $diamonds,
            'package_id'     => $packageId,
        ]);
    }

    /**
     * 法币单的网关下单
     *
     * manual：不动网关（还没接，或本就想走人工），订单留在后台等运营核对；
     * starpay：金额传折算后的本币，币种走 extra.fiat_currency（网关按这个校验）；
     * 其它 code 一律拒绝，避免「以为对接了其实没对接」把钱收错。
     */
    private function callFiatGateway(array $order, array $channel, array $fx, string $returnUrl): array
    {
        $gateway = (string) $channel['gateway'];
        if ($gateway === '' || $gateway === 'manual') {
            return [];
        }
        if ($gateway !== 'starpay') {
            throw new BusinessException(
                ResultCode::PARAM_ERROR,
                '「' . $channel['name'] . '」尚未对接网关，请联系客服'
            );
        }

        $product = (string) $channel['gref'] !== '' ? (string) $channel['gref'] : (string) config('starpay.product');
        $gw = (new StarPayService())->createDeposit(
            (string) $order['order_no'],
            (string) $fx['amount'],
            $product,
            ['fiat_currency' => (string) $fx['currency']],
            $returnUrl !== '' ? $returnUrl : (string) config('starpay.return_url')
        );

        return [
            'system_ref' => (string) ($gw['system_ref'] ?? ''),
            'pay_url'    => (string) ($gw['payurl'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
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
     * @param array  $opt   biz_type / vip_plan_id / return_url
     * @param string $gcode 收单通道（前台用户自选），留空走默认通道
     */
    private function createFlowPayOrder(int $userId, int $tokens, string $amount, ?int $packageId, array $opt = [], string $gcode = ''): array
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

        // 先归一化通道（留空走默认，传入未开通的 gcode 直接拒绝），再落库
        $gcode = $flowPay->resolveChannel($gcode);

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
            'pay_gcode'      => $gcode,
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
            $gw = $flowPay->createOrder($order['order_no'], $order['pay_amount'], $gcode);
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

            // 结算：订阅单开通/顺延会员，充值单加代币
            $this->settleOrder($db, $order, $remark);

            $db->commit();
            $order['status'] = 1;
            // 服务端回传：充值 / 订阅成功（与浏览器端用同一个 event_id 去重）
            $this->sendCapiForOrder($order);
            return $order;
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * 服务端回传充值 / 订阅事件到 Facebook（尽力而为，失败只记日志）
     */
    private function sendCapiForOrder(array $order): void
    {
        try {
            $userId = (int) ($order['user_id'] ?? 0);
            if ($userId <= 0) {
                return;
            }
            $db      = Db::connect('live_mysql');
            $user    = $db->table('lp_user')->where('id', $userId)->find();
            $profile = $db->table('lp_user_profile')->where('user_id', $userId)->find();
            $isVip   = (string) ($order['biz_type'] ?? 'coin') === 'vip';

            FbCapiService::send($isVip ? 'subscribe' : 'recharge', [
                'email'    => (string) ($user['email'] ?? ''),
                'user_id'  => (string) $userId,
                'ip'       => (string) ($profile['last_login_ip'] ?? ''),
                'px'       => (string) ($user['reg_px'] ?? ''),
                'domain'   => (string) ($user['reg_domain'] ?? ''),
                'event_id' => (string) ($order['order_no'] ?? ''),
                'value'    => (float) ($order['pay_amount'] ?? 0),
                'currency' => 'USD',
            ]);
        } catch (\Throwable $e) {
            \think\facade\Log::warning('[capi] 订单回传异常 order=' . ($order['order_no'] ?? '') . ' err=' . $e->getMessage());
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
                $res = $flowPay->queryOrder($orderNo);
                // 实测：查单接口里 trade_state 恒为 notpay、pay_time 恒为 0（网关没维护这两个字段），
                // 真正标记「已支付」的是 status=success，所以以它为准；trade_state=success 也一并认。
                $trade  = (string) ($res['trade_state'] ?? '');
                $ostate = (string) ($res['status'] ?? '');
                if ($trade !== 'success' && $ostate !== 'success') {
                    // 只有网关明确给了「未支付」才落这个状态，拿不到结论时保持未知
                    if ($trade === 'notpay' || $ostate === 'pending') {
                        $this->markPayChecked((int) $r['id'], 2);
                        $stat['unpaid']++;
                    }
                    continue;
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

        switch (self::ADDR_FAMILY[$type] ?? '') {
            case 'evm':
                if (!preg_match('/^0x[0-9a-fA-F]{40}$/', $addr)) {
                    return 'EVM 地址格式不对（应为 0x 开头的 42 位十六进制）';
                }
                if (preg_match('/^0x0{40}$/i', $addr)) {
                    return '不能使用全 0 占位地址，请填真实收款地址';
                }
                return '';

            case 'tron':
                if (!str_starts_with($addr, 'T') || strlen($addr) !== 34) {
                    return 'TRON 地址应以 T 开头且共 34 位';
                }
                return self::base58checkValid($addr)
                    ? ''
                    : 'TRON 地址 base58 校验和不通过（地址被改动过），钱包会拒绝转账，请核对后重填';

            case 'solana':
                return preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $addr)
                    ? ''
                    : 'Solana 地址格式不对（应为 32~44 位 base58）';

            case 'bitcoin':
                return preg_match('/^(bc1[a-z0-9]{25,62}|[13][1-9A-HJ-NP-Za-km-z]{25,34})$/', $addr)
                    ? ''
                    : 'BTC 地址格式不对（应为 bc1 开头或 1/3 开头）';

            case 'litecoin':
                return preg_match('/^(ltc1[a-z0-9]{25,62}|[LM3][1-9A-HJ-NP-Za-km-z]{25,34})$/', $addr)
                    ? ''
                    : 'LTC 地址格式不对（应为 ltc1 或 L/M 开头）';

            case 'bitcoincash':
                return preg_match('/^(bitcoincash:)?[qp][a-z0-9]{25,62}$/i', $addr)
                    ? ''
                    : 'BCH 地址格式不对（CashAddr 应以 bitcoincash: 或 q/p 开头）';

            case 'dogecoin':
                return preg_match('/^D[1-9A-HJ-NP-Za-km-z]{33}$/', $addr)
                    ? ''
                    : 'DOGE 地址格式不对（应以 D 开头且共 34 位）';

            case 'xrp':
                return preg_match('/^r[1-9A-HJ-NP-Za-km-z]{24,34}$/', $addr)
                    ? ''
                    : 'XRP 地址格式不对（应以 r 开头）';

            case 'ton':
                return preg_match('/^(EQ|UQ|kQ|0Q)[A-Za-z0-9_-]{46}$/', $addr)
                    ? ''
                    : 'TON 地址格式不对（应为 EQ/UQ 开头的 48 位）';
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
