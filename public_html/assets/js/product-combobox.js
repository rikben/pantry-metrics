/* /public_html/assets/js/product-combobox.js */
/*
 * Progressively enhances a <select data-combobox> product picker into a
 * searchable combobox: type to filter by name/brand/AH id, see a
 * thumbnail and package info per row, pick with the mouse or the
 * keyboard. The underlying <select> stays in the DOM and keeps its
 * `name` attribute, so form submission and every existing "change"
 * listener (package-button, unit-conversion panels, ...) keep working
 * unmodified - this only replaces how the picker looks and is searched.
 */
document.addEventListener('DOMContentLoaded', () => {
    const enhance = (select) => {
        if (select.dataset.comboboxReady === '1') {
            return;
        }

        select.dataset.comboboxReady = '1';

        const options = () => Array.from(select.options).filter((option) => option.value !== '');
        const placeholderText = select.options[0]?.textContent?.trim() || 'Search a product…';

        const wrapper = document.createElement('div');
        wrapper.className = 'combobox';

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'combobox-input';
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('autocomplete', 'off');
        input.placeholder = placeholderText;

        if (select.required) {
            input.dataset.required = '1';
        }

        const list = document.createElement('ul');
        list.className = 'combobox-list is-hidden';
        list.setAttribute('role', 'listbox');

        const listId = `combobox-list-${Math.random().toString(36).slice(2, 9)}`;
        list.id = listId;
        input.setAttribute('aria-controls', listId);

        select.classList.add('combobox-native-select');
        select.setAttribute('aria-hidden', 'true');
        select.tabIndex = -1;

        select.insertAdjacentElement('afterend', wrapper);
        wrapper.append(input, list);

        let activeIndex = -1;
        let visibleOptions = [];

        const optionMeta = (option) => ({
            value: option.value,
            name: option.dataset.name || option.textContent.trim(),
            meta: option.dataset.meta || '',
            image: option.dataset.image || '',
            search: (option.dataset.search || option.textContent).toLocaleLowerCase('nl-NL'),
        });

        const labelFor = (option) => {
            const meta = optionMeta(option);
            return meta.meta ? `${meta.name} · ${meta.meta}` : meta.name;
        };

        const syncInputFromSelect = () => {
            const selected = select.selectedOptions[0];
            input.value = selected && selected.value !== '' ? labelFor(selected) : '';
        };

        const closeList = () => {
            list.classList.add('is-hidden');
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            activeIndex = -1;
        };

        const highlight = (index) => {
            const items = Array.from(list.children);
            items.forEach((item, itemIndex) => {
                item.classList.toggle('is-active', itemIndex === index);
            });

            activeIndex = index;

            if (index >= 0 && items[index]) {
                items[index].scrollIntoView({ block: 'nearest' });
                input.setAttribute('aria-activedescendant', items[index].id);
            } else {
                input.removeAttribute('aria-activedescendant');
            }
        };

        const selectOption = (option) => {
            select.value = option.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            syncInputFromSelect();
            closeList();
        };

        const renderList = (query) => {
            const normalized = query.trim().toLocaleLowerCase('nl-NL');
            visibleOptions = options().filter((option) => optionMeta(option).search.includes(normalized));

            list.replaceChildren();

            if (visibleOptions.length === 0) {
                const empty = document.createElement('li');
                empty.className = 'combobox-empty';
                empty.textContent = 'No matching products.';
                list.appendChild(empty);
                list.classList.remove('is-hidden');
                input.setAttribute('aria-expanded', 'true');
                return;
            }

            visibleOptions.slice(0, 60).forEach((option, index) => {
                const meta = optionMeta(option);
                const item = document.createElement('li');
                item.className = 'combobox-option';
                item.id = `${listId}-option-${index}`;
                item.setAttribute('role', 'option');
                item.dataset.value = option.value;

                if (meta.image) {
                    const image = document.createElement('img');
                    image.className = 'combobox-option-image';
                    image.src = meta.image;
                    image.alt = '';
                    image.loading = 'lazy';
                    item.appendChild(image);
                } else {
                    const placeholder = document.createElement('span');
                    placeholder.className = 'combobox-option-image combobox-option-image-empty';
                    placeholder.setAttribute('aria-hidden', 'true');
                    item.appendChild(placeholder);
                }

                const text = document.createElement('span');
                text.className = 'combobox-option-text';

                const name = document.createElement('strong');
                name.textContent = meta.name;
                text.appendChild(name);

                if (meta.meta) {
                    const small = document.createElement('small');
                    small.textContent = meta.meta;
                    text.appendChild(small);
                }

                item.appendChild(text);

                item.addEventListener('mousedown', (event) => {
                    // Prevent the input from blurring before the click registers.
                    event.preventDefault();
                });
                item.addEventListener('click', () => selectOption(option));

                list.appendChild(item);
            });

            list.classList.remove('is-hidden');
            input.setAttribute('aria-expanded', 'true');
            highlight(-1);
        };

        input.addEventListener('input', () => {
            renderList(input.value);

            if (input.value.trim() === '' && select.value !== '') {
                select.value = '';
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        input.addEventListener('focus', () => renderList(input.value));

        input.addEventListener('keydown', (event) => {
            if (list.classList.contains('is-hidden') && event.key !== 'Escape') {
                renderList(input.value);
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                highlight(Math.min(activeIndex + 1, visibleOptions.length - 1));
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                highlight(Math.max(activeIndex - 1, 0));
            } else if (event.key === 'Enter') {
                if (activeIndex >= 0 && visibleOptions[activeIndex]) {
                    event.preventDefault();
                    selectOption(visibleOptions[activeIndex]);
                }
            } else if (event.key === 'Escape') {
                closeList();
            }
        });

        input.addEventListener('blur', () => {
            // Allow a pending click on a list item to run first.
            window.setTimeout(() => {
                syncInputFromSelect();
                closeList();
            }, 120);
        });

        document.addEventListener('click', (event) => {
            if (!wrapper.contains(event.target)) {
                closeList();
            }
        });

        // Re-render if something else (e.g. a "Returning from Create
        // product" flow) sets select.value programmatically.
        select.addEventListener('change', () => {
            if (document.activeElement !== input) {
                syncInputFromSelect();
            }
        });

        syncInputFromSelect();
    };

    document.querySelectorAll('select[data-combobox]').forEach(enhance);

    // Cards for imported-ingredient linking can be revealed later
    // (accordion opens), but all of them exist at load time in this
    // app, so a single pass covers every instance.
});
