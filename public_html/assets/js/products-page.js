/* /public_html/assets/js/products-page.js */
/*
 * Wires the /products list's "Add manually", "Import from AH" and
 * per-row "Edit" actions to the shared modals (product-form-modal.js,
 * product-import-modal.js) instead of navigating to a separate page,
 * and keeps the table in sync (insert on create/import, replace on
 * edit) without a full reload.
 */
document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.querySelector('#products-table-body');
    const createButton = document.querySelector('#create-product-button');
    const importButton = document.querySelector('#import-product-button');

    if (!tbody) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

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

    const formatNumber = (value, digits = 1) => Number(value || 0).toLocaleString('en-US', {
        maximumFractionDigits: digits,
    });

    const buildRow = (product) => {
        const row = document.createElement('tr');
        row.dataset.productId = product.id;
        row.dataset.search = [product.name, product.brand, product.source_identifier]
            .filter(Boolean)
            .join(' ')
            .toLocaleLowerCase('nl-NL');

        const imageCell = document.createElement('td');
        if (product.image_path) {
            const image = document.createElement('img');
            image.className = 'product-list-image';
            image.src = product.image_path;
            image.alt = '';
            image.loading = 'lazy';
            imageCell.appendChild(image);
        } else {
            const placeholder = document.createElement('span');
            placeholder.className = 'product-image-placeholder';
            placeholder.setAttribute('aria-label', 'No image');
            placeholder.appendChild(svg(
                'M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5v-13Zm2 11.25 '
                + '3.4-3.4a1 1 0 0 1 1.42 0l1.43 1.43 2.65-2.65a1 1 0 0 1 1.42 0L18 14.31V6H6v10.75ZM8.5 10A1.5 1.5 0 1 0 8.5 7a1.5 1.5 0 0 0 0 3Z'
            ));
            imageCell.appendChild(placeholder);
        }

        const nameCell = document.createElement('td');
        const strong = document.createElement('strong');
        strong.textContent = product.name || '';
        nameCell.appendChild(strong);
        if (!product.owner_user_id) {
            const badge = document.createElement('span');
            badge.className = 'shared-badge';
            badge.textContent = 'Shared';
            nameCell.appendChild(document.createTextNode(' '));
            nameCell.appendChild(badge);
        }
        if (product.brand) {
            const small = document.createElement('small');
            small.textContent = product.brand;
            nameCell.appendChild(small);
        }

        const packageCell = document.createElement('td');
        packageCell.textContent = product.package_description
            || (product.package_amount ? `${product.package_amount} ${product.package_unit || ''}`.trim() : 'Unknown');

        const ahCell = document.createElement('td');
        ahCell.textContent = product.source_identifier || '—';

        const referenceCell = document.createElement('td');
        referenceCell.textContent = `${product.reference_amount} ${product.reference_unit}`;

        const kcalCell = document.createElement('td');
        kcalCell.textContent = formatNumber(product.energy_kcal);

        const proteinCell = document.createElement('td');
        proteinCell.textContent = `${formatNumber(product.protein_g)} g`;

        const actionsCell = document.createElement('td');
        const actions = document.createElement('div');
        actions.className = 'icon-actions';

        const editButton = document.createElement('button');
        editButton.className = 'icon-button';
        editButton.type = 'button';
        editButton.dataset.action = 'edit-product';
        editButton.title = 'Edit product';
        editButton.setAttribute('aria-label', `Edit ${product.name}`);
        editButton.appendChild(svg(
            'm15.23 5.21 3.56 3.56L8.06 19.5H4.5v-3.56L15.23 5.21Zm1.42-1.42 1.06-1.06a2 2 0 0 1 2.83 0l.73.73a2 2 0 0 1 0 2.83l-1.06 1.06-3.56-3.56Z'
        ));

        const archiveForm = document.createElement('form');
        archiveForm.method = 'post';
        archiveForm.action = `/products/${product.id}/archive`;
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_csrf';
        csrfInput.value = csrfToken;
        const archiveButton = document.createElement('button');
        archiveButton.className = 'icon-button icon-button-danger';
        archiveButton.type = 'submit';
        archiveButton.title = 'Archive product';
        archiveButton.setAttribute('aria-label', `Archive ${product.name}`);
        archiveButton.appendChild(svg(
            'M7 4V2h10v2h5v2h-2l-1 15H5L4 6H2V4h5Zm2 4v9h2V8H9Zm4 0v9h2V8h-2Z'
        ));
        archiveForm.append(csrfInput, archiveButton);

        actions.append(editButton, archiveForm);
        actionsCell.appendChild(actions);

        row.append(
            imageCell, nameCell, packageCell, ahCell,
            referenceCell, kcalCell, proteinCell, actionsCell
        );

        return row;
    };

    const upsertRow = (product) => {
        if (!product) return;

        const newRow = buildRow(product);
        const existingRow = tbody.querySelector(`tr[data-product-id="${product.id}"]`);

        if (existingRow) {
            existingRow.replaceWith(newRow);
        } else {
            newRow.classList.add('row-enter');
            tbody.prepend(newRow);
        }

        window.pantryApplyProductFilter?.();
    };

    createButton?.addEventListener('click', () => {
        window.PantryProductModal?.openCreate({ onSaved: upsertRow });
    });

    importButton?.addEventListener('click', () => {
        window.PantryProductImportModal?.open({ onImported: upsertRow });
    });

    tbody.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-action="edit-product"]');
        if (!trigger) return;

        const row = trigger.closest('tr[data-product-id]');
        const productId = row?.dataset.productId;
        if (!productId) return;

        window.PantryProductModal?.openEdit(productId, { onSaved: upsertRow });
    });
});
