<?php

use think\facade\Route;

// 用户创作（独立完成）接口，均需登录
Route::group('v1/creator', function () {
    Route::post('independent', '\app\api\controller\Creator@independent');
    Route::get('my-creations', '\app\api\controller\Creator@myCreations');
})->middleware(\app\live\middleware\Auth::class);
