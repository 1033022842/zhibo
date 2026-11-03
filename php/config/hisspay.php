<?php
declare(strict_types=1);

/**
 * HissPay 代收（托管收银台）网关配置
 *
 * .env 示例（[HISSPAY] 段）：
 *   GATEWAY = https://transaction.hisspay.com
 *   APP_ID = 应用标识（商户后台「应用密钥」里的 appId）
 *   APP_SECRET = 应用密钥
 *   NOTIFY_URL = https://域名/api/v1/recharge/notifyHissPay
 *   RETURN_URL = https://域名/ai/CryptoPay.html?pay=ok
 *   RETURN_URL_VIP = https://域名/ai/Subscriptions.html?pay=ok
 *   CURRENCY = USD
 *   PRODUCT = USD-EKCBBONA
 *   ORDER_PREFIX = HSP
 *
 * 签名：请求体 + appId/timestamp/nonce 按 key 升序拼 k=v&k=v，末尾追加 appSecret，
 *       SHA-256 小写十六进制（不是 HMAC / RSA）。
 */
return [
    'gateway'        => rtrim((string) env('hisspay.gateway', 'https://transaction.hisspay.com'), '/'),
    'app_id'         => (string) env('hisspay.app_id', ''),
    'app_secret'     => (string) env('hisspay.app_secret', ''),
    'notify_url'     => (string) env('hisspay.notify_url', ''),
    'return_url'     => (string) env('hisspay.return_url', ''),
    // 会员订阅页回跳
    'return_url_vip' => (string) env('hisspay.return_url_vip', ''),
    // 订单币种（须与商户开通的代收产品一致）
    'currency'       => strtoupper((string) env('hisspay.currency', 'USD')),
    // 代收产品编码；后台通道的「网关标识」非空时优先用通道里的值
    'product'        => (string) env('hisspay.product', 'USD-EKCBBONA'),
    'order_prefix'   => (string) env('hisspay.order_prefix', 'HSP'),
    'timeout'        => 20,
];
