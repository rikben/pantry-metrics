<?php
// /public_html/app/Controllers/ProductController.php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthServiceInterface;
use App\Core\Container;
use App\Repositories\ProductRepository;
use App\Repositories\ProductUnitConversionRepository;
use App\Services\RemoteImageService;

final class ProductController
{
    public function index(): void
    {
        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        $archived = ($_GET['archived'] ?? '') === '1';

        view('products/index', [
            'title' => $archived ? 'Archived products' : 'Products',
            'products' => (new ProductRepository())->allForUser((int) $user['id'], $archived),
            'archived' => $archived,
        ]);
    }

    public function create(): void
    {
        view('products/form', [
            'title' => 'Add product',
            'product' => null,
            'action' => '/products',
            'returnTo' => $this->safeReturnTo((string) ($_GET['return_to'] ?? '')),
            'sourceIngredientId' => max((int) ($_GET['source_ingredient'] ?? 0), 0),
        ]);
    }

    public function store(): void
    {
        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        $data = $this->validatedData();
        $repository = new ProductRepository();
        $productId = $repository->create((int) $user['id'], $data);

        if ($data['source_url'] !== '') {
            $repository->setImage(
                $productId,
                (int) $user['id'],
                (new RemoteImageService())->importFromPage($data['source_url'], 'products')
            );
        }

        /*
         * The inline "create a new product" panel on the recipe page
         * creates a product without navigating away, so it asks for JSON
         * back instead of the usual redirect.
         */
        if ($this->wantsJson()) {
            $this->json(['product' => $repository->findForUser($productId, (int) $user['id'])]);
        }

        $returnTo = $this->safeReturnTo((string) ($_POST['return_to'] ?? ''));
        $sourceIngredientId = max((int) ($_POST['source_ingredient'] ?? 0), 0);
        redirect($returnTo !== ''
            ? $returnTo
                . '?selected_product='
                . $productId
                . (
                    $sourceIngredientId > 0
                        ? '&source_ingredient='
                            . $sourceIngredientId
                        : ''
                )
                . '#source-ingredients'
            : '/products?created=' . $productId
        );
    }

    public function edit(string $id): void
    {
        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        $product = (new ProductRepository())->findForUser((int) $id, (int) $user['id']);

        if (!$product) {
            if ($this->wantsJson()) {
                $this->jsonOrExit(['error' => 'Product not found.'], 404);
            }

            http_response_code(404);
            view('errors/404', ['title' => 'Product not found']);
            return;
        }

        $conversions = (new ProductUnitConversionRepository())->forProduct((int) $id);

        /*
         * The product edit modal (used from both /products and the recipe
         * page) fetches the product + its conversions as JSON instead of
         * loading the full edit page.
         */
        if ($this->wantsJson()) {
            $this->json(['product' => $product + ['conversions' => $conversions]]);
        }

        view('products/form', [
            'title' => 'Edit product',
            'product' => $product,
            'action' => "/products/{$id}/update",
            'returnTo' => '',
            'conversions' => $conversions,
        ]);
    }

    public function addConversion(string $id): void
    {
        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        $product = (new ProductRepository())->findForUser((int) $id, (int) $user['id']);

        if (!$product) {
            $this->jsonOrExit(['error' => 'Product not found.'], 404);
        }

        $unit = trim((string) ($_POST['unit'] ?? ''));
        $referenceAmount = (float) ($_POST['reference_amount'] ?? 0);
        $allowedUnits = ['kg', 'mg', 'l', 'cl', 'dl', 'tbsp', 'tsp'];

        if (!in_array($unit, $allowedUnits, true) || $referenceAmount <= 0) {
            $this->jsonOrExit(['error' => 'Enter a unit and a positive amount.'], 422);
        }

        $repository = new ProductUnitConversionRepository();
        $repository->remember((int) $id, $unit, $referenceAmount);

        if ($this->wantsJson()) {
            $this->json(['conversions' => $repository->forProduct((int) $id)]);
        }

        redirect("/products/{$id}/edit");
    }

    public function deleteConversion(string $id, string $unit): void
    {
        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        $product = (new ProductRepository())->findForUser((int) $id, (int) $user['id']);

        if (!$product) {
            $this->jsonOrExit(['error' => 'Product not found.'], 404);
        }

        $repository = new ProductUnitConversionRepository();
        $repository->forget((int) $id, $unit);

        if ($this->wantsJson()) {
            $this->json(['conversions' => $repository->forProduct((int) $id)]);
        }

        redirect("/products/{$id}/edit");
    }

    public function update(string $id): void
    {
        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        $data = $this->validatedData();
        $repository = new ProductRepository();
        $repository->update((int) $id, (int) $user['id'], $data);

        if ($data['source_url'] !== '' && isset($_POST['refresh_image'])) {
            $repository->setImage(
                (int) $id,
                (int) $user['id'],
                (new RemoteImageService())->importFromPage($data['source_url'], 'products')
            );
        }

        /*
         * The product edit modal saves without navigating away, so it
         * asks for JSON back instead of the usual redirect.
         */
        if ($this->wantsJson()) {
            $this->json(['product' => $repository->findForUser((int) $id, (int) $user['id'])]);
        }

        redirect('/products');
    }

    public function archive(string $id): void
    {
        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        (new ProductRepository())->setArchived((int) $id, (int) $user['id'], true);
        redirect('/products');
    }

    public function restore(string $id): void
    {
        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        (new ProductRepository())->setArchived((int) $id, (int) $user['id'], false);
        redirect('/products?archived=1');
    }

    private function validatedData(): array
    {
        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'brand' => trim((string) ($_POST['brand'] ?? '')),
            'source_url' => trim((string) ($_POST['source_url'] ?? '')),
            'package_amount' => max((float) ($_POST['package_amount'] ?? 0), 0),
            'package_unit' => trim((string) ($_POST['package_unit'] ?? '')),
            'package_description' => trim((string) ($_POST['package_description'] ?? '')),
            'reference_amount' => max((float) ($_POST['reference_amount'] ?? 100), 0.001),
            'reference_unit' => in_array($_POST['reference_unit'] ?? '', ['g', 'ml', 'serving'], true)
                ? $_POST['reference_unit']
                : 'g',
        ];

        foreach ([
                     'energy_kj', 'energy_kcal', 'fat_g', 'saturated_fat_g',
                     'carbohydrates_g', 'sugars_g', 'fiber_g', 'protein_g', 'salt_g',
                 ] as $field) {
            $data[$field] = max((float) ($_POST[$field] ?? 0), 0);
        }

        if ($data['name'] === '') {
            $this->jsonOrExit(['error' => 'Product name is required.'], 422);
        }

        if ($data['package_amount'] <= 0) {
            $data['package_amount'] = null;
        }

        return $data;
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
