<?php
// /public_html/app/Repositories/RecipeRepository.php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class RecipeRepository
{
    public function allForUser(int $userId, bool $archived = false, ?int $categoryId = null): array
    {
        $categoryJoin = $categoryId !== null
            ? 'INNER JOIN recipe_categories rc ON rc.recipe_id = r.id AND rc.category_id = :category_id'
            : '';

        $statement = Database::connection()->prepare(
            "SELECT r.*, COUNT(DISTINCT ri.id) AS ingredient_count,
                COALESCE(SUM((ri.amount / p.reference_amount) * p.energy_kcal), 0) AS total_kcal
             FROM recipes r
             LEFT JOIN recipe_ingredients ri ON ri.recipe_id = r.id
             LEFT JOIN products p ON p.id = ri.product_id
             {$categoryJoin}
             WHERE (r.owner_user_id = :user_id OR r.owner_user_id IS NULL)
               AND r.is_archived = :archived
             GROUP BY r.id
             ORDER BY r.updated_at DESC"
        );

        $parameters = [
            'user_id' => $userId,
            'archived' => $archived ? 1 : 0,
        ];

        if ($categoryId !== null) {
            $parameters['category_id'] = $categoryId;
        }

        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function create(int $userId, array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO recipes (
                owner_user_id,
                name,
                description,
                instructions,
                source_url,
                source_identifier,
                servings
             ) VALUES (
                :owner_user_id,
                :name,
                :description,
                :instructions,
                :source_url,
                :source_identifier,
                :servings
             )'
        );
        $statement->execute([
            'owner_user_id' => $userId,
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'instructions' => $data['instructions'] ?? null,
            'source_url' => $data['source_url'] ?: null,
            'source_identifier' => $data['source_identifier'] ?? null,
            'servings' => $data['servings'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * AH-imported recipes join the shared catalog (owner_user_id is left
     * NULL), so any signed-in user who imports the same recipe reuses
     * this row instead of creating a private duplicate. Manually created
     * recipes keep using create() above and stay private.
     */
    public function createShared(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO recipes (
                owner_user_id,
                name,
                description,
                instructions,
                source_url,
                source_identifier,
                servings
             ) VALUES (
                NULL,
                :name,
                :description,
                :instructions,
                :source_url,
                :source_identifier,
                :servings
             )'
        );
        $statement->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'instructions' => $data['instructions'] ?? null,
            'source_url' => $data['source_url'] ?: null,
            'source_identifier' => $data['source_identifier'] ?? null,
            'servings' => $data['servings'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Creates a private copy of a recipe - including its ingredients -
     * owned by the given user, e.g. to tweak a shared recipe without
     * changing it for everyone else. The copy never carries over the AH
     * source identifier, so it behaves like any other manually created
     * recipe; re-importing the original AH recipe later still targets
     * the original shared row, not this copy.
     */
    public function duplicate(int $recipeId, int $userId): ?int
    {
        $source = $this->findForUser($recipeId, $userId);

        if (!$source) {
            return null;
        }

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $statement = $connection->prepare(
                'INSERT INTO recipes (
                    owner_user_id, name, description, instructions,
                    source_url, image_path, image_source_url, servings
                 ) VALUES (
                    :owner_user_id, :name, :description, :instructions,
                    :source_url, :image_path, :image_source_url, :servings
                 )'
            );
            $statement->execute([
                'owner_user_id' => $userId,
                'name' => $source['name'] . ' (copy)',
                'description' => $source['description'],
                'instructions' => $source['instructions'],
                'source_url' => $source['source_url'],
                'image_path' => $source['image_path'],
                'image_source_url' => $source['image_source_url'],
                'servings' => $source['servings'],
            ]);

            $newRecipeId = (int) $connection->lastInsertId();

            $copyIngredients = $connection->prepare(
                'INSERT INTO recipe_ingredients (
                    recipe_id, product_id, position, original_description, amount, unit, notes
                 )
                 SELECT :new_recipe_id, product_id, position, original_description, amount, unit, notes
                 FROM recipe_ingredients
                 WHERE recipe_id = :source_recipe_id
                 ORDER BY position, id'
            );
            $copyIngredients->execute([
                'new_recipe_id' => $newRecipeId,
                'source_recipe_id' => $recipeId,
            ]);

            $connection->commit();

            return $newRecipeId;
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function update(
        int $recipeId,
        int $userId,
        array $data
    ): void {
        /*
         * Keep the imported source identifier when a normal edit form
         * does not submit one. Every bound parameter below occurs
         * exactly once in the SQL statement.
         */
        $statement = Database::connection()->prepare(
            'UPDATE recipes SET
                name = :name,
                description = :description,
                instructions = :instructions,
                source_url = :source_url,
                source_identifier = COALESCE(
                    :source_identifier,
                    source_identifier
                ),
                servings = :servings
             WHERE id = :id
               AND (owner_user_id = :owner_user_id OR owner_user_id IS NULL)'
        );

        $sourceIdentifier =
            $data['source_identifier'] ?? null;

        if ($sourceIdentifier === '') {
            $sourceIdentifier = null;
        }

        $statement->execute([
            'id' => $recipeId,
            'owner_user_id' => $userId,
            'name' => $data['name'],
            'description' =>
                $data['description'] !== ''
                    ? $data['description']
                    : null,
            'instructions' =>
                ($data['instructions'] ?? '') !== ''
                    ? $data['instructions']
                    : null,
            'source_url' =>
                $data['source_url'] !== ''
                    ? $data['source_url']
                    : null,
            'source_identifier' => $sourceIdentifier,
            'servings' => $data['servings'],
        ]);
    }
    /**
     * AH-imported recipes are shared catalog entries, so this lookup is
     * intentionally global: whoever imports a given recipe first,
     * everyone else reuses that same row instead of creating a
     * duplicate (which would also violate the recipes.source_identifier
     * unique key).
     */
    public function findBySource(
        string $sourceIdentifier
    ): ?array {
        $statement = Database::connection()->prepare(
            'SELECT * FROM recipes
             WHERE source_identifier = :source_identifier
             LIMIT 1'
        );
        $statement->execute([
            'source_identifier' => $sourceIdentifier,
        ]);

        return $statement->fetch() ?: null;
    }

    public function updateImported(
        int $recipeId,
        array $data
    ): void {
        $statement = Database::connection()->prepare(
            'UPDATE recipes SET
                name = :name,
                description = :description,
                instructions = :instructions,
                source_url = :source_url,
                source_identifier = :source_identifier,
                servings = :servings,
                is_archived = 0
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $recipeId,
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'instructions' => $data['instructions'] ?: null,
            'source_url' => $data['source_url'] ?: null,
            'source_identifier' => $data['source_identifier'],
            'servings' => $data['servings'],
        ]);
    }
    public function setImage(int $recipeId, int $userId, ?array $image): void
    {
        if ($image === null) {
            return;
        }

        $statement = Database::connection()->prepare(
            'UPDATE recipes
             SET image_path = :image_path, image_source_url = :image_source_url
             WHERE id = :id
               AND (owner_user_id = :owner_user_id OR owner_user_id IS NULL)'
        );
        $statement->execute([
            'id' => $recipeId,
            'owner_user_id' => $userId,
            'image_path' => $image['path'],
            'image_source_url' => $image['source_url'],
        ]);
    }

    public function setArchived(int $recipeId, int $userId, bool $archived): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE recipes SET is_archived = :value
             WHERE id = :id
               AND (owner_user_id = :owner_user_id OR owner_user_id IS NULL)'
        );
        $statement->execute([
            'id' => $recipeId,
            'owner_user_id' => $userId,
            'value' => $archived ? 1 : 0,
        ]);
    }

    /**
     * Makes a recipe public (owner_user_id NULL, the same shared-catalog
     * state AH imports already use - visible and editable by every
     * signed-in user) or private again (owned by the acting user). Since
     * a public row already has no single owner, any user who can see it
     * can also toggle it back to private, taking ownership themselves -
     * consistent with every other edit action on a shared recipe.
     */
    public function setPublic(int $recipeId, int $userId, bool $public): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE recipes SET owner_user_id = :new_owner_user_id
             WHERE id = :id
               AND (owner_user_id = :acting_user_id OR owner_user_id IS NULL)'
        );
        $statement->execute([
            'id' => $recipeId,
            'acting_user_id' => $userId,
            'new_owner_user_id' => $public ? null : $userId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function findForUser(int $recipeId, int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM recipes
             WHERE id = :id
               AND (owner_user_id = :user_id OR owner_user_id IS NULL)
             LIMIT 1'
        );
        $statement->execute(['id' => $recipeId, 'user_id' => $userId]);

        return $statement->fetch() ?: null;
    }

    public function ingredients(int $recipeId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT ri.*, p.name AS product_name, p.brand, p.image_path,
                p.source_identifier, p.package_amount, p.package_unit, p.package_description,
                p.reference_amount, p.reference_unit,
                p.energy_kj, p.energy_kcal, p.fat_g, p.saturated_fat_g,
                p.carbohydrates_g, p.sugars_g, p.fiber_g, p.protein_g, p.salt_g
             FROM recipe_ingredients ri
             INNER JOIN products p ON p.id = ri.product_id
             WHERE ri.recipe_id = :recipe_id
             ORDER BY ri.position, ri.id'
        );
        $statement->execute(['recipe_id' => $recipeId]);

        return $statement->fetchAll();
    }

    public function addIngredient(int $recipeId, array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO recipe_ingredients (
                recipe_id, product_id, position, original_description, amount, unit, notes
             ) VALUES (
                :recipe_id, :product_id, :position, :original_description, :amount, :unit, :notes
             )'
        );
        $statement->execute([
            'recipe_id' => $recipeId,
            'product_id' => $data['product_id'],
            'position' => $data['position'],
            'original_description' => $data['original_description'] ?: null,
            'amount' => $data['amount'],
            'unit' => $data['unit'],
            'notes' => $data['notes'] ?: null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function updateIngredient(
        int $recipeId,
        int $ingredientId,
        int $userId,
        array $data
    ): bool {
        $statement = Database::connection()->prepare(
            'UPDATE recipe_ingredients ri
             INNER JOIN recipes r ON r.id = ri.recipe_id
             SET ri.amount = :amount, ri.unit = :unit, ri.notes = :notes
             WHERE ri.id = :ingredient_id
               AND ri.recipe_id = :recipe_id
               AND (r.owner_user_id = :user_id OR r.owner_user_id IS NULL)'
        );
        $statement->execute([
            'ingredient_id' => $ingredientId,
            'recipe_id' => $recipeId,
            'user_id' => $userId,
            'amount' => $data['amount'],
            'unit' => $data['unit'],
            'notes' => $data['notes'] ?: null,
        ]);

        if ($statement->rowCount() > 0) {
            return true;
        }

        /*
         * MySQL reports zero affected rows when the submitted values are
         * identical to the stored values. Confirm ownership/existence before
         * treating that as a missing ingredient.
         */
        $existsStatement = Database::connection()->prepare(
            'SELECT 1
             FROM recipe_ingredients ri
             INNER JOIN recipes r ON r.id = ri.recipe_id
             WHERE ri.id = :ingredient_id
               AND ri.recipe_id = :recipe_id
               AND (r.owner_user_id = :user_id OR r.owner_user_id IS NULL)
             LIMIT 1'
        );
        $existsStatement->execute([
            'ingredient_id' => $ingredientId,
            'recipe_id' => $recipeId,
            'user_id' => $userId,
        ]);

        return (bool) $existsStatement->fetchColumn();
    }

    public function deleteIngredient(int $recipeId, int $ingredientId, int $userId): bool
    {
        $statement = Database::connection()->prepare(
            'DELETE ri FROM recipe_ingredients ri
             INNER JOIN recipes r ON r.id = ri.recipe_id
             WHERE ri.id = :ingredient_id
               AND ri.recipe_id = :recipe_id
               AND (r.owner_user_id = :user_id OR r.owner_user_id IS NULL)'
        );
        $statement->execute([
            'ingredient_id' => $ingredientId,
            'recipe_id' => $recipeId,
            'user_id' => $userId,
        ]);

        return $statement->rowCount() > 0;
    }
}
