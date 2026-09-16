<?php
// /public_html/views/products/create.php

declare(strict_types=1);
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">Reference library</p>
        <h1>Add product</h1>
    </div>
</div>

<form class="card form-grid" method="post" action="/products">
    <?= csrf_field() ?>

    <label>
        Product name
        <input name="name" required maxlength="191">
    </label>

    <label>
        Brand
        <input name="brand" maxlength="191">
    </label>

    <label class="full-width">
        Source URL
        <input type="url" name="source_url" placeholder="https://www.ah.nl/producten/...">
    </label>

    <label>
        Reference amount
        <input type="number" name="reference_amount" value="100" min="0.001" step="0.001" required>
    </label>

    <label>
        Reference unit
        <select name="reference_unit">
            <option value="g">g</option>
            <option value="ml">ml</option>
            <option value="serving">serving</option>
        </select>
    </label>

    <?php
    $nutritionFields = [
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
    <?php foreach ($nutritionFields as $field => $label): ?>
        <label>
            <?= e($label) ?>
            <input type="number" name="<?= e($field) ?>" value="0" min="0" step="0.001">
        </label>
    <?php endforeach; ?>

    <div class="full-width actions">
        <button class="button" type="submit">Save product</button>
        <a class="button button-secondary" href="/products">Cancel</a>
    </div>
</form>
