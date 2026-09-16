<?php
// /public_html/views/recipes/show.php

declare(strict_types=1);

$perServing = $nutrition['per_serving'];
$per100g = $nutrition['per_100g'];

/*
 * Build one AH shopping row per product. If a product occurs more than once,
 * its ingredient amounts are combined before calculating package quantity.
 */
$shoppingProducts = [];

foreach ($nutrition['ingredients'] as $ingredient) {
    $sourceIdentifier = (string) ($ingredient['source_identifier'] ?? '');

    if (!preg_match('/^wi(\d+)$/', $sourceIdentifier, $identifierMatch)) {
        continue;
    }

    $productId = (int) $ingredient['product_id'];

    if (!isset($shoppingProducts[$productId])) {
        $shoppingProducts[$productId] = [
                'product_id' => $productId,
                'ah_id' => $identifierMatch[1],
                'name' => $ingredient['product_name'],
                'brand' => $ingredient['brand'],
                'image_path' => $ingredient['image_path'],
                'package_description' => $ingredient['package_description'],
                'package_amount' => (float) ($ingredient['package_amount'] ?? 0),
                'package_unit' => (string) ($ingredient['package_unit'] ?? ''),
                'ingredient_amount' => 0.0,
                'ingredient_unit' => (string) $ingredient['unit'],
                'units_match' => true,
        ];
    }

    $row = &$shoppingProducts[$productId];

    if ($row['ingredient_unit'] !== (string) $ingredient['unit']) {
        $row['units_match'] = false;
    }

    $row['ingredient_amount'] += (float) $ingredient['amount'];
    unset($row);
}

foreach ($shoppingProducts as &$shoppingProduct) {
    $canCalculate =
            $shoppingProduct['units_match']
            && $shoppingProduct['package_amount'] > 0
            && $shoppingProduct['package_unit'] === $shoppingProduct['ingredient_unit'];

    $shoppingProduct['default_quantity'] = $canCalculate
            ? max(1, (int) ceil(
                    $shoppingProduct['ingredient_amount'] /
                    $shoppingProduct['package_amount']
            ))
            : 1;

    $shoppingProduct['calculation_note'] = $canCalculate
            ? sprintf(
                    '%s %s needed; %s per package',
                    rtrim(rtrim(number_format($shoppingProduct['ingredient_amount'], 3, '.', ''), '0'), '.'),
                    $shoppingProduct['ingredient_unit'],
                    $shoppingProduct['package_description']
                            ?: rtrim(rtrim(number_format($shoppingProduct['package_amount'], 3, '.', ''), '0'), '.')
                            . ' ' . $shoppingProduct['package_unit']
            )
            : 'Package quantity could not be calculated; defaulted to 1.';
}
unset($shoppingProduct);
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

<?php if (!empty($recipeCategoryIds)): ?>
    <?php
    $recipeCategoryLookup = array_column($categories ?? [], null, 'id');
    ?>
    <div class="category-badges">
        <?php foreach ($recipeCategoryIds as $categoryId): ?>
            <?php if (isset($recipeCategoryLookup[$categoryId])): ?>
                <span class="category-badge"><?= e($recipeCategoryLookup[$categoryId]['name']) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (
    !empty($recipe['description'])
    || !empty($recipe['instructions'])
): ?>
<section class="recipe-copy card" id="recipe-description">
    <?php if (!empty($recipe['description'])): ?>
        <div>
            <p class="eyebrow">Description</p>
            <p class="recipe-description-text">
                <?= nl2br(e($recipe['description'])) ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (!empty($recipe['instructions'])): ?>
        <div class="recipe-instructions">
            <p class="eyebrow">Preparation</p>
            <div class="recipe-instructions-text">
                <?= nl2br(e($recipe['instructions'])) ?>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
