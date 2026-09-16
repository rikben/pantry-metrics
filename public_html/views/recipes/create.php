<?php
// /public_html/views/recipes/create.php

declare(strict_types=1);
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">Saved combinations</p>
        <h1>Create recipe</h1>
    </div>
</div>

<form class="card form-grid" method="post" action="/recipes">
    <?= csrf_field() ?>

    <label>
        Recipe name
        <input name="name" required maxlength="191">
    </label>

    <label>
        Servings
        <input type="number" name="servings" value="4" min="0.01" step="0.01" required>
    </label>

    <label class="full-width">
        Source URL
        <input type="url" name="source_url" placeholder="https://www.ah.nl/allerhande/recept/...">
    </label>

    <label class="full-width">
        Description
        <textarea name="description" rows="4"></textarea>
    </label>

    <div class="full-width actions">
        <button class="button" type="submit">Create recipe</button>
        <a class="button button-secondary" href="/recipes">Cancel</a>
    </div>
</form>
