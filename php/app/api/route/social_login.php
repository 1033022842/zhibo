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
