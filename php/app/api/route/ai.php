<?php
use think\facade\Route;

Route::get('v1/ai/tasks/pull', '\app\api\controller\AiTask@pull');
Route::post('v1/ai/tasks/accept', '\app\api\controller\AiTask@accept');
Route::post('v1/ai/tasks/progress', '\app\api\controller\AiTask@progress');
Route::post('v1/ai/tasks/complete', '\app\api\controller\AiTask@complete');
Route::post('v1/ai/tasks/fail', '\app\api\controller\AiTask@fail');
Route::post('v1/ai/tasks/stream-end', '\app\api\controller\AiTask@streamEnd');
Route::get('v1/ai/tasks/stream-token', '\app\api\controller\AiTask@streamToken');
Route::post('v1/srs/unpublish', '\app\api\controller\AiTask@streamEndByRoom');
Route::get('v1/srs/unpublish', '\app\api\controller\AiTask@streamEndByRoom');
