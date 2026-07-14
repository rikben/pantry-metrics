<?php
// /public_html/app/Controllers/RecipeController.php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthServiceInterface;
use App\Core\Container;
use App\Repositories\ProductRepository;
use App\Repositories\RecipeRepository;
use App\Services\NutritionCalculator;

final class RecipeController
{
    public function index(): void
    {
        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        $archived = ($_GET['archived'] ?? '') === '1';

        view('recipes/index', [
            'title' => $archived ? 'Archived recipes' : 'Recipes',
            'recipes' => (new RecipeRepository())->allForUser((int) $user['id'], $archived),
            'archived' => $archived,
        ]);
    }

    public function create(): void
    {
        view('recipes/form', [
            'title' => 'Create recipe',
            'recipe' => null,
            'action' => '/recipes',
        ]);
    }

    public function store(): void
    {
        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        $recipeId = (new RecipeRepository())->create((int) $user['id'], $this->validatedData());
        redirect("/recipes/{$recipeId}");
    }

    public function edit(string $id): void
    {
        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        $recipe = (new RecipeRepository())->findForUser((int) $id, (int) $user['id']);

        if (!$recipe) {
            http_response_code(404);
            view('errors/404', ['title' => 'Recipe not found']);
            return;
        }

        view('recipes/form', [
            'title' => 'Edit recipe',
            'recipe' => $recipe,
            'action' => "/recipes/{$id}/update",
        ]);
    }

    public function update(string $id): void
    {
        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        (new RecipeRepository())->update((int) $id, (int) $user['id'], $this->validatedData());
        redirect("/recipes/{$id}");
    }

    public function archive(string $id): void
    {
        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        (new RecipeRepository())->setArchived((int) $id, (int) $user['id'], true);
        redirect('/recipes');
    }

    public function restore(string $id): void
    {
        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        (new RecipeRepository())->setArchived((int) $id, (int) $user['id'], false);
        redirect('/recipes?archived=1');
    }

    public function show(string $id): void
    {
        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        $repository = new RecipeRepository();
        $recipe = $repository->findForUser((int) $id, (int) $user['id']);

        if (!$recipe) {
            http_response_code(404);
            view('errors/404', ['title' => 'Recipe not found']);
            return;
        }

        $ingredients = $repository->ingredients((int) $id);
        $nutrition = (new NutritionCalculator())->calculate($ingredients, (float) $recipe['servings']);

        view('recipes/show', [
            'title' => $recipe['name'],
            'recipe' => $recipe,
            'nutrition' => $nutrition,
            'products' => (new ProductRepository())->allForUser((int) $user['id']),
        ]);
    }

    public function addIngredient(string $id): void
    {
        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        $repository = new RecipeRepository();
        $recipe = $repository->findForUser((int) $id, (int) $user['id']);

        if (!$recipe) {
            http_response_code(404);
            exit('Recipe not found.');
        }

        $data = [
            'product_id' => (int) ($_POST['product_id'] ?? 0),
            'position' => max((int) ($_POST['position'] ?? 0), 0),
            'original_description' => trim((string) ($_POST['original_description'] ?? '')),
            'amount' => max((float) ($_POST['amount'] ?? 0), 0.001),
            'unit' => in_array($_POST['unit'] ?? '', ['g', 'ml', 'serving'], true)
                ? $_POST['unit']
                : 'g',
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ];

        if ($data['product_id'] < 1) {
            http_response_code(422);
            exit('Please select a product.');
        }

        $repository->addIngredient((int) $id, $data);

        $redirectTo = (string) ($_POST['redirect_to'] ?? '');
        if (in_array($redirectTo, ['/products/create', '/products/import'], true)) {
            redirect($redirectTo . '?return_to=' . rawurlencode("/recipes/{$id}"));
        }

        redirect("/recipes/{$id}");
    }

    private function validatedData(): array
    {
        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'source_url' => trim((string) ($_POST['source_url'] ?? '')),
            'servings' => max((float) ($_POST['servings'] ?? 1), 0.01),
        ];

        if ($data['name'] === '') {
            http_response_code(422);
            exit('Recipe name is required.');
        }

        return $data;
    }
}
