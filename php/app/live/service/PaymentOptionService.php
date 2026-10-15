<?php
declare(strict_types=1);

namespace app\live\service;

use think\facade\Db;

/**
 * 站点真实可用的支付方式（设置页 Payment methods 区块）
 *
 * 站点没有卡 token 化 / 绑卡能力（FlowPay、star-pay 都是跳转式收银台，不返回卡信息），
 * 所以这里不提供「保存卡」，只如实列出当前可用的支付方式，并给出对应入口：
 *   - card       FlowPay 收银台（Visa / Mastercard，配置齐全时才出现）
 *   - usdt_trc20 / eth ...  后台 lp_recharge_channel 里启用的链上转账渠道
 */
final class PaymentOptionService
{
    /** 链上渠道 type -> 展示文案（英文页面）；未收录的 type 退回渠道名 */
    private const CRYPTO_LABELS = [
        'usdt_trc20' => ['name' => 'USDT (TRC-20)', 'desc' => 'TRON network transfer'],
        'usdt_erc20' => ['name' => 'USDT (ERC-20)', 'desc' => 'Ethereum network transfer'],
        'usdc_erc20' => ['name' => 'USDC (ERC-20)', 'desc' => 'Ethereum network transfer'],
        'usdt_bep20' => ['name' => 'USDT (BEP-20)', 'desc' => 'BNB Chain network transfer'],
        'bnb'        => ['name' => 'BNB', 'desc' => 'BNB Chain network transfer'],
        'eth'        => ['name' => 'ETH', 'desc' => 'Ethereum mainnet transfer'],
        'btc'        => ['name' => 'Bitcoin', 'desc' => 'Bitcoin network transfer'],
        'ltc'        => ['name' => 'Litecoin', 'desc' => 'Litecoin network transfer'],
        'trx'        => ['name' => 'TRX', 'desc' => 'TRON network transfer'],
    ];

    /**
     * 可用支付方式列表
     *
     * @return array<int, array{key:string, name:string, desc:string, url:string}>
     */
    public function list(): array
    {
        $out = [];

        // 卡支付：FlowPay 收银台
        if ((new FlowPayService())->enabled()) {
            $out[] = [
                'key'  => 'card',
                'name' => 'Credit / Debit Card',
                'desc' => 'Visa, Mastercard',
                'url'  => './Subscriptions.html',
            ];
        }

        // 链上转账渠道（后台 lp_recharge_channel 启用中的）
        $channels = Db::connect('live_mysql')->table('lp_recharge_channel')
            ->where('status', 1)
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->field('name, type, min_amount')
            ->select()->toArray();

        foreach ($channels as $channel) {
            $type  = (string) $channel['type'];
            $label = self::CRYPTO_LABELS[$type] ?? [
                'name' => (string) $channel['name'],
                'desc' => 'Crypto transfer',
            ];

            $min = (float) $channel['min_amount'];
            $out[] = [
                'key'  => $type,
                'name' => $label['name'],
                'desc' => $min > 0 ? ($label['desc'] . ' · min $' . number_format($min, 0)) : $label['desc'],
                'url'  => './purchasePoints.html',
            ];
        }

        return $out;
    }
}
