<?php
// /public_html/app/Http/HttpClient.php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class HttpClient
{
    private const ALLOWED_HOSTS = [
        'ah.nl',
        'www.ah.nl',
    ];

    private const USER_AGENT =
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) ' .
        'AppleWebKit/537.36 (KHTML, like Gecko) ' .
        'Chrome/149.0.0.0 Safari/537.36';

    public function get(string $url): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException(
                'The PHP cURL extension is required for product imports.'
            );
        }

        $this->assertAllowedUrl($url);

        $cookieFile = tempnam(sys_get_temp_dir(), 'pantry_metrics_ah_');
        if ($cookieFile === false) {
            throw new RuntimeException(
                'Unable to create a temporary cookie file.'
            );
        }

        try {
            /*
             * Initialize an ordinary anonymous AH browser session first.
             * AH may reject a direct request that has no cookies or navigation
             * context, even when the product page itself is publicly visible.
             */
            $this->request(
                'https://www.ah.nl/',
                $cookieFile,
                null,
                false
            );

            return $this->request(
                $url,
                $cookieFile,
                'https://www.ah.nl/',
                true
            );
        } finally {
            if (is_file($cookieFile)) {
                @unlink($cookieFile);
            }
        }
    }

    private function request(
        string $url,
        string $cookieFile,
        ?string $referer,
        bool $requireSuccess
    ): string {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException(
                'Unable to initialize the HTTP client.'
            );
        }

        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: nl-NL,nl;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: ' . ($referer === null ? 'none' : 'same-origin'),
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1',
        ];

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($referer !== null) {
            curl_setopt($handle, CURLOPT_REFERER, $referer);
        }

        $body = curl_exec($handle);
        $status = (int) curl_getinfo(
            $handle,
            CURLINFO_RESPONSE_CODE
        );
        $contentType = (string) curl_getinfo(
            $handle,
            CURLINFO_CONTENT_TYPE
        );
        $error = curl_error($handle);

        curl_close($handle);

        if (!is_string($body)) {
            throw new RuntimeException(
                'Unable to retrieve the AH page: ' .
                ($error !== '' ? $error : 'unknown cURL error')
            );
        }

        /*
         * The warm-up request is best effort. A product request must succeed.
         */
        if ($requireSuccess && $status !== 200) {
            $message = match ($status) {
                403 => 'AH refused the automated request with HTTP 403. ' .
                    'The URL is valid, but AH is blocking this server request.',
                404 => 'AH returned HTTP 404. The product may no longer be available.',
                429 => 'AH returned HTTP 429. Too many requests were made; please wait and try again.',
                default => "AH returned HTTP status {$status}.",
            };

            throw new RuntimeException($message);
        }

        if ($requireSuccess && !str_contains(
                mb_strtolower($contentType),
                'text/html'
            )) {
            throw new RuntimeException(
                'AH returned an unexpected content type: ' .
                ($contentType !== '' ? $contentType : 'unknown')
            );
        }

        if (strlen($body) > 5_000_000) {
            throw new RuntimeException(
                'The downloaded AH page is unexpectedly large.'
            );
        }

        return $body;
    }

    private function assertAllowedUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = mb_strtolower((string) ($parts['scheme'] ?? ''));
        $host = mb_strtolower((string) ($parts['host'] ?? ''));

        if (
            $scheme !== 'https'
            || !in_array($host, self::ALLOWED_HOSTS, true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
        ) {
            throw new RuntimeException(
                'Only public HTTPS URLs on ah.nl are allowed.'
            );
        }
    }
}
