<?php
use think\facade\Route;

Route::get('v1/feed/live', '\app\api\controller\Room@feedLive');
Route::get('v1/rooms/:id', '\app\api\controller\Room@detail');
