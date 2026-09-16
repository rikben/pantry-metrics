<?php
// /public_html/app/Services/RecipeImport/AhRecipeImporter.php

declare(strict_types=1);

namespace App\Services\RecipeImport;

use App\Http\HttpClient;
use DOMDocument;
use DOMXPath;
use RuntimeException;

final class AhRecipeImporter
{
    public function import(string $url): ImportedRecipe
    {
        $url = trim($url);

        if (!preg_match(
            '#^https://(?:www\.)?ah\.nl/allerhande/recept/(R-R\d+)(?:/|$)#i',
            $url,
            $identifierMatch
        )) {
            throw new RuntimeException(
                'Enter a valid public AH Allerhande recipe URL.'
            );
        }

        $html = (new HttpClient())->get($url);
        $document = $this->document($html);
        $xpath = new DOMXPath($document);

        $structured = $this->recipeJsonLd($xpath);

        if ($structured !== null) {
            return $this->fromJsonLd(
                $structured,
                $url,
                $identifierMatch[1]
            );
        }

        return $this->fromVisibleHtml(
            $xpath,
            $document,
            $url,
            $identifierMatch[1]
        );
    }

    private function document(string $html): DOMDocument
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new RuntimeException(
                'The AH recipe page could not be parsed.'
            );
        }

        return $document;
    }

    private function recipeJsonLd(DOMXPath $xpath): ?array
    {
        $nodes = $xpath->query(
            '//script[@type="application/ld+json"]'
        );

        if ($nodes === false) {
            return null;
        }

        foreach ($nodes as $node) {
            $decoded = json_decode(
                trim((string) $node->textContent),
                true
            );

            foreach ($this->jsonObjects($decoded) as $object) {
                $type = $object['@type'] ?? null;
                $types = is_array($type) ? $type : [$type];

                if (in_array('Recipe', $types, true)) {
                    return $object;
                }
            }
        }

        return null;
    }

    private function jsonObjects(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $objects = [];

        if (array_is_list($value)) {
            foreach ($value as $item) {
                $objects = [
                    ...$objects,
                    ...$this->jsonObjects($item),
                ];
            }

            return $objects;
        }

        $objects[] = $value;

        if (isset($value['@graph'])) {
            $objects = [
                ...$objects,
                ...$this->jsonObjects($value['@graph']),
            ];
        }

        return $objects;
    }

    private function fromJsonLd(
        array $recipe,
        string $url,
        string $identifier
    ): ImportedRecipe {
        $instructions = [];

        foreach ((array) ($recipe['recipeInstructions'] ?? []) as $step) {
            if (is_string($step)) {
                $instructions[] = $this->text($step);
                continue;
            }

            if (is_array($step)) {
                $value = (string) (
                    $step['text']
                    ?? $step['name']
                    ?? ''
                );

                if ($value !== '') {
                    $instructions[] = $this->text($value);
                }
            }
        }

        return new ImportedRecipe(
            name: $this->requiredText(
                (string) ($recipe['name'] ?? ''),
                'Recipe name'
            ),
            description: $this->text(
                (string) ($recipe['description'] ?? '')
            ),
            servings: $this->servings(
                $recipe['recipeYield'] ?? 1
            ),
            ingredients: array_values(
                array_filter(
                    array_map(
                        fn ($ingredient) =>
                            $this->text((string) $ingredient),
                        (array) (
                            $recipe['recipeIngredient']
                            ?? []
                        )
                    )
                )
            ),
            instructions: $instructions,
            sourceUrl: $url,
            sourceIdentifier: $identifier,
            imageUrl: $this->imageUrl(
                $recipe['image'] ?? null
            ),
        );
    }

    private function fromVisibleHtml(
        DOMXPath $xpath,
        DOMDocument $document,
        string $url,
        string $identifier
    ): ImportedRecipe {
        $name = $this->firstText(
            $xpath,
            ['//main//h1[1]', '//h1[1]']
        );

        $description = $this->firstText(
            $xpath,
            [
                '//meta[@name="description"]/@content',
                '//meta[@property="og:description"]/@content',
            ]
        );

        $fullText = $this->text(
            (string) $document->textContent
        );

        $servings = 1.0;

        if (preg_match(
            '/Aantal\s+personen\s+([\d.,]+)/iu',
            $fullText,
            $match
        )) {
            $servings = $this->number($match[1]);
        } elseif (preg_match(
            '/([\d.,]+)\s+personen/iu',
            $fullText,
            $match
        )) {
            $servings = $this->number($match[1]);
        }

        $ingredients = $this->sectionItems(
            $xpath,
            'Ingrediënten',
            ['Dit heb je nodig', 'Aan de slag']
        );

        $instructions = $this->sectionItems(
            $xpath,
            'Aan de slag',
            ['Voedingswaarden', 'Gerelateerde recepten']
        );

        $image = $this->firstText(
            $xpath,
            [
                '//meta[@property="og:image"]/@content',
                '//meta[@name="twitter:image"]/@content',
            ]
        );

        if ($ingredients === [] || $instructions === []) {
            throw new RuntimeException(
                'The AH recipe ingredients or instructions could not be parsed.'
            );
        }

        return new ImportedRecipe(
            name: $this->requiredText($name, 'Recipe name'),
            description: $description,
            servings: $servings,
            ingredients: $ingredients,
            instructions: $instructions,
            sourceUrl: $url,
            sourceIdentifier: $identifier,
            imageUrl: $image !== '' ? $image : null,
        );
    }

    private function sectionItems(
        DOMXPath $xpath,
        string $heading,
        array $stopHeadings
    ): array {
        $headingLiteral = $this->xpathLiteral($heading);
        $query = sprintf(
            '//*[self::h2 or self::h3]'
            . '[normalize-space(.)=%s]'
            . '/following::*[self::li or self::p]',
            $headingLiteral
        );

        $nodes = $xpath->query($query);

        if ($nodes === false) {
            return [];
        }

        $items = [];

        foreach ($nodes as $node) {
            $text = $this->text(
                (string) $node->textContent
            );

            foreach ($stopHeadings as $stop) {
                if (mb_stripos($text, $stop) === 0) {
                    return $items;
                }
            }

            if (
                $text !== ''
                && mb_strlen($text) <= 1000
                && !in_array($text, $items, true)
            ) {
                $items[] = preg_replace(
                    '/^\d+\s+/u',
                    '',
                    $text
                ) ?? $text;
            }
        }

        return $items;
    }

    private function firstText(
        DOMXPath $xpath,
        array $queries
    ): string {
        foreach ($queries as $query) {
            $nodes = $xpath->query($query);

            if ($nodes !== false && $nodes->length > 0) {
                $value = $this->text(
                    (string) $nodes->item(0)?->nodeValue
                );

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    private function servings(mixed $value): float
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        if (preg_match(
            '/([\d.,]+)/',
            (string) $value,
            $match
        )) {
            return max($this->number($match[1]), 0.01);
        }

        return 1.0;
    }

    private function imageUrl(mixed $image): ?string
    {
        if (is_string($image)) {
            return $image;
        }

        if (is_array($image)) {
            if (isset($image['url'])) {
                return (string) $image['url'];
            }

            $first = reset($image);

            if (is_string($first)) {
                return $first;
            }

            if (is_array($first) && isset($first['url'])) {
                return (string) $first['url'];
            }
        }

        return null;
    }

    private function number(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }

    private function requiredText(
        string $value,
        string $field
    ): string {
        $value = $this->text($value);

        if ($value === '') {
            throw new RuntimeException(
                "{$field} could not be found."
            );
        }

        return $value;
    }

    private function text(string $value): string
    {
        $value = html_entity_decode(
            $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        return trim(
            preg_replace('/\s+/u', ' ', strip_tags($value))
            ?? $value
        );
    }

    private function xpathLiteral(string $value): string
    {
        if (!str_contains($value, "'")) {
            return "'{$value}'";
        }

        return '"' . str_replace('"', '', $value) . '"';
    }
}
