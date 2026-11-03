<?php

use think\facade\Route;

/**
 * AI 建角向导（createCharacter）：角色创建 / 形象图生成 / 计费动作
 *
 * 注意：本机只有 app/api/route/ 下的模块路由文件在运行时生效
 * （全局 route/live.php 在多应用模式下不加载——TP8 多应用），
 * 所以这里显式注册并挂 Auth；同时 Live 控制器 $middleware only 列表也含这些方法（双保险）。
 */
Route::post('live/customRoleCreate',      '\app\api\controller\Live@customRoleCreate')->middleware(\app\live\middleware\Auth::class);
Route::get('live/customRoleImageStatus',  '\app\api\controller\Live@customRoleImageStatus')->middleware(\app\live\middleware\Auth::class);
Route::post('live/customRoleSave',        '\app\api\controller\Live@customRoleSave')->middleware(\app\live\middleware\Auth::class);
Route::post('live/customRoleRegenerate',  '\app\api\controller\Live@customRoleRegenerate')->middleware(\app\live\middleware\Auth::class);
