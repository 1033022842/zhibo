<?php
use think\facade\Route;

Route::get('v1/feed/live', '\app\api\controller\Room@feedLive');
Route::get('v1/rooms/:id', '\app\api\controller\Room@detail');

Route::group('v1/rooms/switch', function () {
    Route::post('privilege', '\app\api\controller\RoomSwitch@triggerPrivilege');
    Route::post('confirm', '\app\api\controller\RoomSwitch@confirm');
    Route::post('fail', '\app\api\controller\RoomSwitch@fail');
    Route::post('expire-check', '\app\api\controller\RoomSwitch@expireCheck');
    Route::get('status', '\app\api\controller\RoomSwitch@status');
});
