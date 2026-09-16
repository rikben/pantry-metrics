<?php
// /public_html/app/Auth/OidcClient.php

declare(strict_types=1);

namespace App\Auth;

final class OidcClient
{
    private array $config;

    public function __construct()
    {
        $this->config = config('oidc');
        $this->assertConfigured();
    }

    public function authorizationUrl(string $returnTo = '/'): string
    {
        $discovery = $this->discovery();

        $state = $this->randomBase64Url(32);
        $nonce = $this->randomBase64Url(32);
        $verifier = $this->randomBase64Url(64);
        $challenge = self::base64UrlEncode(
            hash('sha256', $verifier, true)
        );

        $_SESSION['oidc_transaction'] = [
            'created_at' => time(),
            'state' => $state,
            'nonce' => $nonce,
            'code_verifier' => $verifier,
            'return_to' => $this->safeReturnTo($returnTo),
        ];

        $parameters = [
            'response_type' => 'code',
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'scope' => implode(' ', $this->config['scopes']),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ];

        return $discovery['authorization_endpoint']
            . '?'
            . http_build_query(
                $parameters,
                '',
                '&',
                PHP_QUERY_RFC3986
            );
    }

    public function completeAuthorization(array $query): array
    {
        if (!empty($query['error'])) {
            throw new \RuntimeException(
                'The identity provider returned an authorization error.'
            );
        }

        $transaction = $_SESSION['oidc_transaction'] ?? null;
        unset($_SESSION['oidc_transaction']);

        if (!is_array($transaction)) {
            throw new \RuntimeException(
                'The sign-in transaction is missing or has expired.'
            );
        }

        if (
            (int) ($transaction['created_at'] ?? 0)
            < time() - 600
        ) {
            throw new \RuntimeException(
                'The sign-in transaction has expired.'
            );
        }

        $receivedState = (string) ($query['state'] ?? '');
        $expectedState = (string) ($transaction['state'] ?? '');

        if (
            $receivedState === ''
            || $expectedState === ''
            || !hash_equals($expectedState, $receivedState)
        ) {
            throw new \RuntimeException(
                'Invalid OpenID Connect state.'
            );
        }

        $code = (string) ($query['code'] ?? '');

        if ($code === '') {
            throw new \RuntimeException(
                'No authorization code was returned.'
            );
        }

        $discovery = $this->discovery();

        $tokenResponse = $this->requestJson(
            $discovery['token_endpoint'],
            'POST',
            [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            http_build_query(
                [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->config['redirect_uri'],
                    'code_verifier' =>
                        (string) $transaction['code_verifier'],
                ],
                '',
                '&',
                PHP_QUERY_RFC3986
            ),
            $this->config['client_id']
                . ':'
                . $this->config['client_secret']
        );

        $idToken = (string) ($tokenResponse['id_token'] ?? '');
        $accessToken = (string) (
            $tokenResponse['access_token']
            ?? ''
        );

        if ($idToken === '' || $accessToken === '') {
            throw new \RuntimeException(
                'The token response did not contain the required tokens.'
            );
        }

        $claims = $this->validateIdToken(
            $idToken,
            (string) $transaction['nonce'],
            $discovery
        );

        $userInfo = $this->requestJson(
            $discovery['userinfo_endpoint'],
            'GET',
            [
                'Accept: application/json',
                'Authorization: Bearer ' . $accessToken,
            ]
        );

        if (
            empty($userInfo['sub'])
            || !hash_equals(
                (string) $claims['sub'],
                (string) $userInfo['sub']
            )
        ) {
            throw new \RuntimeException(
                'The UserInfo subject does not match the ID token.'
            );
        }

        $identity = array_merge(
            $claims,
            $userInfo
        );

        $email = trim((string) ($identity['email'] ?? ''));

        if (
            $email === ''
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new \RuntimeException(
                'Authentik did not return a valid email address.'
            );
        }

        return [
            'issuer' => (string) $claims['iss'],
            'subject' => (string) $claims['sub'],
            'email' => $email,
            'display_name' => $this->displayName($identity),
            'id_token' => $idToken,
            'return_to' => $this->safeReturnTo(
                (string) ($transaction['return_to'] ?? '/')
            ),
        ];
    }

    public function logoutUrl(?string $idToken): ?string
    {
        $discovery = $this->discovery();
        $endpoint = (string) (
            $discovery['end_session_endpoint']
            ?? ''
        );

        if ($endpoint === '') {
            return null;
        }

        $parameters = [
            'post_logout_redirect_uri' =>
                $this->config['post_logout_redirect_uri'],
            'client_id' =>
                $this->config['client_id'],
        ];

        if ($idToken !== null && $idToken !== '') {
            $parameters['id_token_hint'] = $idToken;
        }

        return $endpoint
            . '?'
            . http_build_query(
                $parameters,
                '',
                '&',
                PHP_QUERY_RFC3986
            );
    }

    private function validateIdToken(
        string $jwt,
        string $expectedNonce,
        array $discovery
    ): array {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new \RuntimeException(
                'The ID token is malformed.'
            );
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] =
            $parts;

        $header = json_decode(
            self::base64UrlDecode($encodedHeader),
            true,
            32,
            JSON_THROW_ON_ERROR
        );
        $claims = json_decode(
            self::base64UrlDecode($encodedPayload),
            true,
            64,
            JSON_THROW_ON_ERROR
        );

        if (
            !is_array($header)
            || !is_array($claims)
            || ($header['alg'] ?? null) !== 'RS256'
        ) {
            throw new \RuntimeException(
                'Only RS256 OpenID Connect ID tokens are accepted.'
            );
        }

        $kid = (string) ($header['kid'] ?? '');

        if ($kid === '') {
            throw new \RuntimeException(
                'The ID token does not contain a key identifier.'
            );
        }

        $jwks = $this->requestJson(
            $discovery['jwks_uri'],
            'GET',
            ['Accept: application/json']
        );

        $jwk = null;

        foreach ((array) ($jwks['keys'] ?? []) as $candidate) {
            if (
                is_array($candidate)
                && (string) ($candidate['kid'] ?? '') === $kid
                && (string) ($candidate['kty'] ?? '') === 'RSA'
            ) {
                $jwk = $candidate;
                break;
            }
        }

        if ($jwk === null) {
            throw new \RuntimeException(
                'The signing key for the ID token was not found.'
            );
        }

        $publicKey = openssl_pkey_get_public(
            $this->rsaJwkToPem($jwk)
        );

        if ($publicKey === false) {
            throw new \RuntimeException(
                'The OpenID signing key could not be loaded.'
            );
        }

        $signatureValid = openssl_verify(
            $encodedHeader . '.' . $encodedPayload,
            self::base64UrlDecode($encodedSignature),
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        if ($signatureValid !== 1) {
            throw new \RuntimeException(
                'The ID token signature is invalid.'
            );
        }

        $expectedIssuer = (string) $this->config['issuer'];
        $discoveredIssuer = (string) (
            $discovery['issuer']
            ?? ''
        );
        $tokenIssuer = (string) ($claims['iss'] ?? '');

        if (
            $expectedIssuer === ''
            || $discoveredIssuer === ''
            || !hash_equals($expectedIssuer, $discoveredIssuer)
            || !hash_equals($expectedIssuer, $tokenIssuer)
        ) {
            throw new \RuntimeException(
                'The ID token issuer does not match the configured issuer.'
            );
        }

        $audience = $claims['aud'] ?? [];
        $audiences = is_array($audience)
            ? $audience
            : [$audience];

        if (!in_array(
            $this->config['client_id'],
            $audiences,
            true
        )) {
            throw new \RuntimeException(
                'The ID token audience is invalid.'
            );
        }

        if (
            count($audiences) > 1
            && (string) ($claims['azp'] ?? '')
                !== $this->config['client_id']
        ) {
            throw new \RuntimeException(
                'The ID token authorized party is invalid.'
            );
        }

        $now = time();
        $leeway = 60;

        if (
            !isset($claims['exp'])
            || (int) $claims['exp'] < $now - $leeway
        ) {
            throw new \RuntimeException(
                'The ID token has expired.'
            );
        }

        if (
            isset($claims['iat'])
            && (int) $claims['iat'] > $now + $leeway
        ) {
            throw new \RuntimeException(
                'The ID token issue time is invalid.'
            );
        }

        if (
            isset($claims['nbf'])
            && (int) $claims['nbf'] > $now + $leeway
        ) {
            throw new \RuntimeException(
                'The ID token is not valid yet.'
            );
        }

        $nonce = (string) ($claims['nonce'] ?? '');

        if (
            $nonce === ''
            || !hash_equals($expectedNonce, $nonce)
        ) {
            throw new \RuntimeException(
                'The ID token nonce is invalid.'
            );
        }

        if (empty($claims['sub'])) {
            throw new \RuntimeException(
                'The ID token has no subject.'
            );
        }

        return $claims;
    }

    private function discovery(): array
    {
        static $cache = null;

        if (is_array($cache)) {
            return $cache;
        }

        $cache = $this->requestJson(
            $this->config['discovery_url'],
            'GET',
            ['Accept: application/json']
        );

        foreach ([
            'issuer',
            'authorization_endpoint',
            'token_endpoint',
            'userinfo_endpoint',
            'jwks_uri',
        ] as $required) {
            if (empty($cache[$required])) {
                throw new \RuntimeException(
                    "OIDC discovery is missing {$required}."
                );
            }
        }

        return $cache;
    }

    private function requestJson(
        string $url,
        string $method,
        array $headers,
        ?string $body = null,
        ?string $basicCredentials = null
    ): array {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException(
                'The PHP cURL extension is required for OIDC.'
            );
        }

        $this->assertHttpsUrl($url);

        $handle = curl_init($url);

        if ($handle === false) {
            throw new \RuntimeException(
                'Unable to initialize the OIDC HTTP client.'
            );
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        if ($basicCredentials !== null) {
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $options[CURLOPT_USERPWD] = $basicCredentials;
        }

        curl_setopt_array($handle, $options);

        $response = curl_exec($handle);
        $status = (int) curl_getinfo(
            $handle,
            CURLINFO_RESPONSE_CODE
        );
        $error = curl_error($handle);

        curl_close($handle);

        if (!is_string($response)) {
            throw new \RuntimeException(
                'OIDC request failed: '
                . ($error !== '' ? $error : 'unknown error')
            );
        }

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(
                "OIDC endpoint returned HTTP {$status}."
            );
        }

        $decoded = json_decode(
            $response,
            true,
            64,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($decoded)) {
            throw new \RuntimeException(
                'OIDC endpoint returned invalid JSON.'
            );
        }

        return $decoded;
    }

