import { createIcons } from 'lucide';
import { VanillaIcons } from './icons/vanilla/index.js';

// Render every pending icon inside a single root. Kept private so all public
// entry points are queued and can never block the main thread in one shot.
const renderNow = (root) => {
    if (!root) {
        return;
    }

    createIcons({ icons: VanillaIcons, root });
};

window.lucide = {
    createIcons: (opts = {}) =>
        createIcons({
            icons: VanillaIcons,
            ...opts,
        }),
    icons: VanillaIcons,
};

if (typeof window.lucide.replace === 'undefined') {
    window.lucide.replace = function (opts = {}) {
        try {
            window.lucide.createIcons(opts);
        } catch (e) {
            console.error('Error replacing icons:', e);
        }
    };
}

// ── Chunked icon renderer ──────────────────────────────────────────────────
// A feed page can contain a thousand icons. Replacing them all in one task
// blocks scrolling for seconds and floods Alpine's mutation observer (which
// compares every added node against every removed one). Instead we render a
// small batch per animation frame, in DOM order, so above-the-fold icons are
// ready almost immediately and the rest fill in while the page stays usable.
const ICON_BATCH = 24;
const FRAME_BUDGET_MS = 8;

let queue = [];
let cursor = 0;
let scheduled = false;
const queued = new WeakSet();

const schedule = () => {
    if (scheduled) {
        return;
    }

    scheduled = true;

    const run = () => {
        scheduled = false;
        drainQueue();
    };

    if (typeof window.requestAnimationFrame === 'function') {
        window.requestAnimationFrame(run);
    } else {
        window.setTimeout(run, 16);
    }
};

const collect = (root) => {
    if (!root || typeof root.querySelectorAll !== 'function') {
        return;
    }

    const add = (el) => {
        if (el && !queued.has(el)) {
            queued.add(el);
            queue.push(el);
        }
    };

    if (root.matches?.('[data-lucide]')) {
        add(root);
    }

    root.querySelectorAll('[data-lucide]').forEach(add);
};

const drainQueue = () => {
    const startedAt = performance.now();
    const parents = new Set();
    let touched = 0;

    while (
        cursor < queue.length &&
        touched < ICON_BATCH &&
        performance.now() - startedAt < FRAME_BUDGET_MS
    ) {
        const el = queue[cursor++];

        // Icons are replaced by <svg>, so a stale node is already handled.
        if (!el.isConnected || !el.hasAttribute('data-lucide')) {
            continue;
        }

        const parent = el.parentElement;

        if (parent) {
            parents.add(parent);
            touched++;
        }
    }

    parents.forEach((parent) => {
        try {
            renderNow(parent);
        } catch (error) {
            console.error('Error rendering icons:', error);
        }
    });

    if (cursor < queue.length) {
        schedule();
    } else {
        queue = [];
        cursor = 0;
    }
};

/**
 * Queue icons inside a container. Safe to call as often as needed: only the
 * first call for a node is queued and work is spread over animation frames.
 */
window.renderLucideIcons = (root = document) => {
    collect(root);
    schedule();
};

// Backwards-compatible helper. It now routes through the chunked renderer so
// existing call sites (cards, toasts, AJAX fragments) stay non-blocking.
window.createLucideIcons = function (container) {
    window.renderLucideIcons(container || document);
};

// Small, one-shot convenience for media/player setup: run after the browser
// has painted the page so first interaction is never blocked by enhancement.
window.whenIdle = function (callback, timeout = 1500) {
    if (typeof callback !== 'function') {
        return;
    }

    if ('requestIdleCallback' in window) {
        window.requestIdleCallback(callback, { timeout });
    } else {
        window.setTimeout(callback, 1);
    }
};

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        () => window.renderLucideIcons(document),
        { once: true }
    );
} else {
    window.renderLucideIcons(document);
}
