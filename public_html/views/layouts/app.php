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
    <link rel="stylesheet" href="/assets/css/auth.css">
    <script src="/assets/js/product-combobox.js" defer></script>
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
    <script defer src="/assets/js/recipe-import-workflow.js"></script>
    <script defer src="/assets/js/source-return-flow.js"></script>
</body>
</html>
