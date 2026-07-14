<?php
// /public_html/app/Services/RemoteImageService.php

declare(strict_types=1);

namespace App\Services;

use App\Http\HttpClient;
use DOMDocument;
use DOMXPath;

final class RemoteImageService
{
    private const MAX_PAGE_BYTES = 3_000_000;
    private const MAX_IMAGE_BYTES = 8_000_000;

    private const ALLOWED_IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    public function importFromPage(string $pageUrl, string $folder): ?array
    {
        $pageUrl = trim($pageUrl);

        if ($pageUrl === '') {
            return null;
        }

        try {
            $host = strtolower((string) (parse_url($pageUrl, PHP_URL_HOST) ?? ''));

            /*
             * AH blocks the generic downloader. Reuse the browser-like,
             * cookie-aware client that already works for product imports.
             */
            if (in_array($host, ['ah.nl', 'www.ah.nl'], true)) {
                $html = (new HttpClient())->get($pageUrl);
                $resolvedPageUrl = $pageUrl;
            } else {
                $page = $this->request(
                    $pageUrl,
                    self::MAX_PAGE_BYTES,
                    ['text/html', 'application/xhtml+xml']
                );

                $html = $page['body'];
                $resolvedPageUrl = $page['url'];
            }

            $imageUrl = $this->extractImageUrl(
                $html,
                $resolvedPageUrl
            );

            if ($imageUrl === null) {
                return null;
            }

            return $this->downloadImage(
                $imageUrl,
                $folder,
                $resolvedPageUrl
            );
        } catch (\Throwable $exception) {
            error_log(
                'Remote image import failed: ' .
                $exception->getMessage()
            );

            return null;
        }
    }

    public function downloadImage(
        string $imageUrl,
        string $folder,
        ?string $referer = null
    ): ?array {
        try {
            $response = $this->request(
                $imageUrl,
                self::MAX_IMAGE_BYTES,
                array_keys(self::ALLOWED_IMAGE_TYPES),
                $referer
            );

            $headerMime = strtolower(
                trim(
                    explode(
                        ';',
                        (string) $response['content_type']
                    )[0]
                )
            );

            /*
             * Some CDNs return application/octet-stream or another generic
             * header. Detect the actual image bytes as a fallback.
             */
            $detectedMime = null;

            if (class_exists(\finfo::class)) {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $value = $finfo->buffer(
                    (string) $response['body']
                );

                if (is_string($value) && $value !== '') {
                    $detectedMime = strtolower($value);
                }
            }

            $mime = isset(
                self::ALLOWED_IMAGE_TYPES[$headerMime]
            )
                ? $headerMime
                : $detectedMime;

            $extension = $mime !== null
                ? (
                    self::ALLOWED_IMAGE_TYPES[$mime]
                    ?? null
                )
                : null;

            if ($extension === null) {
                throw new \RuntimeException(
                    'Unsupported image type. Header: '
                    . ($headerMime !== ''
                        ? $headerMime
                        : 'unknown')
                    . '; detected: '
                    . ($detectedMime ?? 'unknown')
                );
            }

            $folder = trim($folder, '/');

            if (!preg_match('/^[a-z0-9_-]+$/', $folder)) {
                throw new \RuntimeException(
                    'Invalid image folder.'
                );
            }

            $relativeDirectory = '/uploads/' . $folder;
            $absoluteDirectory =
                dirname(__DIR__, 2)
                . $relativeDirectory;

            if (
                !is_dir($absoluteDirectory)
                && !mkdir(
                    $absoluteDirectory,
                    0775,
                    true
                )
                && !is_dir($absoluteDirectory)
            ) {
                throw new \RuntimeException(
                    'Unable to create image directory.'
                );
            }

            $body = (string) $response['body'];
            $filename =
                hash('sha256', $body)
                . '.'
                . $extension;
            $absolutePath =
                $absoluteDirectory
                . '/'
                . $filename;

            if (
                !is_file($absolutePath)
                && file_put_contents(
                    $absolutePath,
                    $body,
                    LOCK_EX
                ) === false
            ) {
                throw new \RuntimeException(
                    'Unable to store downloaded image.'
                );
            }

            return [
                'path' =>
                    $relativeDirectory
                    . '/'
                    . $filename,
                'source_url' =>
                    (string) $response['url'],
            ];
        } catch (\Throwable $exception) {
            error_log(
                'Remote image download failed: '
                . $exception->getMessage()
            );

            return null;
        }
    }

    private function extractImageUrl(
        string $html,
        string $pageUrl
    ): ?string {
        $document = new DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NOERROR |
            LIBXML_NOWARNING |
            LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return null;
        }

        $xpath = new DOMXPath($document);

