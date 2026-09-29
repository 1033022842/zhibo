<?php

use think\facade\Route;

/**
 * 邀请好友（Affiliate Program / Inviter 页）
 *
 * 注意：本机只有 app/api/route/ 下的模块路由文件在运行时生效，
 * 放在全局 route/ 目录里的规则不会注册（route:list 能看到但不生效）。
 */
Route::get('live/invite',          '\app\api\controller\Invite@index')->middleware(\app\live\middleware\Auth::class);
Route::post('live/saveInviteCode', '\app\api\controller\Invite@saveCode')->middleware(\app\live\middleware\Auth::class);
