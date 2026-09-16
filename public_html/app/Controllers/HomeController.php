<?php
// /public_html/app/Controllers/HomeController.php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthServiceInterface;
use App\Core\Container;
use App\Repositories\ProductRepository;
use App\Repositories\RecipeRepository;

final class HomeController
{
    public function index(): void
    {
        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        $recipes = (new RecipeRepository())->allForUser((int) $user['id']);
        $products = (new ProductRepository())->allForUser((int) $user['id']);

        view('home/index', [
            'title' => 'Dashboard',
            'user' => $user,
            'recipeCount' => count($recipes),
            'productCount' => count($products),
            'recentRecipes' => array_slice($recipes, 0, 5),
        ]);
    }
}
