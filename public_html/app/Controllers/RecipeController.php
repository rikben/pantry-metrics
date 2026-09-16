<?php
// /public_html/app/Controllers/RecipeController.php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthServiceInterface;
use App\Core\Container;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductUnitConversionRepository;
use App\Repositories\RecipeRepository;
use App\Repositories\RecipeSourceIngredientRepository;
use App\Services\NutritionCalculator;
use App\Services\RemoteImageService;
use App\Services\UnitConverter;

final class RecipeController
{
    public function index(): void
    {
        $user = $this->user();
        $archived = ($_GET['archived'] ?? '') === '1';
        $categoryId = (int) ($_GET['category'] ?? 0);

        $recipes = (new RecipeRepository())->allForUser(
            (int) $user['id'],
            $archived,
            $categoryId > 0 ? $categoryId : null
        );

        $categoryRepository = new CategoryRepository();

        view('recipes/index', [
            'title' => $archived ? 'Archived recipes' : 'Recipes',
            'recipes' => $recipes,
            'archived' => $archived,
            'categories' => $categoryRepository->all(),
            'selectedCategoryId' => $categoryId,
            'recipeCategories' => $categoryRepository->forRecipes(array_column($recipes, 'id')),
        ]);
    }

    public function create(): void
    {
        view('recipes/form', [
            'title' => 'Create recipe',
            'recipe' => null,
            'action' => '/recipes',
            'categories' => (new CategoryRepository())->all(),
            'selectedCategoryIds' => [],
        ]);
    }

    public function store(): void
    {
        $user = $this->user();
        $data = $this->validatedRecipeData();
        $repository = new RecipeRepository();
        $recipeId = $repository->create((int) $user['id'], $data);
        (new CategoryRepository())->sync($recipeId, $this->submittedCategoryIds());

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

        $categoryRepository = new CategoryRepository();

        view('recipes/form', [
            'title' => 'Edit recipe',
            'recipe' => $recipe,
            'action' => "/recipes/{$id}/update",
            'categories' => $categoryRepository->all(),
            'selectedCategoryIds' => $categoryRepository->idsForRecipe((int) $id),
        ]);
    }

