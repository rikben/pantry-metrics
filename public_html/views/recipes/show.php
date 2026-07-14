<?php
// /public_html/views/recipes/show.php

declare(strict_types=1);

$perServing = $nutrition['per_serving'];
?>
<div class="recipe-hero">
    <?php if ($recipe['image_path']): ?>
        <img class="recipe-hero-image" src="<?= e($recipe['image_path']) ?>" alt="">
    <?php endif; ?>

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
</div>

<section class="stats nutrition-stats" id="nutrition-stats">
    <article class="card"><span class="stat-value" data-stat="energy_kcal"><?= e(round($perServing['energy_kcal'])) ?></span><span class="stat-label">kcal / serving</span></article>
    <article class="card"><span class="stat-value" data-stat="protein_g"><?= e(round($perServing['protein_g'], 1)) ?> g</span><span class="stat-label">Protein</span></article>
    <article class="card"><span class="stat-value" data-stat="carbohydrates_g"><?= e(round($perServing['carbohydrates_g'], 1)) ?> g</span><span class="stat-label">Carbohydrates</span></article>
    <article class="card"><span class="stat-value" data-stat="fat_g"><?= e(round($perServing['fat_g'], 1)) ?> g</span><span class="stat-label">Fat</span></article>
</section>

<section>
    <div class="section-heading"><h2>Ingredients</h2></div>

    <div class="empty-state <?= $nutrition['ingredients'] === [] ? '' : 'is-hidden' ?>" id="ingredients-empty">
        Add a product below to start calculating.
    </div>

    <div class="table-wrap <?= $nutrition['ingredients'] === [] ? 'is-hidden' : '' ?>" id="ingredients-table-wrap">
        <table>
            <thead>
            <tr>
                <th>Image</th>
                <th>Product</th>
                <th>Package</th>
                <th>Amount</th>
                <th>kcal</th>
                <th>Protein</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody id="ingredients-body">
            <?php foreach ($nutrition['ingredients'] as $ingredient): ?>
                <tr data-ingredient-id="<?= e($ingredient['id']) ?>">
                    <td>
                        <?php if ($ingredient['image_path']): ?>
                            <img class="ingredient-image" src="<?= e($ingredient['image_path']) ?>" alt="">
                        <?php else: ?>
                            <span class="image-placeholder">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= e($ingredient['product_name']) ?></strong>
                        <?php if ($ingredient['brand']): ?><small><?= e($ingredient['brand']) ?></small><?php endif; ?>
                    </td>
                    <td><?= e($ingredient['package_description'] ?: 'Unknown') ?></td>
                    <td>
                        <div class="inline-ingredient-fields">
                            <input class="inline-amount" type="number" value="<?= e($ingredient['amount']) ?>" min="0.001" step="0.001">
                            <select class="inline-unit">
                                <?php foreach (['g', 'ml', 'serving'] as $unit): ?>
                                    <option value="<?= e($unit) ?>" <?= $ingredient['unit'] === $unit ? 'selected' : '' ?>><?= e($unit) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </td>
                    <td data-cell="kcal"><?= e(round($ingredient['calculated_energy_kcal'], 1)) ?></td>
                    <td data-cell="protein"><?= e(round($ingredient['calculated_protein_g'], 1)) ?> g</td>
                    <td>
                        <div class="table-actions">
                            <button class="link-button ingredient-save" type="button">Save</button>
                            <button class="link-button danger-link ingredient-delete" type="button">Remove</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section id="add-ingredient">
    <div class="section-heading"><h2>Add ingredient</h2></div>

    <div class="ajax-message is-hidden" id="ingredient-message" role="status"></div>

    <form class="card form-grid" id="ingredient-form" method="post" action="/recipes/<?= e($recipe['id']) ?>/ingredients">
        <?= csrf_field() ?>

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
                            <?= $selectedProductId === (int) $product['id'] ? 'selected' : '' ?>
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
            Notes
            <input name="notes" maxlength="255">
        </label>

        <input type="hidden" name="position" value="<?= e(count($nutrition['ingredients']) + 1) ?>">

        <div class="full-width actions">
            <button class="button" type="submit">Add ingredient</button>
            <a class="button button-secondary" href="/products/create?return_to=<?= rawurlencode('/recipes/' . $recipe['id']) ?>">Create product</a>
            <a class="button button-secondary" href="/products/import?return_to=<?= rawurlencode('/recipes/' . $recipe['id']) ?>">Import AH product</a>
        </div>
    </form>
</section>

<script type="application/json" id="recipe-page-config">
<?= json_encode([
            'recipeId' => (int) $recipe['id'],
            'csrfToken' => \App\Core\Csrf::token(),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
</script>
