/* /public_html/assets/js/product-form-modal.js */
/*
 * Create/edit a product in a modal instead of navigating to /products/
 * create or /products/{id}/edit. Used from the products list ("Add
 * manually" / row "Edit") and from the recipe page ("Create product"
 * and the ingredient combobox's "+ Create new product" option).
 */
document.addEventListener('DOMContentLoaded', () => {
    const fields = window.PantryProductFields;
    const modal = window.PantryModal;
    if (!fields || !modal) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const renderConversions = (form, productId, referenceUnit, conversions) => {
        const list = form.querySelector('[data-role="conversions-list"]');
        const refLabel = form.querySelector('[data-role="conversion-reference-unit-label"]');
        if (!list) return;

        if (refLabel) refLabel.textContent = referenceUnit || '';
        list.replaceChildren();

        const sorted = [...(conversions || [])].sort((a, b) => a.unit.localeCompare(b.unit));

        if (sorted.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'conversions-empty';
            empty.textContent = 'No conversions saved yet for this product.';
            list.appendChild(empty);
            return;
        }

        sorted.forEach((conversion) => {
            const item = document.createElement('li');

            const text = document.createElement('span');
            text.textContent = `1 ${conversion.unit} = ${Number(conversion.reference_amount)} ${referenceUnit}`;

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'link-button danger-link';
            removeButton.textContent = 'Remove';
            removeButton.addEventListener('click', async () => {
                try {
                    const formData = new FormData();
                    formData.set('_csrf', csrfToken);
                    const payload = await fields.requestJson(
                        `/products/${productId}/conversions/${encodeURIComponent(conversion.unit)}/delete`,
                        { method: 'POST', body: formData }
                    );
                    renderConversions(form, productId, referenceUnit, payload.conversions);
                } catch (error) {
                    fields.showFormMessage(form, error.message);
                }
            });

            item.append(text, removeButton);
            list.appendChild(item);
        });
    };

    const wireConversions = (form, product) => {
        const section = form.querySelector('[data-role="conversions-section"]');
        section?.classList.remove('is-hidden');
        renderConversions(form, product.id, product.reference_unit, product.conversions || []);

        const unitSelect = form.querySelector('[data-role="conversion-unit"]');
        const amountInput = form.querySelector('[data-role="conversion-amount"]');
        const addButton = form.querySelector('[data-role="conversion-add"]');

        addButton?.addEventListener('click', async () => {
            const amount = Number(amountInput?.value);
            if (!amount || amount <= 0) {
                fields.showFormMessage(form, 'Enter a positive amount for the conversion.');
                amountInput?.focus();
                return;
            }

            const formData = new FormData();
            formData.set('_csrf', csrfToken);
            formData.set('unit', unitSelect.value);
            formData.set('reference_amount', String(amount));

            try {
                const payload = await fields.requestJson(`/products/${product.id}/conversions`, {
                    method: 'POST',
                    body: formData,
                });
                renderConversions(form, product.id, product.reference_unit, payload.conversions);
                amountInput.value = '';
            } catch (error) {
                fields.showFormMessage(form, error.message);
            }
        });
    };

    const openForm = ({ title, product = null, defaultName = '', onSaved }) => {
        const form = fields.buildProductForm();
        fields.fillProductForm(form, product);

        if (defaultName && !product) {
            form.querySelector('[name="name"]').value = defaultName;
        }

        if (product) {
            form.querySelector('[data-role="refresh-image-row"]')?.classList.remove('is-hidden');
            wireConversions(form, product);
        } else {
            form.querySelector('[data-role="conversions-section"]')?.remove();
        }

        form.querySelector('[data-role="cancel"]')?.addEventListener('click', () => modal.close());

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const nameInput = form.querySelector('[name="name"]');
            if (!nameInput.value.trim()) {
                fields.showFormMessage(form, 'Product name is required.');
                nameInput.focus();
                return;
            }

            const submitButton = form.querySelector('[data-role="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = 'Saving…';

            try {
                const url = product ? `/products/${product.id}/update` : '/products';
                const payload = await fields.requestJson(url, {
                    method: 'POST',
                    body: fields.collectProductFormData(form, csrfToken),
                });
                modal.close();
                onSaved?.(payload.product);
            } catch (error) {
                fields.showFormMessage(form, error.message);
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'Save product';
            }
        });

        modal.open(form, { title });
        return form;
    };

    const openCreate = ({ defaultName = '', onSaved } = {}) => {
        openForm({ title: 'Create a new product', defaultName, onSaved });
    };

    const openEdit = async (productId, { onSaved } = {}) => {
        try {
            const payload = await fields.requestJson(`/products/${productId}/edit`, { method: 'GET' });
            openForm({ title: 'Edit product', product: payload.product, onSaved });
        } catch (error) {
            window.alert(error.message || 'Could not load this product.');
        }
    };

    window.PantryProductModal = { openCreate, openEdit };
});
