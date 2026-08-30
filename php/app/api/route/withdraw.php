<?php

use think\facade\Route;

// 提现接口（需登录 + 商家认证）
Route::group('v1/withdraw', function () {
    Route::get('account', '\app\api\controller\Withdraw@account');
    Route::post('account', '\app\api\controller\Withdraw@saveAccount');
    Route::get('preview', '\app\api\controller\Withdraw@preview');
    Route::post('apply', '\app\api\controller\Withdraw@apply');
    Route::get('records', '\app\api\controller\Withdraw@records');
});

// 钱包流水（Financial 页资金记录）
Route::group('v1/wallet', function () {
    Route::get('ledger', '\app\api\controller\Wallet@ledger');
});
