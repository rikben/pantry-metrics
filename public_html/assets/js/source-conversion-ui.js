/* /public_html/assets/js/source-conversion-ui.js */
document.addEventListener('DOMContentLoaded', () => {
    const list = document.querySelector('#source-ingredient-list');

    if (!list) {
        return;
    }

    const updateCard = (card) => {
        const productSelect = card.querySelector(
            '.source-product-select'
        );
        const sourceAmountInput = card.querySelector(
            '.source-amount'
        );
        const sourceUnitSelect = card.querySelector(
            '.source-unit'
        );
        const conversionPanel = card.querySelector(
            '.source-conversion'
        );
        const convertedUnitInput = card.querySelector(
            '.converted-unit'
        );
        const sourceLabel = card.querySelector(
            '.conversion-source-label'
        );

        if (
            !productSelect
            || !sourceAmountInput
            || !sourceUnitSelect
            || !conversionPanel
        ) {
            return false;
        }

        const selectedOption =
            productSelect.selectedOptions[0];
        const referenceUnit =
            selectedOption?.dataset.referenceUnit || '';
        const sourceUnit =
            sourceUnitSelect.value || 'serving';
        const sourceAmount =
            sourceAmountInput.value || '1';

        const conversionRequired =
            productSelect.value !== ''
            && referenceUnit !== ''
            && referenceUnit !== sourceUnit;

        conversionPanel.classList.toggle(
            'is-hidden',
            !conversionRequired
        );

        if (convertedUnitInput) {
            convertedUnitInput.value =
                conversionRequired
                    ? referenceUnit
                    : '';
        }

        if (sourceLabel) {
            sourceLabel.textContent =
                `${sourceAmount} ${sourceUnit}`;
        }

        conversionPanel.setAttribute(
            'aria-hidden',
            conversionRequired ? 'false' : 'true'
        );

        card.dataset.conversionRequired =
            conversionRequired ? '1' : '0';

        return conversionRequired;
    };

    list.querySelectorAll(
        '.source-ingredient-card'
    ).forEach(updateCard);

    list.addEventListener('change', (event) => {
        const target = event.target;

        if (
            !target.matches(
                '.source-product-select, '
                + '.source-amount, '
                + '.source-unit'
            )
        ) {
            return;
        }

        const card = target.closest(
            '.source-ingredient-card'
        );

        if (card) {
            updateCard(card);
        }
    });

    /*
     * Other scripts select a product programmatically after returning
     * from Create/Import. Re-evaluate once after they have run.
     */
    window.setTimeout(() => {
        list.querySelectorAll(
            '.source-ingredient-card'
        ).forEach(updateCard);
    }, 250);
});
