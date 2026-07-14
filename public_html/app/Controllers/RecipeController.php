<?php
// /public_html/app/Controllers/RecipeController.php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthServiceInterface;
use App\Core\Container;
use App\Repositories\ProductRepository;
use App\Repositories\RecipeRepository;
use App\Repositories\RecipeSourceIngredientRepository;
use App\Services\NutritionCalculator;
use App\Services\RemoteImageService;

final class RecipeController
{
    public function index(): void
    {
        $user = $this->user();
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
        $user = $this->user();
        $data = $this->validatedRecipeData();
        $repository = new RecipeRepository();
        $recipeId = $repository->create((int) $user['id'], $data);

        if ($data['source_url'] !== '') {
            $repository->setImage(
                $recipeId,
                (int) $user['id'],
                (new RemoteImageService())->importFromPage($data['source_url'], 'recipes')
            );
        }

        redirect("/recipes/{$recipeId}");
    }

    public function edit(string $id): void
    {
        $user = $this->user();
        $recipe = (new RecipeRepository())->findForUser((int) $id, (int) $user['id']);

        if (!$recipe) {
            $this->notFound();
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
        $user = $this->user();
        $data = $this->validatedRecipeData();
        $repository = new RecipeRepository();
        $repository->update((int) $id, (int) $user['id'], $data);

        if ($data['source_url'] !== '' && isset($_POST['refresh_image'])) {
            $repository->setImage(
                (int) $id,
                (int) $user['id'],
                (new RemoteImageService())->importFromPage($data['source_url'], 'recipes')
            );
        }

        redirect("/recipes/{$id}");
    }

    public function archive(string $id): void
    {
        $user = $this->user();
        (new RecipeRepository())->setArchived((int) $id, (int) $user['id'], true);
        redirect('/recipes');
    }

    public function restore(string $id): void
    {
        $user = $this->user();
        (new RecipeRepository())->setArchived((int) $id, (int) $user['id'], false);
        redirect('/recipes?archived=1');
    }

    public function show(string $id): void
    {
        $user = $this->user();
        $repository = new RecipeRepository();
        $recipe = $repository->findForUser((int) $id, (int) $user['id']);

        if (!$recipe) {
            $this->notFound();
            return;
        }

        $payload = $this->recipePayload($repository, $recipe);

        view('recipes/show', [
            'title' => $recipe['name'],
            'recipe' => $recipe,
            'nutrition' => $payload['nutrition'],
            'products' => (new ProductRepository())->allForUser((int) $user['id']),
            'sourceIngredients' => (
                new RecipeSourceIngredientRepository()
            )->allForRecipe(
                (int) $id,
                (int) $user['id']
            ),
            'selectedProductId' => (int) ($_GET['selected_product'] ?? 0),
            'selectedSourceIngredientId' => (int) (
                $_GET['source_ingredient'] ?? 0
            ),
        ]);
    }

    public function addIngredient(string $id): void
    {
        $user = $this->user();
        $repository = new RecipeRepository();
        $recipe = $repository->findForUser((int) $id, (int) $user['id']);

        if (!$recipe) {
            $this->jsonOrExit(['error' => 'Recipe not found.'], 404);
        }

        $data = $this->validatedIngredientData(true);
        $repository->addIngredient((int) $id, $data);

        if ($this->wantsJson()) {
            $this->json($this->recipePayload($repository, $recipe));
        }

        redirect("/recipes/{$id}#add-ingredient");
    }

    public function updateIngredient(string $id, string $ingredientId): void
    {
        $user = $this->user();
        $repository = new RecipeRepository();
        $recipe = $repository->findForUser((int) $id, (int) $user['id']);

        if (!$recipe) {
            $this->jsonOrExit(['error' => 'Recipe not found.'], 404);
        }

        $updated = $repository->updateIngredient(
            (int) $id,
            (int) $ingredientId,
            (int) $user['id'],
            $this->validatedIngredientData(false)
        );

        if (!$updated) {
            $this->jsonOrExit(['error' => 'Ingredient not found or unchanged.'], 404);
        }

        $this->json($this->recipePayload($repository, $recipe));
    }

    public function deleteIngredient(string $id, string $ingredientId): void
    {
        $user = $this->user();
        $repository = new RecipeRepository();
        $recipe = $repository->findForUser((int) $id, (int) $user['id']);

        if (!$recipe) {
            $this->jsonOrExit(['error' => 'Recipe not found.'], 404);
        }

        if (!$repository->deleteIngredient(
            (int) $id,
            (int) $ingredientId,
            (int) $user['id']
        )) {
            $this->jsonOrExit(['error' => 'Ingredient not found.'], 404);
        }

        $this->json($this->recipePayload($repository, $recipe));
    }

    public function linkSourceIngredient(
        string $id,
        string $sourceIngredientId
    ): void {
        $user = $this->user();

        $productId = (int) (
            $_POST['product_id'] ?? 0
        );
        $amount = max(
            (float) ($_POST['amount'] ?? 0),
            0.001
        );
        $unit = trim(
            (string) ($_POST['unit'] ?? 'g')
        );

        $allowedUnits = [
            'g', 'kg', 'mg',
            'ml', 'l', 'cl', 'dl',
            'tbsp', 'tsp', 'serving',
        ];

        if (
            $productId < 1
            || !in_array($unit, $allowedUnits, true)
        ) {
            $this->json(
                ['error' => 'Invalid product mapping.'],
                422
            );
        }

        $linked = (
            new RecipeSourceIngredientRepository()
        )->link(
            (int) $id,
            (int) $sourceIngredientId,
            (int) $user['id'],
            $productId,
            $amount,
            $unit
        );

        if (!$linked) {
            $this->json(
                ['error' => 'Ingredient or product not found.'],
                404
            );
        }

        $this->sourceIngredientPayload(
            (int) $id,
            (int) $user['id']
        );
    }

    public function ignoreSourceIngredient(
        string $id,
        string $sourceIngredientId
    ): void {
        $user = $this->user();

        (
            new RecipeSourceIngredientRepository()
        )->setIgnored(
            (int) $id,
            (int) $sourceIngredientId,
            (int) $user['id'],
            true
        );

        $this->sourceIngredientPayload(
            (int) $id,
            (int) $user['id']
        );
    }

    public function restoreSourceIngredient(
        string $id,
        string $sourceIngredientId
    ): void {
        $user = $this->user();

        (
            new RecipeSourceIngredientRepository()
        )->setIgnored(
            (int) $id,
            (int) $sourceIngredientId,
            (int) $user['id'],
            false
        );

        $this->sourceIngredientPayload(
            (int) $id,
            (int) $user['id']
        );
    }

    private function sourceIngredientPayload(
        int $recipeId,
        int $userId
    ): never {
        $repository = new RecipeRepository();
        $recipe = $repository->findForUser(
            $recipeId,
            $userId
        );

        if (!$recipe) {
            $this->json(
                ['error' => 'Recipe not found.'],
                404
            );
        }

        $this->json([
            'sourceIngredients' => (
                new RecipeSourceIngredientRepository()
            )->allForRecipe($recipeId, $userId),
            'nutrition' => $this->recipePayload(
                $repository,
                $recipe
            )['nutrition'],
        ]);
    }
    private function recipePayload(RecipeRepository $repository, array $recipe): array
    {
        $ingredients = $repository->ingredients((int) $recipe['id']);
        $nutrition = (new NutritionCalculator())->calculate(
            $ingredients,
            (float) $recipe['servings']
        );

        return [
            'recipe_id' => (int) $recipe['id'],
            'servings' => (float) $recipe['servings'],
            'nutrition' => $nutrition,
        ];
    }

    private function validatedRecipeData(): array
    {
        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'instructions' => trim((string) ($_POST['instructions'] ?? '')),
            'source_identifier' => null,
            'source_url' => trim((string) ($_POST['source_url'] ?? '')),
            'servings' => max((float) ($_POST['servings'] ?? 1), 0.01),
        ];

        if ($data['name'] === '') {
            http_response_code(422);
            exit('Recipe name is required.');
        }

        return $data;
    }

    private function validatedIngredientData(bool $requiresProduct): array
    {
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

        if ($requiresProduct && $data['product_id'] < 1) {
            $this->jsonOrExit(['error' => 'Please select a product.'], 422);
        }

        return $data;
    }

    private function wantsJson(): bool
    {
        return str_contains(
                strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')),
                'application/json'
            ) || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_THROW_ON_ERROR);
        exit;
    }

    private function jsonOrExit(array $payload, int $status): never
    {
        if ($this->wantsJson()) {
            $this->json($payload, $status);
        }

        http_response_code($status);
        exit((string) ($payload['error'] ?? 'Request failed.'));
    }

    private function user(): array
    {
        return Container::instance()->get(AuthServiceInterface::class)->user();
    }

    private function notFound(): void
    {
        http_response_code(404);
        view('errors/404', ['title' => 'Recipe not found']);
    }
}
