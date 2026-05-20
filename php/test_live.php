<?php
require __DIR__ . '/vendor/autoload.php';

try {
    $app = new think\App();
    $c = new app\api\controller\LiveUser($app);
    echo "OK: " . get_class($c) . PHP_EOL;

    $methods = get_class_methods($c);
    foreach ($methods as $m) {
        echo "  method: $m" . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
