/**
 * Client-side announcement dismissal.
 *
 * - Warning notices come back after a day so people do not permanently miss
 *   hosting limitations.
 * - Update notices stay dismissed for that version (keyed by version).
 */
const STORAGE_PREFIX = 'bleep.announcement';
const DAY_MS = 24 * 60 * 60 * 1000;

const storageKey = (element) => {
    const type = element.dataset.announcement || 'notice';
    const identifier = element.dataset.announcementKey || type;
    return `${STORAGE_PREFIX}.${identifier}`;
};

const isDismissed = (element) => {
    const key = storageKey(element);
    let stored = null;

    try {
        stored = window.localStorage.getItem(key);
    } catch {
        return false;
    }

    if (!stored) return false;

    if (element.dataset.announcement === 'warning') {
        const dismissedAt = Number(stored);
        if (!Number.isFinite(dismissedAt)) return false;

        const resetHours = Number(element.dataset.announcementResetHours || 24);
        return Date.now() - dismissedAt < resetHours * 60 * 60 * 1000;
    }

    return true;
};

const hide = (element) => {
    element.classList.add('hidden');
    element.setAttribute('aria-hidden', 'true');
};

const dismiss = (element) => {
    try {
        window.localStorage.setItem(
            storageKey(element),
            element.dataset.announcement === 'warning' ? String(Date.now()) : '1'
        );
    } catch {
        // Private mode / storage disabled: still hide for this page view.
    }

    hide(element);
};

const initAnnouncements = () => {
    document.querySelectorAll('[data-announcement]').forEach((element) => {
        if (isDismissed(element)) {
            hide(element);
            return;
        }

        element.querySelectorAll('[data-announcement-dismiss]').forEach((button) => {
            button.addEventListener('click', () => dismiss(element));
        });
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAnnouncements, { once: true });
} else {
    initAnnouncements();
}
