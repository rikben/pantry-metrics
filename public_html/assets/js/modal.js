/* /public_html/assets/js/modal.js */
/*
 * Generic modal dialog shell, shared by every "don't navigate away"
 * flow (create/edit a product, import from AH, ...). Callers hand it a
 * DOM node to display; this file only knows how to show/hide it, trap
 * focus, and close on Escape/backdrop click. #app-modal is rendered
 * once in the layout so every page can use window.PantryModal.
 */
document.addEventListener('DOMContentLoaded', () => {
    const overlay = document.querySelector('#app-modal');
    const titleElement = document.querySelector('#app-modal-title');
    const bodyElement = document.querySelector('#app-modal-body');
    const closeButton = document.querySelector('#app-modal-close');

    if (!overlay || !bodyElement) {
        return;
    }

    let lastFocused = null;
    let onCloseCallback = null;

    const isOpen = () => !overlay.classList.contains('is-hidden');

    const close = () => {
        if (!isOpen()) return;

        overlay.classList.add('is-hidden');
        overlay.setAttribute('aria-hidden', 'true');
        bodyElement.replaceChildren();
        document.body.classList.remove('modal-open');

        const callback = onCloseCallback;
        onCloseCallback = null;

        const toFocus = lastFocused;
        lastFocused = null;
        toFocus?.focus?.();

        callback?.();
    };

    const open = (contentNode, { title = '', onClose = null } = {}) => {
        if (titleElement) titleElement.textContent = title;
        bodyElement.replaceChildren(contentNode);

        overlay.classList.remove('is-hidden');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        onCloseCallback = onClose;
        lastFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;

        window.setTimeout(() => {
            const focusTarget = bodyElement.querySelector('input, select, textarea, button:not([data-role="cancel"])');
            (focusTarget || closeButton)?.focus();
        }, 20);
    };

    closeButton?.addEventListener('click', close);

    overlay.addEventListener('mousedown', (event) => {
        if (event.target === overlay) close();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            close();
        }
    });

    // Rudimentary focus trap: keep Tab cycling inside the dialog panel.
    overlay.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab' || !isOpen()) return;

        const focusable = Array.from(
            overlay.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')
        ).filter((element) => !element.disabled && element.offsetParent !== null);

        if (focusable.length === 0) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    window.PantryModal = { open, close, isOpen };
});
