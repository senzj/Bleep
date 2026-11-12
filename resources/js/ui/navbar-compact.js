/**
 * Direction-aware navbar collapse.
 *
 * Scrolling down hides the full navbar and reveals the floating compact
 * clusters (brand + nav links on the left, notifications + profile on the
 * right). Page tabs rise into the empty centre of the row. Scrolling up
 * restores the full navbar; near the top it is always expanded.
 */
const initCompactNavbar = () => {
    const navbar = document.getElementById('app-navbar');
    const compactNav = document.getElementById('compact-nav');

    // Guests have no compact profile/nav cluster, so keep the full bar.
    if (!navbar || !compactNav) return;

    const COMPACT_AT = 96;
    const REVEAL_AT = 24;
    const DELTA = 6;

    let lastY = window.scrollY || 0;
    let compact = false;
    let ticking = false;

    const setCompact = (next) => {
        if (next === compact) return;

        compact = next;
        document.body.classList.toggle('nav-compact', compact);
        compactNav.setAttribute('aria-hidden', compact ? 'false' : 'true');
        navbar.setAttribute('aria-hidden', compact ? 'true' : 'false');
    };

    const apply = () => {
        const y = Math.max(0, window.scrollY || 0);
        const delta = y - lastY;

        navbar.classList.toggle('is-scrolled', y > 4);

        if (y <= REVEAL_AT) {
            setCompact(false);
        } else if (Math.abs(delta) >= DELTA) {
            // Scrolling down past the threshold collapses; scrolling up reveals.
            setCompact(delta > 0 && y > COMPACT_AT);
        }

        lastY = y;
        ticking = false;
    };

    const onScroll = () => {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(apply);
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    apply();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCompactNavbar, { once: true });
} else {
    initCompactNavbar();
}
