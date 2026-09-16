<?php

use think\facade\Route;

// AI 女友端 Candy Shop 商店（商品由后台配置，前台公开读取）
Route::get('live/shopItems', '\app\api\controller\Shop@items');
Route::get('live/shopItem',  '\app\api\controller\Shop@item');
