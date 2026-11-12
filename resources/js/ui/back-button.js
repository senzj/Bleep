/**
 * Delegated back button used by page headers.
 */
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-back-button]');
    if (!button) return;

    const fallback = button.getAttribute('data-back-fallback') || '/';

    if (window.history.length > 1) {
        window.history.back();

        return;
    }

    window.location.href = fallback;
});
