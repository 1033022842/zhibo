<?php

use think\facade\Route;

// 公开接口 (无需路由显式注册，ThinkPHP 多应用模式自动由 URL 映射到控制器/方法)
// URL: POST /api/live/register → Controller: Live, Action: register

// 需登录接口 - 显式注册以附加中间件
Route::post('live/logout',           '\app\api\controller\Live@logout')->middleware(\app\live\middleware\Auth::class);
Route::get('live/profile',           '\app\api\controller\Live@profile')->middleware(\app\live\middleware\Auth::class);
Route::get('live/userInfo',          '\app\api\controller\Live@userInfo')->middleware(\app\live\middleware\Auth::class);
Route::put('live/update-profile',    '\app\api\controller\Live@updateProfile')->middleware(\app\live\middleware\Auth::class);
Route::post('live/customRoleOne',    '\app\api\controller\Live@customRoleOne')->middleware(\app\live\middleware\Auth::class);
Route::post('live/customRoleCreate', '\app\api\controller\Live@customRoleCreate')->middleware(\app\live\middleware\Auth::class);
Route::post('live/customOneList',    '\app\api\controller\Live@customOneList')->middleware(\app\live\middleware\Auth::class);
Route::post('live/upload',           '\app\api\controller\Live@upload')->middleware(\app\live\middleware\Auth::class);
Route::post('live/uploadMediaAsset',  '\app\api\controller\Live@uploadMediaAsset')->middleware(\app\live\middleware\Auth::class);
Route::get('live/mediaAssetList',    '\app\api\controller\Live@mediaAssetList')->middleware(\app\live\middleware\Auth::class);
Route::post('live/mediaAssetEdit',    '\app\api\controller\Live@mediaAssetEdit')->middleware(\app\live\middleware\Auth::class);
Route::post('live/mediaAssetDelete',  '\app\api\controller\Live@mediaAssetDelete')->middleware(\app\live\middleware\Auth::class);
Route::get('live/replayClips',       '\app\api\controller\Live@replayClips')->middleware(\app\live\middleware\Auth::class);
Route::get('live/affection',         '\app\api\controller\Live@affection')->middleware(\app\live\middleware\Auth::class);
Route::post('live/buyAffection',     '\app\api\controller\Live@buyAffection')->middleware(\app\live\middleware\Auth::class);
Route::post('live/unlockVideo',      '\app\api\controller\Live@unlockVideo')->middleware(\app\live\middleware\Auth::class);

// VIP 定制角色视频（AI 电脑生成）
Route::get('customVideo/options',    '\app\api\controller\CustomVideo@options')->middleware(\app\live\middleware\Auth::class);
Route::post('customVideo/submit',    '\app\api\controller\CustomVideo@submit')->middleware(\app\live\middleware\Auth::class);
Route::get('customVideo/myList',     '\app\api\controller\CustomVideo@myList')->middleware(\app\live\middleware\Auth::class);
// worker 侧（X-Api-Key 鉴权，控制器内 middleware only）
Route::get('customVideo/pending',    '\app\api\controller\CustomVideo@pending');
Route::post('customVideo/accept',    '\app\api\controller\CustomVideo@accept');
Route::post('customVideo/uploadVideo', '\app\api\controller\CustomVideo@uploadVideo');

// 公开接口 - 显式注册
Route::post('live/registerFromAi',   '\app\api\controller\Live@registerFromAi');
Route::post('live/login',            '\app\api\controller\Live@login');
Route::post('live/refresh_token',    '\app\api\controller\Live@refreshToken');
Route::get('live/channelType',       '\app\api\controller\Live@channelType');
Route::get('live/customPrice',       '\app\api\controller\Live@customPrice');
Route::get('live/chatHistory',       '\app\api\controller\Live@chatHistory');

// WHEP WebRTC 代理（透传到 MediaMTX）
Route::post('v1/whep/<app>/<stream>', '\app\api\controller\Whep@index');
