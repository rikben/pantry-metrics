<?php
// /public_html/app/bootstrap.php

declare(strict_types=1);

use App\Auth\AuthServiceInterface;
use App\Auth\DevelopmentAuthService;
use App\Core\Container;
use App\Core\Env;

define('ROOT_PATH', dirname(__DIR__, 2));
define('APP_PATH', __DIR__);
define('VIEW_PATH', dirname(__DIR__) . '/views');

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = APP_PATH . '/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/helpers.php';

Env::load(ROOT_PATH . '/.env');

$sessionConfig = require dirname(__DIR__) . '/config/session.php';

session_name($sessionConfig['name']);
session_set_cookie_params([
    'httponly' => true,
    'secure' => $sessionConfig['secure'],
    'samesite' => $sessionConfig['same_site'],
    'path' => '/',
]);
session_start();

$container = Container::instance();
$container->set(AuthServiceInterface::class, static fn (): AuthServiceInterface => new DevelopmentAuthService());

return $container;
