/**
 * Direction-aware mobile navigation strip.
 *
 * The strip is the quick way around on a phone. It tucks itself under the
 * viewport when the page scrolls down, and slides back as soon as the reader
 * scrolls up or reaches the top again. The floating menu button is untouched,
 * so the full slide-over stays one tap away while the strip is hidden.
 */
const initMobileNavStrip = () => {
    const strip = document.getElementById('mobile-nav-strip');

    if (!strip) return;

    const HIDE_AT = 96;
    const REVEAL_AT = 24;
    const DELTA = 6;

    let lastY = window.scrollY || 0;
    let hidden = false;
    let ticking = false;

    const setHidden = (next) => {
        if (next === hidden) return;

        hidden = next;
        strip.classList.toggle('mobile-nav-strip--hidden', hidden);
        strip.toggleAttribute('inert', hidden);
        strip.setAttribute('aria-hidden', hidden ? 'true' : 'false');
    };

    const apply = () => {
        const y = Math.max(0, window.scrollY || 0);
        const delta = y - lastY;

        if (y <= REVEAL_AT) {
            setHidden(false);
        } else if (Math.abs(delta) >= DELTA) {
            setHidden(delta > 0 && y > HIDE_AT);
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
    document.addEventListener('DOMContentLoaded', initMobileNavStrip, { once: true });
} else {
    initMobileNavStrip();
}
