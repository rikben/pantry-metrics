<?php
// /public_html/app/Controllers/AuthController.php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthentikAuthService;
use App\Auth\OidcClient;
use App\Repositories\UserRepository;

final class AuthController
{
    public function login(): void
    {
        $returnTo = (string) (
            $_GET['return_to']
            ?? '/'
        );

        $auth = new AuthentikAuthService();

        if ($auth->check()) {
            redirect(
                $this->safeReturnTo($returnTo)
            );
        }

        try {
            $url = (new OidcClient())
                ->authorizationUrl($returnTo);

            redirect($url);
        } catch (\Throwable $exception) {
            $this->renderError($exception);
        }
    }

    public function callback(): void
    {
        try {
            $result = (new OidcClient())
                ->completeAuthorization($_GET);

            $user = (new UserRepository())
                ->findOrProvisionOidc(
                    $result['issuer'],
                    $result['subject'],
                    $result['email'],
                    $result['display_name']
                );

            session_regenerate_id(true);

            $_SESSION['auth_user_id'] =
                (int) $user['id'];
            $_SESSION['auth_expires_at'] =
                time()
                + (int) config('oidc')['session_ttl'];
            $_SESSION['oidc_id_token'] =
                $result['id_token'];

            redirect($result['return_to']);
        } catch (\Throwable $exception) {
            $this->renderError($exception);
        }
    }

    public function logout(): void
    {
        $idToken = (string) (
            $_SESSION['oidc_id_token']
            ?? ''
        );

        $logoutUrl = null;

        try {
            $logoutUrl = (new OidcClient())
                ->logoutUrl(
                    $idToken !== ''
                        ? $idToken
                        : null
                );
        } catch (\Throwable $exception) {
            error_log(
                'OIDC logout URL error: '
                . $exception->getMessage()
            );
        }

        (new AuthentikAuthService())->logout();

        redirect(
            $logoutUrl
            ?: '/auth/logged-out'
        );
    }

    public function loggedOut(): void
    {
        view('auth/logged-out', [
            'title' => 'Signed out',
        ]);
    }

    private function renderError(
        \Throwable $exception
    ): never {
        error_log(
            'OIDC authentication error: '
            . $exception->getMessage()
        );

        http_response_code(401);

        $debug = filter_var(
            env('APP_DEBUG', 'false'),
            FILTER_VALIDATE_BOOL
        );

        view('auth/error', [
            'title' => 'Sign-in failed',
            'message' => $debug
                ? $exception->getMessage()
                : 'Single sign-on could not be completed.',
        ]);

        exit;
    }

    private function safeReturnTo(
        string $path
    ): string {
        if (
            !str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_starts_with($path, '/auth/')
        ) {
            return '/';
        }

        return $path;
    }
}
