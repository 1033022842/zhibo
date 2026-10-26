<?php
/**
 * Cregis 支付引擎配置（收银台模式）
 *
 * 后台：Cregis 客户端 → 支付引擎 → 设置 → 开发者中心
 * 文档：https://developers.cregis.com/zh/reference/waas-api/
 *
 * .env 示例（[CREGIS] 段）：
 *   BASE_URL = https://t-qiwnyuds.cregis.io
 *   PID = 1468496399934560
 *   API_KEY = 项目密钥（仅服务端保存）
 *   NOTIFY_URL = https://本站域名/api/v1/recharge/notifyCregis
 *   RETURN_URL = https://本站域名/ai/CryptoPay.html?pay=ok
 *   RETURN_URL_VIP = https://本站域名/ai/Subscriptions.html?pay=ok
 *   LANGUAGE = sc
 *   ORDER_PREFIX = CK
 *
 * 注意：
 *  - API Key 必须与 PID 同项目，且调用服务器 IP 要加进「设置 → IP 白名单」
 *  - test 环境 Base URL 带 t- 前缀，上线前务必换成生产项目的 URL / PID / KEY
 *  - 建单金额与币种即订单币种（建议 USD），收银台内用户自选支付币种由 Cregis 换算
 */
return [
    // 项目专属 Base URL（不带 /api/v2）
    'base_url'       => rtrim((string) env('cregis.base_url', ''), '/'),
    'pid'            => (string) env('cregis.pid', ''),
    'api_key'        => (string) env('cregis.api_key', ''),
    // 异步回调地址（本站域名）
    'notify_url'     => (string) env('cregis.notify_url', ''),
    // 支付完成后浏览器跳回（代币充值 / 会员订阅分开配）
    'return_url'     => (string) env('cregis.return_url', ''),
    'return_url_vip' => (string) env('cregis.return_url_vip', ''),
    // 收银台语言：en / tc / sc
    'language'       => (string) env('cregis.language', 'en'),
    // 订单号前缀：各站独立，避免商户订单号冲突
    'order_prefix'   => (string) env('cregis.order_prefix', 'CK'),
    // 限定可用支付币种（逗号分隔，如 USDT-TRC20,USDT-BEP20）；留空 = 收银台全部币种
    'tokens'         => (string) env('cregis.tokens', ''),
    // 请求超时（秒）
    'timeout'        => 20,
    // 订单有效期（分钟，Cregis 允许 10~1440）
    'expire_minutes' => 120,
];
