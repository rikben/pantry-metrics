<?php
// /public_html/views/recipes/import/create.php

declare(strict_types=1);
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">Allerhande import</p>
        <h1>Import AH recipe</h1>
        <p>
            Import the recipe title, servings, ingredients,
            description and preparation steps.
        </p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert">
        <strong>Import failed</strong>
        <p><?= e($error) ?></p>
    </div>
<?php endif; ?>

<form
    class="card form-grid"
    method="post"
    action="/recipes/import/preview"
>
    <?= csrf_field() ?>

    <label class="full-width">
        AH Allerhande recipe URL
        <input
            type="url"
            name="url"
            value="<?= e($url) ?>"
            required
            placeholder="https://www.ah.nl/allerhande/recept/R-R..."
        >
    </label>

    <div class="full-width actions">
        <button class="button" type="submit">
            Import and preview
        </button>
        <a
            class="button button-secondary"
            href="/recipes"
        >
            Cancel
        </a>
    </div>
</form>
