/**
 * Mobile slide-over menu (horizontal layout) and shared Lucide refresh.
 */
document.addEventListener('DOMContentLoaded', () => {
    const menuButtons = [
        document.getElementById('hor-mobile-menu-btn'),
        document.getElementById('compact-mobile-menu-btn'),
    ];

    const closeBtn = document.getElementById('hor-mobile-close-btn');
    const drawer = document.getElementById('hor-mobile-drawer');
    const overlay = document.getElementById('hor-mobile-overlay');

    if (!drawer || !overlay) return;

    const openDrawer = () => {
        drawer.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
    };

    const closeDrawer = () => {
        drawer.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    };

    menuButtons.forEach((button) => button?.addEventListener('click', openDrawer));
    closeBtn?.addEventListener('click', closeDrawer);
    overlay.addEventListener('click', closeDrawer);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeDrawer();
    });

    window.createLucideIcons?.();
});
