<?php
header('Content-Type: text/plain');
header('X-Debug: reached');
echo "OK - PHP is responding\n";
echo "Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
