<?php
// /public_html/views/recipes/index.php

declare(strict_types=1);
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">Saved combinations</p>
        <h1><?= $archived ? 'Archived recipes' : 'Recipes' ?></h1>
    </div>
    <div class="actions">
        <?php if (!$archived): ?>
            <a class="button" href="/recipes/import">Import AH recipe</a>
            <a class="button button-secondary" href="/recipes/create">Create manually</a>
            <a class="button button-secondary" href="/recipes?archived=1">Archived</a>
        <?php else: ?>
            <a class="button button-secondary" href="/recipes">Active recipes</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($categories)): ?>
    <div class="category-filters">
        <a
                class="category-filter <?= ($selectedCategoryId ?? 0) === 0 ? 'is-active' : '' ?>"
                href="?<?= $archived ? 'archived=1' : '' ?>"
        >
            All
        </a>
        <?php foreach ($categories as $category): ?>
            <a
                    class="category-filter <?= ($selectedCategoryId ?? 0) === (int) $category['id'] ? 'is-active' : '' ?>"
                    href="?<?= $archived ? 'archived=1&' : '' ?>category=<?= e($category['id']) ?>"
            >
                <?= e($category['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($recipes === []): ?>
    <div class="empty-state">No recipes found.</div>
<?php else: ?>
    <div class="card-list">
        <?php foreach ($recipes as $recipe): ?>
            <article class="card card-row">
                <a class="card-main-link" href="/recipes/<?= e($recipe['id']) ?>">
                    <strong><?= e($recipe['name']) ?></strong>
                    <span>
                        <?= e($recipe['servings']) ?> servings ·
                        <?= e($recipe['ingredient_count']) ?> ingredients ·
                        <?= e(round((float) $recipe['total_kcal'] / max((float) $recipe['servings'], 0.01))) ?> kcal/serving
                        <?php if (!empty($recipeCategories[(int) $recipe['id']])): ?>
                            · <?= e(implode(', ', array_column($recipeCategories[(int) $recipe['id']], 'name'))) ?>
                        <?php endif; ?>
                        <?php if ($recipe['owner_user_id'] === null): ?>
                            <span class="shared-badge">Shared</span>
                        <?php endif; ?>
                    </span>
                </a>
                <div class="table-actions">
                    <?php if (!$archived): ?>
                        <a href="/recipes/<?= e($recipe['id']) ?>/edit">Edit</a>
                    <?php endif; ?>
                    <form method="post" action="/recipes/<?= e($recipe['id']) ?>/duplicate">
                        <?= csrf_field() ?>
                        <button class="link-button" type="submit">Duplicate</button>
                    </form>
                    <form method="post" action="/recipes/<?= e($recipe['id']) ?>/<?= $archived ? 'restore' : 'archive' ?>">
                        <?= csrf_field() ?>
                        <button class="link-button" type="submit"><?= $archived ? 'Restore' : 'Archive' ?></button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
