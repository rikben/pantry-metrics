<?php
// /public_html/views/products/index.php

declare(strict_types=1);
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">Reference library</p>
        <h1><?= $archived ? 'Archived products' : 'Products' ?></h1>
    </div>
    <div class="actions">
        <?php if (!$archived): ?>
            <a class="button" href="/products/import">Import from AH</a>
            <a class="button button-secondary" href="/products/create">Add manually</a>
            <a class="button button-secondary" href="/products?archived=1">Archived</a>
        <?php else: ?>
            <a class="button button-secondary" href="/products">Active products</a>
        <?php endif; ?>
    </div>
</div>

<div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Product</th>
            <th>Package</th>
            <th>AH ID</th>
            <th>Reference</th>
            <th>kcal</th>
            <th>Protein</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $product): ?>
            <tr>
                <td>
                    <strong><?= e($product['name']) ?></strong>
                    <?php if ($product['brand']): ?><small><?= e($product['brand']) ?></small><?php endif; ?>
                </td>
                <td><?= e($product['package_description'] ?: (
                    $product['package_amount']
                        ? $product['package_amount'] . ' ' . $product['package_unit']
                        : 'Unknown'
                )) ?></td>
                <td><?= e($product['source_identifier'] ?: '—') ?></td>
                <td><?= e($product['reference_amount']) ?> <?= e($product['reference_unit']) ?></td>
                <td><?= e(round((float) $product['energy_kcal'], 1)) ?></td>
                <td><?= e(round((float) $product['protein_g'], 1)) ?> g</td>
                <td>
                    <div class="table-actions">
                        <a href="/products/<?= e($product['id']) ?>/edit">Edit</a>
                        <form method="post" action="/products/<?= e($product['id']) ?>/<?= $archived ? 'restore' : 'archive' ?>">
                            <?= csrf_field() ?>
                            <button class="link-button" type="submit"><?= $archived ? 'Restore' : 'Archive' ?></button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
