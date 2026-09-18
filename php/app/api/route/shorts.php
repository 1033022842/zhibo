<?php

use think\facade\Route;

// AI 女友端 Candy Shorts 短剧（卡片与剧集由后台配置，前台公开读取）
Route::get('live/shorts', '\app\api\controller\Shorts@items');

// 某部短剧的剧集列表（公开；未解锁的集不下发 video_url）
Route::get('live/shortEpisodes', '\app\api\controller\Shorts@episodes');

// 解锁某一集（扣钻石，需登录）
Route::post('live/shortUnlock', '\app\api\controller\Shorts@unlock')
    ->middleware(\app\live\middleware\Auth::class);
