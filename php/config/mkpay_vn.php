<?php
/**
 * MKPay（越南）支付网关配置
 *
 * 商户后台：https://mch.mkpay8888.com/#/app/integration
 * 文档：商户后台「对接设置 → 查看完整对接文档」
 *  - 鉴权   Header：X-Mch-ID / X-Timestamp / X-Signature（HMAC-SHA256）
 *  - 代收   POST {gateway}/api/v1/pay            → redirect_url / qr_code_url
 *  - 代付   POST {gateway}/api/v1/payout         （未接入）
 *  - 查单   POST {gateway}/api/v1/query-by-moid  → 按商户订单号查
 *  - 余额   POST {gateway}/api/v1/balance
 *  - 回调   网关 POST notify_url（JSON，含 sign），成功返回 2xx + SUCCESS
 *
 * 签名规则：
 *   请求：signature = HMAC-SHA256(timestamp + 实际发送的 JSON 字符串, mch_secret)
 *   回调：去掉 sign 后按键名排序、紧凑 JSON，再 HMAC-SHA256(timestamp + body, mch_secret)
 *
 * 金额单位：VND 无小数（factor=1），接口传整数 VND。
 */
return [
    // 生产 API 网关（不含 /api/v1 路径）；mch.mkpay8888.com 是商户后台，不能用于接口调用
    'gateway'         => rtrim((string) env('mkpay_vn.gateway', 'https://pay.mkpay8888.com'), '/'),
    'mch_id'          => (string) env('mkpay_vn.mch_id', ''),
    'mch_secret'      => (string) env('mkpay_vn.mch_secret', ''),
    // 异步回调地址（本站域名）
    'notify_url'      => (string) env('mkpay_vn.notify_url', ''),
    // 支付完成后浏览器跳回（代币充值 / 会员订阅分开配）
    'return_url'      => (string) env('mkpay_vn.return_url', ''),
    'return_url_vip'  => (string) env('mkpay_vn.return_url_vip', ''),
    // 订单号前缀：两站各自独立，避免商户订单号冲突（hey girl MKHV / sugus MKSV）
    'order_prefix'    => (string) env('mkpay_vn.order_prefix', 'MKV'),
    // 当前开通的越南代收产品码
    'product_code'    => (string) env('mkpay_vn.product_code', 'VN01'),
    // 请求超时（秒）
    'timeout'         => 15,
    // 订单有效期（分钟）
    'expire_minutes'  => 120,
];
