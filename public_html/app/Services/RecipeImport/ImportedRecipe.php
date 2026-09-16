<?php
// /public_html/app/Services/RecipeImport/ImportedRecipe.php

declare(strict_types=1);

namespace App\Services\RecipeImport;

final readonly class ImportedRecipe
{
    public function __construct(
        public string $name,
        public string $description,
        public float $servings,
        public array $ingredients,
        public array $instructions,
        public string $sourceUrl,
        public string $sourceIdentifier,
        public ?string $imageUrl,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'servings' => $this->servings,
            'ingredients' => $this->ingredients,
            'instructions' => $this->instructions,
            'source_url' => $this->sourceUrl,
            'source_identifier' => $this->sourceIdentifier,
            'image_url' => $this->imageUrl,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            description: (string) ($data['description'] ?? ''),
            servings: (float) ($data['servings'] ?? 1),
            ingredients: array_values((array) ($data['ingredients'] ?? [])),
            instructions: array_values((array) ($data['instructions'] ?? [])),
            sourceUrl: (string) $data['source_url'],
            sourceIdentifier: (string) $data['source_identifier'],
            imageUrl: !empty($data['image_url'])
                ? (string) $data['image_url']
                : null,
        );
    }
}
