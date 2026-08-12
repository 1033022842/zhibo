<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// [ 应用入口文件 ]
namespace think;

$server = isset($_REQUEST['server']) || isset($_SERVER['HTTP_SERVER']) || substr($_SERVER['REQUEST_URI'], 1, 9) == 'index.php' || $_SERVER['REQUEST_METHOD'] == 'OPTIONS' || str_starts_with($_SERVER['REQUEST_URI'], '/api/') || str_starts_with($_SERVER['REQUEST_URI'], '/admin/') || str_starts_with($_SERVER['REQUEST_URI'], '/live/');
if (!$server) {
    /*
     * 用户访问前端
     * 不在tp加载后判断，为了安全的使用 exit()（常驻内存运行时不走本文件）
     */
    $rootPath = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR;

    // 安装检测-s
    if (!is_file($rootPath . 'install.lock') && is_file($rootPath . 'install' . DIRECTORY_SEPARATOR . 'index.html')) {
        header("location:" . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR);
        exit();
    }
    // 安装检测-e

    // 检测是否已编译前端（如果存在 index.html，则访问）-s
    if (is_file($rootPath . 'index.html')) {
        header("location:" . DIRECTORY_SEPARATOR . 'index.html');
        exit();
    }
    // 检测是否已编译前端-e
}

$debugStart = microtime(true);
error_log(sprintf('[DEBUG %s] %s %s - START', date('H:i:s'), $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] ?? '/'), 3, __DIR__ . '/debug.log');

require __DIR__ . '/../vendor/autoload.php';
error_log(sprintf('[DEBUG] autoload loaded (%.3fs)', microtime(true) - $debugStart), 3, __DIR__ . '/debug.log');

// 执行HTTP应用并响应
$http = (new App())->http;
error_log(sprintf('[DEBUG] App created (%.3fs)', microtime(true) - $debugStart), 3, __DIR__ . '/debug.log');

$response = $http->run();
error_log(sprintf('[DEBUG] Response generated (%.3fs)', microtime(true) - $debugStart), 3, __DIR__ . '/debug.log');

$response->send();

$http->end($response);
