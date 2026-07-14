<?php
// /public_html/views/recipes/show.php

declare(strict_types=1);

$perServing = $nutrition['per_serving'];

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

<?php
$sourceIngredients = $sourceIngredients ?? [];
require __DIR__ . '/_source_ingredients.php';
?>
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
    'selectedSourceIngredientId' => ($selectedSourceIngredientId ?? 0),
    'selectedProductId' => ($selectedProductId ?? 0),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
</script>
