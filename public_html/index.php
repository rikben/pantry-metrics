<?php
// /public_html/index.php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\ProductController;
use App\Controllers\ProductImportController;
use App\Controllers\RecipeController;
use App\Controllers\RecipeImportController;
use App\Core\Router;
use App\Middleware\SecurityHeadersMiddleware;

require __DIR__ . '/app/bootstrap.php';

SecurityHeadersMiddleware::handle();

$router = new Router();

$router->get('/', [HomeController::class, 'index']);

$router->get('/products', [ProductController::class, 'index']);
$router->get('/products/create', [ProductController::class, 'create']);
$router->post('/products', [ProductController::class, 'store']);
$router->get('/products/{id}/edit', [ProductController::class, 'edit']);
$router->post('/products/{id}/update', [ProductController::class, 'update']);
$router->post('/products/{id}/archive', [ProductController::class, 'archive']);
$router->post('/products/{id}/restore', [ProductController::class, 'restore']);

$router->get('/products/import', [ProductImportController::class, 'create']);
$router->post('/products/import/preview', [ProductImportController::class, 'preview']);
$router->post('/products/import/store', [ProductImportController::class, 'store']);

$router->get('/recipes', [RecipeController::class, 'index']);
$router->get('/recipes/create', [RecipeController::class, 'create']);
$router->post('/recipes', [RecipeController::class, 'store']);
$router->get('/recipes/import', [RecipeImportController::class, 'create']);
$router->post('/recipes/import/preview', [RecipeImportController::class, 'preview']);
$router->post('/recipes/import/store', [RecipeImportController::class, 'store']);
$router->get('/recipes/{id}', [RecipeController::class, 'show']);
$router->get('/recipes/{id}/edit', [RecipeController::class, 'edit']);
$router->post('/recipes/{id}/update', [RecipeController::class, 'update']);
$router->post('/recipes/{id}/archive', [RecipeController::class, 'archive']);
$router->post('/recipes/{id}/restore', [RecipeController::class, 'restore']);

$router->post('/recipes/{id}/ingredients', [RecipeController::class, 'addIngredient']);
$router->post('/recipes/{id}/ingredients/{ingredientId}/update', [RecipeController::class, 'updateIngredient']);
$router->post('/recipes/{id}/ingredients/{ingredientId}/delete', [RecipeController::class, 'deleteIngredient']);

$router->post('/recipes/{id}/source-ingredients/{sourceIngredientId}/link', [RecipeController::class, 'linkSourceIngredient']);
$router->post('/recipes/{id}/source-ingredients/{sourceIngredientId}/ignore', [RecipeController::class, 'ignoreSourceIngredient']);
$router->post('/recipes/{id}/source-ingredients/{sourceIngredientId}/restore', [RecipeController::class, 'restoreSourceIngredient']);

$router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'
);
