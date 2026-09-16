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

        /*
         * recipe_ingredients.unit only ever holds 'g', 'ml' or 'serving'
         * (culinary units like tbsp are resolved into one of those via
         * UnitConverter before storage), so a gram-equivalent recipe
         * weight can be built by summing the g/ml amounts directly
         * (1 ml treated as ~1 g, a standard kitchen approximation).
         * Ingredients measured in whole servings (e.g. "2 eggs") have no
         * known mass, so their presence just marks the total as
         * approximate rather than being excluded from the UI entirely.
         */
        $totalWeightG = 0.0;
        $weightIsApproximate = false;

        foreach ($ingredients as $ingredient) {
            $factor = (float) $ingredient['amount'] / (float) $ingredient['reference_amount'];
            $row = $ingredient;

            foreach (self::FIELDS as $field) {
                $row['calculated_' . $field] = $factor * (float) $ingredient[$field];
                $totals[$field] += $row['calculated_' . $field];
            }

            $unit = (string) ($ingredient['unit'] ?? '');
            if ($unit === 'g' || $unit === 'ml') {
                $totalWeightG += (float) $ingredient['amount'];
            } elseif ($unit === 'serving') {
                $weightIsApproximate = true;
            }

            $calculatedIngredients[] = $row;
        }

        $safeServings = max($servings, 0.01);
        $perServing = [];

        foreach ($totals as $field => $value) {
            $perServing[$field] = $value / $safeServings;
        }

        $per100g = null;

        if ($totalWeightG > 0) {
            $per100g = [];

            foreach ($totals as $field => $value) {
                $per100g[$field] = $value / $totalWeightG * 100;
            }
        }

        return [
            'ingredients' => $calculatedIngredients,
            'totals' => $totals,
            'per_serving' => $perServing,
            'per_100g' => $per100g,
            'total_weight_g' => $totalWeightG,
            'weight_is_approximate' => $weightIsApproximate,
        ];
    }
}
