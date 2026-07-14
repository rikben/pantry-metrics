<?php
// /public_html/app/Services/NutritionCalculator.php

declare(strict_types=1);

namespace App\Services;

final class NutritionCalculator
{
    private const FIELDS = [
        'energy_kj',
        'energy_kcal',
        'fat_g',
        'saturated_fat_g',
        'carbohydrates_g',
        'sugars_g',
        'fiber_g',
        'protein_g',
        'salt_g',
    ];

    public function calculate(array $ingredients, float $servings): array
    {
        $totals = array_fill_keys(self::FIELDS, 0.0);
        $calculatedIngredients = [];

        foreach ($ingredients as $ingredient) {
            $factor = (float) $ingredient['amount'] / (float) $ingredient['reference_amount'];
            $row = $ingredient;

            foreach (self::FIELDS as $field) {
                $row['calculated_' . $field] = $factor * (float) $ingredient[$field];
                $totals[$field] += $row['calculated_' . $field];
            }

            $calculatedIngredients[] = $row;
        }

        $safeServings = max($servings, 0.01);
        $perServing = [];

        foreach ($totals as $field => $value) {
            $perServing[$field] = $value / $safeServings;
        }

        return [
            'ingredients' => $calculatedIngredients,
            'totals' => $totals,
            'per_serving' => $perServing,
        ];
    }
}
