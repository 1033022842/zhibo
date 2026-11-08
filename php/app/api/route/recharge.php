<?php

use think\facade\Route;

// star-pay 异步通知（免登录，回调方为支付网关）
Route::post('v1/recharge/notify', '\app\api\controller\Recharge@notify');

// FlowPay 异步通知（免登录，回调方为支付网关）
Route::post('v1/recharge/notifyFlowPay', '\app\api\controller\Recharge@notifyFlowPay');

// MKPay（巴西）异步通知（免登录，回调方为支付网关）
Route::post('v1/recharge/notifyMkPay', '\app\api\controller\Recharge@notifyMkPay');

// MKPay（越南）异步通知（免登录，回调方为支付网关）
Route::post('v1/recharge/notifyMkPayVn', '\app\api\controller\Recharge@notifyMkPayVn');

// MKPay（墨西哥）异步通知（免登录，回调方为支付网关）
Route::post('v1/recharge/notifyMkPayMx', '\app\api\controller\Recharge@notifyMkPayMx');

// MKPay（印度）异步通知（免登录，回调方为支付网关）
Route::post('v1/recharge/notifyMkPayIn', '\app\api\controller\Recharge@notifyMkPayIn');

// Cregis 异步通知（免登录，回调方为支付网关）
Route::post('v1/recharge/notifyCregis', '\app\api\controller\Recharge@notifyCregis');

// AIPowerPay 异步通知（免登录，回调方为支付网关）
Route::post('v1/recharge/notifyAiPowerPay', '\app\api\controller\Recharge@notifyAiPowerPay');

// WisonPay 美国钱包支付回调（免登录，RSA 验签）。
Route::post('v1/recharge/notifyWisonPay', '\app\api\controller\Recharge@notifyWisonPay');

// HissPay 代收异步通知（免登录，SHA-256 签名，ACK 纯文本 SUCCESS）
Route::post('v1/recharge/notifyHissPay', '\app\api\controller\Recharge@notifyHissPay');

// 铭森支付（微信/支付宝）异步通知（免登录，MD5 验签）
Route::post('v1/recharge/notifySjhsll', '\app\api\controller\Recharge@notifySjhsll');

// Skytrade 固定商品链接支付成功回调（免登录，md5(order_no+密钥) 验签；GET/POST/JSON 均可）
Route::rule('v1/recharge/notifySkytrade', '\app\api\controller\Recharge@notifySkytrade', 'GET|POST');

// Skytrade 浏览器回跳（第三方配置 success/cancel 回跳地址；按订单类型 302 到订阅页/代币页）
Route::rule('v1/recharge/returnSkytrade', '\app\api\controller\Recharge@returnSkytrade', 'GET|POST');

// 铭森支付浏览器回跳（网关 POST 回跳，静态页会 405，这里 302 到充值/订阅页）
Route::rule('v1/recharge/returnSjhsll', '\app\api\controller\Recharge@returnSjhsll', 'GET|POST');

// AI 女友端充值套餐 + 支付方式（免登录展示）
Route::get('v1/recharge/packages', '\app\api\controller\Recharge@packages');

// 法币支付通道（免登录展示：币种 + 后台汇率）
Route::get('v1/recharge/fiat-channels', '\app\api\controller\Recharge@fiatChannels');

// 自选点数报价（免登录，与下单同算法）
Route::get('v1/recharge/quote', '\app\api\controller\Recharge@quote');

// 自选金额报价（按 USD 输入；免登录，前端滑条用）
Route::get('v1/recharge/quote-amount', '\app\api\controller\Recharge@quoteAmount');

Route::group('v1/recharge', function () {
    Route::get('channels', '\app\api\controller\Recharge@channels');
    Route::post('submit', '\app\api\controller\Recharge@submit');
    Route::get('orders', '\app\api\controller\Recharge@orders');
    Route::get('status', '\app\api\controller\Recharge@status');
    Route::post('upload', '\app\api\controller\Recharge@upload');
    // star-pay 下单
    Route::post('createOrder', '\app\api\controller\Recharge@createOrder');
})->middleware(\app\live\middleware\Auth::class);
