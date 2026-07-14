<?php
// /public_html/app/Repositories/RecipeRepository.php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class RecipeRepository
{
    public function allForUser(int $userId, bool $archived = false): array
    {
        $statement = Database::connection()->prepare(
            'SELECT r.*, COUNT(ri.id) AS ingredient_count,
                COALESCE(SUM((ri.amount / p.reference_amount) * p.energy_kcal), 0) AS total_kcal
             FROM recipes r
             LEFT JOIN recipe_ingredients ri ON ri.recipe_id = r.id
             LEFT JOIN products p ON p.id = ri.product_id
             WHERE r.owner_user_id = :user_id AND r.is_archived = :archived
             GROUP BY r.id
             ORDER BY r.updated_at DESC'
        );
        $statement->execute([
            'user_id' => $userId,
            'archived' => $archived ? 1 : 0,
        ]);

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
               AND owner_user_id = :owner_user_id'
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
    public function findBySource(
        int $userId,
        string $sourceIdentifier
    ): ?array {
        $statement = Database::connection()->prepare(
            'SELECT * FROM recipes
             WHERE owner_user_id = :user_id
               AND source_identifier = :source_identifier
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => $userId,
            'source_identifier' => $sourceIdentifier,
        ]);

        return $statement->fetch() ?: null;
    }

    public function updateImported(
        int $recipeId,
        int $userId,
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
             WHERE id = :id
               AND owner_user_id = :owner_user_id'
        );

        $statement->execute([
            'id' => $recipeId,
            'owner_user_id' => $userId,
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
             WHERE id = :id AND owner_user_id = :owner_user_id'
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
             WHERE id = :id AND owner_user_id = :owner_user_id'
        );
        $statement->execute([
            'id' => $recipeId,
            'owner_user_id' => $userId,
            'value' => $archived ? 1 : 0,
        ]);
    }

    public function findForUser(int $recipeId, int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM recipes WHERE id = :id AND owner_user_id = :user_id LIMIT 1'
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
               AND r.owner_user_id = :user_id'
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
               AND r.owner_user_id = :user_id
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
               AND r.owner_user_id = :user_id'
        );
        $statement->execute([
            'ingredient_id' => $ingredientId,
            'recipe_id' => $recipeId,
            'user_id' => $userId,
        ]);

        return $statement->rowCount() > 0;
    }
}
