/* /public_html/assets/js/app.js */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[type="number"]').forEach((input) => {
        input.addEventListener('wheel', () => input.blur(), { passive: true });
    });

    const search = document.querySelector('#product-search');
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

    if (search && select) {
        const placeholder = select.options[0];
        const options = Array.from(select.options).slice(1);

        search.addEventListener('input', () => {
            const query = search.value.trim().toLocaleLowerCase('nl-NL');
            const selectedValue = select.value;

            select.replaceChildren(placeholder);
            options
                .filter((option) => (option.dataset.search || '').includes(query))
                .forEach((option) => select.appendChild(option));

            if (Array.from(select.options).some((option) => option.value === selectedValue)) {
                select.value = selectedValue;
            }

            updatePackageButton();
        });

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

    const escapeText = (value) => String(value ?? '');

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
        };

        Object.entries(values).forEach(([field, value]) => {
            const element = document.querySelector(`[data-stat="${field}"]`);
            if (element) element.textContent = value;
        });
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
            imageCell.appendChild(image);
        } else {
            imageCell.textContent = '—';
        }

        const productCell = document.createElement('td');
        const strong = document.createElement('strong');
        strong.textContent = escapeText(ingredient.product_name);
        productCell.appendChild(strong);
        if (ingredient.brand) {
            const small = document.createElement('small');
            small.textContent = escapeText(ingredient.brand);
            productCell.appendChild(small);
        }

        const packageCell = document.createElement('td');
        packageCell.textContent = escapeText(ingredient.package_description || 'Unknown');

        const amountCell = document.createElement('td');
        const fields = document.createElement('div');
        fields.className = 'inline-ingredient-fields';

        const amountInput = document.createElement('input');
        amountInput.className = 'inline-amount';
        amountInput.type = 'number';
        amountInput.min = '0.001';
        amountInput.step = '0.001';
        amountInput.value = ingredient.amount;

        const unitSelect = document.createElement('select');
        unitSelect.className = 'inline-unit';
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
        actions.className = 'table-actions';

        const saveButton = document.createElement('button');
        saveButton.className = 'link-button ingredient-save';
        saveButton.type = 'button';
        saveButton.textContent = 'Save';

        const deleteButton = document.createElement('button');
        deleteButton.className = 'link-button danger-link ingredient-delete';
        deleteButton.type = 'button';
        deleteButton.textContent = 'Remove';

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
        renderStats(payload.nutrition.per_serving);
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

            const selectedProduct = select.value;
            ingredientForm.reset();
            select.value = selectedProduct;
            updatePackageButton();
            amount.focus();

            showMessage('Ingredient added. You can add the next one.');
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

    if (select?.value) {
        updatePackageButton();
        window.setTimeout(() => amount?.focus(), 100);
    }
});
