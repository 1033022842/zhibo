#!/usr/bin/env php
<?php
chdir(__DIR__);
require_once __DIR__ . '/vendor/autoload.php';

// 载入 .env：本部署没装 vlucas/phpdotenv，webman 内置的 .env 读取会因为
// class_exists('Dotenv\Dotenv') 为 false 而整体跳过，导致 getenv() 全部返回 false，
// config/database.php / config/redis.php 只能退化成硬编码默认值
//（两站数据库账号不同，HeyGirl 会因此连不上库、静默写不进去）。
require_once __DIR__ . '/support/Env.php';
support\Env::load(__DIR__);

support\App::run();
