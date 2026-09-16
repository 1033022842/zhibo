<?php

use think\facade\Route;

// AI 女友端 Posts 动态（动态由后台配置，前台公开读取）
Route::get('live/posts', '\app\api\controller\Posts@items');
