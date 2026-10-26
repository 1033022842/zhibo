<?php
/**
 * AIPowerPay 信用卡收银台配置
 *
 * 文档：AIPowerPay 接入手册 1.4.9
 *  测试 API：https://testapi.aipowerpay.com
 *  生产 API：https://api.aipowerpay.com
 *  测试商户后台：https://testmerchant.aipowerpay.com/payment_online/login
 *  生产商户后台：https://merchant.aipowerpay.com/payment_online/login
 *
 * .env 示例（[AIPOWERPAY] 段）：
 *   BASE_URL = https://testapi.aipowerpay.com
 *   APP_KEY = 测试 appKey
 *   SECRET_KEY = 测试 secretKey
 *   SITE_DOMAIN = https://kisss.ai
 *   NOTIFY_URL = https://kisss.ai/api/v1/recharge/notifyAiPowerPay
 *   RETURN_URL = https://kisss.ai/ai/CryptoPay.html?pay=ok
 *   RETURN_URL_VIP = https://kisss.ai/ai/Subscriptions.html?pay=ok
 *   CURRENCY = USD
 *   ORDER_PREFIX = AKP
 *
 * 注意：
 *  - Authorization: Basic base64(appKey:secretKey)
 *  - siteDomain 必须是商户后台「站点管理」里添加并审核通过的域名，否则返回 code=11000 invalid site
 *  - 生产切换时只改 .env 的 BASE_URL / APP_KEY / SECRET_KEY，并在生产后台添加同一站点域名
 */
return [
    // API 网关（不含 /payment/gateway 路径）
    'base_url'        => rtrim((string) env('aipowerpay.base_url', 'https://testapi.aipowerpay.com'), '/'),
    'app_key'         => (string) env('aipowerpay.app_key', ''),
    'secret_key'      => (string) env('aipowerpay.secret_key', ''),
    // 商户后台已审核通过的站点域名（可带 https://）
    'site_domain'     => (string) env('aipowerpay.site_domain', ''),
    // 异步回调（本站域名）
    'notify_url'      => (string) env('aipowerpay.notify_url', ''),
    // 支付完成跳回（代币充值 / 会员订阅分开配）
    'return_url'      => (string) env('aipowerpay.return_url', ''),
    'return_url_vip'  => (string) env('aipowerpay.return_url_vip', ''),
    // 订单币种（ISO 4217）；法币通道按 USD 计价时保持 USD 即可
    'currency'        => strtoupper((string) env('aipowerpay.currency', 'USD')),
    // 订单号前缀：各站独立，避免商户订单号冲突
    'order_prefix'    => (string) env('aipowerpay.order_prefix', 'AKP'),
    // 站点类型标识（网关兼容字段）
    'bn'              => (string) env('aipowerpay.bn', 'wordpress'),
    // 请求超时（秒）
    'timeout'         => 20,
    // 账单信息占位（虚拟商品没有真实收货地址，网关要求必填）
    'bill'            => [
        'first_name' => 'Customer',
        'last_name'  => 'User',
        'country'    => 'US',
        'city'       => 'New York',
        'address'    => '350 5th Ave',
        'zip_code'   => '10118',
        'zone'       => 'NY',
        'telephone'  => '+12025550123',
    ],
];
