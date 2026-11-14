/**
 * Same-route navigation guard.
 *
 * Clicking a link that points at the page you are already on reloads the
 * document for no benefit. Instead of disabling those controls (which makes
 * the UI feel broken), swallow the click and leave the button looking normal.
 *
 * Two spam tiers:
 * - 120 same-route attempts inside 10 seconds: that link is put on a one
 *   minute cooldown. Other pages stay reachable.
 * - 1200 attempts inside 60 seconds (scripted hammering): all same-origin
 *   navigation pauses for one minute as a last resort.
 *
 * Deadlines are kept in sessionStorage so a reload cannot bypass them.
 */
const SOFT_WINDOW_MS = 10 * 1000;
const SOFT_MAX_ATTEMPTS = 120;
const HARD_WINDOW_MS = 60 * 1000;
const HARD_MAX_ATTEMPTS = 1200;
const COOLDOWN_MS = 60 * 1000;
const WAIT_STORAGE_KEY = 'bleep.nav-guard.wait-until';
const PAUSE_STORAGE_KEY = 'bleep.nav-guard.paused-until';

const WAIT_MESSAGE = 'Please wait a moment before trying again';

let attempts = [];
let waitUntil = 0;
let pausedUntil = 0;
let notice = null;
let noticeTimer = null;

const routeKey = (url) => {
    const parsed = new URL(url, window.location.href);
    const pathname = parsed.pathname.replace(/\/+$/, '') || '/';

    return `${pathname}${parsed.search}`;
};

const readDeadline = (key) => {
    try {
        return Number(window.sessionStorage.getItem(key)) || 0;
    } catch {
        return 0;
    }
};

const writeDeadline = (key, value) => {
    try {
        if (value > Date.now()) {
            window.sessionStorage.setItem(key, String(value));
        } else {
            window.sessionStorage.removeItem(key);
        }
    } catch {
        // Private mode / storage disabled: the in-memory deadline still works.
    }
};

const secondsLeft = (deadline, now) => Math.max(1, Math.ceil((deadline - now) / 1000));

const showNotice = (message, visibleFor = 4000) => {
    if (!notice) {
        notice = document.createElement('div');
        notice.setAttribute('role', 'status');
        notice.setAttribute('aria-live', 'polite');
        notice.className =
            'alert alert-warning fixed bottom-4 left-1/2 z-[100] w-auto max-w-[min(92vw,24rem)] -translate-x-1/2 text-sm shadow-lg';
        document.body.appendChild(notice);
    }

    notice.textContent = message;
    notice.classList.remove('hidden');

    window.clearTimeout(noticeTimer);
    noticeTimer = window.setTimeout(() => notice.classList.add('hidden'), visibleFor);
};

const isModifiedClick = (event) =>
    event.defaultPrevented ||
    event.button !== 0 ||
    event.metaKey ||
    event.ctrlKey ||
    event.shiftKey ||
    event.altKey;

const targetUrl = (event) => {
    const anchor = event.target instanceof Element ? event.target.closest('a[href]') : null;

    if (!anchor) return null;
    if (anchor.target && anchor.target !== '_self') return null;
    if (anchor.hasAttribute('download')) return null;

    const href = anchor.getAttribute('href');
    if (!href || href.startsWith('#')) return null;

    const url = new URL(anchor.href, window.location.href);
    if (url.origin !== window.location.origin) return null;

    return url;
};

const registerAttempt = (now) => {
    attempts = attempts.filter((attempt) => now - attempt < HARD_WINDOW_MS);
    attempts.push(now);

    return {
        recent: attempts.filter((attempt) => now - attempt < SOFT_WINDOW_MS).length,
        total: attempts.length,
    };
};

const onClick = (event) => {
    if (isModifiedClick(event)) return;

    const url = targetUrl(event);
    if (!url) return;

    const now = Date.now();
    const isSameRoute = routeKey(url.href) === routeKey(window.location.href);

    if (!isSameRoute) {
        // Hard cooldown is the only thing that pauses navigation to other pages.
        if (now < pausedUntil) {
            event.preventDefault();
            showNotice(`${WAIT_MESSAGE} (${secondsLeft(pausedUntil, now)}s).`);
        }

        return;
    }

    // Never re-request the route we are already on. The control stays enabled.
    event.preventDefault();

    if (now < pausedUntil) {
        showNotice(`${WAIT_MESSAGE} (${secondsLeft(pausedUntil, now)}s).`);
        return;
    }

    const { recent, total } = registerAttempt(now);

    if (total >= HARD_MAX_ATTEMPTS) {
        attempts = [];
        pausedUntil = now + COOLDOWN_MS;
        writeDeadline(PAUSE_STORAGE_KEY, pausedUntil);
        showNotice(`${WAIT_MESSAGE} (${COOLDOWN_MS / 1000}s).`);
        return;
    }

    if (now < waitUntil) {
        showNotice(`${WAIT_MESSAGE} (${secondsLeft(waitUntil, now)}s).`);
        return;
    }

    if (recent >= SOFT_MAX_ATTEMPTS) {
        waitUntil = now + COOLDOWN_MS;
        writeDeadline(WAIT_STORAGE_KEY, waitUntil);
        showNotice(`${WAIT_MESSAGE} (${COOLDOWN_MS / 1000}s).`);
    }
};

waitUntil = readDeadline(WAIT_STORAGE_KEY);
pausedUntil = readDeadline(PAUSE_STORAGE_KEY);

if (waitUntil <= Date.now()) {
    writeDeadline(WAIT_STORAGE_KEY, 0);
}

if (pausedUntil <= Date.now()) {
    writeDeadline(PAUSE_STORAGE_KEY, 0);
}

document.addEventListener('click', onClick);
