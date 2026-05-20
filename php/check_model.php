<?php
define('APP_DEBUG', true);

require __DIR__ . '/vendor/autoload.php';

try {
    $user = \app\live\model\User::find(1);
    echo 'find(1): ' . var_export($user ? $user->toArray() : null, true) . PHP_EOL;

    $userByNo = \app\live\model\User::where('user_no', 'U202605170158154714')->find();
    echo 'where user_no: ' . var_export($userByNo ? $userByNo->toArray() : null, true) . PHP_EOL;

    echo 'connection: ' . \app\live\model\User::getConfig('prefix') . PHP_EOL;
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
