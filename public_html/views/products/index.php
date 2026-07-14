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
            <th>Image</th>
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
                    <?php if (!empty($product['image_path'])): ?>
                        <img
                                class="product-list-image"
                                src="<?= e($product['image_path']) ?>"
                                alt=""
                                loading="lazy"
                        >
                    <?php else: ?>
                        <span class="product-image-placeholder" aria-label="No image">
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5v-13Zm2 11.25 3.4-3.4a1 1 0 0 1 1.42 0l1.43 1.43 2.65-2.65a1 1 0 0 1 1.42 0L18 14.31V6H6v10.75ZM8.5 10A1.5 1.5 0 1 0 8.5 7a1.5 1.5 0 0 0 0 3Z"/>
                            </svg>
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <strong><?= e($product['name']) ?></strong>
                    <?php if ($product['brand']): ?>
                        <small><?= e($product['brand']) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?= e($product['package_description'] ?: (
                    $product['package_amount']
                            ? $product['package_amount'] . ' ' . $product['package_unit']
                            : 'Unknown'
                    )) ?>
                </td>
                <td><?= e($product['source_identifier'] ?: '—') ?></td>
                <td><?= e($product['reference_amount']) ?> <?= e($product['reference_unit']) ?></td>
                <td><?= e(round((float) $product['energy_kcal'], 1)) ?></td>
                <td><?= e(round((float) $product['protein_g'], 1)) ?> g</td>
                <td>
                    <div class="icon-actions">
                        <?php if (!$archived): ?>
                            <a
                                    class="icon-button"
                                    href="/products/<?= e($product['id']) ?>/edit"
                                    aria-label="Edit <?= e($product['name']) ?>"
                                    title="Edit product"
                            >
                                <svg aria-hidden="true" viewBox="0 0 24 24">
                                    <path d="m15.23 5.21 3.56 3.56L8.06 19.5H4.5v-3.56L15.23 5.21Zm1.42-1.42 1.06-1.06a2 2 0 0 1 2.83 0l.73.73a2 2 0 0 1 0 2.83l-1.06 1.06-3.56-3.56Z"/>
                                </svg>
                            </a>
                        <?php endif; ?>

                        <form method="post" action="/products/<?= e($product['id']) ?>/<?= $archived ? 'restore' : 'archive' ?>">
                            <?= csrf_field() ?>
                            <button
                                    class="icon-button <?= $archived ? '' : 'icon-button-danger' ?>"
                                    type="submit"
                                    aria-label="<?= $archived ? 'Restore' : 'Archive' ?> <?= e($product['name']) ?>"
                                    title="<?= $archived ? 'Restore product' : 'Archive product' ?>"
                            >
                                <?php if ($archived): ?>
                                    <svg aria-hidden="true" viewBox="0 0 24 24">
                                        <path d="M12 5a7 7 0 1 1-6.32 4H3l3.5-3.5L10 9H7.78A5 5 0 1 0 12 7V5Z"/>
                                    </svg>
                                <?php else: ?>
                                    <svg aria-hidden="true" viewBox="0 0 24 24">
                                        <path d="M7 4V2h10v2h5v2h-2l-1 15H5L4 6H2V4h5Zm2 4v9h2V8H9Zm4 0v9h2V8h-2Z"/>
                                    </svg>
                                <?php endif; ?>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
