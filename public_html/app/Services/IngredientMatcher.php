<?php
// /public_html/app/Services/IngredientMatcher.php

declare(strict_types=1);

namespace App\Services;

/**
 * Best-effort matching of a free-text recipe ingredient line (as
 * imported from AH, e.g. "bloem" once the amount/unit have already been
 * parsed off) against the existing product catalog, so the "Imported
 * ingredient checklist" can arrive with a sensible product already
 * selected instead of a blank picker.
 *
 * This never saves anything by itself - it only decides what a <select>
 * starts out pointing at. The user still has to review the card and
 * click "Link product" (or pick a different match themselves) before
 * anything is written to the recipe.
 */
final class IngredientMatcher
{
    /**
     * Below this similarity score a suggestion is considered too
     * unreliable to show - an empty picker is more honest than a
     * confidently wrong one.
     */
    private const MIN_CONFIDENCE = 0.6;

    /**
     * Preparation/qualifier words that show up in recipe ingredient
     * lines but never in product names - stripping them before
     * comparing improves matches like "ui, fijngesneden" against a
     * plain "Ui" product.
     */
    private const NOISE_WORDS = [
        'fijngesneden', 'gesneden', 'gesnipperd', 'geraspt', 'gepeld',
        'geplet', 'gehakt', 'gemengd', 'vers', 'verse', 'grof', 'fijn',
        'in blokjes', 'blokjes', 'plakjes', 'partjes', 'blaadjes',
        'takjes', 'snufje', 'snuf', 'eventueel', 'naar smaak', 'los',
        'biologisch', 'bio',
    ];

    /**
     * @param array<int, array<string, mixed>> $products
     * @return array{product_id: int, confidence: float}|null
     */
    public function suggest(string $ingredientText, array $products): ?array
    {
        $needle = $this->normalize($ingredientText);

        if ($needle === '') {
            return null;
        }

        $bestProductId = null;
        $bestScore = 0.0;

        foreach ($products as $product) {
            $score = $this->score($needle, $product);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestProductId = (int) $product['id'];
            }
        }

        if ($bestProductId === null || $bestScore < self::MIN_CONFIDENCE) {
            return null;
        }

        return ['product_id' => $bestProductId, 'confidence' => $bestScore];
    }

    private function score(string $needle, array $product): float
    {
        $name = $this->normalize((string) $product['name']);

        if ($name === '') {
            return 0.0;
        }

        $withBrand = $this->normalize(
            trim($product['name'] . ' ' . (string) ($product['brand'] ?? ''))
        );

        $score = max(
            $this->similarity($needle, $name),
            $this->similarity($needle, $withBrand)
        );

        /*
         * A whole-word containment either way is a strong signal that
         * plain character-overlap similarity can under-score - e.g. a
         * short ingredient name ("ui") fully inside a longer product
         * name ("AH Uien 1 kg").
         */
        if (str_contains($name, $needle) || str_contains($needle, $name)) {
            $score = max($score, 0.75);
        }

        return $score;
    }

    private function similarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        similar_text($a, $b, $percent);

        return $percent / 100;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = str_replace(self::NOISE_WORDS, ' ', $text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
