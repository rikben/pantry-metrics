<?php
// /public_html/app/Repositories/RecipeSourceIngredientRepository.php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class RecipeSourceIngredientRepository
{
    public function hasAnyForRecipe(
        int $recipeId,
        int $userId
    ): bool {
        $statement = Database::connection()->prepare(
            'SELECT 1
             FROM recipe_source_ingredients rsi
             INNER JOIN recipes r ON r.id = rsi.recipe_id
             WHERE rsi.recipe_id = :recipe_id
               AND r.owner_user_id = :user_id
             LIMIT 1'
        );
        $statement->execute([
            'recipe_id' => $recipeId,
            'user_id' => $userId,
        ]);

        return (bool) $statement->fetchColumn();
    }
    public function createMany(
        int $recipeId,
        array $ingredients
    ): void {
        $statement = Database::connection()->prepare(
            'INSERT INTO recipe_source_ingredients (
                recipe_id,
                position,
                raw_text,
                parsed_amount,
                parsed_unit,
                parsed_name
             ) VALUES (
                :recipe_id,
                :position,
                :raw_text,
                :parsed_amount,
                :parsed_unit,
                :parsed_name
             )'
        );

        foreach ($ingredients as $position => $rawText) {
            $parsed = $this->parse(
                (string) $rawText
            );

            $statement->execute([
                'recipe_id' => $recipeId,
                'position' => $position + 1,
                'raw_text' => $rawText,
                'parsed_amount' => $parsed['amount'],
                'parsed_unit' => $parsed['unit'],
                'parsed_name' => $parsed['name'],
            ]);
        }
    }

    public function allForRecipe(
        int $recipeId,
        int $userId
    ): array {
        $statement = Database::connection()->prepare(
            'SELECT rsi.*, p.name AS linked_product_name,
                p.brand AS linked_product_brand,
                p.image_path AS linked_product_image
             FROM recipe_source_ingredients rsi
             INNER JOIN recipes r ON r.id = rsi.recipe_id
             LEFT JOIN products p
                ON p.id = rsi.linked_product_id
             WHERE rsi.recipe_id = :recipe_id
               AND r.owner_user_id = :user_id
             ORDER BY rsi.position, rsi.id'
        );
        $statement->execute([
            'recipe_id' => $recipeId,
            'user_id' => $userId,
        ]);

        return $statement->fetchAll();
    }

    public function link(
        int $recipeId,
        int $sourceIngredientId,
        int $userId,
        int $productId,
        float $amount,
        string $unit
    ): bool {
        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $sourceStatement = $connection->prepare(
                'SELECT rsi.*
                 FROM recipe_source_ingredients rsi
                 INNER JOIN recipes r
                    ON r.id = rsi.recipe_id
                 WHERE rsi.id = :source_id
                   AND rsi.recipe_id = :recipe_id
                   AND r.owner_user_id = :user_id
                 FOR UPDATE'
            );
            $sourceStatement->execute([
                'source_id' => $sourceIngredientId,
                'recipe_id' => $recipeId,
                'user_id' => $userId,
            ]);
            $source = $sourceStatement->fetch();

            if (!$source) {
                $connection->rollBack();
                return false;
            }

            $productStatement = $connection->prepare(
                'SELECT id FROM products
                 WHERE id = :product_id
                   AND (
                       owner_user_id = :user_id
                       OR owner_user_id IS NULL
                   )
                   AND is_archived = 0
                 LIMIT 1'
            );
            $productStatement->execute([
                'product_id' => $productId,
                'user_id' => $userId,
            ]);

            if (!$productStatement->fetchColumn()) {
                $connection->rollBack();
                return false;
            }

            $recipeIngredientId =
                $source['recipe_ingredient_id'];

            if ($recipeIngredientId) {
                $ingredientStatement =
                    $connection->prepare(
                        'UPDATE recipe_ingredients
                         SET product_id = :product_id,
                             amount = :amount,
                             unit = :unit,
                             original_description =
                                :description
                         WHERE id = :id
                           AND recipe_id = :recipe_id'
                    );
                $ingredientStatement->execute([
                    'id' => $recipeIngredientId,
                    'recipe_id' => $recipeId,
                    'product_id' => $productId,
                    'amount' => $amount,
                    'unit' => $unit,
                    'description' => $source['raw_text'],
                ]);
            } else {
                $ingredientStatement =
                    $connection->prepare(
                        'INSERT INTO recipe_ingredients (
                            recipe_id,
                            product_id,
                            position,
                            original_description,
                            amount,
                            unit,
                            notes
                         ) VALUES (
                            :recipe_id,
                            :product_id,
                            :position,
                            :description,
                            :amount,
                            :unit,
                            NULL
                         )'
                    );
                $ingredientStatement->execute([
                    'recipe_id' => $recipeId,
                    'product_id' => $productId,
                    'position' => $source['position'],
                    'description' => $source['raw_text'],
                    'amount' => $amount,
                    'unit' => $unit,
                ]);

                $recipeIngredientId =
                    (int) $connection->lastInsertId();
            }

            $updateStatement = $connection->prepare(
                'UPDATE recipe_source_ingredients
                 SET linked_product_id = :product_id,
                     recipe_ingredient_id =
                        :recipe_ingredient_id,
                     parsed_amount = :amount,
                     parsed_unit = :unit,
                     is_ignored = 0
                 WHERE id = :id'
            );
            $updateStatement->execute([
                'id' => $sourceIngredientId,
                'product_id' => $productId,
                'recipe_ingredient_id' => $recipeIngredientId,
                'amount' => $amount,
                'unit' => $unit,
            ]);

            $connection->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function setIgnored(
        int $recipeId,
        int $sourceIngredientId,
        int $userId,
        bool $ignored
    ): bool {
        $statement = Database::connection()->prepare(
            'UPDATE recipe_source_ingredients rsi
             INNER JOIN recipes r
                ON r.id = rsi.recipe_id
             SET rsi.is_ignored = :ignored
             WHERE rsi.id = :source_id
               AND rsi.recipe_id = :recipe_id
               AND r.owner_user_id = :user_id'
        );
        $statement->execute([
            'ignored' => $ignored ? 1 : 0,
            'source_id' => $sourceIngredientId,
            'recipe_id' => $recipeId,
            'user_id' => $userId,
        ]);

        return $statement->rowCount() > 0;
    }

    private function parse(string $rawText): array
    {
        $text = trim(
            preg_replace('/\s+/u', ' ', $rawText)
            ?? $rawText
        );

        $patterns = [
            '/^([\d]+(?:[.,]\d+)?)\s*'
                . '(kg|g|mg|l|ml|cl|dl|el|tl|stuks?|st)\s+'
                . '(.+)$/iu',
            '/^([\d]+(?:[.,]\d+)?)\s+(.+)$/u',
        ];

        foreach ($patterns as $index => $pattern) {
            if (preg_match($pattern, $text, $match)) {
                if ($index === 0) {
                    return [
                        'amount' => $this->number($match[1]),
                        'unit' => $this->normalizeUnit(
                            $match[2]
                        ),
                        'name' => trim($match[3]),
                    ];
                }

                return [
                    'amount' => $this->number($match[1]),
                    'unit' => 'serving',
                    'name' => trim($match[2]),
                ];
            }
        }

        return [
            'amount' => null,
            'unit' => null,
            'name' => $text,
        ];
    }

    private function normalizeUnit(string $unit): string
    {
        $unit = mb_strtolower($unit);

        return match ($unit) {
            'kg' => 'kg',
            'l' => 'l',
            'mg' => 'mg',
            'cl' => 'cl',
            'dl' => 'dl',
            'el' => 'tbsp',
            'tl' => 'tsp',
            'st', 'stuk', 'stuks' => 'serving',
            default => $unit,
        };
    }

    private function number(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }
}
