<?php
// /public_html/app/Services/ProductImport/AhProductImporter.php

declare(strict_types=1);

namespace App\Services\ProductImport;

use App\Http\HttpClient;
use DOMDocument;
use DOMXPath;

final class AhProductImporter implements ProductImporterInterface
{
    public function __construct(private readonly HttpClient $httpClient = new HttpClient())
    {
    }

    public function supports(string $url): bool
    {
        $parts = parse_url($url);
        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && in_array(strtolower((string) ($parts['host'] ?? '')), ['ah.nl', 'www.ah.nl'], true)
            && preg_match('#^/producten/product/(wi\d+)(?:/|$)#', (string) ($parts['path'] ?? '')) === 1;
    }

    public function import(string $url): ImportedProduct
    {
        $url = trim($url);

        if (!$this->supports($url)) {
            throw new ImportException('Enter a valid public AH product URL.');
        }

        preg_match('#/producten/product/(wi\d+)#', $url, $identifierMatch);

        try {
            $html = $this->httpClient->get($url);
        } catch (\Throwable $exception) {
            throw new ImportException($exception->getMessage(), 0, $exception);
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new ImportException('The downloaded AH page could not be parsed.');
        }

        $xpath = new DOMXPath($document);
        $name = $this->extractName($xpath);
        $text = $this->normalizeText((string) $document->textContent);
        $nutrition = $this->nutritionSection($text);

        if (!preg_match('/Per\s+([\d.,]+)\s+(Gram|Milliliter)/iu', $nutrition, $reference)) {
            throw new ImportException('No supported nutrition reference was found.');
        }

        [$energyKj, $energyKcal] = $this->energy($nutrition);
        [$packageAmount, $packageUnit, $packageDescription] = $this->package($html, $text);

        return new ImportedProduct(
            name: $name,
            brand: str_starts_with(mb_strtoupper($name), 'AH ') ? 'AH' : null,
            sourceIdentifier: $identifierMatch[1],
            sourceUrl: $url,
            packageAmount: $packageAmount,
            packageUnit: $packageUnit,
            packageDescription: $packageDescription,
            referenceAmount: $this->number($reference[1]),
            referenceUnit: mb_strtolower($reference[2]) === 'milliliter' ? 'ml' : 'g',
            energyKj: $energyKj,
            energyKcal: $energyKcal,
            fatG: $this->nutrient($nutrition, ['Vet', 'Vetten']),
            saturatedFatG: $this->nutrient($nutrition, ['waarvan verzadigd', 'verzadigd vet']),
            carbohydratesG: $this->nutrient($nutrition, ['Koolhydraten', 'Koolhydraat']),
            sugarsG: $this->nutrient($nutrition, ['waarvan suikers', 'Suikers']),
            fiberG: $this->nutrient($nutrition, ['Voedingsvezel', 'Voedingsvezels', 'Vezels']),
            proteinG: $this->nutrient($nutrition, ['Eiwitten', 'Eiwit']),
            saltG: $this->nutrient($nutrition, ['Zout']),
        );
    }

    private function extractName(DOMXPath $xpath): string
    {
        foreach (['//main//h1[1]', '//h1[1]', '//meta[@property="og:title"]/@content'] as $query) {
            $nodes = $xpath->query($query);
            $value = $nodes !== false && $nodes->length > 0
                ? trim((string) $nodes->item(0)?->nodeValue)
                : '';

            if ($value !== '') {
                return preg_replace('/\s+bestellen\s*$/iu', '', $value) ?? $value;
            }
        }

        throw new ImportException('The product name could not be found.');
    }

    private function nutritionSection(string $text): string
    {
        $start = mb_stripos($text, 'Voedingswaarden');
        if ($start === false) {
            throw new ImportException('The AH page has no nutrition section.');
        }

        $section = mb_substr($text, $start, 2500);
        foreach (['Gebruik', 'Bewaren', 'Herkomst', 'Contactgegevens', 'Bereiding'] as $marker) {
            $end = mb_stripos($section, $marker, 20);
            if ($end !== false) {
                $section = mb_substr($section, 0, $end);
            }
        }

        return $section;
    }

    private function energy(string $text): array
    {
        if (!preg_match(
            '/(?:Energie|Energetische\s+waarde)\s*([\d.,]+)\s*kJ\s*(?:\(|\/)?\s*([\d.,]+)\s*kcal/iu',
            $text,
            $matches
        )) {
            throw new ImportException('Energy could not be parsed.');
        }

        return [$this->number($matches[1]), $this->number($matches[2])];
    }

    private function nutrient(string $text, array $labels): float
    {
        foreach ($labels as $label) {
            if (preg_match(
                '/' . preg_quote($label, '/') . '\s*:?\s*(?:<\s*)?'
                . '([\d]+(?:[.,]\d+)?)\s*(?:g|gram)(?=\s|$|[\p{L}])/iu',
                $text,
                $matches
            )) {
                return $this->number($matches[1]);
            }
        }

        return 0.0;
    }

    private function package(string $html, string $text): array
    {
        $description = null;

        foreach ([
            '/"unitSize"\s*:\s*"([^"]+)"/u',
            '/"salesUnitSize"\s*:\s*"([^"]+)"/u',
        ] as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                $description = stripcslashes($match[1]);
                break;
            }
        }

        if ($description === null && preg_match('/\b(\d+(?:[.,]\d+)?)\s*(g|kg|ml|l)\b/iu', mb_substr($text, 0, 5000), $match)) {
            $description = $match[1] . ' ' . $match[2];
        }

        if ($description === null) {
            return [null, null, null];
        }

        if (!preg_match('/(\d+(?:[.,]\d+)?)\s*(kg|g|ml|l|stuks?|st)\b/iu', $description, $match)) {
            return [null, null, $description];
        }

        $amount = $this->number($match[1]);
        $unit = mb_strtolower($match[2]);

        if ($unit === 'kg') {
            $amount *= 1000;
            $unit = 'g';
        } elseif ($unit === 'l') {
            $amount *= 1000;
            $unit = 'ml';
        } elseif (str_starts_with($unit, 'st')) {
            $unit = 'serving';
        }

        return [$amount, $unit, $description];
    }

    private function number(string $value): float
    {
        $value = trim($value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        return (float) $value;
    }

    private function normalizeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\u{00A0}", "\u{202F}", "\u{2007}", "\u{200B}", "\u{FEFF}"], ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
