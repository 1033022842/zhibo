<?php
/**
 * MKPay（巴西）支付网关配置
 *
 * 商户后台：https://br.mkpayeasy.com/admin-OSdkkq/（登录名 sugusai）
 * API 文档：https://s.apifox.cn/5caac221-771e-4b75-929d-962481063367
 *
 * .env 示例（[MKPAY] 段）：
 *   GATEWAY = https://br.mkpayeasy.com
 *   APPID = 697146150936
 *   SIGNKEY = 商户后台「开发者管理」重置获取（平台不保存该密钥）
 *   NOTIFY_URL = https://本站域名/api/v1/recharge/notifyMkPay
 *   ORDER_PREFIX = MKS
 *
 * 注意：
 *  - 接口所有金额单位均为「分」（巴西本币 BRL 的百分之一）
 *  - hey girl 项目与本站共用同一个 appId，两站 MKPay 订单号用不同前缀
 *    （本站 MKS / hey girl MKH）满足网关「商户订单号收+付全程唯一」的要求
 *  - 网关回调出口 IP：54.207.113.227（服务器侧无需白名单，回调由网关发起）
 */
return [
    // 网关地址（不含接口路径）
    'gateway'       => rtrim((string) env('mkpay.gateway', 'https://br.mkpayeasy.com'), '/'),
    'app_id'        => (string) env('mkpay.appid', ''),
    'sign_key'      => (string) env('mkpay.signkey', ''),
    // 异步回调地址（本站域名）
    'notify_url'    => (string) env('mkpay.notify_url', ''),
    // MKPay 订单号前缀：与 hey girl 共用 appId，靠前缀区分两站订单（本站 MKS / hey girl MKH）
    'order_prefix'  => (string) env('mkpay.order_prefix', 'MKS'),
    // 请求超时（秒）
    'timeout'       => 15,
    // 订单有效期（分钟），与法币单 expire_at 口径一致
    'expire_minutes'=> 120,
];