    public function update(string $id): void
    {
        $user = $this->user();
        $data = $this->validatedRecipeData();
        $repository = new RecipeRepository();
        $repository->update((int) $id, (int) $user['id'], $data);
        (new CategoryRepository())->sync((int) $id, $this->submittedCategoryIds());

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
        $products = (new ProductRepository())->allForUser((int) $user['id']);
        $categoryRepository = new CategoryRepository();

        view('recipes/show', [
            'title' => $recipe['name'],
            'recipe' => $recipe,
            'nutrition' => $payload['nutrition'],
            'products' => $products,
            'productConversions' => (new ProductUnitConversionRepository())->forProducts(
                array_column($products, 'id')
            ),
            'categories' => $categoryRepository->all(),
            'recipeCategoryIds' => $categoryRepository->idsForRecipe((int) $id),
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

        $product = (new ProductRepository())->findForUser($data['product_id'], (int) $user['id']);

        if (!$product) {
            $this->jsonOrExit(['error' => 'Please select a product.'], 422);
        }

        $resolved = $this->resolveIngredientUnit((int) $product['id'], (string) $product['reference_unit'], $data);
        $data['amount'] = $resolved['amount'];
        $data['unit'] = $resolved['unit'];

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

        $data = $this->validatedIngredientData(false);

        $existingIngredient = null;
        foreach ($repository->ingredients((int) $id) as $ingredient) {
            if ((int) $ingredient['id'] === (int) $ingredientId) {
                $existingIngredient = $ingredient;
                break;
            }
        }

        if (!$existingIngredient) {
            $this->jsonOrExit(['error' => 'Ingredient not found.'], 404);
        }

        $resolved = $this->resolveIngredientUnit(
            (int) $existingIngredient['product_id'],
            (string) $existingIngredient['reference_unit'],
            $data
        );
        $data['amount'] = $resolved['amount'];
        $data['unit'] = $resolved['unit'];

        $updated = $repository->updateIngredient(
            (int) $id,
            (int) $ingredientId,
            (int) $user['id'],
            $data
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

        $productId = (int) ($_POST['product_id'] ?? 0);
        $amount = max((float) ($_POST['amount'] ?? 0), 0.001);
        $unit = trim((string) ($_POST['unit'] ?? 'g'));

        $convertedAmountText = trim(
            (string) ($_POST['converted_amount'] ?? '')
        );
        $convertedAmount = $convertedAmountText !== ''
            ? max((float) $convertedAmountText, 0.001)
            : null;
        $convertedUnit = trim(
            (string) ($_POST['converted_unit'] ?? '')
        );
        $convertedUnit = $convertedUnit !== '' ? $convertedUnit : null;
        $rememberConversion = ($_POST['remember_conversion'] ?? '1') === '1';

        $allowedUnits = [
            'g', 'kg', 'mg',
            'ml', 'l', 'cl', 'dl',
            'tbsp', 'tsp', 'serving',
        ];

        if (
            $productId < 1
            || !in_array($unit, $allowedUnits, true)
        ) {
            $this->json(['error' => 'Invalid product mapping.'], 422);
        }

        try {
            $linked = (
                new RecipeSourceIngredientRepository()
            )->link(
                (int) $id,
                (int) $sourceIngredientId,
                (int) $user['id'],
                $productId,
                $amount,
                $unit,
                $convertedAmount,
                $convertedUnit,
                $rememberConversion
            );
        } catch (\InvalidArgumentException $exception) {
            $this->json(['error' => $exception->getMessage()], 422);
        }

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

    /**
     * @return int[]
     */
    private function submittedCategoryIds(): array
    {
        $ids = $_POST['category_ids'] ?? [];

        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Units accepted here are broader than the g/ml/serving that
     * recipe_ingredients actually stores: a culinary unit (tbsp, tsp,
     * kg, ...) is converted to the product's reference unit via
     * UnitConverter before it reaches the repository, using either an
     * explicit converted_amount or a conversion remembered earlier for
     * that product.
     */
    private const ALLOWED_ENTRY_UNITS = [
        'g', 'kg', 'mg',
        'ml', 'l', 'cl', 'dl',
        'tbsp', 'tsp', 'serving',
    ];

    private function validatedIngredientData(bool $requiresProduct): array
    {
        $convertedAmountText = trim((string) ($_POST['converted_amount'] ?? ''));

        $data = [
            'product_id' => (int) ($_POST['product_id'] ?? 0),
            'position' => max((int) ($_POST['position'] ?? 0), 0),
            'original_description' => trim((string) ($_POST['original_description'] ?? '')),
            'amount' => max((float) ($_POST['amount'] ?? 0), 0.001),
            'unit' => in_array($_POST['unit'] ?? '', self::ALLOWED_ENTRY_UNITS, true)
                ? $_POST['unit']
                : 'g',
            'converted_amount' => $convertedAmountText !== ''
                ? max((float) $convertedAmountText, 0.001)
                : null,
            'remember_conversion' => ($_POST['remember_conversion'] ?? '1') === '1',
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ];

        if ($requiresProduct && $data['product_id'] < 1) {
            $this->jsonOrExit(['error' => 'Please select a product.'], 422);
        }

        return $data;
    }

    /**
     * Resolves the culinary unit/amount a form submitted into the
     * product's own reference unit (g, ml or serving), which is all
     * recipe_ingredients can store. Ends the request with a 422 when a
     * conversion is required but neither given explicitly nor known yet.
     *
     * @return array{amount: float, unit: string}
     */
    private function resolveIngredientUnit(int $productId, string $referenceUnit, array $data): array
    {
        try {
            $resolved = (new UnitConverter())->resolve(
                $productId,
                $referenceUnit,
                $data['amount'],
                $data['unit'],
                $data['converted_amount'],
                $referenceUnit,
                $data['remember_conversion']
            );
        } catch (\InvalidArgumentException $exception) {
            $this->jsonOrExit(['error' => $exception->getMessage()], 422);
        }

        return ['amount' => $resolved['amount'], 'unit' => $resolved['unit']];
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
