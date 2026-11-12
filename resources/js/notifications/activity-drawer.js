/**
 * Activity drawer (notifications + announcements) for compact layouts.
 */
document.addEventListener('DOMContentLoaded', () => {
    const drawer = document.getElementById('activity-drawer');
    const panel = document.getElementById('activity-drawer-panel');
    const overlay = document.getElementById('activity-drawer-overlay');
    const openBtn = document.getElementById('activity-drawer-open');
    const closeBtn = document.getElementById('activity-drawer-close');
    const tabs = Array.from(document.querySelectorAll('.activity-tab'));
    const panels = Array.from(document.querySelectorAll('[data-activity-panel]'));

    if (!drawer || !panel || !overlay) return;

    let isOpen = false;
    let lastFocused = null;

    const setOpen = (open) => {
        if (open === isOpen) return;
        isOpen = open;

        if (open) {
            lastFocused = document.activeElement;
            drawer.classList.remove('hidden');
            drawer.setAttribute('aria-hidden', 'false');
            openBtn?.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
            requestAnimationFrame(() => {
                panel.classList.remove('translate-x-full');
                overlay.classList.add('opacity-100');
            });
            requestAnimationFrame(() => closeBtn?.focus({ preventScroll: true }));
        } else {
            panel.classList.add('translate-x-full');
            overlay.classList.remove('opacity-100');
            openBtn?.setAttribute('aria-expanded', 'false');
            drawer.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            window.setTimeout(() => {
                if (!isOpen) drawer.classList.add('hidden');
            }, 300);
            if (lastFocused && document.contains(lastFocused)) {
                lastFocused.focus({ preventScroll: true });
            }
        }
    };

    const setTab = (name) => {
        tabs.forEach((tab) => {
            const active = tab.dataset.activityTab === name;
            tab.classList.toggle('btn-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach((p) => {
            p.classList.toggle('hidden', p.dataset.activityPanel !== name);
        });
    };

    openBtn?.addEventListener('click', () => setOpen(true));
    closeBtn?.addEventListener('click', () => setOpen(false));
    overlay.addEventListener('click', () => setOpen(false));

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => setTab(tab.dataset.activityTab));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen) setOpen(false);
    });

    // Keep the drawer trigger badge in sync with the live bell.
    document.addEventListener('notifications:unread-updated', (event) => {
        const count = Number(event.detail?.count || 0);
        const badge = openBtn?.querySelector('[data-activity-unread-badge]');
        const tabBadge = document.querySelector('[data-activity-tab-badge]');

        if (badge) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.classList.toggle('hidden', count === 0);
        }

        if (tabBadge) {
            tabBadge.textContent = count > 99 ? '99+' : String(count);
            tabBadge.classList.toggle('hidden', count === 0);
        }
    });

    window.createLucideIcons?.(drawer);
});
