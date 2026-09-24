<?php
/**
 * FlowPay（safepaycash）支付网关配置
 *
 * .env 示例（[FLOWPAY] 段）：
 *   GATEWAY = https://api.safepaycash.com
 *   MCHID = 商户号
 *   KEY = 商户密钥（签名末尾拼接）
 *   GCODE = 产品编码（商户后台查询）
 *   NOTIFY_URL = https://域名/api/v1/recharge/notifyFlowPay
 *   RETURN_URL = http://域名:8082/purchasePoints.html
 *   RETURN_URL_VIP = http://域名:8082/Subscriptions.html
 *   MIN_AMOUNT = 10
 */
return [
    // 网关地址（不含接口路径）
    'gateway'        => rtrim((string) env('flowpay.gateway', 'https://api.safepaycash.com'), '/'),
    'mchid'          => (string) env('flowpay.mchid', ''),
    'key'            => (string) env('flowpay.key', ''),
    'gcode'          => (string) env('flowpay.gcode', ''),
    'notify_url'     => (string) env('flowpay.notify_url', ''),
    'return_url'     => (string) env('flowpay.return_url', ''),
    // 会员订阅页同步跳回地址
    'return_url_vip' => (string) env('flowpay.return_url_vip', ''),
    // 网关最小支付金额（USD），低于该金额下单会被网关拒绝
    'min_amount'     => (float) env('flowpay.min_amount', 10),
    // 请求超时（秒）
    'timeout'        => 15,
    // 订单有效期（分钟）
    'expire_minutes' => 120,
];