<section class="stats nutrition-stats" id="nutrition-stats">
    <article class="card"><span class="stat-value" data-stat="energy_kcal"><?= e(round($perServing['energy_kcal'])) ?></span><span class="stat-label">kcal / serving</span></article>
    <article class="card"><span class="stat-value" data-stat="protein_g"><?= e(round($perServing['protein_g'], 1)) ?> g</span><span class="stat-label">Protein</span></article>
    <article class="card"><span class="stat-value" data-stat="carbohydrates_g"><?= e(round($perServing['carbohydrates_g'], 1)) ?> g</span><span class="stat-label">Carbohydrates</span></article>
    <article class="card"><span class="stat-value" data-stat="fat_g"><?= e(round($perServing['fat_g'], 1)) ?> g</span><span class="stat-label">Fat</span></article>
    <article class="card is-hidden" data-stat-extra><span class="stat-value" data-stat="saturated_fat_g"><?= e(round($perServing['saturated_fat_g'], 1)) ?> g</span><span class="stat-label">Saturated fat</span></article>
    <article class="card is-hidden" data-stat-extra><span class="stat-value" data-stat="sugars_g"><?= e(round($perServing['sugars_g'], 1)) ?> g</span><span class="stat-label">Sugars</span></article>
    <article class="card is-hidden" data-stat-extra><span class="stat-value" data-stat="fiber_g"><?= e(round($perServing['fiber_g'], 1)) ?> g</span><span class="stat-label">Fiber</span></article>
    <article class="card is-hidden" data-stat-extra><span class="stat-value" data-stat="salt_g"><?= e(round($perServing['salt_g'], 2)) ?> g</span><span class="stat-label">Salt</span></article>
    <article class="card is-hidden" data-stat-extra><span class="stat-value" data-stat="energy_kj"><?= e(round($perServing['energy_kj'])) ?></span><span class="stat-label">kJ / serving</span></article>
</section>

<div class="nutrition-stats-toggle">
    <div class="nutrition-mode-toggle" id="nutrition-mode-toggle" role="group" aria-label="Nutrition display mode">
        <button class="nutrition-mode-button is-active" type="button" data-mode="serving">Per serving</button>
        <button
                class="nutrition-mode-button"
                type="button"
                data-mode="100g"
                <?= $per100g === null ? 'disabled' : '' ?>
                title="<?= $per100g === null ? 'Add ingredients measured in g or ml to enable this view' : '' ?>"
        >
            Per 100g
        </button>
    </div>
    <button class="link-button" type="button" id="nutrition-stats-expand" aria-expanded="false" aria-controls="nutrition-stats">
        Show all metrics
    </button>
</div>
<p class="nutrition-mode-note is-hidden" id="nutrition-mode-note">
    Approximate: this recipe includes ingredients measured in whole servings, which have no known weight.
