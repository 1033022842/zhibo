<?php
/**
 * FlowPay（safepaycash）支付网关配置
 *
 * .env 示例（[FLOWPAY] 段）：
 *   GATEWAY = https://api.safepaycash.com
 *   MCHID = 商户号
 *   KEY = 商户密钥（签名末尾拼接）
 *   GCODE = 默认产品编码（商户后台查询；不在 channels 里时以 channels 第一条为准）
 *   NOTIFY_URL = https://域名/api/v1/recharge/notifyFlowPay
 *   RETURN_URL = http://域名:8082/purchasePoints.html
 *   RETURN_URL_VIP = http://域名:8082/Subscriptions.html
 *   MIN_AMOUNT = 15
 */
return [
    // 网关地址（不含接口路径）
    'gateway'        => rtrim((string) env('flowpay.gateway', 'https://api.safepaycash.com'), '/'),
    'mchid'          => (string) env('flowpay.mchid', ''),
    'key'            => (string) env('flowpay.key', ''),
    'gcode'          => (string) env('flowpay.gcode', ''),
    /*
     * 前台可选支付通道：gcode => 前台展示名（数组顺序即前台按钮顺序）
     *
     * 网关同一个收银台（ramp.amopay.io）下挂了多套收单路由，每条 gcode 对应一套：
     * 下单金额 / 到账金额 / 收银台地址完全一致，差别只在发卡行侧的收单通道。
     * 所以前台把它们平铺成多个「Pay with xxx」按钮，由用户自选。
     */
    'channels'       => [
        // gcode ↔ 通道名的对应关系以 FlowPay 商户后台为准，不能凭编号猜
        // （曾配错：把 666888 当成 applepay，实际是 Visa）
        '666777' => 'Amopay-applepay',
        '666999' => 'Amopay-GGPay',
        '666888' => 'Amopay-Visa',
    ],
    'notify_url'     => (string) env('flowpay.notify_url', ''),
    'return_url'     => (string) env('flowpay.return_url', ''),
    // 会员订阅页同步跳回地址
    'return_url_vip' => (string) env('flowpay.return_url_vip', ''),
    // 网关最小支付金额（USD），低于该金额下单会被网关拒绝。
    // 实测该商户的收单通道最低 $15：14.50 会被网关以「没有找到可用的通道」拒掉，15.00 正常，
    // 所以这里必须 >= 15，否则用户在收银台会看到一个看不懂的网关报错。
    'min_amount'     => (float) env('flowpay.min_amount', 15),
    // 请求超时（秒）
    'timeout'        => 15,
    // 订单有效期（分钟）
    'expire_minutes' => 120,
];
