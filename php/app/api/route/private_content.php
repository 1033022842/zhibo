<?php

use think\facade\Route;

// AI 女友端 Private Content 私密内容（卡片由后台配置，前台公开读取）
Route::get('live/privateContents', '\app\api\controller\PrivateContent@items');

// 解锁（扣钻石，需登录）
Route::post('live/privateUnlock', '\app\api\controller\PrivateContent@unlock')
    ->middleware(\app\live\middleware\Auth::class);
