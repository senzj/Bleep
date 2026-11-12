// Media loading runs after the page has finished loading its assets and
// components, then only fetches media that is visible or about to be visible.
// Nothing here loops over the whole document up front, so a feed with hundreds
// of posts never blocks the UI while it warms up.
(function () {
    'use strict';

    const ROOT_MARGIN = '400px 0px';
    const observed = new WeakSet();
    const pendingFallback = new Set();
    let observer = null;
    let fallbackTicking = false;

    // Stop the skeleton as soon as the element has something to paint. Shared
    // with the NSFW reveal path, which sets sources outside the lazy loader.
    const clearPendingWhenReady = (el) => {
        const frame = el.closest?.('.media-pending');
        if (!frame) return;

        let cleared = false;
        const clear = () => {
            if (cleared) return;

            cleared = true;
            frame.classList.remove('media-pending');
        };

        if (el.tagName === 'IMG') {
            if (el.complete && el.naturalWidth > 0) {
                clear();
            } else {
                el.addEventListener('load', clear, { once: true });
                el.addEventListener('error', clear, { once: true });
            }

            return;
        }

        const video = el.tagName === 'VIDEO' ? el : el.closest('video');
        if (!video) return;

        if (video.readyState >= 1) {
            clear();
        } else {
            // `loadeddata` may never fire for metadata-only videos, which left
            // the shimmer running forever. Metadata is enough to stop it; the
            // first frame paints right after.
            video.addEventListener('loadedmetadata', clear, { once: true });
            video.addEventListener('loadeddata', clear, { once: true });
            video.addEventListener('canplay', clear, { once: true });
            video.addEventListener('error', clear, { once: true });
            window.setTimeout(clear, 8000);
        }
    };

    // NSFW media is loaded by nsfw.js after the age gate, so it needs the same
    // skeleton cleanup without going through the IntersectionObserver.
    window.clearMediaPending = clearPendingWhenReady;

    // Load a single image
    const loadImage = (img) => {
        const src = img.getAttribute('data-src');
        if (src && !img.src) {
            clearPendingWhenReady(img);

            img.src = src;
            img.removeAttribute('data-src');
        }
    };

    // Load a single video
    const loadVideo = (video) => {
        const sources = video.querySelectorAll('source[data-src], source[data-media-src]');
        sources.forEach((source) => {
            const src = source.getAttribute('data-src') || source.getAttribute('data-media-src');
            if (src && !source.src) {
                source.src = src;
                source.removeAttribute('data-src');
                source.removeAttribute('data-media-src');
            }
        });

        clearPendingWhenReady(video);

        if (video.load) {
            video.load();
        }
    };

    // Load a single audio element
    const loadAudio = (audio) => {
        const src = audio.getAttribute('data-src');
        if (src && !audio.src) {
            audio.src = src;
            audio.removeAttribute('data-src');
        }
    };

    const load = (el) => {
        if (el.tagName === 'IMG') {
            loadImage(el);
        } else if (el.tagName === 'VIDEO') {
            loadVideo(el);
        } else if (el.tagName === 'AUDIO') {
            loadAudio(el);
        }

        observer?.unobserve(el);
        observed.delete(el);
    };

    // True when the element still has something left to fetch.
    const needsLoading = (el) => {
        if (el.tagName === 'IMG') {
            return el.hasAttribute('data-src');
        }
        if (el.tagName === 'VIDEO') {
            return el.querySelector('source[data-src], source[data-media-src]') !== null;
        }
        if (el.tagName === 'AUDIO') {
            return el.hasAttribute('data-src');
        }

        return false;
    };

    const mediaIn = (root) => {
        const nodes = [];

        if (root.matches?.('img[data-src], video, audio[data-src]')) {
            nodes.push(root);
        }

        root.querySelectorAll?.('img[data-src], video, audio[data-src]').forEach((el) => nodes.push(el));

        return nodes;
    };

    const ensureObserver = () => {
        if (observer || !('IntersectionObserver' in window)) {
            return observer;
        }

        observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    const el = entry.target;
                    observed.delete(el);

                    if (needsLoading(el)) {
                        load(el);
                    } else {
                        observer.unobserve(el);
                    }
                });
            },
            { rootMargin: ROOT_MARGIN }
        );

        return observer;
    };

    // Watch media in a container (the whole page on first load, or a freshly
    // appended feed page for infinite scroll).
    const watchMedia = (root = document) => {
        const io = ensureObserver();

        mediaIn(root).forEach((el) => {
            if (!needsLoading(el) || observed.has(el)) {
                return;
            }

            observed.add(el);

            if (io) {
                io.observe(el);
            } else {
                // Very old browsers: load only what the viewport can reach.
                pendingFallback.add(el);
                flushFallback();
            }
        });
    };

    const flushFallback = () => {
        if (observer) {
            return;
        }

        fallbackTicking = false;

        pendingFallback.forEach((el) => {
            if (!el.isConnected) {
                pendingFallback.delete(el);
                return;
            }

            const rect = el.getBoundingClientRect();

            if (rect.bottom >= -300 && rect.top <= window.innerHeight + 300) {
                pendingFallback.delete(el);
                load(el);
            }
        });
    };

    window.addEventListener(
        'scroll',
        () => {
            if (observer || pendingFallback.size === 0) {
                return;
            }

            if (fallbackTicking) {
                return;
            }

            fallbackTicking = true;
            window.requestAnimationFrame(flushFallback);
        },
        { passive: true }
    );

    // Export function globally for infinite scroll to use. New content is
    // watched rather than force-loaded, so a long feed cannot freeze the page.
    window.loadNewMedia = (container) => {
        if (container) {
            watchMedia(container);
        }
    };

    // A safety net for media that finished before its listener was attached
    // (cached images, videos with metadata only). Prevents a shimmer frame
    // from being left behind after the page has visibly finished loading.
    const clearResolvedShimmers = () => {
        document.querySelectorAll('.media-pending').forEach((frame) => {
            const image = frame.querySelector('img');
            const video = frame.querySelector('video');

            if ((image?.complete && image.naturalWidth > 0) || (video && video.readyState >= 1)) {
                frame.classList.remove('media-pending');
            }
        });
    };

    const start = () => {
        const begin = () => {
            clearResolvedShimmers();
            watchMedia(document);
        };

        if ('requestIdleCallback' in window) {
            requestIdleCallback(begin, { timeout: 1200 });
        } else {
            window.setTimeout(begin, 0);
        }
    };

    // Wait for every script, stylesheet and image the page needs before we
    // start pulling media. `load` already implies the document is complete;
    // an idle callback keeps the first media request off the critical path.
    if (document.readyState === 'complete') {
        start();
    } else {
        window.addEventListener('load', start, { once: true });
    }
})();
