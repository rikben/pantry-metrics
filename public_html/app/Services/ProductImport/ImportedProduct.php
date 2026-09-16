<?php
// /public_html/app/Services/ProductImport/ImportedProduct.php

declare(strict_types=1);

namespace App\Services\ProductImport;

final readonly class ImportedProduct
{
    public function __construct(
        public string $name,
        public ?string $brand,
        public string $sourceIdentifier,
        public string $sourceUrl,
        public ?float $packageAmount,
        public ?string $packageUnit,
        public ?string $packageDescription,
        public float $referenceAmount,
        public string $referenceUnit,
        public float $energyKj,
        public float $energyKcal,
        public float $fatG,
        public float $saturatedFatG,
        public float $carbohydratesG,
        public float $sugarsG,
        public float $fiberG,
        public float $proteinG,
        public float $saltG,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'brand' => $this->brand,
            'source_type' => 'ah',
            'source_identifier' => $this->sourceIdentifier,
            'source_url' => $this->sourceUrl,
            'package_amount' => $this->packageAmount,
            'package_unit' => $this->packageUnit,
            'package_description' => $this->packageDescription,
            'reference_amount' => $this->referenceAmount,
            'reference_unit' => $this->referenceUnit,
            'energy_kj' => $this->energyKj,
            'energy_kcal' => $this->energyKcal,
            'fat_g' => $this->fatG,
            'saturated_fat_g' => $this->saturatedFatG,
            'carbohydrates_g' => $this->carbohydratesG,
            'sugars_g' => $this->sugarsG,
            'fiber_g' => $this->fiberG,
            'protein_g' => $this->proteinG,
            'salt_g' => $this->saltG,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            brand: isset($data['brand']) && $data['brand'] !== '' ? (string) $data['brand'] : null,
            sourceIdentifier: (string) $data['source_identifier'],
            sourceUrl: (string) $data['source_url'],
            packageAmount: isset($data['package_amount']) && $data['package_amount'] !== null
                ? (float) $data['package_amount']
                : null,
            packageUnit: isset($data['package_unit']) && $data['package_unit'] !== ''
                ? (string) $data['package_unit']
                : null,
            packageDescription: isset($data['package_description']) && $data['package_description'] !== ''
                ? (string) $data['package_description']
                : null,
            referenceAmount: (float) $data['reference_amount'],
            referenceUnit: (string) $data['reference_unit'],
            energyKj: (float) $data['energy_kj'],
            energyKcal: (float) $data['energy_kcal'],
            fatG: (float) $data['fat_g'],
            saturatedFatG: (float) $data['saturated_fat_g'],
            carbohydratesG: (float) $data['carbohydrates_g'],
            sugarsG: (float) $data['sugars_g'],
            fiberG: (float) $data['fiber_g'],
            proteinG: (float) $data['protein_g'],
            saltG: (float) $data['salt_g'],
        );
    }
}
