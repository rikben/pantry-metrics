<?php
// /public_html/views/products/form.php

declare(strict_types=1);

$value = static fn (string $field, string $default = ''): string =>
e($product[$field] ?? $default);
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">Reference library</p>
        <h1><?= e($title) ?></h1>
    </div>
</div>

<form class="card form-grid" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="return_to" value="<?= e($returnTo ?? '') ?>">
    <input type="hidden" name="source_ingredient" value="<?= e($sourceIngredientId ?? 0) ?>">

    <?php if (!empty($product['image_path'])): ?>
        <div class="full-width image-preview">
            <img src="<?= e($product['image_path']) ?>" alt="">
        </div>
    <?php endif; ?>

    <label>
        Product name
        <input name="name" value="<?= $value('name') ?>" required maxlength="191">
    </label>

    <label>
        Brand
        <input name="brand" value="<?= $value('brand') ?>" maxlength="191">
    </label>

    <label class="full-width">
        Source URL
        <input type="url" name="source_url" value="<?= $value('source_url') ?>">
    </label>

    <?php if ($product): ?>
        <label class="checkbox-label full-width">
            <input type="checkbox" name="refresh_image" value="1">
            Download the source image again
        </label>
    <?php endif; ?>

    <label>
        Package amount
        <input type="number" name="package_amount" value="<?= $value('package_amount') ?>" min="0" step="0.001">
    </label>

    <label>
        Package unit
        <select name="package_unit">
            <option value="">Unknown</option>
            <?php foreach (['g', 'ml', 'serving'] as $unit): ?>
                <option value="<?= e($unit) ?>" <?= ($product['package_unit'] ?? '') === $unit ? 'selected' : '' ?>>
                    <?= e($unit) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label class="full-width">
        Package description
        <input name="package_description" value="<?= $value('package_description') ?>" maxlength="100">
    </label>

    <label>
        Nutrition reference amount
        <input type="number" name="reference_amount" value="<?= $value('reference_amount', '100') ?>" min="0.001" step="0.001" required>
    </label>

    <label>
        Nutrition reference unit
        <select name="reference_unit">
            <?php foreach (['g', 'ml', 'serving'] as $unit): ?>
                <option value="<?= e($unit) ?>" <?= ($product['reference_unit'] ?? 'g') === $unit ? 'selected' : '' ?>>
                    <?= e($unit) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <?php
    $fields = [
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
    <?php foreach ($fields as $field => $label): ?>
        <label>
            <?= e($label) ?>
            <input type="number" name="<?= e($field) ?>" value="<?= $value($field, '0') ?>" min="0" step="0.001">
        </label>
    <?php endforeach; ?>

    <div class="full-width actions">
        <button class="button" type="submit">Save product</button>
        <a class="button button-secondary" href="<?= e(($returnTo ?? '') ?: '/products') ?>">Cancel</a>
    </div>
</form>

<?php if ($product): ?>
    <section id="unit-conversions">
        <div class="section-heading">
            <div>
                <h2>Culinary units</h2>
                <p class="section-copy">
                    Set how much a spoon, cup or other unit weighs for this product once, and
                    every recipe that uses it - including recipes other people add - will reuse
                    the same conversion automatically.
                </p>
            </div>
        </div>

        <div class="card">
            <?php if (empty($conversions)): ?>
                <p class="empty-state" id="conversions-empty">
                    No conversions saved yet for this product.
                </p>
            <?php endif; ?>

            <table class="table-wrap" id="conversions-table" <?= empty($conversions) ? 'style="display:none"' : '' ?>>
                <thead>
                <tr>
                    <th>Unit</th>
                    <th>Equals</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody id="conversions-body">
                <?php foreach (($conversions ?? []) as $conversion): ?>
                    <tr data-unit="<?= e($conversion['unit']) ?>">
                        <td><?= e($conversion['unit']) ?></td>
                        <td>
                            <?= e($conversion['reference_amount']) ?>
                            <?= e($product['reference_unit']) ?>
                        </td>
                        <td>
                            <form
                                    method="post"
                                    action="/products/<?= e($product['id']) ?>/conversions/<?= e($conversion['unit']) ?>/delete"
                            >
                                <?= csrf_field() ?>
                                <button class="link-button danger-link" type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <form class="form-grid conversion-form" method="post" action="/products/<?= e($product['id']) ?>/conversions">
                <?= csrf_field() ?>

                <label>
                    Unit
                    <select name="unit" required>
                        <option value="tbsp">tbsp</option>
                        <option value="tsp">tsp</option>
                        <option value="kg">kg</option>
                        <option value="mg">mg</option>
                        <option value="l">l</option>
                        <option value="cl">cl</option>
                        <option value="dl">dl</option>
                    </select>
                </label>

                <label>
                    Equals (in <?= e($product['reference_unit']) ?>)
                    <input type="number" name="reference_amount" min="0.001" step="0.001" placeholder="e.g. 15" required>
                </label>

                <div class="full-width actions">
                    <button class="button button-secondary" type="submit">Save conversion</button>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>
