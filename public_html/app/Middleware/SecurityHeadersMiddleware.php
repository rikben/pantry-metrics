<?php
// /public_html/app/Middleware/SecurityHeadersMiddleware.php

declare(strict_types=1);

namespace App\Middleware;

final class SecurityHeadersMiddleware
{
    public static function handle(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
        header(
            "Content-Security-Policy: default-src 'self'; " .
            "style-src 'self'; script-src 'self'; img-src 'self' data:; " .
            "base-uri 'self'; frame-ancestors 'none'; form-action 'self'"
        );
    }
}
