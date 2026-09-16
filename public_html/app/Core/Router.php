<?php
// /public_html/app/Core/Router.php

declare(strict_types=1);

namespace App\Core;

use App\Auth\AuthServiceInterface;

final class Router
{
    private array $routes = [];

    public function get(
        string $path,
        array $handler
    ): void {
        $this->add(
            'GET',
            $path,
            $handler,
            true
        );
    }

    public function post(
        string $path,
        array $handler
    ): void {
        $this->add(
            'POST',
            $path,
            $handler,
            true
        );
    }

    public function getPublic(
        string $path,
        array $handler
    ): void {
        $this->add(
            'GET',
            $path,
            $handler,
            false
        );
    }

    public function postPublic(
        string $path,
        array $handler
    ): void {
        $this->add(
            'POST',
            $path,
            $handler,
            false
        );
    }

    private function add(
        string $method,
        string $path,
        array $handler,
        bool $authRequired
    ): void {
        $parameterNames = [];
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (
                array $matches
            ) use (&$parameterNames): string {
                $parameterNames[] = $matches[1];
                return '([^/]+)';
            },
            $path
        );

        $this->routes[] = [
            'method' => $method,
            'pattern' => '#^'
                . $pattern
                . '/?$#',
            'handler' => $handler,
            'parameters' => $parameterNames,
            'auth_required' => $authRequired,
        ];
    }

    public function dispatch(
        string $method,
        string $path
    ): void {
        foreach ($this->routes as $route) {
            if (
                $route['method'] !== $method
                || !preg_match(
                    $route['pattern'],
                    $path,
                    $matches
                )
            ) {
                continue;
            }

            $auth = Container::instance()
                ->get(AuthServiceInterface::class);

            if (
                $route['auth_required']
                && !$auth->check()
            ) {
                $this->authenticationRequired(
                    $method
                );
            }

            /*
             * Validate CSRF only after authentication has been
             * established. The OIDC callback itself is a public GET.
             */
            if ($method === 'POST') {
                Csrf::validateRequest();
            }

            array_shift($matches);

            $arguments = array_map(
                'urldecode',
                $matches
            );

            [
                $controllerClass,
                $action,
            ] = $route['handler'];

            $controller = Container::instance()
                ->get($controllerClass);

            $controller->{$action}(...$arguments);
            return;
        }

        http_response_code(404);

        view('errors/404', [
            'title' => 'Page not found',
        ]);
    }

    private function authenticationRequired(
        string $method
    ): never {
        if ($method === 'GET') {
            $requestUri = (string) (
                $_SERVER['REQUEST_URI']
                ?? '/'
            );

            redirect(
                '/auth/login?return_to='
                . rawurlencode($requestUri)
            );
        }

        $accept = strtolower(
            (string) (
                $_SERVER['HTTP_ACCEPT']
                ?? ''
            )
        );
        $ajax = strtolower(
                (string) (
                    $_SERVER[
                    'HTTP_X_REQUESTED_WITH'
                    ]
                    ?? ''
                )
            ) === 'xmlhttprequest';

        http_response_code(401);

        if (
            $ajax
            || str_contains(
                $accept,
                'application/json'
            )
        ) {
            header(
                'Content-Type: application/json; charset=utf-8'
            );

            echo json_encode([
                'error' =>
                    'Your session has expired. Please sign in again.',
                'login_url' =>
                    '/auth/login',
            ]);

            exit;
        }

        echo 'Authentication required.';
        exit;
    }
}
