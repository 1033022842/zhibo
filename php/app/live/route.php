<?php

use think\facade\Route;

// 公开接口
Route::post('user/register', 'live.controller.UserController/register');
Route::post('user/login', 'live.controller.UserController/login');
Route::post('user/refresh-token', 'live.controller.UserController/refreshToken');

// 需登录接口
Route::group('user', function () {
    Route::post('logout', 'live.controller.UserController/logout');
    Route::get('profile', 'live.controller.UserController/profile');
    Route::put('profile', 'live.controller.UserController/updateProfile');
})->middleware(\app\live\middleware\Auth::class);
