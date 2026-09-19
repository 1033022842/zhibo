<?php
/**
 * star-pay 支付网关配置
 *
 * .env 示例（[STARPAY] 段）：
 *   GATEWAY = https://api.star-pay.vip
 *   MERCHANT_NO = 商户号
 *   KEY = 商户密钥（构造签名原文用）
 *   PRODUCT = TRC20Buy
 *   PRODUCTS = TRC20Buy,ERC20Buy,USDCERC20Buy,TRC20H5
 *   NOTIFY_URL = https://域名/api/v1/recharge/notify
 *   RETURN_URL = http://域名:8082/purchasePoints.html
 *   RETURN_URL_VIP = http://域名:8082/Subscriptions.html
 */

$productNames = [
    // 买币类：收银台内刷卡（Visa/MasterCard/Apple Pay 等）
    'ERC20Buy'          => 'USDT ERC20 (Card / Buy)',
    'TRC20Buy'          => 'USDT TRC20 (Card / Buy)',
    'TRC20H5'           => 'USDT TRC20 H5 (Card / Buy)',
    'USDCERC20Buy'      => 'USDC ERC20 (Card / Buy)',
    // 充值类：给出收款地址，自己钱包转账
    'USDCDeposit'       => 'USDC (Wallet Transfer)',
    'USDT-ERC20Deposit' => 'USDT ERC20 (Wallet Transfer)',
    'USDT-TRC20Deposit' => 'USDT TRC20 (Wallet Transfer)',
];

// 买币类产品按法币下单，必须携带 extra.fiat_currency（网关会校验）
$productFiat = ['ERC20Buy', 'TRC20Buy', 'TRC20H5', 'USDCERC20Buy'];

$enabledCodes = array_filter(array_map('trim', explode(',', (string) env('starpay.products', 'TRC20Buy,ERC20Buy,USDCERC20Buy,TRC20H5'))));
$products     = [];
foreach ($enabledCodes as $code) {
    if ($code === '') {
        continue;
    }
    $products[] = ['code' => $code, 'name' => $productNames[$code] ?? $code];
}

return [
    // 网关地址（不含接口路径）
    'gateway'        => rtrim((string) env('starpay.gateway', 'https://api.star-pay.vip'), '/'),
    'merchant_no'    => (string) env('starpay.merchant_no', ''),
    'key'            => (string) env('starpay.key', ''),
    'sign_type'      => 'MD5',
    // 默认产品
    'product'        => (string) env('starpay.product', 'TRC20Buy'),
    // 买币类产品下单时的法币币种
    'fiat_currency'  => (string) env('starpay.fiat_currency', 'USD'),
    'fiat_products'  => $productFiat,
    // 前端可选支付方式（即 star-pay 的 product）
    'products'       => $products,
    'notify_url'     => (string) env('starpay.notify_url', ''),
    'return_url'     => (string) env('starpay.return_url', ''),
    // 会员订阅页同步跳回地址
    'return_url_vip' => (string) env('starpay.return_url_vip', ''),
    // 网关最小支付金额（USD），低于该金额下单会被网关拒绝
    'min_amount'     => (float) env('starpay.min_amount', 10),
    // 请求超时（秒）
    'timeout'        => 15,
    // 订单有效期（分钟）
    'expire_minutes' => 120,
];
