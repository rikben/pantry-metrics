<?php
// /public_html/config/database.php

declare(strict_types=1);

return [
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', '3306'),
    'database' => env('DB_NAME', 'pantry_metrics'),
    'username' => env('DB_USER', 'pantry_metrics'),
    'password' => env('DB_PASS', ''),
    'charset' => 'utf8mb4',
];
