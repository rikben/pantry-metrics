<?php
// /public_html/config/oidc.php

declare(strict_types=1);

$issuer = rtrim((string) env('OIDC_ISSUER', ''), '/') . '/';

return [
    'enabled' => filter_var(
        env('OIDC_ENABLED', 'false'),
        FILTER_VALIDATE_BOOL
    ),
    'issuer' => $issuer,
    'discovery_url' => env(
        'OIDC_DISCOVERY_URL',
        $issuer !== '/'
            ? $issuer . '.well-known/openid-configuration'
            : ''
    ),
    'client_id' => (string) env('OIDC_CLIENT_ID', ''),
    'client_secret' => (string) env('OIDC_CLIENT_SECRET', ''),
    'redirect_uri' => (string) env(
        'OIDC_REDIRECT_URI',
        'http://localhost:8080/auth/callback'
    ),
    'post_logout_redirect_uri' => (string) env(
        'OIDC_POST_LOGOUT_REDIRECT_URI',
        rtrim((string) env('APP_URL', 'http://localhost:8080'), '/')
            . '/auth/logged-out'
    ),
    'scopes' => preg_split(
        '/\s+/',
        trim((string) env('OIDC_SCOPES', 'openid profile email'))
    ) ?: ['openid', 'profile', 'email'],
    'session_ttl' => max(
        (int) env('OIDC_SESSION_TTL', '28800'),
        300
    ),
    'bootstrap_user_id' => max(
        (int) env('OIDC_BOOTSTRAP_USER_ID', '0'),
        0
    ),
    'allow_email_link' => filter_var(
        env('OIDC_ALLOW_EMAIL_LINK', 'false'),
        FILTER_VALIDATE_BOOL
    ),
];
