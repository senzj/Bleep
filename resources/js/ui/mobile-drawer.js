/**
 * Mobile slide-over menu and shared Lucide refresh.
 *
 * There is one trigger in every layout and mode: the fixed bottom-right
 * button rendered by the mobile nav component.
 */
document.addEventListener('DOMContentLoaded', () => {
    const menuBtn = document.getElementById('mobile-menu-btn');
    const closeBtn = document.getElementById('hor-mobile-close-btn');
    const drawer = document.getElementById('hor-mobile-drawer');
    const overlay = document.getElementById('hor-mobile-overlay');

    if (!drawer || !overlay) return;

    let isOpen = false;

    const setOpen = (open) => {
        if (open === isOpen) return;
        isOpen = open;

        drawer.classList.toggle('-translate-x-full', !open);
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        overlay.classList.toggle('hidden', !open);
        menuBtn?.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.style.overflow = open ? 'hidden' : '';

        if (open) {
            closeBtn?.focus({ preventScroll: true });
        } else {
            menuBtn?.focus({ preventScroll: true });
        }
    };

    menuBtn?.addEventListener('click', () => setOpen(true));
    closeBtn?.addEventListener('click', () => setOpen(false));
    overlay.addEventListener('click', () => setOpen(false));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen) setOpen(false);
    });

    window.createLucideIcons?.();
});
