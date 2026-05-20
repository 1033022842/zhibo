<?php
define('APP_DEBUG', true);
$_GET['server'] = '1';

require __DIR__ . '/vendor/autoload.php';

$app = new think\App();
$http = $app->http;

$response = $http->run();
$response->send();
$http->end($response);
