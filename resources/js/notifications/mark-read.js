/**
 * Marks a notification as read when its row is activated, then lets the
 * browser follow the link (or stay on the page for href="#" rows).
 */
document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-notification-id]');

    if (!link || link.dataset.notificationRead === '1') {
        return;
    }

    if (link.getAttribute('href') === '#') {
        event.preventDefault();
    }

    link.dataset.notificationRead = '1';

    const template = document.querySelector('meta[name="notification-read-url"]')?.content;
    if (!template) return;

    const url = template.replace('__ID__', encodeURIComponent(link.dataset.notificationId));

    fetch(url, {
        method: 'POST',
        keepalive: true,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
    }).catch(() => {});
});
