<?php

use think\facade\Route;

Route::group('v1/recharge', function () {
    Route::get('channels', '\app\api\controller\Recharge@channels');
    Route::post('submit', '\app\api\controller\Recharge@submit');
    Route::get('orders', '\app\api\controller\Recharge@orders');
    Route::get('status', '\app\api\controller\Recharge@status');
    Route::post('upload', '\app\api\controller\Recharge@upload');
})->middleware(\app\live\middleware\Auth::class);
