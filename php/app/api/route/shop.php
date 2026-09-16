<?php

use think\facade\Route;

// AI 女友端 Candy Shop 商店（商品由后台配置，前台公开读取）
Route::get('live/shopItems', '\app\api\controller\Shop@items');
Route::get('live/shopItem',  '\app\api\controller\Shop@item');

// 我的背包（需登录）
Route::get('live/inventory', '\app\api\controller\Shop@inventory')
    ->middleware(\app\live\middleware\Auth::class);

// 购买商品（扣钻石，需登录）
Route::post('live/shopBuy', '\app\api\controller\Shop@buy')
    ->middleware(\app\live\middleware\Auth::class);
