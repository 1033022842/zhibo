<?php

use think\facade\Route;

// 公开接口 (无需路由显式注册，ThinkPHP 多应用模式自动由 URL 映射到控制器/方法)
// URL: POST /api/live/register → Controller: Live, Action: register

// 需登录接口 - 显式注册以附加中间件
Route::post('live/logout',           '\app\api\controller\Live@logout')->middleware(\app\live\middleware\Auth::class);
Route::get('live/profile',           '\app\api\controller\Live@profile')->middleware(\app\live\middleware\Auth::class);
Route::put('live/update-profile',    '\app\api\controller\Live@updateProfile')->middleware(\app\live\middleware\Auth::class);
