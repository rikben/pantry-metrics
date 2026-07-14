<?php
// /public_html/app/Controllers/RecipeImportController.php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthServiceInterface;
use App\Core\Container;
use App\Repositories\RecipeRepository;
use App\Repositories\RecipeSourceIngredientRepository;
use App\Services\RecipeImport\AhRecipeImporter;
use App\Services\RecipeImport\ImportedRecipe;
use App\Services\RemoteImageService;

final class RecipeImportController
{
    public function create(): void
    {
        view('recipes/import/create', [
            'title' => 'Import AH recipe',
            'error' => null,
            'url' => '',
        ]);
    }

    public function preview(): void
    {
        $url = trim((string) ($_POST['url'] ?? ''));

        try {
            $recipe = (new AhRecipeImporter())->import($url);
        } catch (\Throwable $exception) {
            http_response_code(422);
            view('recipes/import/create', [
                'title' => 'Import AH recipe',
                'error' => $exception->getMessage(),
                'url' => $url,
            ]);
            return;
        }

        $token = bin2hex(random_bytes(24));
        $_SESSION['recipe_import_previews'][$token] = [
            'created_at' => time(),
            'recipe' => $recipe->toArray(),
        ];

        view('recipes/import/preview', [
            'title' => 'Review AH recipe',
            'recipe' => $recipe,
            'previewToken' => $token,
        ]);
    }

    public function store(): void
    {
        $token = (string) (
            $_POST['preview_token']
            ?? ''
        );
        $preview =
            $_SESSION['recipe_import_previews'][$token]
            ?? null;
        unset(
            $_SESSION['recipe_import_previews'][$token]
        );

        if (
            !is_array($preview)
            || (int) ($preview['created_at'] ?? 0)
                < time() - 1800
        ) {
            http_response_code(422);
            exit(
                'The recipe import preview has expired.'
            );
        }

        $imported = ImportedRecipe::fromArray(
            (array) $preview['recipe']
        );

        $name = trim(
            (string) ($_POST['name'] ?? $imported->name)
        );
        $description = trim(
            (string) (
                $_POST['description']
                ?? $imported->description
            )
        );
        $servings = max(
            (float) (
                $_POST['servings']
                ?? $imported->servings
            ),
            0.01
        );

        $instructions = array_values(
            array_filter(
                array_map(
                    'trim',
                    (array) (
                        $_POST['instructions']
                        ?? $imported->instructions
                    )
                )
            )
        );

        $ingredients = array_values(
            array_filter(
                array_map(
                    'trim',
                    (array) (
                        $_POST['ingredients']
                        ?? $imported->ingredients
                    )
                )
            )
        );

        if ($name === '' || $ingredients === []) {
            http_response_code(422);
            exit(
                'Recipe name and at least one ingredient are required.'
            );
        }

        $user = Container::instance()
            ->get(AuthServiceInterface::class)
            ->user();

        $recipeData = [
            'name' => $name,
            'description' => $description,
            'instructions' => implode(
                "\n\n",
                array_map(
                    static fn (
                        string $step,
                        int $index
                    ): string =>
                        ($index + 1) . '. ' . $step,
                    $instructions,
                    array_keys($instructions)
                )
            ),
            'source_url' => $imported->sourceUrl,
            'source_identifier' => $imported->sourceIdentifier,
            'servings' => $servings,
        ];

        $repository = new RecipeRepository();
        $sourceIngredientRepository =
            new RecipeSourceIngredientRepository();

        $existingRecipe = $repository->findBySource(
            (int) $user['id'],
            $imported->sourceIdentifier
        );

        if ($existingRecipe !== null) {
            $recipeId = (int) $existingRecipe['id'];

            $repository->updateImported(
                $recipeId,
                (int) $user['id'],
                $recipeData
            );

            if (
                !$sourceIngredientRepository->hasAnyForRecipe(
                    $recipeId,
                    (int) $user['id']
                )
            ) {
                $sourceIngredientRepository->createMany(
                    $recipeId,
                    $ingredients
                );
            }
        } else {
            $recipeId = $repository->create(
                (int) $user['id'],
                $recipeData
            );

            $sourceIngredientRepository->createMany(
                $recipeId,
                $ingredients
            );
        }

        $image = $imported->imageUrl !== null
            ? (new RemoteImageService())->downloadImage(
                $imported->imageUrl,
                'recipes',
                $imported->sourceUrl
            )
            : (new RemoteImageService())->importFromPage(
                $imported->sourceUrl,
                'recipes'
            );

        $repository->setImage(
            $recipeId,
            (int) $user['id'],
            $image
        );

        redirect(
            "/recipes/{$recipeId}#source-ingredients"
        );
    }
}
