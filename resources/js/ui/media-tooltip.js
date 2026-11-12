/**
 * Media extension badge tooltip.
 *
 * Desktop shows the card on hover/focus. Touch devices have no hover, so a
 * tap (or keyboard focus) pins the card open until the user taps elsewhere.
 */
(() => {
    const OPEN_CLASS = 'is-open';
    const badges = () => document.querySelectorAll('[data-media-ext-badge]');

    const closeAll = (except = null) => {
        badges().forEach((badge) => {
            if (badge !== except) {
                badge.classList.remove(OPEN_CLASS);
                badge.setAttribute('aria-expanded', 'false');
            }
        });
    };

    const toggle = (badge) => {
        const shouldOpen = !badge.classList.contains(OPEN_CLASS);

        closeAll(badge);
        badge.classList.toggle(OPEN_CLASS, shouldOpen);
        badge.setAttribute('aria-expanded', String(shouldOpen));
    };

    const init = () => {
        badges().forEach((badge) => {
            badge.setAttribute('aria-expanded', 'false');

            badge.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                toggle(badge);
            });

            badge.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    toggle(badge);
                }

                if (event.key === 'Escape') {
                    badge.classList.remove(OPEN_CLASS);
                    badge.setAttribute('aria-expanded', 'false');
                }
            });
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest?.('[data-media-ext-badge]')) {
                closeAll();
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
