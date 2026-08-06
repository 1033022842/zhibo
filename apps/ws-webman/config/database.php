<?php

return [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'database' => getenv('DB_NAME') ?: 'zhibo',
    'username' => getenv('DB_USER') ?: 'zhibo',
    'password' => getenv('DB_PASSWORD') ?: '12345678',
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
];
