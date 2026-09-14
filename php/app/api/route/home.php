<?php

use think\facade\Route;

// AI 女友端首页（candy.ai 风格）接口
Route::group('v1/home', function () {
    // 轮播图列表（公开，含点击跳转链接）
    Route::get('banners', '\app\api\controller\Home@banners');
    // 推荐角色列表（公开）
    Route::get('characters', '\app\api\controller\Home@characters');
});
