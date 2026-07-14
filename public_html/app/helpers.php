<?php
// /public_html/app/helpers.php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\View;

function env(string $key, ?string $default = null): ?string
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

function config(string $file): array
{
    static $cache = [];

    if (!isset($cache[$file])) {
        $path = dirname(__DIR__) . "/config/{$file}.php";
        if (!is_file($path)) {
            throw new RuntimeException("Configuration file not found: {$file}");
        }

        $cache[$file] = require $path;
    }

    return $cache[$file];
}

function view(string $template, array $data = []): void
{
    View::render($template, $data);
}

function redirect(string $path): never
{
    header("Location: {$path}");
    exit;
}

function csrf_field(): string
{
    $token = htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf" value="' . $token . '">';
}

function old(string $key, string $default = ''): string
{
    return htmlspecialchars($_POST[$key] ?? $default, ENT_QUOTES, 'UTF-8');
}

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
