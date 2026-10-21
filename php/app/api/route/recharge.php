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

// AI 女友端充值套餐 + 支付方式（免登录展示）
Route::get('v1/recharge/packages', '\app\api\controller\Recharge@packages');

// 法币支付通道（免登录展示：币种 + 后台汇率）
Route::get('v1/recharge/fiat-channels', '\app\api\controller\Recharge@fiatChannels');

// 自选点数报价（免登录，与下单同算法）
Route::get('v1/recharge/quote', '\app\api\controller\Recharge@quote');

Route::group('v1/recharge', function () {
    Route::get('channels', '\app\api\controller\Recharge@channels');
    Route::post('submit', '\app\api\controller\Recharge@submit');
    Route::get('orders', '\app\api\controller\Recharge@orders');
    Route::get('status', '\app\api\controller\Recharge@status');
    Route::post('upload', '\app\api\controller\Recharge@upload');
    // star-pay 下单
    Route::post('createOrder', '\app\api\controller\Recharge@createOrder');
})->middleware(\app\live\middleware\Auth::class);
