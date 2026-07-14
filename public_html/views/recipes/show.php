<?php
// /public_html/views/recipes/show.php

declare(strict_types=1);

$perServing = $nutrition['per_serving'];
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">Recipe workspace</p>
        <h1><?= e($recipe['name']) ?></h1>
        <p><?= e($recipe['servings']) ?> servings</p>
    </div>
    <div class="actions">
        <a class="button button-secondary" href="/recipes/<?= e($recipe['id']) ?>/edit">Edit recipe</a>
        <?php if ($recipe['source_url']): ?>
            <a class="button button-secondary" href="<?= e($recipe['source_url']) ?>" rel="noreferrer" target="_blank">Open source</a>
        <?php endif; ?>
    </div>
</div>

<section class="stats nutrition-stats">
    <article class="card"><span class="stat-value"><?= e(round($perServing['energy_kcal'])) ?></span><span class="stat-label">kcal / serving</span></article>
    <article class="card"><span class="stat-value"><?= e(round($perServing['protein_g'], 1)) ?> g</span><span class="stat-label">Protein</span></article>
    <article class="card"><span class="stat-value"><?= e(round($perServing['carbohydrates_g'], 1)) ?> g</span><span class="stat-label">Carbohydrates</span></article>
    <article class="card"><span class="stat-value"><?= e(round($perServing['fat_g'], 1)) ?> g</span><span class="stat-label">Fat</span></article>
</section>

<section>
    <div class="section-heading"><h2>Ingredients</h2></div>
    <?php if ($nutrition['ingredients'] === []): ?>
        <div class="empty-state">Add a product below to start calculating.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Product</th><th>Package</th><th>Amount</th><th>kcal</th><th>Protein</th><th>Carbs</th><th>Fat</th></tr></thead>
                <tbody>
                <?php foreach ($nutrition['ingredients'] as $ingredient): ?>
                    <tr>
                        <td><strong><?= e($ingredient['product_name']) ?></strong><?php if ($ingredient['brand']): ?><small><?= e($ingredient['brand']) ?></small><?php endif; ?></td>
                        <td><?= e($ingredient['package_description'] ?: 'Unknown') ?></td>
                        <td><?= e($ingredient['amount']) ?> <?= e($ingredient['unit']) ?></td>
                        <td><?= e(round($ingredient['calculated_energy_kcal'], 1)) ?></td>
                        <td><?= e(round($ingredient['calculated_protein_g'], 1)) ?> g</td>
                        <td><?= e(round($ingredient['calculated_carbohydrates_g'], 1)) ?> g</td>
                        <td><?= e(round($ingredient['calculated_fat_g'], 1)) ?> g</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section>
    <div class="section-heading"><h2>Add ingredient</h2></div>

    <form class="card form-grid" id="ingredient-form" method="post" action="/recipes/<?= e($recipe['id']) ?>/ingredients">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect_to" id="ingredient-redirect-to" value="">

        <label class="full-width">
            Search product
            <input type="search" id="product-search" placeholder="Search by product name, brand or AH ID">
        </label>

        <label class="full-width">
            Product
            <select name="product_id" id="product-select" required>
                <option value="">Select a product</option>
                <?php foreach ($products as $product): ?>
                    <?php
                    $searchText = trim(implode(' ', array_filter([
                        $product['name'],
                        $product['brand'],
                        $product['source_identifier'],
                    ])));
                    ?>
                    <option
                        value="<?= e($product['id']) ?>"
                        data-search="<?= e(mb_strtolower($searchText)) ?>"
                        data-package-amount="<?= e($product['package_amount']) ?>"
                        data-package-unit="<?= e($product['package_unit']) ?>"
                    >
                        <?= e($product['name']) ?>
                        <?= $product['brand'] ? ' · ' . e($product['brand']) : '' ?>
                        <?= $product['source_identifier'] ? ' · ' . e($product['source_identifier']) : '' ?>
                        <?= $product['package_description'] ? ' · ' . e($product['package_description']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Amount
            <input type="number" id="ingredient-amount" name="amount" min="0.001" step="0.001" required>
        </label>

        <label>
            Unit
            <select id="ingredient-unit" name="unit">
                <option value="g">g</option>
                <option value="ml">ml</option>
                <option value="serving">serving</option>
            </select>
        </label>

        <div class="full-width actions">
            <button class="button button-secondary" id="use-whole-package" type="button" disabled>Use whole package</button>
        </div>

        <label class="full-width">
            Original description
            <input name="original_description" maxlength="255">
        </label>

        <label class="full-width">
            Notes
            <input name="notes" maxlength="255">
        </label>

        <input type="hidden" name="position" value="<?= e(count($nutrition['ingredients']) + 1) ?>">

        <div class="full-width actions">
            <button class="button" type="submit">Add ingredient</button>
            <a class="button button-secondary save-before-leave" href="/products/create" data-target="/products/create">Create product</a>
            <a class="button button-secondary save-before-leave" href="/products/import" data-target="/products/import">Import AH product</a>
        </div>
    </form>
</section>

<dialog id="save-progress-dialog">
    <form method="dialog" class="dialog-card">
        <h2>Save current ingredient?</h2>
        <p>You have entered ingredient details. Save them before leaving this page?</p>
        <div class="actions">
            <button class="button" value="save">Save and continue</button>
            <button class="button button-secondary" value="leave">Continue without saving</button>
            <button class="button button-secondary" value="cancel">Cancel</button>
        </div>
    </form>
</dialog>
