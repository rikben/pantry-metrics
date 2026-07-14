<?php
// /public_html/config/app.php

declare(strict_types=1);

return [
    'name' => 'Pantry Metrics',
    'environment' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    'url' => env('APP_URL', 'http://localhost:8080'),
    'key' => env('APP_KEY', ''),
];
