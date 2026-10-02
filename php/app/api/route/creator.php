<?php

use think\facade\Route;

// 用户创作（独立完成）接口，均需登录
Route::group('v1/creator', function () {
    Route::post('independent', '\app\api\controller\Creator@independent');
    Route::get('my-creations', '\app\api\controller\Creator@myCreations');
    // 作品本人：查看详情（编辑回填）、修改、删除
    Route::get('detail', '\app\api\controller\Creator@detail');
    Route::post('update', '\app\api\controller\Creator@update');
    Route::post('remove', '\app\api\controller\Creator@remove');
})->middleware(\app\live\middleware\Auth::class);

// 收入排行榜（公开）
Route::get('v1/creator/leaderboard', '\app\api\controller\Creator@leaderboard');
