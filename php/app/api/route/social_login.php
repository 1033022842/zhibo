<?php

use think\facade\Route;

Route::get('live/loginOptions', '\app\api\controller\LoginGateway@options');
Route::post('live/sendEmailLoginCode', '\app\api\controller\LoginGateway@sendEmailLoginCode');
Route::post('live/emailCodeLogin', '\app\api\controller\LoginGateway@emailCodeLogin');
Route::post('live/socialStart/:provider', '\app\api\controller\LoginGateway@socialStart')
    ->pattern(['provider' => 'google|discord|twitter']);
Route::get('live/socialCallback/:provider', '\app\api\controller\LoginGateway@socialCallback')
    ->pattern(['provider' => 'google|discord|twitter']);
Route::post('live/socialExchange', '\app\api\controller\LoginGateway@socialExchange');
Route::post('live/telegramLogin', '\app\api\controller\LoginGateway@telegramLogin');
Route::post('live/telegramStart', '\app\api\controller\LoginGateway@telegramStart');
Route::post('live/telegramPoll', '\app\api\controller\LoginGateway@telegramPoll');
// Telegram bot webhook（setWebhook 指向这里；对 Telegram 服务器开放，无需鉴权中间件）
Route::post('live/telegramWebhook', '\app\api\controller\LoginGateway@telegramWebhook');
