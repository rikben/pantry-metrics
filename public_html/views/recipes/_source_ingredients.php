<?php
// /public_html/views/recipes/_source_ingredients.php

declare(strict_types=1);
?>
<?php if ($sourceIngredients !== []): ?>
<section id="source-ingredients">
    <div class="section-heading">
        <div>
            <h2>Link imported ingredients</h2>
            <p class="section-copy">
                Match each imported recipe line to a product.
                Progress is saved immediately.
            </p>
        </div>
        <span
            class="mapping-progress"
            id="mapping-progress"
            aria-live="polite"
        ></span>
    </div>

    <div
        class="source-ingredient-list"
        id="source-ingredient-list"
    >
        <?php foreach (
            $sourceIngredients as $sourceIngredient
        ): ?>
            <article
                class="card source-ingredient-card
                    <?= $sourceIngredient['linked_product_id']
                        ? 'source-ingredient-linked'
                        : '' ?>
                    <?= $sourceIngredient['is_ignored']
                        ? 'source-ingredient-ignored'
                        : '' ?>"
                data-source-ingredient-id="<?= e(
                    $sourceIngredient['id']
                ) ?>"
            >
                <div class="source-ingredient-heading">
                    <span class="source-position">
                        <?= e($sourceIngredient['position']) ?>
                    </span>
                    <div>
                        <strong>
                            <?= e($sourceIngredient['raw_text']) ?>
                        </strong>
                        <?php if (
                            $sourceIngredient['parsed_name']
                            && $sourceIngredient['parsed_name']
                                !== $sourceIngredient['raw_text']
                        ): ?>
                            <small>
                                Parsed as:
                                <?= e(
                                    $sourceIngredient['parsed_name']
                                ) ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <span class="mapping-status">
                        <?php if (
                            $sourceIngredient['linked_product_id']
                        ): ?>
                            Linked
                        <?php elseif (
                            $sourceIngredient['is_ignored']
                        ): ?>
                            Ignored
                        <?php else: ?>
                            Not linked
                        <?php endif; ?>
                    </span>
                </div>

                <div class="source-ingredient-controls">
                    <label>
                        Product
                        <select
                            class="source-product-select"
                            <?= $sourceIngredient['is_ignored']
                                ? 'disabled'
                                : '' ?>
                        >
                            <option value="">
                                Select a product
                            </option>
                            <?php foreach (
                                $products as $product
                            ): ?>
                                <option
                                    value="<?= e($product['id']) ?>"
                                    data-search="<?= e(
                                        mb_strtolower(
                                            trim(
                                                ($product['name'] ?? '')
                                                . ' '
                                                . ($product['brand'] ?? '')
                                                . ' '
                                                . ($product['source_identifier'] ?? '')
                                            )
                                        )
                                    ) ?>"
                                    <?= (int) $sourceIngredient['linked_product_id']
                                        === (int) $product['id']
                                            ? 'selected'
                                            : '' ?>
                                >
                                    <?= e($product['name']) ?>
                                    <?= $product['brand']
                                        ? ' · ' . e($product['brand'])
                                        : '' ?>
                                    <?= $product['source_identifier']
                                        ? ' · ' . e($product['source_identifier'])
                                        : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Amount
                        <input
                            class="source-amount"
                            type="number"
                            min="0.001"
                            step="0.001"
                            value="<?= e(
                                $sourceIngredient['parsed_amount']
                                ?: 1
                            ) ?>"
                            <?= $sourceIngredient['is_ignored']
                                ? 'disabled'
                                : '' ?>
                        >
                    </label>

                    <label>
                        Unit
                        <select
                            class="source-unit"
                            <?= $sourceIngredient['is_ignored']
                                ? 'disabled'
                                : '' ?>
                        >
                            <?php foreach (
                                [
                                    'g', 'kg', 'mg',
                                    'ml', 'l', 'cl', 'dl',
                                    'tbsp', 'tsp', 'serving',
                                ] as $unit
                            ): ?>
                                <option
                                    value="<?= e($unit) ?>"
                                    <?= (
                                        $sourceIngredient['parsed_unit']
                                        ?: 'serving'
                                    ) === $unit
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= e($unit) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="source-ingredient-actions">
                    <?php if (
                        !$sourceIngredient['is_ignored']
                    ): ?>
                        <button
                            class="button source-link-button"
                            type="button"
                        >
                            Save link
                        </button>

                        <a
                            class="button button-secondary"
                            href="/products/create?return_to=<?= rawurlencode(
                                '/recipes/' . $recipe['id']
                            ) ?>&source_ingredient=<?= e(
                                $sourceIngredient['id']
                            ) ?>"
                        >
                            Create product
                        </a>

                        <a
                            class="button button-secondary"
                            href="/products/import?return_to=<?= rawurlencode(
                                '/recipes/' . $recipe['id']
                            ) ?>&source_ingredient=<?= e(
                                $sourceIngredient['id']
                            ) ?>"
                        >
                            Import AH product
                        </a>

                        <button
                            class="link-button source-ignore-button"
                            type="button"
                        >
                            Ignore
                        </button>
                    <?php else: ?>
                        <button
                            class="link-button source-restore-button"
                            type="button"
                        >
                            Restore ingredient
                        </button>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
