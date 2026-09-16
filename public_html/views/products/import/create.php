<?php
// /public_html/views/products/import/create.php

declare(strict_types=1);
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">AH product import</p>
        <h1>Import AH product</h1>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert"><strong>Import failed</strong><p><?= e($error) ?></p></div>
<?php endif; ?>

<form class="card form-grid" method="post" action="/products/import/preview">
    <?= csrf_field() ?>
    <input type="hidden" name="return_to" value="<?= e($returnTo ?? '') ?>">
    <input type="hidden" name="source_ingredient" value="<?= e($sourceIngredientId ?? 0) ?>">

    <label class="full-width">
        AH product URL
        <input type="url" name="url" value="<?= e($url) ?>" required placeholder="https://www.ah.nl/producten/product/wi...">
    </label>

    <div class="full-width actions">
        <button class="button" type="submit">Import and preview</button>
        <a class="button button-secondary" href="<?= e($returnTo ?: '/products') ?>">Cancel</a>
    </div>
</form>
