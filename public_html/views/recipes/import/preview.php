<?php
// /public_html/views/recipes/import/preview.php

declare(strict_types=1);
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">Allerhande import</p>
        <h1>Review imported recipe</h1>
        <p>
            You can correct every imported line before
            creating the recipe.
        </p>
    </div>
</div>

<form
    class="card form-grid"
    method="post"
    action="/recipes/import/store"
>
    <?= csrf_field() ?>
    <input
        type="hidden"
        name="preview_token"
        value="<?= e($previewToken) ?>"
    >

    <label class="full-width">
        Recipe name
        <input
            name="name"
            value="<?= e($recipe->name) ?>"
            required
        >
    </label>

    <label>
        Servings
        <input
            type="number"
            name="servings"
            value="<?= e($recipe->servings) ?>"
            min="0.01"
            step="0.01"
            required
        >
    </label>

    <label class="full-width">
        Description
        <textarea
            name="description"
            rows="4"
        ><?= e($recipe->description) ?></textarea>
    </label>

    <fieldset class="full-width import-fieldset">
        <legend>Ingredients</legend>

        <div
            class="import-repeatable-list"
            data-repeatable-list="ingredients"
        >
            <?php foreach (
                $recipe->ingredients as $ingredient
            ): ?>
                <div class="import-repeatable-row">
                    <input
                        name="ingredients[]"
                        value="<?= e($ingredient) ?>"
                        required
                    >
                    <button
                        class="icon-button icon-button-danger"
                        type="button"
                        data-remove-repeatable
                        aria-label="Remove ingredient"
                    >
                        ×
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <button
            class="button button-secondary"
            type="button"
            data-add-repeatable="ingredients"
        >
            Add ingredient line
        </button>
    </fieldset>

    <fieldset class="full-width import-fieldset">
        <legend>Preparation steps</legend>

        <div
            class="import-repeatable-list"
            data-repeatable-list="instructions"
        >
            <?php foreach (
                $recipe->instructions as $index => $step
            ): ?>
                <div class="import-repeatable-row">
                    <span class="step-number">
                        <?= e($index + 1) ?>
                    </span>
                    <textarea
                        name="instructions[]"
                        rows="3"
                        required
                    ><?= e($step) ?></textarea>
                    <button
                        class="icon-button icon-button-danger"
                        type="button"
                        data-remove-repeatable
                        aria-label="Remove preparation step"
                    >
                        ×
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <button
            class="button button-secondary"
            type="button"
            data-add-repeatable="instructions"
        >
            Add preparation step
        </button>
    </fieldset>

    <div class="full-width actions">
        <button class="button" type="submit">
            Create recipe and start linking
        </button>
        <a
            class="button button-secondary"
            href="/recipes/import"
        >
            Start over
        </a>
    </div>
</form>
