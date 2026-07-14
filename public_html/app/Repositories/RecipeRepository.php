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
            'SELECT r.*,
                COUNT(ri.id) AS ingredient_count,
                COALESCE(SUM((ri.amount / p.reference_amount) * p.energy_kcal), 0) AS total_kcal
             FROM recipes r
             LEFT JOIN recipe_ingredients ri ON ri.recipe_id = r.id
             LEFT JOIN products p ON p.id = ri.product_id
             WHERE r.owner_user_id = :user_id AND r.is_archived = :is_archived
             GROUP BY r.id
             ORDER BY r.updated_at DESC'
        );
        $statement->execute([
            'user_id' => $userId,
            'is_archived' => $archived ? 1 : 0,
        ]);

        return $statement->fetchAll();
    }

    public function create(int $userId, array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO recipes (owner_user_id, name, description, source_url, servings)
             VALUES (:owner_user_id, :name, :description, :source_url, :servings)'
        );
        $statement->execute([
            'owner_user_id' => $userId,
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'source_url' => $data['source_url'] ?: null,
            'servings' => $data['servings'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $recipeId, int $userId, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE recipes SET
                name = :name,
                description = :description,
                source_url = :source_url,
                servings = :servings
             WHERE id = :id AND owner_user_id = :owner_user_id'
        );
        $statement->execute([
            'id' => $recipeId,
            'owner_user_id' => $userId,
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'source_url' => $data['source_url'] ?: null,
            'servings' => $data['servings'],
        ]);
    }

    public function setArchived(int $recipeId, int $userId, bool $archived): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE recipes
             SET is_archived = :is_archived
             WHERE id = :id AND owner_user_id = :owner_user_id'
        );
        $statement->execute([
            'id' => $recipeId,
            'owner_user_id' => $userId,
            'is_archived' => $archived ? 1 : 0,
        ]);
    }

    public function findForUser(int $recipeId, int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM recipes WHERE id = :id AND owner_user_id = :user_id LIMIT 1'
        );
        $statement->execute(['id' => $recipeId, 'user_id' => $userId]);

        $recipe = $statement->fetch();
        return $recipe ?: null;
    }

    public function ingredients(int $recipeId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT ri.*, p.name AS product_name, p.brand,
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

    public function addIngredient(int $recipeId, array $data): void
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
    }
}
