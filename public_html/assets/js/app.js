/* /public_html/assets/js/app.js */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[type="number"]').forEach((input) => {
        input.addEventListener('wheel', () => input.blur(), { passive: true });
    });

    const statsExpandButton = document.querySelector('#nutrition-stats-expand');

    statsExpandButton?.addEventListener('click', () => {
        const expanded = statsExpandButton.getAttribute('aria-expanded') === 'true';
        document.querySelectorAll('[data-stat-extra]').forEach((card) => {
            card.classList.toggle('is-hidden', expanded);
        });
        statsExpandButton.setAttribute('aria-expanded', String(!expanded));
        statsExpandButton.textContent = expanded ? 'Show all metrics' : 'Show fewer metrics';
    });

    const productFilterInput = document.querySelector('#product-filter');
    const productFilterCount = document.querySelector('#product-filter-count');
    const productRows = Array.from(document.querySelectorAll('#products-table-body tr[data-search]'));

    if (productFilterInput && productRows.length) {
        const applyProductFilter = () => {
            const query = productFilterInput.value.trim().toLocaleLowerCase('nl-NL');
            let visibleCount = 0;

            productRows.forEach((row) => {
                const matches = query === '' || row.dataset.search.includes(query);
                row.classList.toggle('is-hidden', !matches);
                if (matches) visibleCount += 1;
            });

            if (productFilterCount) {
                productFilterCount.textContent = query === ''
                    ? ''
                    : `${visibleCount} of ${productRows.length} shown`;
            }
        };

        productFilterInput.addEventListener('input', applyProductFilter);
    }

    const select = document.querySelector('#product-select');
    const packageButton = document.querySelector('#use-whole-package');
    const amount = document.querySelector('#ingredient-amount');
    const unit = document.querySelector('#ingredient-unit');

    const updatePackageButton = () => {
        if (!select || !packageButton) return;

        const option = select.selectedOptions[0];
        const packageAmount = option?.dataset.packageAmount || '';
        const packageUnit = option?.dataset.packageUnit || '';
        const supported = packageAmount !== '' && ['g', 'ml', 'serving'].includes(packageUnit);

        packageButton.disabled = !supported;
        packageButton.textContent = supported
            ? `Use whole package (${packageAmount} ${packageUnit})`
            : 'Whole package unavailable';
    };

    if (select) {
        select.addEventListener('change', updatePackageButton);
        updatePackageButton();
    }

    packageButton?.addEventListener('click', () => {
        const option = select?.selectedOptions[0];
        if (!option || !amount || !unit) return;

        amount.value = option.dataset.packageAmount || '';
        unit.value = option.dataset.packageUnit || 'g';
        amount.focus();
    });

    /*
     * When the chosen unit doesn't match the product's own nutrition
     * unit, either apply a conversion remembered earlier for that
     * product (see ProductUnitConversionRepository) or ask for one -
     * mirrors the panel used when linking imported ingredients.
     */
    const conversionPanel = document.querySelector('#ingredient-conversion');
    const conversionNote = document.querySelector('#ingredient-conversion-note');
    const conversionLabel = document.querySelector('#ingredient-conversion-label');
    const convertedAmountInput = document.querySelector('#ingredient-converted-amount');
    const convertedUnitInput = document.querySelector('#ingredient-converted-unit');

    const productConversionsRaw = (() => {
        const configElement = document.querySelector('#recipe-page-config');
        if (!configElement) return {};
        try {
            return JSON.parse(configElement.textContent).productConversions || {};
        } catch {
            return {};
        }
    })();

    const updateIngredientConversion = () => {
        if (!select || !unit || !conversionPanel) return false;

        const option = select.selectedOptions[0];
        const referenceUnit = option?.dataset.referenceUnit || '';
        const sourceUnit = unit.value;
        const sourceAmount = amount?.value || '1';

        const required = select.value !== '' && referenceUnit !== '' && sourceUnit !== referenceUnit;
        const saved = required
            ? productConversionsRaw[select.value]?.[sourceUnit]
            : null;

        conversionPanel.classList.toggle('is-hidden', !required || Boolean(saved));

        if (convertedUnitInput) {
            convertedUnitInput.value = required ? referenceUnit : '';
        }

        if (conversionLabel) {
            conversionLabel.textContent = `${sourceAmount} ${sourceUnit}`;
        }

        if (saved && conversionNote) {
            const grams = (Number(sourceAmount) || 0) * Number(saved.reference_amount || 0);
            conversionNote.textContent =
                `Using a saved conversion: 1 ${sourceUnit} of this product = `
                + `${Number(saved.reference_amount)} ${referenceUnit} (≈ ${grams.toFixed(2)} ${referenceUnit} total).`;
        } else if (conversionNote) {
            conversionNote.textContent = "Enter the equivalent amount in the product's nutrition unit.";
        }

        return required && !saved;
    };

    if (select && unit) {
        select.addEventListener('change', updateIngredientConversion);
        select.addEventListener('change', () => renderProductConversions());

        // A genuine product pick (click or keyboard Enter in the
        // combobox) moves focus straight to the amount field, so typing
        // a search term, arrowing to a result and hitting Enter can flow
        // directly into entering the quantity without touching the mouse.
        select.addEventListener('combobox:pick', () => {
            amount?.focus();
            amount?.select();
        });

        // Changing the unit is a deliberate action too: if that now
        // requires a conversion that isn't saved yet, send focus to the
        // conversion field instead of leaving the user to find it.
        unit.addEventListener('change', () => {
            const needsConversion = updateIngredientConversion();
            if (needsConversion) {
                convertedAmountInput?.focus();
            }
        });

        amount?.addEventListener('input', updateIngredientConversion);
        updateIngredientConversion();
    }

    const shoppingContainer = document.querySelector('#ah-shopping-products');
    const selectAll = document.querySelector('#shopping-select-all');
    const shoppingButton = document.querySelector('#open-ah-shopping-list');
    const shoppingSummary = document.querySelector('#shopping-selection-summary');

    const shoppingRows = () => {
        return Array.from(
            shoppingContainer?.querySelectorAll('.shopping-product') || []
        );
    };

    const updateShoppingState = () => {
        const rows = shoppingRows();
        const checkedRows = rows.filter((row) => {
            return row.querySelector('.shopping-product-checkbox')?.checked;
        });

        rows.forEach((row) => {
            const selected = row.querySelector('.shopping-product-checkbox')?.checked;
            row.classList.toggle('shopping-product-disabled', !selected);
            const quantity = row.querySelector('.shopping-product-quantity');
            if (quantity) quantity.disabled = !selected;
        });

        if (selectAll) {
            selectAll.checked = rows.length > 0 && checkedRows.length === rows.length;
            selectAll.indeterminate = checkedRows.length > 0 && checkedRows.length < rows.length;
        }

        if (shoppingButton) {
            shoppingButton.disabled = checkedRows.length === 0;
        }

        if (shoppingSummary) {
            shoppingSummary.textContent = `${checkedRows.length} of ${rows.length} selected`;
        }
    };

    selectAll?.addEventListener('change', () => {
        shoppingRows().forEach((row) => {
            const checkbox = row.querySelector('.shopping-product-checkbox');
            if (checkbox) checkbox.checked = selectAll.checked;
        });
        updateShoppingState();
    });

    shoppingContainer?.addEventListener('change', (event) => {
        if (
            event.target.matches('.shopping-product-checkbox')
            || event.target.matches('.shopping-product-quantity')
        ) {
            updateShoppingState();
        }
    });

    shoppingButton?.addEventListener('click', () => {
        const parameters = new URLSearchParams();

        shoppingRows().forEach((row) => {
            const checked = row.querySelector('.shopping-product-checkbox')?.checked;
            if (!checked) return;

            const ahId = row.dataset.ahId;
            const quantityInput = row.querySelector('.shopping-product-quantity');
            const quantity = Math.min(
                99,
                Math.max(1, Number.parseInt(quantityInput?.value || '1', 10) || 1)
            );

            parameters.append('p', `${ahId}:${quantity}`);
        });

        const url = `https://www.ah.nl/mijnlijst/add-multiple?${parameters.toString()}`;
        window.open(url, '_blank', 'noopener,noreferrer');
    });

    updateShoppingState();

    const configElement = document.querySelector('#recipe-page-config');
    const ingredientForm = document.querySelector('#ingredient-form');

    if (!configElement || !ingredientForm) {
        return;
    }

    const config = JSON.parse(configElement.textContent);
    const tbody = document.querySelector('#ingredients-body');
    const tableWrap = document.querySelector('#ingredients-table-wrap');
    const emptyState = document.querySelector('#ingredients-empty');
    const message = document.querySelector('#ingredient-message');

    let nutritionState = config.nutrition || { per_serving: {}, per_100g: null, weight_is_approximate: false };
    let nutritionMode = 'serving';
    const nutritionModeToggle = document.querySelector('#nutrition-mode-toggle');
    const nutritionModeNote = document.querySelector('#nutrition-mode-note');

    const request = async (url, formData) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        const payload = await response.json().catch(() => ({
            error: 'The server returned an invalid response.',
        }));

        if (!response.ok) {
            throw new Error(payload.error || 'The request failed.');
        }

        return payload;
    };

    const showMessage = (text, type = 'success') => {
        message.textContent = text;
        message.className = `ajax-message ajax-message-${type}`;
        window.setTimeout(() => message.classList.add('is-hidden'), 2800);
    };

    const formatNumber = (value, digits = 1) => {
        return Number(value || 0).toLocaleString('en-US', {
            maximumFractionDigits: digits,
        });
    };

    const renderStats = (perServing) => {
        const values = {
            energy_kcal: formatNumber(perServing.energy_kcal, 0),
            protein_g: `${formatNumber(perServing.protein_g)} g`,
            carbohydrates_g: `${formatNumber(perServing.carbohydrates_g)} g`,
            fat_g: `${formatNumber(perServing.fat_g)} g`,
            saturated_fat_g: `${formatNumber(perServing.saturated_fat_g)} g`,
            sugars_g: `${formatNumber(perServing.sugars_g)} g`,
            fiber_g: `${formatNumber(perServing.fiber_g)} g`,
            salt_g: `${formatNumber(perServing.salt_g, 2)} g`,
            energy_kj: formatNumber(perServing.energy_kj, 0),
        };

        Object.entries(values).forEach(([field, value]) => {
            const element = document.querySelector(`[data-stat="${field}"]`);
            if (element) element.textContent = value;
        });
    };

    const applyNutritionMode = () => {
        const values = (nutritionMode === '100g' && nutritionState.per_100g)
            ? nutritionState.per_100g
            : nutritionState.per_serving;
        renderStats(values);

        if (nutritionModeNote) {
            nutritionModeNote.classList.toggle(
                'is-hidden',
                !(nutritionMode === '100g' && nutritionState.weight_is_approximate)
            );
        }
    };

    const syncNutritionModeAvailability = () => {
        const per100gButton = nutritionModeToggle?.querySelector('[data-mode="100g"]');
        if (!per100gButton) return;

        const available = Boolean(nutritionState.per_100g);
        per100gButton.disabled = !available;
        per100gButton.title = available
            ? ''
            : 'Add ingredients measured in g or ml to enable this view';

        if (!available && nutritionMode === '100g') {
            nutritionMode = 'serving';
            nutritionModeToggle.querySelectorAll('.nutrition-mode-button').forEach((button) => {
                button.classList.toggle('is-active', button.dataset.mode === 'serving');
            });
        }
    };

    nutritionModeToggle?.addEventListener('click', (event) => {
        const button = event.target.closest('.nutrition-mode-button');
        if (!button || button.disabled) return;

        nutritionModeToggle.querySelectorAll('.nutrition-mode-button').forEach((otherButton) => {
            otherButton.classList.toggle('is-active', otherButton === button);
        });

        nutritionMode = button.dataset.mode === '100g' ? '100g' : 'serving';
        applyNutritionMode();
    });

    /*
     * Saved unit conversions for the currently selected product, managed
     * right here on the recipe page (add/remove) instead of forcing a
     * trip to that product's own edit page. Mirrors the "Culinary units"
     * panel on /products/{id}/edit, but scoped to whichever product is
     * picked in the add-ingredient combobox.
     */
    const conversionsManager = document.querySelector('#product-conversions-manager');
    const conversionsList = document.querySelector('#product-conversions-list');
    const conversionsProductName = document.querySelector('#product-conversions-product-name');
    const conversionsUnitSelect = document.querySelector('#product-conversions-unit');
    const conversionsAmountInput = document.querySelector('#product-conversions-amount');
    const conversionsRefUnitLabel = document.querySelector('#product-conversions-reference-unit-label');
    const conversionsAddButton = document.querySelector('#product-conversions-add');

    const applyConversionsPayload = (productId, conversions) => {
        productConversionsRaw[productId] = {};
        (conversions || []).forEach((conversion) => {
            productConversionsRaw[productId][conversion.unit] = conversion;
        });
    };

    function renderProductConversions() {
        if (!select || !conversionsManager) return;

        const option = select.selectedOptions[0];

        if (!select.value || !option) {
            conversionsManager.classList.add('is-hidden');
            return;
        }

        conversionsManager.classList.remove('is-hidden');

        if (conversionsProductName) {
            conversionsProductName.textContent = `for ${option.dataset.name || ''}`;
        }

        const referenceUnit = option.dataset.referenceUnit || 'g';
        if (conversionsRefUnitLabel) conversionsRefUnitLabel.textContent = referenceUnit;

        const conversions = productConversionsRaw[select.value] || {};
        const units = Object.keys(conversions);

        conversionsList.replaceChildren();

        if (units.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'conversions-empty';
            empty.textContent = 'No conversions saved yet for this product.';
            conversionsList.appendChild(empty);
            return;
        }

        units.sort().forEach((conversionUnit) => {
            const conversion = conversions[conversionUnit];
            const item = document.createElement('li');

            const text = document.createElement('span');
            text.textContent = `1 ${conversionUnit} = ${Number(conversion.reference_amount)} ${referenceUnit}`;

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'link-button danger-link';
            removeButton.textContent = 'Remove';
            removeButton.addEventListener('click', () => deleteProductConversion(conversionUnit));

            item.append(text, removeButton);
            conversionsList.appendChild(item);
        });
    }

    const deleteProductConversion = async (conversionUnit) => {
        const productId = select.value;
        if (!productId) return;

        const formData = new FormData();
        formData.set('_csrf', config.csrfToken);

        try {
            const payload = await request(
                `/products/${productId}/conversions/${encodeURIComponent(conversionUnit)}/delete`,
                formData
            );
            applyConversionsPayload(productId, payload.conversions);
            renderProductConversions();
            updateIngredientConversion();
            showMessage('Conversion removed.');
        } catch (error) {
            showMessage(error.message, 'error');
        }
    };

    conversionsAddButton?.addEventListener('click', async () => {
        const productId = select.value;
        if (!productId) return;

        const unitValue = conversionsUnitSelect.value;
        const amountValue = Number(conversionsAmountInput.value);

        if (!amountValue || amountValue <= 0) {
            showMessage('Enter a positive amount for the conversion.', 'error');
            conversionsAmountInput.focus();
            return;
        }

        const formData = new FormData();
        formData.set('_csrf', config.csrfToken);
        formData.set('unit', unitValue);
        formData.set('reference_amount', String(amountValue));

        try {
            const payload = await request(`/products/${productId}/conversions`, formData);
            applyConversionsPayload(productId, payload.conversions);
            renderProductConversions();
            updateIngredientConversion();
            const referenceUnit = conversionsRefUnitLabel?.textContent || '';
            showMessage(`Saved: 1 ${unitValue} = ${amountValue} ${referenceUnit}.`);
            conversionsAmountInput.value = '';
        } catch (error) {
            showMessage(error.message, 'error');
        }
    });

    /*
     * Inline "create a new product" panel, opened from the combobox's
     * trailing "+ Create ..." row when a search finds nothing useful.
     * Posts straight to /products and, on success, adds the new product
     * as an option in the picker and selects it - no page navigation.
     */
    const inlineCreatePanel = document.querySelector('#inline-product-create');
    const inlineCreateMessage = document.querySelector('#inline-product-create-message');
    const NEW_PRODUCT_FIELDS = [
        'energy_kj', 'energy_kcal', 'fat_g', 'saturated_fat_g',
        'carbohydrates_g', 'sugars_g', 'fiber_g', 'protein_g', 'salt_g',
    ];

    const showInlineCreateMessage = (text, type = 'error') => {
        if (!inlineCreateMessage) return;
        inlineCreateMessage.textContent = text;
        inlineCreateMessage.className = `ajax-message ajax-message-${type}`;
        inlineCreateMessage.classList.remove('is-hidden');
    };

    const addProductOption = (product) => {
        const option = document.createElement('option');
        option.value = product.id;
        option.dataset.name = product.name;
        option.dataset.meta = [product.brand, product.package_description].filter(Boolean).join(' · ');
        option.dataset.search = [product.name, product.brand].filter(Boolean).join(' ').toLocaleLowerCase('nl-NL');
        option.dataset.image = product.image_path || '';
        option.dataset.referenceUnit = product.reference_unit;
        option.dataset.packageAmount = product.package_amount || '';
        option.dataset.packageUnit = product.package_unit || '';
        option.textContent = product.brand ? `${product.name} · ${product.brand}` : product.name;
        select.appendChild(option);
    };

    select?.addEventListener('combobox:create-requested', (event) => {
        if (!inlineCreatePanel) return;

        inlineCreatePanel.classList.remove('is-hidden');
        inlineCreateMessage?.classList.add('is-hidden');

        const nameInput = document.querySelector('#new-product-name');
        if (nameInput) {
            nameInput.value = event.detail?.query || '';
            nameInput.focus();
        }
    });

    document.querySelector('#inline-product-create-cancel')?.addEventListener('click', () => {
        inlineCreatePanel?.classList.add('is-hidden');
    });

    document.querySelector('#inline-product-create-save')?.addEventListener('click', async () => {
        const nameInput = document.querySelector('#new-product-name');
        const name = nameInput?.value.trim() || '';

        if (!name) {
            showInlineCreateMessage('Product name is required.');
            nameInput?.focus();
            return;
        }

        const formData = new FormData();
        formData.set('_csrf', config.csrfToken);
        formData.set('name', name);
        formData.set('brand', document.querySelector('#new-product-brand')?.value.trim() || '');
        formData.set('reference_amount', document.querySelector('#new-product-reference-amount')?.value || '100');
        formData.set('reference_unit', document.querySelector('#new-product-reference-unit')?.value || 'g');
        formData.set('package_amount', '0');
        formData.set('package_unit', '');
        formData.set('package_description', '');
        formData.set('source_url', '');

        NEW_PRODUCT_FIELDS.forEach((field) => {
            const fieldInput = document.querySelector(`#new-product-${field.replace(/_/g, '-')}`);
            formData.set(field, fieldInput?.value || '0');
        });

        try {
            const payload = await request('/products', formData);
            const product = payload.product;

            addProductOption(product);
            select.value = String(product.id);
            select.dispatchEvent(new Event('change', { bubbles: true }));

            inlineCreatePanel?.classList.add('is-hidden');
            amount?.focus();
            showMessage(`"${product.name}" created and selected.`);
        } catch (error) {
            showInlineCreateMessage(error.message);
        }
    });

    const svgElement = (pathData) => {
        const namespace = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(namespace, 'svg');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('viewBox', '0 0 24 24');
        const path = document.createElementNS(namespace, 'path');
        path.setAttribute('d', pathData);
        svg.appendChild(path);
        return svg;
    };

    const ingredientRow = (ingredient) => {
        const row = document.createElement('tr');
        row.dataset.ingredientId = ingredient.id;
        row.classList.add('row-enter');

        const imageCell = document.createElement('td');
        if (ingredient.image_path) {
            const image = document.createElement('img');
            image.className = 'ingredient-image';
            image.src = ingredient.image_path;
            image.alt = '';
            image.loading = 'lazy';
            imageCell.appendChild(image);
        } else {
            imageCell.textContent = '—';
        }

        const productCell = document.createElement('td');
        const strong = document.createElement('strong');
        strong.textContent = ingredient.product_name || '';
        productCell.appendChild(strong);

        if (ingredient.brand) {
            const small = document.createElement('small');
            small.textContent = ingredient.brand;
            productCell.appendChild(small);
        }

        const packageCell = document.createElement('td');
        packageCell.textContent = ingredient.package_description || 'Unknown';

        const amountCell = document.createElement('td');
        const fields = document.createElement('div');
        fields.className = 'inline-ingredient-fields';

        const amountInput = document.createElement('input');
        amountInput.className = 'inline-amount';
        amountInput.type = 'number';
        amountInput.min = '0.001';
        amountInput.step = '0.001';
        amountInput.value = ingredient.amount;
        amountInput.setAttribute('aria-label', 'Ingredient amount');

        const unitSelect = document.createElement('select');
        unitSelect.className = 'inline-unit';
        unitSelect.setAttribute('aria-label', 'Ingredient unit');

        ['g', 'ml', 'serving'].forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            option.selected = value === ingredient.unit;
            unitSelect.appendChild(option);
        });

        fields.append(amountInput, unitSelect);
        amountCell.appendChild(fields);

        const kcalCell = document.createElement('td');
        kcalCell.dataset.cell = 'kcal';
        kcalCell.textContent = formatNumber(ingredient.calculated_energy_kcal);

        const proteinCell = document.createElement('td');
        proteinCell.dataset.cell = 'protein';
        proteinCell.textContent = `${formatNumber(ingredient.calculated_protein_g)} g`;

        const actionsCell = document.createElement('td');
        const actions = document.createElement('div');
        actions.className = 'icon-actions';

        const saveButton = document.createElement('button');
        saveButton.className = 'icon-button ingredient-save';
        saveButton.type = 'button';
        saveButton.title = 'Save changes';
        saveButton.setAttribute('aria-label', 'Save ingredient changes');
        saveButton.appendChild(svgElement(
            'M5 3h12l2 2v16H5V3Zm2 2v5h8V5H7Zm1 9v5h8v-5H8Z'
        ));

        const deleteButton = document.createElement('button');
        deleteButton.className = 'icon-button icon-button-danger ingredient-delete';
        deleteButton.type = 'button';
        deleteButton.title = 'Remove ingredient';
        deleteButton.setAttribute('aria-label', 'Remove ingredient');
        deleteButton.appendChild(svgElement(
            'M7 4V2h10v2h5v2h-2l-1 15H5L4 6H2V4h5Zm2 4v9h2V8H9Zm4 0v9h2V8h-2Z'
        ));

        actions.append(saveButton, deleteButton);
        actionsCell.appendChild(actions);

        row.append(
            imageCell,
            productCell,
            packageCell,
            amountCell,
            kcalCell,
            proteinCell,
            actionsCell
        );

        return row;
    };

    const renderPayload = (payload) => {
        tbody.replaceChildren();
        payload.nutrition.ingredients.forEach((ingredient) => {
            tbody.appendChild(ingredientRow(ingredient));
        });

        const hasIngredients = payload.nutrition.ingredients.length > 0;
        tableWrap.classList.toggle('is-hidden', !hasIngredients);
        emptyState.classList.toggle('is-hidden', hasIngredients);

        nutritionState = payload.nutrition;
        syncNutritionModeAvailability();
        applyNutritionMode();
    };

    ingredientForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitButton = ingredientForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.textContent = 'Adding…';

        try {
            const payload = await request(
                ingredientForm.action,
                new FormData(ingredientForm)
            );

            renderPayload(payload);

            // Reset to a blank product search rather than keeping the
            // just-added product selected, so the whole add flow - type,
            // pick, enter amount, submit - can repeat immediately for a
            // different ingredient without touching the mouse.
            ingredientForm.reset();
            select.value = '';
            select.dispatchEvent(new Event('change', { bubbles: true }));
            updatePackageButton();

            const productSearchInput = select.id
                ? document.querySelector(`#${CSS.escape(select.id)}-search`)
                : null;
            (productSearchInput || amount)?.focus();

            showMessage('Ingredient added. Search for the next one.');
        } catch (error) {
            showMessage(error.message, 'error');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Add ingredient';
        }
    });

    tbody.addEventListener('click', async (event) => {
        const row = event.target.closest('tr[data-ingredient-id]');
        if (!row) return;

        const ingredientId = row.dataset.ingredientId;

        if (event.target.closest('.ingredient-save')) {
            const formData = new FormData();
            formData.set('_csrf', config.csrfToken);
            formData.set('amount', row.querySelector('.inline-amount').value);
            formData.set('unit', row.querySelector('.inline-unit').value);
            formData.set('notes', '');

            row.classList.add('row-busy');

            try {
                const payload = await request(
                    `/recipes/${config.recipeId}/ingredients/${ingredientId}/update`,
                    formData
                );
                renderPayload(payload);
                showMessage('Ingredient updated.');
            } catch (error) {
                row.classList.remove('row-busy');
                showMessage(error.message, 'error');
            }
        }

        if (event.target.closest('.ingredient-delete')) {
            if (!window.confirm('Remove this ingredient from the recipe?')) {
                return;
            }

            const formData = new FormData();
            formData.set('_csrf', config.csrfToken);
            row.classList.add('row-leave');

            try {
                const payload = await request(
                    `/recipes/${config.recipeId}/ingredients/${ingredientId}/delete`,
                    formData
                );

                window.setTimeout(() => {
                    renderPayload(payload);
                    showMessage('Ingredient removed.');
                }, 180);
            } catch (error) {
                row.classList.remove('row-leave');
                showMessage(error.message, 'error');
            }
        }
    });

    renderProductConversions();

    if (select?.value) {
        updatePackageButton();
        window.setTimeout(() => amount?.focus(), 100);
    }
});
