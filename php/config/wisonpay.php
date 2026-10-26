<?php
declare(strict_types=1);

return [
    'base_url' => rtrim((string) env('wisonpay.base_url', 'https://api.wisonpay.com'), '/'),
    // 兼容旧配置：单地区时代的 appid（美国），新代码统一走 regions
    'app_id' => (string) env('wisonpay.app_id', ''),
    // 私钥保存在站点根目录之外，不写入前端或代码仓库。
    'private_key_file' => (string) env('wisonpay.private_key_file', ''),
    'private_key' => (string) env('wisonpay.private_key', ''),
    'platform_public_key' => (string) env('wisonpay.platform_public_key', ''),
    'notify_url' => (string) env('wisonpay.notify_url', ''),
    'order_prefix' => (string) env('wisonpay.order_prefix', 'WKP'),
    'timeout' => 20,

    /**
     * 四个地区各有独立的 appid、下单路径、查单路径和金额单位：
     *
     *   US  美元，浮点（10.00），手机号形如 14241276442
     *   IN  卢比，整数，手机号 10 位不带 91
     *   TH  泰铢，整数，手机号 10 位；channelId=58 二维码、5 银行卡（还要卡号+银行）
     *   ID  印尼盾，整数，手机号 0/62 开头；paymentType=QR/WA/VA（VA 还要 bank）
     */
    'regions' => [
        'US' => [
            'appid'    => (string) env('wisonpay.appid_us', '1780910416'),
            'path'     => '/interface/us/payin.php',
            'query'    => '/interface/us/RechargeQuery.php',
            'country'  => 'US',
            'currency' => 'USD',
            'decimals' => 2,
        ],
        'IN' => [
            'appid'    => (string) env('wisonpay.appid_in', '1779533536'),
            'path'     => '/interface/payin.php',
            'query'    => '/interface/RechargeQuery.php',
            'country'  => '',
            'currency' => 'INR',
            'decimals' => 0,
        ],
        'TH' => [
            'appid'    => (string) env('wisonpay.appid_th', '1780907556'),
            'path'     => '/interface/th/payin.php',
            'query'    => '/interface/th/RechargeQuery.php',
            'country'  => 'TH',
            'currency' => 'THB',
            'decimals' => 0,
        ],
        'ID' => [
            'appid'    => (string) env('wisonpay.appid_id', '1786499512'),
            'path'     => '/interface/id/payin.php',
            'query'    => '/interface/id/RechargeQuery.php',
            'country'  => '',
            'currency' => 'IDR',
            'decimals' => 0,
        ],
    ],
];
