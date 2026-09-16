<?php
// /public_html/views/layouts/app.php

declare(strict_types=1);

$app = config('app');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(\App\Core\Csrf::token()) ?>">
    <title><?= e($title ?? $app['name']) ?> · <?= e($app['name']) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/shopping-list-patch.css">
    <script src="/assets/js/app.js" defer></script>
    <link rel="stylesheet" href="/assets/css/recipe-import-workflow.css">
    <link rel="stylesheet" href="/assets/css/recipe-import-fixes.css">
    <link rel="stylesheet" href="/assets/css/source-concept-fix.css">
    <link rel="stylesheet" href="/assets/css/recipe-page-content-fix.css">
    <link rel="stylesheet" href="/assets/css/source-unit-conversion.css">
    <link rel="stylesheet" href="/assets/css/product-combobox.css">
    <link rel="stylesheet" href="/assets/css/nutrition-detail.css">
    <link rel="stylesheet" href="/assets/css/recipe-categories.css">
    <link rel="stylesheet" href="/assets/css/recipe-quick-actions.css">
    <link rel="stylesheet" href="/assets/css/modal.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
    <script src="/assets/js/product-combobox.js" defer></script>
    <script src="/assets/js/modal.js" defer></script>
    <script src="/assets/js/product-form-fields.js" defer></script>
    <script src="/assets/js/product-form-modal.js" defer></script>
    <script src="/assets/js/product-import-modal.js" defer></script>
    <script src="/assets/js/products-page.js" defer></script>
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="/">Pantry Metrics</a>
        <nav aria-label="Main navigation">
            <a href="/recipes">Recipes</a>
            <a href="/products">Products</a>
                    <?php
            $layoutAuth = \App\Core\Container::instance()
                ->get(\App\Auth\AuthServiceInterface::class);
            ?>
            <?php if ($layoutAuth->check()): ?>
                <?php $layoutUser = $layoutAuth->user(); ?>
                <span class="auth-user">
                    <strong>
                        <?= e(
                            $layoutUser['display_name']
                            ?: $layoutUser['email']
                        ) ?>
                    </strong>

                    <form
                        class="auth-logout-form"
                        method="post"
                        action="/auth/logout"
                    >
                        <?= csrf_field() ?>
                        <button
                            class="auth-logout-button"
                            type="submit"
                        >
                            Sign out
                        </button>
                    </form>
                </span>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
    <?= $content ?>
</main>
<footer class="container site-footer">
    <p>Nutrition calculations are estimates. Always verify source labels when accuracy matters.</p>
</footer>

<div class="modal-overlay is-hidden" id="app-modal" aria-hidden="true">
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="app-modal-title">
        <div class="modal-panel-header">
            <h2 id="app-modal-title"></h2>
            <button class="modal-close" type="button" id="app-modal-close" aria-label="Close dialog">&times;</button>
        </div>
        <div class="modal-panel-body" id="app-modal-body"></div>
    </div>
</div>

<template id="product-form-template">
    <form class="form-grid" novalidate>
        <div class="full-width ajax-message is-hidden" data-role="message" role="status"></div>

        <div class="full-width image-preview is-hidden" data-role="image-preview">
            <img data-role="image-preview-img" src="" alt="">
        </div>

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
            <input type="url" name="source_url">
        </label>

        <label class="checkbox-label full-width is-hidden" data-role="refresh-image-row">
            <input type="checkbox" name="refresh_image" value="1">
            Download the source image again
        </label>

        <label>
            Package amount
            <input type="number" name="package_amount" min="0" step="0.001">
        </label>

        <label>
            Package unit
            <select name="package_unit">
                <option value="">Unknown</option>
                <option value="g">g</option>
                <option value="ml">ml</option>
                <option value="serving">serving</option>
            </select>
        </label>

        <label class="full-width">
            Package description
            <input name="package_description" maxlength="100">
        </label>

        <label>
            Nutrition reference amount
            <input type="number" name="reference_amount" min="0.001" step="0.001" value="100" required>
        </label>

        <label>
            Nutrition reference unit
            <select name="reference_unit">
                <option value="g">g</option>
                <option value="ml">ml</option>
                <option value="serving">serving</option>
            </select>
        </label>

        <label>Energy (kJ) <input type="number" name="energy_kj" min="0" step="0.001" value="0"></label>
        <label>Energy (kcal) <input type="number" name="energy_kcal" min="0" step="0.001" value="0"></label>
        <label>Fat (g) <input type="number" name="fat_g" min="0" step="0.001" value="0"></label>
        <label>Saturated fat (g) <input type="number" name="saturated_fat_g" min="0" step="0.001" value="0"></label>
        <label>Carbohydrates (g) <input type="number" name="carbohydrates_g" min="0" step="0.001" value="0"></label>
        <label>Sugars (g) <input type="number" name="sugars_g" min="0" step="0.001" value="0"></label>
        <label>Fiber (g) <input type="number" name="fiber_g" min="0" step="0.001" value="0"></label>
        <label>Protein (g) <input type="number" name="protein_g" min="0" step="0.001" value="0"></label>
        <label>Salt (g) <input type="number" name="salt_g" min="0" step="0.001" value="0"></label>

        <div class="full-width product-conversions-manager is-hidden" data-role="conversions-section">
            <p class="product-conversions-heading">Saved culinary-unit conversions</p>
            <ul class="conversions-list" data-role="conversions-list"></ul>
            <div class="conversions-add-row">
                <span>1</span>
                <select data-role="conversion-unit" aria-label="Unit to convert from">
                    <option value="tbsp">tbsp</option>
                    <option value="tsp">tsp</option>
                    <option value="kg">kg</option>
                    <option value="mg">mg</option>
                    <option value="l">l</option>
                    <option value="cl">cl</option>
                    <option value="dl">dl</option>
                </select>
                <span>=</span>
                <input type="number" data-role="conversion-amount" min="0.001" step="0.001" placeholder="e.g. 15" aria-label="Equivalent amount">
                <span data-role="conversion-reference-unit-label"></span>
                <button class="button button-secondary" type="button" data-role="conversion-add">Save conversion</button>
            </div>
        </div>

        <div class="full-width actions">
            <button class="button" type="submit" data-role="submit">Save product</button>
            <button class="button button-secondary" type="button" data-role="cancel">Cancel</button>
        </div>
    </form>
</template>

<template id="product-import-url-template">
    <form class="form-grid">
        <div class="full-width ajax-message is-hidden" data-role="message" role="status"></div>

        <label class="full-width">
            AH product link
            <input type="url" name="url" required placeholder="https://www.ah.nl/producten/product/...">
        </label>

        <div class="full-width actions">
            <button class="button" type="submit" data-role="submit">Preview product</button>
            <button class="button button-secondary" type="button" data-role="cancel">Cancel</button>
        </div>
    </form>
</template>

    <script defer src="/assets/js/recipe-import-workflow.js"></script>
    <script defer src="/assets/js/source-return-flow.js"></script>
</body>
</html>
