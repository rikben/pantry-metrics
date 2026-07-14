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

final class ProductImportController
{
    public function create(): void
    {
        view('products/import/create', [
            'title' => 'Import AH product',
            'error' => null,
            'url' => '',
            'returnTo' => $this->safeReturnTo((string) ($_GET['return_to'] ?? '')),
        ]);
    }

    public function preview(): void
    {
        $url = trim((string) ($_POST['url'] ?? ''));
        $returnTo = $this->safeReturnTo((string) ($_POST['return_to'] ?? ''));

        try {
            $product = (new AhProductImporter())->import($url);
        } catch (ImportException $exception) {
            http_response_code(422);
            view('products/import/create', [
                'title' => 'Import AH product',
                'error' => $exception->getMessage(),
                'url' => $url,
                'returnTo' => $returnTo,
            ]);
            return;
        }

        $token = bin2hex(random_bytes(24));
        $_SESSION['product_import_previews'][$token] = [
            'created_at' => time(),
            'product' => $product->toArray(),
            'return_to' => $returnTo,
        ];

        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        $existing = (new ProductRepository())->findBySource(
            (int) $user['id'],
            'ah',
            $product->sourceIdentifier
        );

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
            http_response_code(422);
            exit('The import preview has expired. Please import the product again.');
        }

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

        if ($data['name'] === '') {
            http_response_code(422);
            exit('Product name is required.');
        }

        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        (new ProductRepository())->upsertImported((int) $user['id'], $data);

        $returnTo = $this->safeReturnTo((string) ($preview['return_to'] ?? ''));
        redirect($returnTo !== '' ? $returnTo : '/products');
    }

    private function safeReturnTo(string $path): string
    {
        return preg_match('#^/recipes/\d+$#', $path) === 1 ? $path : '';
    }
}
