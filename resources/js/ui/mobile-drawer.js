/**
 * Mobile slide-over menu and shared Lucide refresh.
 *
 * There is one trigger in every layout and mode: the floating menu button
 * rendered by the mobile nav component. The drawer sits on the same side as
 * that button, so a bottom-left button opens a left drawer and so on.
 */
document.addEventListener('DOMContentLoaded', () => {
    const menuBtn = document.getElementById('mobile-menu-btn');
    const stripMenuBtn = document.getElementById('mobile-strip-menu-btn');
    const closeBtn = document.getElementById('hor-mobile-close-btn');
    const drawer = document.getElementById('hor-mobile-drawer');
    const overlay = document.getElementById('hor-mobile-overlay');

    if (!drawer || !overlay) return;

    const fromRight = drawer.dataset.side === 'right';
    const hiddenClass = fromRight ? 'translate-x-full' : '-translate-x-full';

    let isOpen = false;

    const setOpen = (open) => {
        if (open === isOpen) return;
        isOpen = open;

        drawer.classList.toggle(hiddenClass, !open);
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        overlay.classList.toggle('hidden', !open);
        menuBtn?.setAttribute('aria-expanded', open ? 'true' : 'false');
        stripMenuBtn?.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.style.overflow = open ? 'hidden' : '';

        if (open) {
            closeBtn?.focus({ preventScroll: true });
        } else {
            (menuBtn ?? stripMenuBtn)?.focus({ preventScroll: true });
        }
    };

    menuBtn?.addEventListener('click', () => setOpen(true));
    stripMenuBtn?.addEventListener('click', () => setOpen(true));
    closeBtn?.addEventListener('click', () => setOpen(false));
    overlay.addEventListener('click', () => setOpen(false));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen) setOpen(false);
    });

    window.createLucideIcons?.();
});
