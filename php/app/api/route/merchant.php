<?php

use think\facade\Route;

// 商家认证接口
Route::group('v1/merchant', function () {
    Route::post('submit', '\app\api\controller\Merchant@submit');
    Route::get('status', '\app\api\controller\Merchant@status');
    Route::get('detail', '\app\api\controller\Merchant@detail');
    Route::post('upload', '\app\api\controller\Merchant@upload');
})->middleware(\app\live\middleware\Auth::class);
