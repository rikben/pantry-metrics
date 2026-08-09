<?php
// /database/check-oidc-requirements.php

declare(strict_types=1);

require dirname(__DIR__)
    . '/public_html/app/bootstrap.php';

$checks = [
    'cURL' => function_exists('curl_init'),
    'OpenSSL' => function_exists('openssl_verify'),
    'JSON' => function_exists('json_decode'),
    'random_bytes' => function_exists('random_bytes'),
];

$failed = false;

foreach ($checks as $name => $ok) {
    printf(
        "%-14s %s\n",
        $name,
        $ok ? 'OK' : 'MISSING'
    );

    if (!$ok) {
        $failed = true;
    }
}

$oidc = config('oidc');

echo "\nConfiguration:\n";
echo 'enabled: '
    . ($oidc['enabled'] ? 'yes' : 'no')
    . "\n";
echo 'issuer: '
    . $oidc['issuer']
    . "\n";
echo 'discovery: '
    . $oidc['discovery_url']
    . "\n";
echo 'redirect: '
    . $oidc['redirect_uri']
    . "\n";

exit($failed ? 1 : 0);
