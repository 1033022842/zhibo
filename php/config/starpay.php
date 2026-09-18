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
 *   RETURN_URL = https://域名/purchasePoints.html
 */

$productNames = [
    'ERC20Buy'          => 'USDT ERC20',
    'TRC20Buy'          => 'USDT TRC20',
    'TRC20H5'           => 'USDT TRC20 (H5)',
    'USDCDeposit'       => 'USDC Deposit',
    'USDCERC20Buy'      => 'USDC ERC20',
    'USDT-ERC20Deposit' => 'USDT ERC20 Deposit',
    'USDT-TRC20Deposit' => 'USDT TRC20 Deposit',
];

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
    // 前端可选支付方式（即 star-pay 的 product）
    'products'       => $products,
    'notify_url'     => (string) env('starpay.notify_url', ''),
    'return_url'     => (string) env('starpay.return_url', ''),
    // 请求超时（秒）
    'timeout'        => 15,
    // 订单有效期（分钟）
    'expire_minutes' => 120,
];
