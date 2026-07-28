<?php

use think\facade\Route;

// 公开接口 (无需路由显式注册，ThinkPHP 多应用模式自动由 URL 映射到控制器/方法)
// URL: POST /api/live/register → Controller: Live, Action: register

// 需登录接口 - 显式注册以附加中间件
Route::post('live/logout',           '\app\api\controller\Live@logout')->middleware(\app\live\middleware\Auth::class);
Route::get('live/profile',           '\app\api\controller\Live@profile')->middleware(\app\live\middleware\Auth::class);
Route::get('live/userInfo',          '\app\api\controller\Live@userInfo')->middleware(\app\live\middleware\Auth::class);
Route::put('live/update-profile',    '\app\api\controller\Live@updateProfile')->middleware(\app\live\middleware\Auth::class);
Route::post('live/customRoleOne',    '\app\api\controller\Live@customRoleOne')->middleware(\app\live\middleware\Auth::class);
Route::post('live/customOneList',    '\app\api\controller\Live@customOneList')->middleware(\app\live\middleware\Auth::class);
Route::post('live/upload',           '\app\api\controller\Live@upload')->middleware(\app\live\middleware\Auth::class);
Route::get('live/replayClips',       '\app\api\controller\Live@replayClips')->middleware(\app\live\middleware\Auth::class);

// 公开接口 - 显式注册
Route::post('live/registerFromAi',   '\app\api\controller\Live@registerFromAi');
Route::post('live/login',            '\app\api\controller\Live@login');
Route::post('live/refresh_token',    '\app\api\controller\Live@refreshToken');
Route::get('live/channelType',       '\app\api\controller\Live@channelType');
Route::get('live/customPrice',       '\app\api\controller\Live@customPrice');
