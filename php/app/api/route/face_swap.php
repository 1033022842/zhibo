<?php

use think\facade\Route;

// AI 女友端：上传图片换脸（固定视频）
// 注意：多应用模式下必须放在 app/api/route/ 下（顶层 route/ 目录的规则不生效）
// 鉴权：FaceSwap 控制器内声明 Auth 中间件（only: create/tasks/task），模板列表公开
Route::get('live/faceSwapTemplates', '\app\api\controller\FaceSwap@templates');
Route::post('live/faceSwapCreate', '\app\api\controller\FaceSwap@create');
Route::get('live/faceSwapTasks', '\app\api\controller\FaceSwap@tasks');
Route::get('live/faceSwapTask', '\app\api\controller\FaceSwap@task');
