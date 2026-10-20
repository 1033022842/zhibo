<?php

use think\facade\Route;

// AI 女友端 Private Content 私密内容（卡片由后台配置，前台公开读取）
Route::get('live/privateContents', '\app\api\controller\PrivateContent@items');

// 单条私密内容（观看历史回跳 / 首页直接播放用；已解锁才下发媒体地址）
Route::get('live/privateContent', '\app\api\controller\PrivateContent@detail');

// 解锁（扣代币，需登录）
Route::post('live/privateUnlock', '\app\api\controller\PrivateContent@unlock')
    ->middleware(\app\live\middleware\Auth::class);