    private function rsaJwkToPem(array $jwk): string
    {
        $modulus = self::base64UrlDecode(
            (string) ($jwk['n'] ?? '')
        );
        $exponent = self::base64UrlDecode(
            (string) ($jwk['e'] ?? '')
        );

        if ($modulus === '' || $exponent === '') {
            throw new \RuntimeException(
                'The RSA JWK is incomplete.'
            );
        }

        $rsaPublicKey = $this->asn1Sequence(
            $this->asn1Integer($modulus)
            . $this->asn1Integer($exponent)
        );

        $algorithmIdentifier =
            hex2bin('300d06092a864886f70d0101010500');

        if ($algorithmIdentifier === false) {
            throw new \RuntimeException(
                'Unable to build the RSA key.'
            );
        }

        $subjectPublicKeyInfo = $this->asn1Sequence(
            $algorithmIdentifier
            . "\x03"
            . $this->asn1Length(
                strlen($rsaPublicKey) + 1
            )
            . "\x00"
            . $rsaPublicKey
        );

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(
                base64_encode($subjectPublicKeyInfo),
                64,
                "\n"
            )
            . "-----END PUBLIC KEY-----\n";
    }

    private function asn1Integer(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");

        if ($bytes === '') {
            $bytes = "\x00";
        }

        if ((ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }

        return "\x02"
            . $this->asn1Length(strlen($bytes))
            . $bytes;
    }

    private function asn1Sequence(string $bytes): string
    {
        return "\x30"
            . $this->asn1Length(strlen($bytes))
            . $bytes;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = '';

        while ($length > 0) {
            $bytes = chr($length & 0xff) . $bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private function assertConfigured(): void
    {
        if (!$this->config['enabled']) {
            throw new \RuntimeException(
                'OIDC authentication is disabled.'
            );
        }

        foreach ([
            'issuer',
            'discovery_url',
            'client_id',
            'client_secret',
            'redirect_uri',
        ] as $key) {
            if (trim((string) $this->config[$key]) === '') {
                throw new \RuntimeException(
                    "Missing OIDC configuration: {$key}."
                );
            }
        }

        if (!function_exists('openssl_verify')) {
            throw new \RuntimeException(
                'The PHP OpenSSL extension is required for OIDC.'
            );
        }
    }

    private function assertHttpsUrl(string $url): void
    {
        $parts = parse_url($url);

        if (
            strtolower((string) ($parts['scheme'] ?? ''))
                !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new \RuntimeException(
                'OIDC endpoints must use public HTTPS URLs.'
            );
        }
    }

    private function displayName(array $claims): string
    {
        foreach ([
            'name',
            'preferred_username',
            'email',
        ] as $key) {
            $value = trim((string) ($claims[$key] ?? ''));

            if ($value !== '') {
                return mb_substr($value, 0, 191);
            }
        }

        return 'Pantry Metrics user';
    }

    private function safeReturnTo(string $path): string
    {
        $path = trim($path);

        if (
            $path === ''
            || !str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || preg_match('/[\x00-\x1F\x7F]/', $path)
        ) {
            return '/';
        }

        if (str_starts_with($path, '/auth/')) {
            return '/';
        }

        return $path;
    }

    private function randomBase64Url(int $bytes): string
    {
        return self::base64UrlEncode(
            random_bytes($bytes)
        );
    }

    private static function base64UrlEncode(
        string $value
    ): string {
        return rtrim(
            strtr(base64_encode($value), '+/', '-_'),
            '='
        );
    }

    private static function base64UrlDecode(
        string $value
    ): string {
        $padding = strlen($value) % 4;

        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(
            strtr($value, '-_', '+/'),
            true
        );

        if ($decoded === false) {
            throw new \RuntimeException(
                'Invalid base64url value.'
            );
        }

        return $decoded;
    }
}
