<?php
declare(strict_types=1);
?>
<?php if ($sourceIngredients !== []): ?>
    <details
            class="source-ingredients-accordion"
            id="source-ingredients"
            <?= ($selectedSourceIngredientId ?? 0) > 0 ? 'open' : '' ?>
    >
        <summary>
        <span>
            <strong>Imported ingredient checklist</strong>
            <small>Link original recipe lines to products.</small>
        </span>
            <span class="mapping-progress" id="mapping-progress"></span>
        </summary>

        <div class="source-ingredients-accordion-content">
            <div class="source-ingredient-list" id="source-ingredient-list">
                <?php foreach ($sourceIngredients as $sourceIngredient): ?>
                    <?php
                    $hasSuggestion = !$sourceIngredient['linked_product_id']
                            && !$sourceIngredient['is_ignored']
                            && !empty($sourceIngredient['suggested_product_id']);
                    ?>
                    <article
                            class="card source-ingredient-card
                        <?= $sourceIngredient['linked_product_id']
                                    ? 'source-ingredient-linked'
                                    : '' ?>
                        <?= $sourceIngredient['is_ignored']
                                    ? 'source-ingredient-ignored'
                                    : '' ?>
                        <?= $hasSuggestion ? 'source-ingredient-suggested' : '' ?>"
                            data-source-ingredient-id="<?= e($sourceIngredient['id']) ?>"
                    >
                        <header class="source-concept">
                        <span class="source-position">
                            <?= e($sourceIngredient['position']) ?>
                        </span>
                            <div>
                                <small>Original imported ingredient</small>
                                <strong><?= e($sourceIngredient['raw_text']) ?></strong>
                            </div>
                            <span class="mapping-status">
                            <?= $sourceIngredient['linked_product_id']
                                    ? 'Linked'
                                    : (
                                    $sourceIngredient['is_ignored']
                                            ? 'Ignored'
                                            : (
                                            $hasSuggestion
                                                    ? 'Suggested match — verify'
                                                    : 'Needs linking'
                                            )
                                    ) ?>
                        </span>
                        </header>

                        <div
                                class="linked-product-summary
                            <?= $sourceIngredient['linked_product_id']
                                        ? ''
                                        : 'is-hidden' ?>"
                        >
                            <div>
                                <small>Currently linked to</small>
                                <strong class="linked-product-name">
                                    <?= e(
                                            trim(
                                                    ($sourceIngredient['linked_product_name'] ?? '')
                                                    . (
                                                    $sourceIngredient['linked_product_brand']
                                                            ? ' · ' . $sourceIngredient['linked_product_brand']
                                                            : ''
                                                    )
                                            )
                                    ) ?>
                                </strong>
                            </div>
                        </div>

                        <div class="source-ingredient-controls">
                            <label>
                                Product
                                <select
                                        class="source-product-select"
                                        data-combobox="product"
                                        <?= $sourceIngredient['is_ignored']
                                                ? 'disabled'
                                                : '' ?>
                                >
                                    <option value="">Search a product…</option>
                                    <?php foreach ($products as $product): ?>
                                        <?php
                                        $searchText = trim(implode(' ', array_filter([
                                                $product['name'],
                                                $product['brand'],
                                                $product['source_identifier'],
                                        ])));
                                        $metaText = 'per ' . $product['reference_amount'] . ' ' . $product['reference_unit']
                                                . ($product['brand'] ? ' · ' . $product['brand'] : '');
                                        ?>
                                        <option
                                                value="<?= e($product['id']) ?>"
                                                data-reference-unit="<?= e($product['reference_unit']) ?>"
                                                data-search="<?= e(mb_strtolower($searchText)) ?>"
                                                data-name="<?= e($product['name']) ?>"
                                                data-meta="<?= e($metaText) ?>"
                                                data-image="<?= e($product['image_path'] ?? '') ?>"
                                                <?php
                                                $isConfirmedLink = (int) $sourceIngredient['linked_product_id']
                                                        === (int) $product['id'];
                                                $isSuggestedMatch = $hasSuggestion
                                                        && (int) $sourceIngredient['suggested_product_id']
                                                            === (int) $product['id'];
                                                ?>
                                                <?= ($isConfirmedLink || $isSuggestedMatch) ? 'selected' : '' ?>
                                        >
                                            <?= e($product['name']) ?>
                                            <?= $product['brand']
                                                    ? ' · ' . e($product['brand'])
                                                    : '' ?>
                                            · per <?= e($product['reference_amount']) ?>
                                            <?= e($product['reference_unit']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                Imported amount
                                <input
                                        class="source-amount"
                                        type="number"
                                        min="0.001"
                                        step="0.001"
                                        value="<?= e($sourceIngredient['parsed_amount'] ?: 1) ?>"
                                >
                            </label>

                            <label>
                                Imported unit
                                <select class="source-unit">
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

                        <p class="source-conversion-note is-hidden"></p>

                        <div class="source-conversion is-hidden">
                            <p>
                                Enter the equivalent amount in the product's
                                nutrition unit.
                            </p>
                            <div class="source-conversion-fields">
                                <span class="conversion-source-label"></span>
                                <span>=</span>
                                <input
                                        class="converted-amount"
                                        type="number"
                                        min="0.001"
                                        step="0.001"
                                        value="<?= e(
                                                $sourceIngredient['converted_amount']
                                                ?? ''
                                        ) ?>"
                                        placeholder="e.g. 15"
                                >
                                <input
                                        class="converted-unit"
                                        type="text"
                                        readonly
                                        value="<?= e(
                                                $sourceIngredient['converted_unit']
                                                ?? ''
                                        ) ?>"
                                >
                            </div>
                            <label class="checkbox-label">
                                <input
                                        class="source-remember-conversion"
                                        type="checkbox"
                                        checked
                                >
                                Remember this conversion for future recipes
                            </label>
                        </div>

                        <div class="source-ingredient-actions">
                            <?php if (!$sourceIngredient['is_ignored']): ?>
                                <button
                                        class="button source-link-button"
                                        type="button"
                                >
                                    <?= $sourceIngredient['linked_product_id']
                                            ? 'Update link'
                                            : 'Link product' ?>
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
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </details>
<?php endif; ?>
