<?php

use think\facade\Route;

// 众筹接口
Route::group('v1/crowdfunding', function () {
    // 公开接口
    Route::get('list', '\app\api\controller\Crowdfunding@list');
    Route::get('detail', '\app\api\controller\Crowdfunding@detail');
});

// 需登录的众筹接口
Route::group('v1/crowdfunding', function () {
    Route::post('initiate', '\app\api\controller\Crowdfunding@initiate');
    Route::post('pledge', '\app\api\controller\Crowdfunding@pledge');
    Route::get('my-projects', '\app\api\controller\Crowdfunding@myProjects');
    Route::get('my-pledges', '\app\api\controller\Crowdfunding@myPledges');
    Route::post('link-persona', '\app\api\controller\Crowdfunding@linkPersona');
    Route::get('check-active', '\app\api\controller\Crowdfunding@checkActive');
    Route::get('balance', '\app\api\controller\Crowdfunding@balance');
    Route::post('topup', '\app\api\controller\Crowdfunding@topup');
})->middleware(\app\live\middleware\Auth::class);
