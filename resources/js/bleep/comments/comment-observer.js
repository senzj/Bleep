/**
 * Closes the floating comments panel when the originating bleep card scrolls
 * out of view, and clears the selected-card highlight.
 */
document.addEventListener('DOMContentLoaded', () => {
    let activeObserver = null;
    let selectedCard = null;

    const stopWatching = () => {
        if (activeObserver) {
            activeObserver.disconnect();
            activeObserver = null;
        }

        if (selectedCard) {
            selectedCard.classList.remove('bleep-selected');
            selectedCard = null;
        }
    };

    window.addEventListener('open-comments', (event) => {
        const bleepId = String(event.detail.bleepId);
        stopWatching();

        const card = document.querySelector(`[data-bleep-card="${bleepId}"]`);
        if (!card) return;

        card.classList.add('bleep-selected');
        selectedCard = card;

        // Threshold 0.1 means close after roughly 90% of the card is scrolled away.
        activeObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    window.dispatchEvent(new CustomEvent('close-comments'));
                }
            });
        }, { threshold: 0.1 });

        activeObserver.observe(card);
    });

    window.addEventListener('close-comments', stopWatching);
});
