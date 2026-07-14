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
            <a class="button" href="/recipes/create">Create recipe</a>
            <a class="button button-secondary" href="/recipes?archived=1">Archived</a>
        <?php else: ?>
            <a class="button button-secondary" href="/recipes">Active recipes</a>
        <?php endif; ?>
    </div>
</div>

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
                    </span>
                </a>
                <div class="table-actions">
                    <?php if (!$archived): ?>
                        <a href="/recipes/<?= e($recipe['id']) ?>/edit">Edit</a>
                    <?php endif; ?>
                    <form method="post" action="/recipes/<?= e($recipe['id']) ?>/<?= $archived ? 'restore' : 'archive' ?>">
                        <?= csrf_field() ?>
                        <button class="link-button" type="submit"><?= $archived ? 'Restore' : 'Archive' ?></button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
