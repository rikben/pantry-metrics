/* /public_html/assets/js/source-return-flow.js */
document.addEventListener('DOMContentLoaded', () => {
    const configElement = document.querySelector('#recipe-page-config');

    if (!configElement) {
        return;
    }

    let config;

    try {
        config = JSON.parse(configElement.textContent);
    } catch {
        return;
    }

    const sourceIngredientId =
        Number(config.selectedSourceIngredientId || 0);
    const productId =
        Number(config.selectedProductId || 0);

    if (sourceIngredientId < 1 || productId < 1) {
        return;
    }

    const accordion =
        document.querySelector('#source-ingredients');
    const card = document.querySelector(
        `[data-source-ingredient-id="${sourceIngredientId}"]`
    );

    if (!card) {
        return;
    }

    if (accordion instanceof HTMLDetailsElement) {
        accordion.open = true;
    }

    const productSelect =
        card.querySelector('.source-product-select');

    if (productSelect) {
        const matchingOption = Array.from(
            productSelect.options
        ).some((option) => {
            return option.value === String(productId);
        });

        if (matchingOption) {
            productSelect.value = String(productId);
            productSelect.dispatchEvent(
                new Event('change', {
                    bubbles: true,
                })
            );
        }
    }

    window.requestAnimationFrame(() => {
        window.setTimeout(() => {
            card.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });

            card.classList.add(
                'source-return-highlight'
            );

            window.setTimeout(() => {
                card.classList.remove(
                    'source-return-highlight'
                );
            }, 2400);

            /*
             * Focus the conversion amount if the selected product
             * needs a culinary-unit conversion. Otherwise focus the
             * link button so the user can confirm the mapping.
             */
            const conversion = card.querySelector(
                '.source-conversion'
            );
            const conversionRequired =
                conversion
                && !conversion.classList.contains(
                    'is-hidden'
                );

            if (conversionRequired) {
                card.querySelector(
                    '.converted-amount'
                )?.focus();
            } else {
                card.querySelector(
                    '.source-link-button'
                )?.focus();
            }
        }, 120);
    });
});
