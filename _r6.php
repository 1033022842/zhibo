<?php
define('ROOT_PATH', __DIR__ . '/php');
require ROOT_PATH . '/vendor/autoload.php';
$app = new \think\App(ROOT_PATH); $app->initialize();
$m = new \app\admin\service\ChannelWorkerManager();
$m->stop(6); sleep(1);
echo ($m->start(6)['ok']?'OK':'FAIL') . "\n";
