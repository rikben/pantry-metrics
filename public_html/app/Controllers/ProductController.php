<?php
// /public_html/app/Controllers/ProductController.php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthServiceInterface;
use App\Core\Container;
use App\Repositories\ProductRepository;
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

        $returnTo = $this->safeReturnTo((string) ($_POST['return_to'] ?? ''));
        redirect($returnTo !== ''
            ? $returnTo . '?selected_product=' . $productId . '#add-ingredient'
            : '/products?created=' . $productId
        );
    }

    public function edit(string $id): void
    {
        $user = Container::instance()->get(AuthServiceInterface::class)->user();
        $product = (new ProductRepository())->findForUser((int) $id, (int) $user['id']);

        if (!$product) {
            http_response_code(404);
            view('errors/404', ['title' => 'Product not found']);
            return;
        }

        view('products/form', [
            'title' => 'Edit product',
            'product' => $product,
            'action' => "/products/{$id}/update",
            'returnTo' => '',
        ]);
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
            http_response_code(422);
            exit('Product name is required.');
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
}
