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
        const originalOptions = Array.from(select.options).slice(1);

        search.addEventListener('input', () => {
            const query = search.value.trim().toLocaleLowerCase('nl-NL');
            const selectedValue = select.value;

            select.replaceChildren(select.options[0]);

            originalOptions
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
        amount.dispatchEvent(new Event('input', { bubbles: true }));
    });

    const ingredientForm = document.querySelector('#ingredient-form');
    const redirectInput = document.querySelector('#ingredient-redirect-to');
    const dialog = document.querySelector('#save-progress-dialog');

    document.querySelectorAll('.save-before-leave').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (!ingredientForm || !dialog || !redirectInput) return;

            const dirty = Array.from(
                ingredientForm.querySelectorAll('input:not([type="hidden"]), select, textarea')
            ).some((field) => field.value.trim() !== '');

            if (!dirty) return;

            event.preventDefault();
            const target = link.dataset.target || link.getAttribute('href') || '/products';
            dialog.showModal();

            dialog.addEventListener('close', () => {
                if (dialog.returnValue === 'save') {
                    redirectInput.value = target;
                    ingredientForm.requestSubmit();
                } else if (dialog.returnValue === 'leave') {
                    window.location.href = target;
                }
            }, { once: true });
        });
    });
});
