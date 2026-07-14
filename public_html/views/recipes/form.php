<?php
// /public_html/views/recipes/form.php

declare(strict_types=1);

$value = static fn (string $field, string $default = ''): string =>
e($recipe[$field] ?? $default);
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">Saved combinations</p>
        <h1><?= e($title) ?></h1>
    </div>
</div>

<form class="card form-grid" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>

    <?php if (!empty($recipe['image_path'])): ?>
        <div class="full-width image-preview">
            <img src="<?= e($recipe['image_path']) ?>" alt="">
        </div>
    <?php endif; ?>

    <label>
        Recipe name
        <input name="name" value="<?= $value('name') ?>" required maxlength="191">
    </label>

    <label>
        Servings
        <input type="number" name="servings" value="<?= $value('servings', '4') ?>" min="0.01" step="0.01" required>
    </label>

    <label class="full-width">
        Source URL
        <input type="url" name="source_url" value="<?= $value('source_url') ?>">
        <small>When possible, the page's social preview image will be downloaded locally.</small>
    </label>

    <?php if ($recipe): ?>
        <label class="checkbox-label full-width">
            <input type="checkbox" name="refresh_image" value="1">
            Download the source image again
        </label>
    <?php endif; ?>

    <label class="full-width">
        Description
        <textarea name="description" rows="4"><?= $value('description') ?></textarea>
    </label>

    <div class="full-width actions">
        <button class="button" type="submit">Save recipe</button>
        <a class="button button-secondary" href="<?= $recipe ? '/recipes/' . e($recipe['id']) : '/recipes' ?>">Cancel</a>
    </div>
</form>
