/* /public_html/assets/js/product-form-fields.js */
/*
 * The product name/brand/package/nutrition fields are identical across
 * three flows - creating a product, editing one, and reviewing an AH
 * import - so they're defined once as a <template> in the layout and
 * this file provides the shared helpers (clone, fill, read, message)
 * that product-form-modal.js and product-import-modal.js both build on.
 */
document.addEventListener('DOMContentLoaded', () => {
    const template = document.querySelector('#product-form-template');
    if (!template) return;

    const NUTRITION_FIELDS = [
        'energy_kj', 'energy_kcal', 'fat_g', 'saturated_fat_g',
        'carbohydrates_g', 'sugars_g', 'fiber_g', 'protein_g', 'salt_g',
    ];

    const TEXT_FIELDS = [
        'name', 'brand', 'source_url', 'package_amount',
        'package_unit', 'package_description', 'reference_amount', 'reference_unit',
    ];

    const buildProductForm = () => template.content.firstElementChild.cloneNode(true);

    const fillProductForm = (form, product) => {
        form.querySelector('[name="name"]').value = product?.name || '';
        form.querySelector('[name="brand"]').value = product?.brand || '';
        form.querySelector('[name="source_url"]').value = product?.source_url || '';
        form.querySelector('[name="package_amount"]').value = product?.package_amount || '';
        form.querySelector('[name="package_unit"]').value = product?.package_unit || '';
        form.querySelector('[name="package_description"]').value = product?.package_description || '';
        form.querySelector('[name="reference_amount"]').value = product?.reference_amount || 100;
        form.querySelector('[name="reference_unit"]').value = product?.reference_unit || 'g';

        NUTRITION_FIELDS.forEach((field) => {
            const input = form.querySelector(`[name="${field}"]`);
            if (input) input.value = product?.[field] || 0;
        });

        const imagePreview = form.querySelector('[data-role="image-preview"]');
        const imagePreviewImg = form.querySelector('[data-role="image-preview-img"]');
        if (product?.image_path && imagePreviewImg) {
            imagePreviewImg.src = product.image_path;
            imagePreview?.classList.remove('is-hidden');
        } else {
            imagePreview?.classList.add('is-hidden');
        }
    };

    const collectProductFormData = (form, csrfToken) => {
        const formData = new FormData();
        formData.set('_csrf', csrfToken);

        TEXT_FIELDS.forEach((field) => {
            const input = form.querySelector(`[name="${field}"]`);
            formData.set(field, input?.value || '');
        });

        NUTRITION_FIELDS.forEach((field) => {
            const input = form.querySelector(`[name="${field}"]`);
            formData.set(field, input?.value || '0');
        });

        const refreshImage = form.querySelector('[name="refresh_image"]');
        if (refreshImage?.checked) {
            formData.set('refresh_image', '1');
        }

        return formData;
    };

    const showFormMessage = (form, text, type = 'error') => {
        const message = form.querySelector('[data-role="message"]');
        if (!message) return;
        message.textContent = text;
        message.className = `ajax-message ajax-message-${type}`;
        message.classList.remove('is-hidden');
    };

    const requestJson = async (url, options = {}) => {
        const response = await fetch(url, {
            method: options.method || 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: options.body,
        });

        const payload = await response.json().catch(() => ({
            error: 'The server returned an invalid response.',
        }));

        if (!response.ok) {
            throw new Error(payload.error || 'The request failed.');
        }

        return payload;
    };

    window.PantryProductFields = {
        NUTRITION_FIELDS,
        buildProductForm,
        fillProductForm,
        collectProductFormData,
        showFormMessage,
        requestJson,
    };
});