</p>

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
                            <img class="ingredient-image" src="<?= e($ingredient['image_path']) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="product-image-placeholder product-image-placeholder-small" aria-label="No image">
                                <svg aria-hidden="true" viewBox="0 0 24 24">
                                    <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5v-13Zm2 11.25 3.4-3.4a1 1 0 0 1 1.42 0l1.43 1.43 2.65-2.65a1 1 0 0 1 1.42 0L18 14.31V6H6v10.75ZM8.5 10A1.5 1.5 0 1 0 8.5 7a1.5 1.5 0 0 0 0 3Z"/>
                                </svg>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= e($ingredient['product_name']) ?></strong>
                        <?php if ($ingredient['brand']): ?><small><?= e($ingredient['brand']) ?></small><?php endif; ?>
                    </td>
                    <td><?= e($ingredient['package_description'] ?: 'Unknown') ?></td>
                    <td>
                        <div class="inline-ingredient-fields">
                            <input class="inline-amount" type="number" value="<?= e($ingredient['amount']) ?>" min="0.001" step="0.001" aria-label="Ingredient amount">
                            <select class="inline-unit" aria-label="Ingredient unit">
                                <?php foreach (['g', 'ml', 'serving'] as $unit): ?>
                                    <option value="<?= e($unit) ?>" <?= $ingredient['unit'] === $unit ? 'selected' : '' ?>><?= e($unit) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </td>
                    <td data-cell="kcal"><?= e(round($ingredient['calculated_energy_kcal'], 1)) ?></td>
                    <td data-cell="protein"><?= e(round($ingredient['calculated_protein_g'], 1)) ?> g</td>
                    <td>
                        <div class="icon-actions">
                            <button
                                    class="icon-button ingredient-save"
                                    type="button"
                                    aria-label="Save ingredient changes"
                                    title="Save changes"
                            >
                                <svg aria-hidden="true" viewBox="0 0 24 24">
                                    <path d="M5 3h12l2 2v16H5V3Zm2 2v5h8V5H7Zm1 9v5h8v-5H8Z"/>
                                </svg>
                            </button>
                            <button
                                    class="icon-button icon-button-danger ingredient-delete"
                                    type="button"
                                    aria-label="Remove ingredient"
                                    title="Remove ingredient"
                            >
                                <svg aria-hidden="true" viewBox="0 0 24 24">
                                    <path d="M7 4V2h10v2h5v2h-2l-1 15H5L4 6H2V4h5Zm2 4v9h2V8H9Zm4 0v9h2V8h-2Z"/>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section id="ah-shopping-list">
    <div class="section-heading">
        <div>
            <h2>Add to AH basket</h2>
            <p class="section-copy">
                Select the AH products you need. Quantities default to the number of packages needed for this recipe.
            </p>
        </div>
    </div>

    <?php if ($shoppingProducts === []): ?>
        <div class="empty-state">
            This recipe has no ingredients linked to an AH product ID.
        </div>
    <?php else: ?>
        <div class="card shopping-card">
            <div class="shopping-toolbar">
                <label class="checkbox-label">
                    <input id="shopping-select-all" type="checkbox" checked>
                    Select all
                </label>
                <span id="shopping-selection-summary" aria-live="polite"></span>
            </div>

            <div class="shopping-list" id="ah-shopping-products">
                <?php foreach ($shoppingProducts as $shoppingProduct): ?>
                    <article
                            class="shopping-product"
                            data-ah-id="<?= e($shoppingProduct['ah_id']) ?>"
                    >
                        <label class="shopping-product-select">
                            <input
                                    class="shopping-product-checkbox"
                                    type="checkbox"
                                    checked
                            >
                            <span class="visually-hidden">
                                Add <?= e($shoppingProduct['name']) ?>
                            </span>
                        </label>

                        <?php if ($shoppingProduct['image_path']): ?>
                            <img
                                    class="shopping-product-image"
                                    src="<?= e($shoppingProduct['image_path']) ?>"
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

                        <div class="shopping-product-details">
                            <strong><?= e($shoppingProduct['name']) ?></strong>
                            <small>
                                <?= e($shoppingProduct['brand'] ?: 'AH product') ?>
                                · AH <?= e($shoppingProduct['ah_id']) ?>
                            </small>
                            <small><?= e($shoppingProduct['calculation_note']) ?></small>
                        </div>

                        <label class="shopping-quantity-label">
                            Packages
                            <input
                                    class="shopping-product-quantity"
                                    type="number"
                                    min="1"
                                    max="99"
                                    step="1"
                                    value="<?= e($shoppingProduct['default_quantity']) ?>"
                                    inputmode="numeric"
                            >
                        </label>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="shopping-actions">
                <button class="button" id="open-ah-shopping-list" type="button">
                    Add selected products to AH
                </button>
                <p class="shopping-help">
                    AH opens in a new tab and may ask you to confirm adding the products.
                </p>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php
$sourceIngredients = $sourceIngredients ?? [];
require __DIR__ . '/_source_ingredients.php';
?>


