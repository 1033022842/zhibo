<?php
/**
 * Skytrade 固定商品链接收款（第三方商品页代收）
 *
 * 站点不再自己对接 MKPay，而是把订单金额映射到一个固定的第三方商品付款地址：
 *   用户下单 → 按「订单业务类型 + 金额」取固定地址 → 跳转第三方商品页付款
 *   第三方付款成功后 → 回调 /api/v1/recharge/notifySkytrade 通知本站入账
 *
 * 所有商品链接都带 mit=sugus（第三方按这个标识识别本站并回调）。
 * 下单时还会再拼一个 order_no，供第三方回调时原样带回，用于定位本站订单。
 *
 * .env 示例（[SKYTRADE] 段）：
 *   NOTIFY_SECRET = 换成一段随机字符串（与第三方约定的共享密钥）
 *   ORDER_PREFIX  = SKT
 *
 * 金额键统一用两位小数的 USD 字符串（117 => 117.00）；下单金额必须精确命中，
 * 命中不了会直接报错，避免收错钱。同一金额在不同业务下可能是不同商品（如 25.99），
 * 因此按 vip（会员订阅）/ coin（代币充值）分开映射。
 */
return [
    // 第三方回调验签共享密钥：sign = md5(order_no + notify_secret)
    'notify_secret' => (string) env('skytrade.notify_secret', ''),
    // 本站订单号前缀（与其它网关区分，便于后台对账）
    'order_prefix'  => (string) env('skytrade.order_prefix', 'SKT'),
    // 第三方商户标识：回调里的 mit 必须与它一致
    'merchant_id'   => (string) env('skytrade.merchant_id', 'sugus'),
    // 联调开关：允许「完全没带签名」的回调（生产必须为 0）
    'allow_unsigned' => (bool) env('skytrade.allow_unsigned', false),
    // 付款完成后的浏览器回跳页（第三方配置的回跳地址指向 returnSkytrade，由它按订单类型再 302）
    'return_url'     => (string) env('skytrade.return_url', '/ai/purchasePoints.html'),
    'return_url_vip' => (string) env('skytrade.return_url_vip', '/ai/Subscriptions.html'),

    'links' => [
        // 代币充值套餐（lp_recharge_package.price）
        'coin' => [
            '12.99'  => 'https://skytrade.cc/api/buy/off-the-shoulder-ribbed-sweater-dress?mit=sugus',
            '10.99'  => 'https://skytrade.cc/api/buy/off-the-shoulder-ribbed-sweater-dress-black?mit=sugus',
            '25.99'  => 'https://skytrade.cc/api/buy/square-neck-bodycon-midi-dress?mit=sugus',
            '39.99'  => 'https://skytrade.cc/api/buy/floral-open-knit-maxi-dress?mit=sugus',
            '59.99'  => 'https://skytrade.cc/api/buy/wide-v-neck-ribbed-sweater-dress-beige?mit=sugus',
            '99.99'  => 'https://skytrade.cc/api/buy/ribbed-turtleneck-mini-sweater-dress?mit=sugus',
            '199.99' => 'https://skytrade.cc/api/buy/ribbed-turtleneck-mini-sweater-dress-black?mit=sugus',
            '299.99' => 'https://skytrade.cc/api/buy/wide-v-neck-ribbed-sweater-dress?mit=sugus',
        ],

        // 会员订阅（lp_vip_plan.usd_price）
        'vip' => [
            // Premium · 1 Month（100 / 200 / 300 tokens/mo）
            '12.99' => 'https://skytrade.cc/api/buy/triangle-bikini-lace-skirt-pink?mit=sugus&size=s',
            '19.99' => 'https://skytrade.cc/api/buy/triangle-bikini-lace-skirt-pink?mit=sugus&size=m',
            '25.99' => 'https://skytrade.cc/api/buy/triangle-bikini-lace-skirt-pink?mit=sugus&size=l',

            // Premium · 3 Months（100 / 200 / 300 tokens/mo）
            '26.97' => 'https://skytrade.cc/api/buy/triangle-bikini-lace-skirt-black?mit=sugus&size=s',
            '44.97' => 'https://skytrade.cc/api/buy/triangle-bikini-lace-skirt-black?mit=sugus&size=m',
            '62.97' => 'https://skytrade.cc/api/buy/triangle-bikini-lace-skirt-black?mit=sugus&size=l',

            // Premium · 12 Months（100 / 200 / 300 tokens/mo）
            '47.88'  => 'https://skytrade.cc/api/buy/triangle-bikini-lace-skirt-red?mit=sugus&size=s',
            '119.88' => 'https://skytrade.cc/api/buy/triangle-bikini-lace-skirt-red?mit=sugus&size=m',
            '191.88' => 'https://skytrade.cc/api/buy/triangle-bikini-lace-skirt-red?mit=sugus&size=l',

            // Premium Plus · 1 Month（330 / 500 / 700 tokens/mo）
            '59.99' => 'https://skytrade.cc/api/buy/not-that-deep-graphic-baby-tee?mit=sugus&size=s',
            '69.99' => 'https://skytrade.cc/api/buy/not-that-deep-graphic-baby-tee?mit=sugus&size=m',
            '79.99' => 'https://skytrade.cc/api/buy/not-that-deep-graphic-baby-tee?mit=sugus&size=l',

            // Premium Plus · 3 Months（330 / 500 / 700 tokens/mo）
            // 后台价格是 116.99 / 146.99 / 176.99，商品链接实收 117 / 147 / 177，两种键都兼容
            '116.99' => 'https://skytrade.cc/api/buy/diva-graphic-fitted-baby-tee?mit=sugus&size=s',
            '117.00' => 'https://skytrade.cc/api/buy/diva-graphic-fitted-baby-tee?mit=sugus&size=s',
            '146.99' => 'https://skytrade.cc/api/buy/diva-graphic-fitted-baby-tee?mit=sugus&size=m',
            '147.00' => 'https://skytrade.cc/api/buy/diva-graphic-fitted-baby-tee?mit=sugus&size=m',
            '176.99' => 'https://skytrade.cc/api/buy/diva-graphic-fitted-baby-tee?mit=sugus&size=l',
            '177.00' => 'https://skytrade.cc/api/buy/diva-graphic-fitted-baby-tee?mit=sugus&size=l',

            // Premium Plus · 12 Months（330 / 500 / 700 tokens/mo）
            '215.99' => 'https://skytrade.cc/api/buy/not-a-tourist-graphic-baby-tee?mit=sugus&size=s',
            '216.00' => 'https://skytrade.cc/api/buy/not-a-tourist-graphic-baby-tee?mit=sugus&size=s',
            '335.99' => 'https://skytrade.cc/api/buy/not-a-tourist-graphic-baby-tee?mit=sugus&size=m',
            '336.00' => 'https://skytrade.cc/api/buy/not-a-tourist-graphic-baby-tee?mit=sugus&size=m',
            '455.99' => 'https://skytrade.cc/api/buy/not-a-tourist-graphic-baby-tee?mit=sugus&size=l',
            '456.00' => 'https://skytrade.cc/api/buy/not-a-tourist-graphic-baby-tee?mit=sugus&size=l',
        ],
    ],
];
