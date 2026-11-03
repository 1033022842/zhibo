<?php
/**
 * 铭森支付（sjhsll）网关配置
 *
 * .env 示例（[SJHSLL] 段）：
 *   GATEWAY = http://sjhsll.com
 *   MEMBERID = 商户号
 *   KEY = 商户 APIKEY
 *   NOTIFY_URL = https://域名/api/v1/recharge/notifySjhsll
 *   RETURN_URL = https://域名/ai/tokens.html
 *   RETURN_URL_VIP = https://域名/ai/taocan.html
 *   ORDER_PREFIX = SJ
 *
 * 通道编码（下单时的 pay_bankcode，后台「网关标识」里填）：
 *   1 = 微信支付   2 = 支付宝
 */
return [
    // 网关地址（不含接口路径）
    'gateway'        => rtrim((string) env('sjhsll.gateway', 'http://sjhsll.com'), '/'),
    'memberid'       => (string) env('sjhsll.memberid', ''),
    'key'            => (string) env('sjhsll.key', ''),
    'notify_url'     => (string) env('sjhsll.notify_url', ''),
    'return_url'     => (string) env('sjhsll.return_url', ''),
    // 会员订阅页同步跳回地址
    'return_url_vip' => (string) env('sjhsll.return_url_vip', ''),
    // 浏览器回跳入口：网关以 POST 方式回调到这里，控制器再 302 到 return_url / return_url_vip
    // （静态 .html 不接受 POST，直接填页面会 405）
    'return_endpoint' => (string) env('sjhsll.return_endpoint', ''),
    // 订单号前缀：网关要求商户订单号唯一且不超过 20 位（SJ + 14 位时间 + 4 位随机 = 20）
    'order_prefix'   => (string) env('sjhsll.order_prefix', 'SJ'),
    // 网关只有识别到浏览器 UA + 本站 Referer 才返回收银台地址；留空则自动取 RETURN_URL 的域名根
    'referer'        => (string) env('sjhsll.referer', ''),
    // 请求超时（秒）
    'timeout'        => 20,
    // 网关只有识别到浏览器 UA 才返回收银台地址，服务端下单必须带
    'user_agent'     => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
];
