<?php
return [
    'secret'     => env('jwt.secret', 'live-platform-jwt-secret-key-2026'),
    'access_ttl' => 7200,
    'refresh_ttl' => 604800,
    'issuer'     => 'live-platform',
];
