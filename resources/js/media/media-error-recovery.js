/**
 * Media load-error recovery.
 *
 * Watches images, audio, and video for load failures (including "taking too
 * long") and shows an inline error card with a Retry button. The retry busts
 * the browser cache so a transient failure does not stick forever.
 */
(() => {
    const IMAGE_TIMEOUT = 20000;
    const MEDIA_TIMEOUT = 45000;
    const seen = new WeakSet();
    const timers = new WeakMap();
    const SELECTOR = 'img, video, audio';
    const IGNORED_SELECTOR = '#media-modal, [data-media-error-ignore]';

    /**
     * The media viewer deliberately clears image/video sources when it closes.
     * Those resets must not be mistaken for failed loads, or the next open
     * flashes a stale "could not be loaded" card before the new media arrives.
     */
    const isIgnored = (element) => Boolean(element.closest?.(IGNORED_SELECTOR));

    const currentUrl = (element) => {
        if (element.currentSrc) return element.currentSrc;
        if (element.src) return element.src;
        if (element.dataset.src || element.dataset.mediaSrc) return element.dataset.src || element.dataset.mediaSrc;

        const source = element.querySelector('source');

        return source?.src || source?.dataset?.src || source?.dataset?.mediaSrc || '';
    };

    const clearTimer = (element) => {
        const timer = timers.get(element);
        if (timer) window.clearTimeout(timer);
        timers.delete(element);
    };

    const overlayFor = (element) => {
        const parent = element.parentElement;
        if (!parent) return null;

        let overlay = parent.querySelector(':scope > [data-media-error]');

        if (!overlay) {
            if (getComputedStyle(parent).position === 'static') {
                parent.classList.add('relative');
            }

            overlay = document.createElement('div');
            overlay.dataset.mediaError = '';
            overlay.className = 'absolute inset-0 z-20 grid place-items-center rounded-[inherit] bg-base-300/95 p-3 text-center';
            overlay.innerHTML = `
                <div class="max-w-[16rem] space-y-2">
                    <p class="text-xs font-medium leading-snug text-base-content/80" data-media-error-message>Media could not be loaded.</p>
                    <button type="button" class="btn btn-primary btn-xs gap-1 rounded-lg" data-media-retry>
                        <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i>
                        Retry
                    </button>
                </div>
            `;

            overlay.querySelector('[data-media-retry]')?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                retry(element);
            });

            parent.appendChild(overlay);
        }

        return overlay;
    };

    const hideError = (element) => {
        element.parentElement?.querySelector(':scope > [data-media-error]')?.remove();
        element.removeAttribute('data-media-error-shown');
    };

    const showError = (element, message) => {
        if (isIgnored(element)) return;

        const overlay = overlayFor(element);
        if (!overlay) return;

        const text = overlay.querySelector('[data-media-error-message]');
        if (text) text.textContent = message;

        if (element.tagName !== 'IMG') {
            try {
                element.pause();
            } catch {
                // Ignore elements that are not ready yet.
            }
        }

        element.dataset.mediaErrorShown = '1';
        window.createLucideIcons?.(overlay);
    };

    const classify = async (url) => {
        if (!url) return 'Media could not be loaded.';

        try {
            const response = await fetch(url, { method: 'HEAD', credentials: 'same-origin', cache: 'no-store' });

            if (response.status === 404 || response.status === 410) return 'This media file could not be found.';
            if (response.status === 403) return 'You do not have access to this media.';
            if (response.status >= 500) return 'The server could not load this media.';
            if (!response.ok) return `Media could not be loaded (${response.status}).`;

            return 'Media could not be loaded.';
        } catch {
            return 'Network problem while loading media.';
        }
    };

    const cacheBust = (url) => {
        try {
            const parsed = new URL(url, window.location.href);
            parsed.searchParams.set('_retry', String(Date.now()));

            return parsed.href;
        } catch {
            return url;
        }
    };

    const retry = (element) => {
        const url = element.dataset.mediaOriginalSrc || currentUrl(element);
        if (!url) return;

        element.dataset.mediaOriginalSrc = url;
        hideError(element);
        clearTimer(element);

        if (element.tagName === 'IMG') {
            element.src = cacheBust(url);
        } else {
            const sources = element.querySelectorAll('source');

            if (sources.length) {
                sources.forEach((source) => {
                    const original = source.dataset.src || source.dataset.mediaSrc || source.src;
                    if (original) source.src = cacheBust(original);
                });
                element.load();
            } else if (element.src) {
                element.src = cacheBust(url);
                element.load();
            }
        }

        arm(element);
    };

    const arm = (element) => {
        clearTimer(element);

        if (isIgnored(element)) return;

        if (element.dataset.mediaErrorShown === '1') return;

        if (element.tagName === 'IMG') {
            if (element.complete && element.naturalWidth > 0) return;
        } else if (element.readyState > 0) {
            return;
        }

        if (!currentUrl(element)) return;

        const timeout = element.tagName === 'IMG' ? IMAGE_TIMEOUT : MEDIA_TIMEOUT;

        timers.set(element, window.setTimeout(() => {
            if (element.tagName === 'IMG') {
                if (element.complete && element.naturalWidth > 0) return;
            } else if (element.readyState > 0) {
                return;
            }

            showError(element, 'This media is taking too long to load.');
        }, timeout));
    };

    const onError = async (event) => {
        const element = event.currentTarget;
        if (isIgnored(element)) return;

        const url = currentUrl(element);

        showError(element, 'Media could not be loaded.');
        const message = await classify(url);

        if (element.dataset.mediaErrorShown === '1') {
            showError(element, message);
        }
    };

    const onLoaded = (event) => {
        const element = event.currentTarget;
        clearTimer(element);
        hideError(element);
    };

    const attach = (element) => {
        if (!element.matches?.(SELECTOR) || seen.has(element)) return;
        seen.add(element);

        if (isIgnored(element)) return;

        element.addEventListener('error', onError, true);
        element.addEventListener('load', onLoaded);
        element.addEventListener('loadedmetadata', onLoaded);
        element.addEventListener('loadeddata', onLoaded);
        element.addEventListener('canplay', onLoaded);
        element.addEventListener('playing', onLoaded);

        if (element.tagName !== 'IMG' && !element.dataset.mediaOriginalSrc) {
            const url = element.src || element.querySelector('source')?.src;
            if (url) element.dataset.mediaOriginalSrc = url;
        }

        observer?.observe(element);
        if (!observer) arm(element);
    };

    const scan = (root) => {
        if (!root || root.nodeType !== 1) return;

        if (root.matches(SELECTOR)) attach(root);
        root.querySelectorAll(SELECTOR).forEach(attach);

        if (root.matches('[data-media-error]')) return;
    };

    const observer = 'IntersectionObserver' in window
        ? new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;

                arm(entry.target);
                observer.unobserve(entry.target);
            });
        }, { rootMargin: '200px' })
        : null;

    const start = () => {
        scan(document.body);

        const mutations = new MutationObserver((records) => {
            records.forEach((record) => {
                if (record.type === 'attributes') {
                    const target = record.target;
                    if (target.matches?.(`${SELECTOR}, source`)) {
                        const element = target.matches('source') ? target.closest('video, audio') : target;
                        if (element) {
                            seen.delete(element);
                            attach(element);
                            arm(element);
                        }
                    }

                    return;
                }

                record.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) scan(node);
                });
            });
        });

        mutations.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['src', 'data-src', 'data-media-src'],
        });

        window.addEventListener('bleeps:media:hydrated', () => scan(document.body));
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