        $queries = [
            '//meta[@property="og:image"]/@content',
            '//meta[@property="og:image:secure_url"]/@content',
            '//meta[@name="twitter:image"]/@content',
            '//main//img[1]/@src',
        ];

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            $value =
                $nodes !== false && $nodes->length > 0
                    ? trim(
                    (string) $nodes->item(0)?->nodeValue
                )
                    : '';

            if ($value !== '') {
                return $this->absoluteUrl(
                    $pageUrl,
                    $value
                );
            }
        }

        return null;
    }

    private function absoluteUrl(
        string $baseUrl,
        string $candidate
    ): string {
        if (preg_match('#^https?://#i', $candidate)) {
            return $candidate;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';

        if (str_starts_with($candidate, '//')) {
            return $scheme . ':' . $candidate;
        }

        if (str_starts_with($candidate, '/')) {
            return $scheme . '://' . $host . $candidate;
        }

        $path = $base['path'] ?? '/';
        $directory = rtrim(
            str_replace('\\', '/', dirname($path)),
            '/'
        );

        return
            $scheme .
            '://' .
            $host .
            ($directory === '' ? '' : $directory) .
            '/' .
            $candidate;
    }

    private function request(
        string $url,
        int $maxBytes,
        array $allowedContentTypes,
        ?string $referer = null
    ): array {
        for ($redirects = 0; $redirects <= 4; $redirects++) {
            $this->assertPublicHttpUrl($url);

            $handle = curl_init($url);

            if ($handle === false) {
                throw new \RuntimeException(
                    'Unable to initialize cURL.'
                );
            }

            $headers = [
                'Accept-Language: nl-NL,nl;q=0.9,en;q=0.7',
                'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            ];

            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_PROTOCOLS =>
                    CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_USERAGENT =>
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) ' .
                    'AppleWebKit/537.36 (KHTML, like Gecko) ' .
                    'Chrome/149.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_ENCODING => '',
            ]);

            if ($referer !== null) {
                curl_setopt(
                    $handle,
                    CURLOPT_REFERER,
                    $referer
                );
            }

            $response = curl_exec($handle);
            $status = (int) curl_getinfo(
                $handle,
                CURLINFO_RESPONSE_CODE
            );
            $contentType = (string) curl_getinfo(
                $handle,
                CURLINFO_CONTENT_TYPE
            );
            $headerSize = (int) curl_getinfo(
                $handle,
                CURLINFO_HEADER_SIZE
            );
            $error = curl_error($handle);

            curl_close($handle);

            if (!is_string($response)) {
                throw new \RuntimeException(
                    $error !== ''
                        ? $error
                        : 'Remote request failed.'
                );
            }

            $headersText = substr(
                $response,
                0,
                $headerSize
            );
            $body = substr(
                $response,
                $headerSize
            );

            if (strlen($body) > $maxBytes) {
                throw new \RuntimeException(
                    'Remote response is too large.'
                );
            }

            if (
                in_array(
                    $status,
                    [301, 302, 303, 307, 308],
                    true
                )
            ) {
                if (
                    !preg_match(
                        '/^Location:\s*(.+)$/mi',
                        $headersText,
                        $match
                    )
                ) {
                    throw new \RuntimeException(
                        'Redirect without a location header.'
                    );
                }

                $url = $this->absoluteUrl(
                    $url,
                    trim($match[1])
                );

                continue;
            }

            if ($status !== 200) {
                throw new \RuntimeException(
                    "Remote server returned HTTP {$status}."
                );
            }

            $mime = strtolower(
                trim(explode(';', $contentType)[0])
            );

            if (
                !in_array(
                    $mime,
                    $allowedContentTypes,
                    true
                )
            ) {
                throw new \RuntimeException(
                    'Unexpected remote content type: ' .
                    $mime
                );
            }

            return [
                'body' => $body,
                'url' => $url,
                'content_type' => $contentType,
            ];
        }

        throw new \RuntimeException(
            'Too many redirects.'
        );
    }

    private function assertPublicHttpUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower(
            (string) ($parts['scheme'] ?? '')
        );
        $host = strtolower(
            (string) ($parts['host'] ?? '')
        );

        if (
            !in_array($scheme, ['http', 'https'], true)
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new \RuntimeException(
                'Invalid remote URL.'
            );
        }

        $addresses = gethostbynamel($host) ?: [];

        if ($addresses === []) {
            throw new \RuntimeException(
                'Remote hostname could not be resolved.'
            );
        }

        foreach ($addresses as $address) {
            if (
                filter_var(
                    $address,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE |
                    FILTER_FLAG_NO_RES_RANGE
                ) === false
            ) {
                throw new \RuntimeException(
                    'Private or reserved remote address is not allowed.'
                );
            }
        }
    }
}
