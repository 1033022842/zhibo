<?php

use think\facade\Route;

// 站点公开配置（像素 + 客服渠道），无需登录，带 60s 缓存
Route::group('v1/site', function () {
    Route::get('config', '\app\api\controller\Site@config');
    Route::post('csClick', '\app\api\controller\Site@csClick');
    // 落地访问记录（流量来源：平台 / 域名 / 链接 / 点击 / 注册 / 付费）
    Route::post('visit', '\app\api\controller\Site@visit');
});
