<?php

use think\facade\Route;

// 观看历史：上报进度（需登录）
Route::post('live/watchReport', '\app\api\controller\WatchHistory@report')
    ->middleware(\app\live\middleware\Auth::class);

// 观看历史：当前用户最近观看（未登录返回空列表）
Route::get('live/watchHistory', '\app\api\controller\WatchHistory@items');
