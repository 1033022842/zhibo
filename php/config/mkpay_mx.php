<?php
/**
 * MKPay（墨西哥）支付网关配置
 *
 * 与越南版共用同一个网关与签名算法，但商户号/密钥/币种完全独立。
 *  - 鉴权   Header：X-Mch-ID / X-Timestamp / X-Signature（HMAC-SHA256）
 *  - 代收   POST {gateway}/api/v1/pay            → redirect_url / qr_code_url
 *  - 查单   POST {gateway}/api/v1/query-by-moid  → 按商户订单号查
 *  - 余额   POST {gateway}/api/v1/balance
 *  - 回调   网关 POST notify_url（JSON，含 sign），成功返回 2xx + SUCCESS
 *
 * 金额单位：MXN 分（factor=100），接口传整数分，例：20.00 MXN → 2000。
 *
 * 产品码：墨西哥商户已在后台绑定产品，实测不传 product_code 由网关自动路由；
 * 若以后一个商户绑定多条产品需要指定，在 .env 里补 PRODUCT_CODE 即可。
 */
return [
    // 生产 API 网关（不含 /api/v1 路径）
    'gateway'         => rtrim((string) env('mkpay_mx.gateway', 'https://pay.mkpay8888.com'), '/'),
    'mch_id'          => (string) env('mkpay_mx.mch_id', ''),
    'mch_secret'      => (string) env('mkpay_mx.mch_secret', ''),
    // 异步回调地址（本站域名）
    'notify_url'      => (string) env('mkpay_mx.notify_url', ''),
    // 支付完成后浏览器跳回（代币充值 / 会员订阅分开配）
    'return_url'      => (string) env('mkpay_mx.return_url', ''),
    'return_url_vip'  => (string) env('mkpay_mx.return_url_vip', ''),
    // 订单号前缀：两站各自独立，避免商户订单号冲突（hey girl MKHM / sugus MKSM）
    'order_prefix'    => (string) env('mkpay_mx.order_prefix', 'MKM'),
    // 留空 = 不传 product_code，由网关按商户绑定产品自动路由
    'product_code'    => (string) env('mkpay_mx.product_code', ''),
    // 请求超时（秒）
    'timeout'         => 15,
    // 订单有效期（分钟）
    'expire_minutes'  => 120,
];
