/**
 * Keeps the compact/full navigation badges in sync with the live counters.
 */
(() => {
    const syncBadges = (selector, count) => {
        document.querySelectorAll(selector).forEach((badge) => {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.classList.remove('hidden');
            } else {
                badge.textContent = '';
                badge.classList.add('hidden');
            }
        });
    };

    document.addEventListener('notifications:unread-updated', (event) => {
        syncBadges('.nav-notification-unread-badge', Number(event.detail?.count || 0));
    });

    document.addEventListener('chat:unread-updated', (event) => {
        syncBadges('.nav-chat-unread-badge', Number(event.detail?.count || 0));
    });
})();
