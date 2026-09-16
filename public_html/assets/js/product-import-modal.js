/* /public_html/assets/js/product-import-modal.js */
/*
 * Import an AH product in a modal instead of navigating to /products/
 * import: paste a link, preview the parsed fields in place, tweak if
 * needed, confirm. Reuses the same product field template as the
 * create/edit modal (product-form-modal.js) for the review step, but
 * talks to /products/import/preview and /products/import/store.
 */
document.addEventListener('DOMContentLoaded', () => {
    const fields = window.PantryProductFields;
    const modal = window.PantryModal;
    const urlTemplate = document.querySelector('#product-import-url-template');
    if (!fields || !modal || !urlTemplate) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const buildUrlForm = () => urlTemplate.content.firstElementChild.cloneNode(true);

    const openReviewStep = ({ product, previewToken, existing, onImported }) => {
        const form = fields.buildProductForm();
        fields.fillProductForm(form, product);

        // The AH link already tells us the source URL and image; those
        // fields (and the conversions manager, which only makes sense for
        // an already-saved product) don't apply to a fresh import.
        form.querySelector('[name="source_url"]')?.closest('label')?.remove();
        form.querySelector('[data-role="refresh-image-row"]')?.remove();
        form.querySelector('[data-role="conversions-section"]')?.remove();

        if (existing) {
            const alert = document.createElement('div');
            alert.className = 'full-width alert';
            alert.innerHTML = '<strong>Existing product</strong><p>Saving will update the existing AH product and restore it if archived.</p>';
            form.prepend(alert);
        }

        const meta = document.createElement('p');
        meta.className = 'full-width import-meta';
        meta.textContent = `AH ID: ${product.source_identifier} · Reference: ${product.reference_amount} ${product.reference_unit}`;
        form.querySelector('[name="brand"]')?.closest('label')?.insertAdjacentElement('afterend', meta);

        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = 'preview_token';
        tokenInput.value = previewToken;
        form.appendChild(tokenInput);

        const cancelButton = form.querySelector('[data-role="cancel"]');
        if (cancelButton) cancelButton.textContent = 'Start over';
        cancelButton?.addEventListener('click', () => openUrlStep({ onImported }));

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const submitButton = form.querySelector('[data-role="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = 'Saving…';

            try {
                const formData = fields.collectProductFormData(form, csrfToken);
                formData.set('preview_token', previewToken);
                const payload = await fields.requestJson('/products/import/store', {
                    method: 'POST',
                    body: formData,
                });
                modal.close();
                onImported?.(payload.product);
            } catch (error) {
                fields.showFormMessage(form, error.message);
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'Save product';
            }
        });

        modal.open(form, { title: 'Review AH product' });
    };

    const openUrlStep = ({ onImported } = {}) => {
        const form = buildUrlForm();
        const urlInput = form.querySelector('[name="url"]');

        form.querySelector('[data-role="cancel"]')?.addEventListener('click', () => modal.close());

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const url = urlInput.value.trim();
            if (!url) {
                fields.showFormMessage(form, 'Enter an AH product link.');
                urlInput.focus();
                return;
            }

            const submitButton = form.querySelector('[data-role="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = 'Fetching…';

            try {
                const formData = new FormData();
                formData.set('_csrf', csrfToken);
                formData.set('url', url);
                const payload = await fields.requestJson('/products/import/preview', {
                    method: 'POST',
                    body: formData,
                });
                openReviewStep({
                    product: payload.product,
                    previewToken: payload.preview_token,
                    existing: payload.existing,
                    onImported,
                });
            } catch (error) {
                fields.showFormMessage(form, error.message);
                submitButton.disabled = false;
                submitButton.textContent = 'Preview product';
            }
        });

        modal.open(form, { title: 'Import AH product' });
    };

    window.PantryProductImportModal = { open: openUrlStep };
});
