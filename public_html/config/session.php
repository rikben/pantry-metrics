<?php
// /public_html/config/session.php

declare(strict_types=1);

return [
    'name' => env('SESSION_NAME', 'pantry_metrics_session'),
    'secure' => filter_var(env('SESSION_SECURE', 'false'), FILTER_VALIDATE_BOOL),
    'same_site' => env('SESSION_SAMESITE', 'Lax'),
];
