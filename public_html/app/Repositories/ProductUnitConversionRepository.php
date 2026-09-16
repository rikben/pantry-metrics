<?php
// /public_html/app/Repositories/ProductUnitConversionRepository.php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Reusable culinary-unit conversions for a product (e.g. "1 tbsp of
 * tomato puree = 15 g"), set once on the product and reused automatically
 * every time that unit is used to link an ingredient, in any recipe.
 */
final class ProductUnitConversionRepository
{
    public function forProduct(int $productId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM product_unit_conversions
             WHERE product_id = :product_id
             ORDER BY unit'
        );
        $statement->execute(['product_id' => $productId]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, array<string, mixed>>> conversions keyed by product id, then unit
     */
    public function forProducts(array $productIds): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));

        if ($productIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $statement = Database::connection()->prepare(
            "SELECT * FROM product_unit_conversions
             WHERE product_id IN ({$placeholders})"
        );
        $statement->execute($productIds);

        $grouped = [];
        foreach ($statement->fetchAll() as $row) {
            $grouped[(int) $row['product_id']][(string) $row['unit']] = $row;
        }

        return $grouped;
    }

    public function find(int $productId, string $unit): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM product_unit_conversions
             WHERE product_id = :product_id AND unit = :unit
             LIMIT 1'
        );
        $statement->execute([
            'product_id' => $productId,
            'unit' => $unit,
        ]);

        return $statement->fetch() ?: null;
    }

    /**
     * @param float $referenceAmount how much of the product's reference
     *     unit equals 1 of $unit (e.g. 15 grams per 1 tbsp)
     */
    public function remember(int $productId, string $unit, float $referenceAmount): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO product_unit_conversions (product_id, unit, reference_amount)
             VALUES (:product_id, :unit, :reference_amount)
             ON DUPLICATE KEY UPDATE reference_amount = VALUES(reference_amount)'
        );
        $statement->execute([
            'product_id' => $productId,
            'unit' => $unit,
            'reference_amount' => $referenceAmount,
        ]);
    }

    public function forget(int $productId, string $unit): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM product_unit_conversions
             WHERE product_id = :product_id AND unit = :unit'
        );
        $statement->execute([
            'product_id' => $productId,
            'unit' => $unit,
        ]);
    }
}
