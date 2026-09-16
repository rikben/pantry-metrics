<?php
// /public_html/app/Services/UnitConverter.php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProductUnitConversionRepository;

/**
 * Resolves an amount entered in a culinary unit (tbsp, tsp, kg, ...) into
 * a product's own reference unit (g, ml or serving), reusing a
 * per-product conversion remembered earlier instead of requiring it to
 * be re-entered for every recipe. Used both when linking an imported
 * recipe line to a product and when adding an ingredient by hand.
 */
final class UnitConverter
{
    public function __construct(
        private readonly ProductUnitConversionRepository $conversions = new ProductUnitConversionRepository()
    ) {
    }

    /**
     * @return array{amount: float, unit: string, remembered: bool}
     *
     * @throws \InvalidArgumentException when a conversion is required but
     *     neither an explicit amount nor a remembered one is available
     */
    public function resolve(
        int $productId,
        string $referenceUnit,
        float $amount,
        string $unit,
        ?float $explicitConvertedAmount = null,
        ?string $explicitConvertedUnit = null,
        bool $remember = true
    ): array {
        if ($unit === $referenceUnit) {
            return ['amount' => $amount, 'unit' => $unit, 'remembered' => false];
        }

        $convertedAmount = $explicitConvertedAmount;
        $convertedUnit = $explicitConvertedUnit;

        if ($convertedAmount === null || $convertedAmount <= 0) {
            $saved = $this->conversions->find($productId, $unit);

            if ($saved !== null) {
                $convertedAmount = $amount * (float) $saved['reference_amount'];
                $convertedUnit = $referenceUnit;
            }
        }

        if (
            $convertedAmount === null
            || $convertedAmount <= 0
            || $convertedUnit !== $referenceUnit
        ) {
            throw new \InvalidArgumentException(
                sprintf(
                    'A conversion from %s to %s is required.',
                    $unit,
                    $referenceUnit
                )
            );
        }

        $remembered = false;

        /*
         * Only persist when the caller actually supplied a fresh value
         * this time around (as opposed to one we just looked up above) -
         * otherwise every reuse of a saved conversion would trigger a
         * redundant write.
         */
        if ($remember && $amount > 0 && $explicitConvertedAmount !== null) {
            $this->conversions->remember($productId, $unit, $convertedAmount / $amount);
            $remembered = true;
        }

        return ['amount' => $convertedAmount, 'unit' => $convertedUnit, 'remembered' => $remembered];
    }
}
