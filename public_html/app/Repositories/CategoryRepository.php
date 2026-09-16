<?php
// /public_html/app/Repositories/CategoryRepository.php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class CategoryRepository
{
    public function all(): array
    {
        $statement = Database::connection()->query(
            'SELECT * FROM categories ORDER BY position, name'
        );

        return $statement->fetchAll();
    }

    /**
     * @return int[] category ids currently assigned to the recipe
     */
    public function idsForRecipe(int $recipeId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT category_id FROM recipe_categories WHERE recipe_id = :recipe_id'
        );
        $statement->execute(['recipe_id' => $recipeId]);

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * @return array<int, array<int, array<string, mixed>>> categories keyed by recipe id
     */
    public function forRecipes(array $recipeIds): array
    {
        $recipeIds = array_values(array_unique(array_map('intval', $recipeIds)));

        if ($recipeIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($recipeIds), '?'));
        $statement = Database::connection()->prepare(
            "SELECT rc.recipe_id, c.*
             FROM recipe_categories rc
             INNER JOIN categories c ON c.id = rc.category_id
             WHERE rc.recipe_id IN ({$placeholders})
             ORDER BY c.position, c.name"
        );
        $statement->execute($recipeIds);

        $grouped = [];
        foreach ($statement->fetchAll() as $row) {
            $recipeId = (int) $row['recipe_id'];
            unset($row['recipe_id']);
            $grouped[$recipeId][] = $row;
        }

        return $grouped;
    }

    /**
     * Replace a recipe's category assignments with the given set of
     * category ids. Unknown ids are silently ignored.
     *
     * @param int[] $categoryIds
     */
    public function sync(int $recipeId, array $categoryIds): void
    {
        $validIds = array_map(
            static fn (array $category): int => (int) $category['id'],
            $this->all()
        );

        $categoryIds = array_values(array_unique(array_intersect(
            array_map('intval', $categoryIds),
            $validIds
        )));

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $delete = $connection->prepare(
                'DELETE FROM recipe_categories WHERE recipe_id = :recipe_id'
            );
            $delete->execute(['recipe_id' => $recipeId]);

            if ($categoryIds !== []) {
                $insert = $connection->prepare(
                    'INSERT INTO recipe_categories (recipe_id, category_id) VALUES (:recipe_id, :category_id)'
                );

                foreach ($categoryIds as $categoryId) {
                    $insert->execute([
                        'recipe_id' => $recipeId,
                        'category_id' => $categoryId,
                    ]);
                }
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }
}
