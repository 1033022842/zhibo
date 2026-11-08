<?php

use think\facade\Route;

// 行为埋点采集（公开，无需登录）：页面浏览 / 停留时长 / 功能事件，批量上报
Route::group('v1/track', function () {
    Route::post('collect', '\app\api\controller\Track@collect');
});
