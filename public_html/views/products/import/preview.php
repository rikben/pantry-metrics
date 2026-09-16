<?php
// /public_html/views/products/import/preview.php

declare(strict_types=1);
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">AH product import</p>
        <h1>Review product</h1>
    </div>
</div>

<?php if ($existing): ?>
    <div class="alert"><strong>Existing product</strong><p>Saving will update the existing AH product and restore it if archived.</p></div>
<?php endif; ?>

<form class="card form-grid" method="post" action="/products/import/store">
    <?= csrf_field() ?>
    <input type="hidden" name="preview_token" value="<?= e($previewToken) ?>">

    <label>
        Product name
        <input name="name" value="<?= e($product->name) ?>" required>
    </label>

    <label>
        Brand
        <input name="brand" value="<?= e($product->brand) ?>">
    </label>

    <div class="full-width import-meta">
        <strong>AH ID:</strong> <?= e($product->sourceIdentifier) ?>
        <strong>Reference:</strong> <?= e($product->referenceAmount) ?> <?= e($product->referenceUnit) ?>
    </div>

    <label>
        Package amount
        <input type="number" name="package_amount" value="<?= e($product->packageAmount) ?>" min="0" step="0.001">
    </label>

    <label>
        Package unit
        <select name="package_unit">
            <option value="">Unknown</option>
            <?php foreach (['g', 'ml', 'serving'] as $unit): ?>
                <option value="<?= e($unit) ?>" <?= $product->packageUnit === $unit ? 'selected' : '' ?>><?= e($unit) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label class="full-width">
        Package description
        <input name="package_description" value="<?= e($product->packageDescription) ?>" maxlength="100">
    </label>

    <?php
    $fields = [
        'energy_kj' => ['Energy (kJ)', $product->energyKj],
        'energy_kcal' => ['Energy (kcal)', $product->energyKcal],
        'fat_g' => ['Fat (g)', $product->fatG],
        'saturated_fat_g' => ['Saturated fat (g)', $product->saturatedFatG],
        'carbohydrates_g' => ['Carbohydrates (g)', $product->carbohydratesG],
        'sugars_g' => ['Sugars (g)', $product->sugarsG],
        'fiber_g' => ['Fiber (g)', $product->fiberG],
        'protein_g' => ['Protein (g)', $product->proteinG],
        'salt_g' => ['Salt (g)', $product->saltG],
    ];
    ?>
    <?php foreach ($fields as $field => [$label, $value]): ?>
        <label>
            <?= e($label) ?>
            <input type="number" name="<?= e($field) ?>" value="<?= e($value) ?>" min="0" step="0.001">
        </label>
    <?php endforeach; ?>

    <div class="full-width actions">
        <button class="button" type="submit">Save product</button>
        <a class="button button-secondary" href="/products/import">Start over</a>
    </div>
</form>
