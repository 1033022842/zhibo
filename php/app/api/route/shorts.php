<?php

use think\facade\Route;

// AI 女友端 Candy Shorts 短剧（卡片由后台配置，前台公开读取）
Route::get('live/shorts', '\app\api\controller\Shorts@items');