<section id="add-ingredient">
    <div class="section-heading"><h2>Add ingredient</h2></div>

    <div class="ajax-message is-hidden" id="ingredient-message" role="status"></div>

    <form class="card form-grid" id="ingredient-form" method="post" action="/recipes/<?= e($recipe['id']) ?>/ingredients">
        <?= csrf_field() ?>

        <label class="full-width">
            Product
            <select name="product_id" id="product-select" data-combobox="product" data-combobox-create="1" required>
                <option value="">Search a product…</option>
                <?php foreach ($products as $product): ?>
                    <?php
                    $searchText = trim(implode(' ', array_filter([
                            $product['name'],
                            $product['brand'],
                            $product['source_identifier'],
                    ])));
                    $metaText = trim(implode(' · ', array_filter([
                            $product['brand'],
                            $product['source_identifier'],
                            $product['package_description'],
                    ])));
                    ?>
                    <option
                            value="<?= e($product['id']) ?>"
                            data-search="<?= e(mb_strtolower($searchText)) ?>"
                            data-name="<?= e($product['name']) ?>"
                            data-meta="<?= e($metaText) ?>"
                            data-image="<?= e($product['image_path'] ?? '') ?>"
                            data-reference-unit="<?= e($product['reference_unit']) ?>"
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

        <div class="full-width inline-product-create is-hidden" id="inline-product-create">
            <div class="inline-product-create-header">
                <strong>Create a new product</strong>
                <button class="link-button" type="button" id="inline-product-create-cancel">Cancel</button>
            </div>

            <div class="form-grid">
                <label class="full-width">
                    Product name
                    <input type="text" id="new-product-name" maxlength="191">
                </label>

                <label>
                    Brand
                    <input type="text" id="new-product-brand" maxlength="191">
                </label>

                <label>
                    Reference amount
                    <input type="number" id="new-product-reference-amount" value="100" min="0.001" step="0.001">
                </label>

                <label>
                    Reference unit
                    <select id="new-product-reference-unit">
                        <option value="g">g</option>
                        <option value="ml">ml</option>
                        <option value="serving">serving</option>
                    </select>
                </label>

                <?php
                $newProductFields = [
                        'energy_kj' => 'Energy (kJ)',
                        'energy_kcal' => 'Energy (kcal)',
                        'fat_g' => 'Fat (g)',
                        'saturated_fat_g' => 'Saturated fat (g)',
                        'carbohydrates_g' => 'Carbohydrates (g)',
                        'sugars_g' => 'Sugars (g)',
                        'fiber_g' => 'Fiber (g)',
                        'protein_g' => 'Protein (g)',
                        'salt_g' => 'Salt (g)',
                ];
                ?>
                <?php foreach ($newProductFields as $field => $label): ?>
                    <label>
                        <?= e($label) ?>
                        <input type="number" id="new-product-<?= e(str_replace('_', '-', $field)) ?>" value="0" min="0" step="0.001">
                    </label>
                <?php endforeach; ?>

                <p class="full-width ajax-message is-hidden" id="inline-product-create-message" role="status"></p>

                <div class="full-width actions">
                    <button class="button" type="button" id="inline-product-create-save">Create and use this product</button>
                </div>
            </div>
        </div>

        <label>
            Amount
            <input type="number" id="ingredient-amount" name="amount" min="0.001" step="0.001" required>
        </label>

        <label>
            Unit
            <select id="ingredient-unit" name="unit">
                <option value="g">g</option>
                <option value="kg">kg</option>
                <option value="mg">mg</option>
                <option value="ml">ml</option>
                <option value="l">l</option>
                <option value="cl">cl</option>
                <option value="dl">dl</option>
                <option value="tbsp">tbsp</option>
                <option value="tsp">tsp</option>
                <option value="serving">serving</option>
            </select>
        </label>

        <div class="full-width actions">
            <button class="button button-secondary" id="use-whole-package" type="button" disabled>Use whole package</button>
        </div>

        <div class="full-width ingredient-conversion is-hidden" id="ingredient-conversion">
            <p>
                <span id="ingredient-conversion-note">Enter the equivalent amount in the product's nutrition unit.</span>
            </p>
            <div class="source-conversion-fields">
                <span class="conversion-source-label" id="ingredient-conversion-label"></span>
                <span>=</span>
                <input class="converted-amount" type="number" name="converted_amount" id="ingredient-converted-amount" min="0.001" step="0.001" placeholder="e.g. 15">
                <input class="converted-unit" type="text" name="converted_unit" id="ingredient-converted-unit" readonly>
            </div>
            <label class="checkbox-label">
                <input type="hidden" name="remember_conversion" value="0">
                <input type="checkbox" name="remember_conversion" value="1" id="ingredient-remember-conversion" checked>
                Remember this conversion for future recipes
            </label>
        </div>

        <div class="full-width product-conversions-manager is-hidden" id="product-conversions-manager">
            <p class="product-conversions-heading">
                Saved culinary-unit conversions <span id="product-conversions-product-name"></span>
            </p>
            <ul class="conversions-list" id="product-conversions-list"></ul>
            <div class="conversions-add-row">
                <span>1</span>
                <select id="product-conversions-unit" aria-label="Unit to convert from">
                    <option value="tbsp">tbsp</option>
                    <option value="tsp">tsp</option>
                    <option value="kg">kg</option>
                    <option value="mg">mg</option>
                    <option value="l">l</option>
                    <option value="cl">cl</option>
                    <option value="dl">dl</option>
                </select>
                <span>=</span>
                <input type="number" id="product-conversions-amount" min="0.001" step="0.001" placeholder="e.g. 15" aria-label="Equivalent amount">
                <span id="product-conversions-reference-unit-label"></span>
                <button class="button button-secondary" type="button" id="product-conversions-add">Save conversion</button>
            </div>
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
    'selectedSourceIngredientId' => ($selectedSourceIngredientId ?? 0),
    'selectedProductId' => ($selectedProductId ?? 0),
    'productConversions' => $productConversions ?? [],
    'nutrition' => [
        'per_serving' => $perServing,
        'per_100g' => $per100g,
        'total_weight_g' => $nutrition['total_weight_g'],
        'weight_is_approximate' => $nutrition['weight_is_approximate'],
    ],
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
</script>
