/* /public_html/assets/js/recipe-import-workflow.js */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-add-repeatable]').forEach((button) => {
        button.addEventListener('click', () => {
            const name = button.dataset.addRepeatable;
            const list = document.querySelector(
                `[data-repeatable-list="${name}"]`
            );

            if (!list) return;

            const row = document.createElement('div');
            row.className = 'import-repeatable-row row-enter';

            if (name === 'instructions') {
                const number = document.createElement('span');
                number.className = 'step-number';
                number.textContent = String(list.children.length + 1);

                const textarea = document.createElement('textarea');
                textarea.name = 'instructions[]';
                textarea.rows = 3;
                textarea.required = true;

                row.append(number, textarea);
            } else {
                const input = document.createElement('input');
                input.name = 'ingredients[]';
                input.required = true;
                row.appendChild(input);
            }

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'icon-button icon-button-danger';
            remove.dataset.removeRepeatable = '';
            remove.setAttribute('aria-label', 'Remove row');
            remove.textContent = '×';

            row.appendChild(remove);
            list.appendChild(row);
            row.querySelector('input, textarea')?.focus();
        });
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-repeatable]');

        if (!button) return;

        const row = button.closest('.import-repeatable-row');
        const list = row?.parentElement;
        row?.remove();

        list?.querySelectorAll('.step-number').forEach((element, index) => {
            element.textContent = String(index + 1);
        });
    });

    const configElement = document.querySelector('#recipe-page-config');
    const list = document.querySelector('#source-ingredient-list');

    if (!configElement || !list) return;

    const config = JSON.parse(configElement.textContent);

    const post = async (url, values = {}) => {
        const form = new FormData();
        form.set('_csrf', config.csrfToken);

        Object.entries(values).forEach(([key, value]) => {
            form.set(key, String(value));
        });

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: form,
        });

        const payload = await response.json().catch(() => ({
            error: 'The server returned an invalid response.',
        }));

        if (!response.ok) {
            throw new Error(payload.error || 'Request failed.');
        }

        return payload;
    };

    const number = (value, digits = 1) => {
        return Number(value || 0).toLocaleString('en-US', {
            maximumFractionDigits: digits,
        });
    };

    const svg = (pathData) => {
        const namespace = 'http://www.w3.org/2000/svg';
        const element = document.createElementNS(namespace, 'svg');
        element.setAttribute('aria-hidden', 'true');
        element.setAttribute('viewBox', '0 0 24 24');

        const path = document.createElementNS(namespace, 'path');
        path.setAttribute('d', pathData);
        element.appendChild(path);

        return element;
    };

    const ingredientRow = (ingredient) => {
        const row = document.createElement('tr');
        row.dataset.ingredientId = ingredient.id;
        row.className = 'row-enter';

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
        const productName = document.createElement('strong');
        productName.textContent = ingredient.product_name || '';
        productCell.appendChild(productName);

        if (ingredient.brand) {
            const brand = document.createElement('small');
            brand.textContent = ingredient.brand;
            productCell.appendChild(brand);
        }

        const packageCell = document.createElement('td');
        packageCell.textContent =
            ingredient.package_description || 'Unknown';

        const amountCell = document.createElement('td');
        const fields = document.createElement('div');
        fields.className = 'inline-ingredient-fields';

        const amount = document.createElement('input');
        amount.className = 'inline-amount';
        amount.type = 'number';
        amount.min = '0.001';
        amount.step = '0.001';
        amount.value = ingredient.amount;
        amount.setAttribute('aria-label', 'Ingredient amount');

        const unit = document.createElement('select');
        unit.className = 'inline-unit';
        unit.setAttribute('aria-label', 'Ingredient unit');

        ['g', 'ml', 'serving'].forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            option.selected = ingredient.unit === value;
            unit.appendChild(option);
        });

        fields.append(amount, unit);
        amountCell.appendChild(fields);

        const kcalCell = document.createElement('td');
        kcalCell.dataset.cell = 'kcal';
        kcalCell.textContent = number(
            ingredient.calculated_energy_kcal
        );

        const proteinCell = document.createElement('td');
        proteinCell.dataset.cell = 'protein';
        proteinCell.textContent =
            `${number(ingredient.calculated_protein_g)} g`;

        const actionsCell = document.createElement('td');
        const actions = document.createElement('div');
        actions.className = 'icon-actions';

        const save = document.createElement('button');
        save.className = 'icon-button ingredient-save';
        save.type = 'button';
        save.title = 'Save changes';
        save.setAttribute('aria-label', 'Save ingredient changes');
        save.appendChild(svg(
            'M5 3h12l2 2v16H5V3Zm2 2v5h8V5H7Zm1 9v5h8v-5H8Z'
        ));

        const remove = document.createElement('button');
        remove.className =
            'icon-button icon-button-danger ingredient-delete';
        remove.type = 'button';
        remove.title = 'Remove ingredient';
        remove.setAttribute('aria-label', 'Remove ingredient');
        remove.appendChild(svg(
            'M7 4V2h10v2h5v2h-2l-1 15H5L4 6H2V4h5'
            + 'Zm2 4v9h2V8H9Zm4 0v9h2V8h-2Z'
        ));

        actions.append(save, remove);
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

    const renderNutrition = (nutrition) => {
        if (!nutrition) return;

        const tbody = document.querySelector('#ingredients-body');
        const table = document.querySelector('#ingredients-table-wrap');
        const empty = document.querySelector('#ingredients-empty');

        if (tbody) {
            tbody.replaceChildren();

            nutrition.ingredients.forEach((ingredient) => {
                tbody.appendChild(ingredientRow(ingredient));
            });
        }

        const hasIngredients = nutrition.ingredients.length > 0;
        table?.classList.toggle('is-hidden', !hasIngredients);
        empty?.classList.toggle('is-hidden', hasIngredients);

        const values = {
            energy_kcal: number(
                nutrition.per_serving.energy_kcal,
                0
            ),
            protein_g:
                `${number(nutrition.per_serving.protein_g)} g`,
            carbohydrates_g:
                `${number(nutrition.per_serving.carbohydrates_g)} g`,
            fat_g:
                `${number(nutrition.per_serving.fat_g)} g`,
        };

        Object.entries(values).forEach(([key, value]) => {
            const element = document.querySelector(
                `[data-stat="${key}"]`
            );

            if (element) {
                element.textContent = value;
            }
        });
    };

    const updateProgress = () => {
        const cards = Array.from(
            list.querySelectorAll('.source-ingredient-card')
        );
        const done = cards.filter((card) => {
            return card.classList.contains('source-ingredient-linked')
                || card.classList.contains('source-ingredient-ignored');
        }).length;

        const progress = document.querySelector('#mapping-progress');

        if (progress) {
            progress.textContent =
                `${done} of ${cards.length} handled`;
        }
    };

    const setBusy = (card, busy) => {
        card.classList.toggle('source-ingredient-busy', busy);
    };

    const showLinkedProduct = (card) => {
        const select = card.querySelector('.source-product-select');
        const selected = select?.selectedOptions[0];
        const summary = card.querySelector('.linked-product-summary');
        const name = card.querySelector('.linked-product-name');

        if (name) {
            name.textContent =
                selected?.textContent?.trim() || 'Linked product';
        }

        summary?.classList.remove('is-hidden');
    };

    const linkCard = async (card, productId = null) => {
        const id = card.dataset.sourceIngredientId;
        const select = card.querySelector('.source-product-select');

        if (productId && select) {
            select.value = String(productId);
        }

        const product = productId || select?.value;

        if (!product) {
            throw new Error('Select or create a product first.');
        }

        const payload = await post(
            `/recipes/${config.recipeId}`
            + `/source-ingredients/${id}/link`,
            {
                product_id: product,
                amount:
                    card.querySelector('.source-amount')?.value || '1',
                unit:
                    card.querySelector('.source-unit')?.value
                    || 'serving',
            }
        );

        card.classList.add('source-ingredient-linked');
        card.classList.remove('source-ingredient-ignored');
        card.querySelector('.mapping-status').textContent = 'Linked';

        showLinkedProduct(card);
        renderNutrition(payload.nutrition);
        updateProgress();

        return payload;
    };

    list.addEventListener('click', async (event) => {
        const card = event.target.closest('.source-ingredient-card');

        if (!card) return;

        try {
            setBusy(card, true);

            if (event.target.closest('.source-link-button')) {
                await linkCard(card);
            }

            if (event.target.closest('.source-ignore-button')) {
                const payload = await post(
                    `/recipes/${config.recipeId}`
                    + `/source-ingredients/`
                    + `${card.dataset.sourceIngredientId}/ignore`
                );

                card.classList.add('source-ingredient-ignored');
                card.classList.remove('source-ingredient-linked');
                card.querySelector('.mapping-status').textContent =
                    'Ignored';

                card.querySelectorAll('input, select').forEach((field) => {
                    field.disabled = true;
                });

                renderNutrition(payload.nutrition);
            }

            if (event.target.closest('.source-restore-button')) {
                const payload = await post(
                    `/recipes/${config.recipeId}`
                    + `/source-ingredients/`
                    + `${card.dataset.sourceIngredientId}/restore`
                );

                card.classList.remove('source-ingredient-ignored');
                card.querySelector('.mapping-status').textContent =
                    card.classList.contains('source-ingredient-linked')
                        ? 'Linked'
                        : 'Not linked';

                card.querySelectorAll('input, select').forEach((field) => {
                    field.disabled = false;
                });

                renderNutrition(payload.nutrition);
            }

            updateProgress();
        } catch (error) {
            window.alert(error.message);
        } finally {
            setBusy(card, false);
        }
    });

    updateProgress();

    if (
        Number(config.selectedSourceIngredientId) > 0
        && Number(config.selectedProductId) > 0
    ) {
        const card = list.querySelector(
            `[data-source-ingredient-id="`
            + `${config.selectedSourceIngredientId}"]`
        );

        if (card) {
            setBusy(card, true);

            linkCard(
                card,
                String(config.selectedProductId)
            ).catch((error) => {
                window.alert(error.message);
            }).finally(() => {
                setBusy(card, false);
                card.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                });
            });
        }
    }
});
