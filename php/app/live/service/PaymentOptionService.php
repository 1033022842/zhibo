<?php
declare(strict_types=1);

namespace app\live\service;

/**
 * 设置页 Payment methods 区块展示的支付方式
 *
 * 只列卡支付（FlowPay 收银台）；站点没有卡 token 化 / 绑卡能力，所以也不提供「保存卡」。
 * 链上加密货币不在设置页列出，统一走「充值 / 订阅」入口
 * （purchasePoints.html → CryptoPay.html）里的币种选择。
 */
final class PaymentOptionService
{
    /**
     * 可用支付方式列表
     *
     * @return array<int, array{key:string, name:string, desc:string, url:string}>
     */
    public function list(): array
    {
        $out = [];

        // 卡支付：FlowPay 收银台（商户参数齐全 + 后台开关打开时才出现）
        if ((new FlowPayService())->available()) {
            $out[] = [
                'key'  => 'card',
                'name' => 'Credit / Debit Card',
                'desc' => 'Visa, Mastercard',
                'url'  => './Subscriptions.html',
            ];
        }

        return $out;
    }
}
