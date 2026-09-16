<?php
// /public_html/app/Controllers/ProductImportController.php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthServiceInterface;
use App\Core\Container;
use App\Repositories\ProductRepository;
use App\Services\ProductImport\AhProductImporter;
use App\Services\ProductImport\ImportedProduct;
use App\Services\ProductImport\ImportException;
use App\Services\RemoteImageService;

final class ProductImportController
{
    public function create(): void
    {
        view('products/import/create', [
            'title' => 'Import AH product',
            'error' => null,
            'url' => '',
            'returnTo' => $this->safeReturnTo((string) ($_GET['return_to'] ?? '')),
            'sourceIngredientId' => max((int) ($_GET['source_ingredient'] ?? 0), 0),
            'sourceIngredientId' => max((int) ($_GET['source_ingredient'] ?? 0), 0),
        ]);
    }

    public function preview(): void
    {
        $url = trim((string) ($_POST['url'] ?? ''));
        $returnTo = $this->safeReturnTo((string) ($_POST['return_to'] ?? ''));
        $sourceIngredientId = max((int) ($_POST['source_ingredient'] ?? 0), 0);

        try {
            $product = (new AhProductImporter())->import($url);
        } catch (ImportException $exception) {
            if ($this->wantsJson()) {
                $this->jsonOrExit(['error' => $exception->getMessage()], 422);
            }

            http_response_code(422);
            view('products/import/create', [
                'title' => 'Import AH product',
                'error' => $exception->getMessage(),
                'url' => $url,
                'returnTo' => $returnTo,
                'sourceIngredientId' => $sourceIngredientId,
            ]);
            return;
        }

        $token = bin2hex(random_bytes(24));
        $_SESSION['product_import_previews'][$token] = [
            'created_at' => time(),
            'product' => $product->toArray(),
            'return_to' => $returnTo,
            'source_ingredient' => $sourceIngredientId,
        ];

        $existing = (new ProductRepository())->findBySource('ah', $product->sourceIdentifier);

        /*
         * The AH import modal (recipe page and /products) previews and
         * confirms the import without navigating away, so it asks for
         * JSON back at each step instead of the usual server-rendered
         * review page.
         */
        if ($this->wantsJson()) {
            $this->json([
                'product' => $product->toArray(),
                'preview_token' => $token,
                'existing' => $existing !== null,
            ]);
        }

        view('products/import/preview', [
            'title' => 'Review AH product',
            'product' => $product,
            'previewToken' => $token,
            'existing' => $existing,
        ]);
    }

    public function store(): void
    {
        $token = (string) ($_POST['preview_token'] ?? '');
        $preview = $_SESSION['product_import_previews'][$token] ?? null;
        unset($_SESSION['product_import_previews'][$token]);

        if (!is_array($preview) || (int) ($preview['created_at'] ?? 0) < time() - 1800) {
            $this->jsonOrExit(['error' => 'The import preview has expired.'], 422);
        }

        $sourceIngredientId = (int) ($preview['source_ingredient'] ?? 0);

        $product = ImportedProduct::fromArray((array) $preview['product']);
        $data = $product->toArray();

        foreach (['name', 'brand', 'package_description'] as $field) {
            $data[$field] = trim((string) ($_POST[$field] ?? $data[$field] ?? ''));
        }

        $data['package_amount'] = max((float) ($_POST['package_amount'] ?? $data['package_amount'] ?? 0), 0);
        $data['package_unit'] = trim((string) ($_POST['package_unit'] ?? $data['package_unit'] ?? ''));

        foreach ([
                     'energy_kj', 'energy_kcal', 'fat_g', 'saturated_fat_g',
                     'carbohydrates_g', 'sugars_g', 'fiber_g', 'protein_g', 'salt_g',
                 ] as $field) {
            $data[$field] = max((float) ($_POST[$field] ?? $data[$field]), 0);
        }

        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        $repository = new ProductRepository();
        $productId = $repository->upsertImported($data);

        $repository->setImage(
            $productId,
            (int) $user['id'],
            (new RemoteImageService())->importFromPage($data['source_url'], 'products')
        );

        if ($this->wantsJson()) {
            $this->json(['product' => $repository->findForUser($productId, (int) $user['id'])]);
        }

        $returnTo = $this->safeReturnTo((string) ($preview['return_to'] ?? ''));
        redirect($returnTo !== ''
            ? $returnTo . '?selected_product=' . $productId
                . ($sourceIngredientId > 0
                    ? '&source_ingredient=' . $sourceIngredientId
                    : '')
                . '#source-ingredients'
            : '/products?imported=' . $productId
        );
    }

    private function safeReturnTo(string $path): string
    {
        return preg_match('#^/recipes/\d+$#', $path) === 1 ? $path : '';
    }

    private function wantsJson(): bool
    {
        return str_contains(
            strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')),
            'application/json'
        ) || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_THROW_ON_ERROR);
        exit;
    }

    private function jsonOrExit(array $payload, int $status): never
    {
        if ($this->wantsJson()) {
            $this->json($payload, $status);
        }

        http_response_code($status);
        exit((string) ($payload['error'] ?? 'Request failed.'));
    }
}
