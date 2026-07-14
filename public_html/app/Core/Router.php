<?php
// /public_html/app/Core/Router.php

declare(strict_types=1);

namespace App\Core;

use App\Auth\AuthServiceInterface;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, array $handler): void
    {
        $parameterNames = [];
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $matches) use (&$parameterNames): string {
                $parameterNames[] = $matches[1];
                return '([^/]+)';
            },
            $path
        );

        $this->routes[] = [
            'method' => $method,
            'pattern' => '#^' . $pattern . '/?$#',
            'handler' => $handler,
            'parameters' => $parameterNames,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        if ($method === 'POST') {
            Csrf::validateRequest();
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method || !preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            array_shift($matches);
            $arguments = array_map('urldecode', $matches);

            [$controllerClass, $action] = $route['handler'];
            $controller = Container::instance()->get($controllerClass);

            $auth = Container::instance()->get(AuthServiceInterface::class);
            if (!$auth->check()) {
                http_response_code(401);
                echo 'Authentication required.';
                return;
            }

            $controller->{$action}(...$arguments);
            return;
        }

        http_response_code(404);
        view('errors/404', ['title' => 'Page not found']);
    }
}
