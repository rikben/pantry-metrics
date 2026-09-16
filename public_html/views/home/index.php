<?php
// /public_html/views/home/index.php

declare(strict_types=1);
?>
<section class="hero">
    <div>
        <p class="eyebrow">Recipe nutrition workspace</p>
        <h1>Welcome, <?= e($user['display_name']) ?></h1>
        <p>Build reusable products, customize recipes, and calculate nutrition per serving.</p>
    </div>
    <div class="actions">
        <a class="button" href="/recipes/create">Create recipe</a>
        <a class="button button-secondary" href="/products/import">Import AH product</a>
        <a class="button button-secondary" href="/products/create">Add product manually</a>
    </div>
</section>

<section class="stats">
    <article class="card">
        <span class="stat-value"><?= e($recipeCount) ?></span>
        <span class="stat-label">Recipes</span>
    </article>
    <article class="card">
        <span class="stat-value"><?= e($productCount) ?></span>
        <span class="stat-label">Products</span>
    </article>
</section>

<section>
    <div class="section-heading">
        <h2>Recent recipes</h2>
        <a href="/recipes">View all</a>
    </div>

    <?php if ($recentRecipes === []): ?>
        <div class="empty-state">No recipes yet. Create your first recipe to begin.</div>
    <?php else: ?>
        <div class="card-list">
            <?php foreach ($recentRecipes as $recipe): ?>
                <a class="card card-link" href="/recipes/<?= e($recipe['id']) ?>">
                    <strong><?= e($recipe['name']) ?></strong>
                    <span><?= e($recipe['servings']) ?> servings · <?= e($recipe['ingredient_count']) ?> ingredients</span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
