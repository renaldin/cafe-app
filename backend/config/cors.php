<?php

$origins = array_values(array_filter(array_map('trim', explode(',', (string) env('FRONTEND_URL', 'http://localhost:3000')))));

return [
    'paths' => ['api/*', 'up'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With', 'If-None-Match'],
    'exposed_headers' => ['ETag', 'Content-Disposition', 'Retry-After'],
    'max_age' => 86400,
    'supports_credentials' => false,
];
