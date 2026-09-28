<?php

use think\facade\Route;

// 公开接口 (无需路由显式注册，ThinkPHP 多应用模式自动由 URL 映射到控制器/方法)
// URL: POST /api/live/register → Controller: Live, Action: register

// 设置页「Payment methods」区块：站点真实可用的支付方式（公开）
Route::get('live/paymentOptions',    '\app\api\controller\Live@paymentOptions');
// 需登录接口 - 显式注册以附加中间件
Route::post('live/logout',           '\app\api\controller\Live@logout')->middleware(\app\live\middleware\Auth::class);
Route::get('live/profile',           '\app\api\controller\Live@profile')->middleware(\app\live\middleware\Auth::class);
Route::get('live/userInfo',          '\app\api\controller\Live@userInfo')->middleware(\app\live\middleware\Auth::class);
Route::put('live/update-profile',    '\app\api\controller\Live@updateProfile')->middleware(\app\live\middleware\Auth::class);
// 新版设置页（setting.html）：改密码 / 改邮箱 / 注销账户
// 注意这台服务器上带 "-" 的路由匹配不到，所以这里用驼峰
Route::post('live/changePassword',   '\app\api\controller\Live@changePassword')->middleware(\app\live\middleware\Auth::class);
Route::post('live/changeEmail',      '\app\api\controller\Live@changeEmail')->middleware(\app\live\middleware\Auth::class);
Route::post('live/deleteAccount',    '\app\api\controller\Live@deleteAccount')->middleware(\app\live\middleware\Auth::class);
// 设置页「Global chat customization」的用户偏好
Route::get('live/userSettings',      '\app\api\controller\Live@userSettings')->middleware(\app\live\middleware\Auth::class);
Route::post('live/saveUserSettings', '\app\api\controller\Live@saveUserSettings')->middleware(\app\live\middleware\Auth::class);
// 设置页「Your personas」的我的人格设定
Route::get('live/userPersona',       '\app\api\controller\Live@userPersona')->middleware(\app\live\middleware\Auth::class);
Route::post('live/saveUserPersona',  '\app\api\controller\Live@saveUserPersona')->middleware(\app\live\middleware\Auth::class);
// 设置页订阅管理（Current plan / Payment date / Subscription renewal / 取消订阅）
Route::get('live/subscription',      '\app\api\controller\Live@subscription')->middleware(\app\live\middleware\Auth::class);
Route::post('live/cancelSubscription', '\app\api\controller\Live@cancelSubscription')->middleware(\app\live\middleware\Auth::class);
// 成就（设置页 Achievements / 顶栏奖杯徽章 / 侧边栏）
// 公开接口：未登录也能拿到完整成就清单（全部未解锁），页面不会空白
Route::get('live/achievements',      '\app\api\controller\Live@achievements');
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
Route::post('live/googleLogin',      '\app\api\controller\Live@googleLogin');
Route::get('live/googleClientId',    '\app\api\controller\Live@googleClientId');
Route::post('live/refresh_token',    '\app\api\controller\Live@refreshToken');
Route::get('live/channelType',       '\app\api\controller\Live@channelType');
Route::get('live/customPrice',       '\app\api\controller\Live@customPrice');
Route::get('live/chatHistory',       '\app\api\controller\Live@chatHistory');
Route::get('live/chatConversations', '\app\api\controller\Live@chatConversations');

// WHEP WebRTC 代理（透传到 MediaMTX）
Route::post('v1/whep/<app>/<stream>', '\app\api\controller\Whep@index');
