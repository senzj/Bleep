document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('media-modal');
    if (!modal) return;
    const closeBtn = document.getElementById('media-modal-close');
    const prevBtn = document.getElementById('media-modal-prev');
    const nextBtn = document.getElementById('media-modal-next');
    const image = document.getElementById('media-modal-image');
    const video = document.getElementById('media-modal-video');
    const counter = document.getElementById('media-modal-counter');
    const dots = document.getElementById('media-modal-dots');
    const container = document.getElementById('media-modal-container');
    const loader = document.getElementById('media-modal-loader');

    // Only surface the spinner when decoding actually takes a moment, so a
    // cached thumbnail opens with no visible loading state at all.
    const SPINNER_DELAY = 120;
    let spinnerTimer = 0;
    const setLoading = (on) => {
        window.clearTimeout(spinnerTimer);

        if (!on) {
            loader?.classList.add('hidden');

            return;
        }

        spinnerTimer = window.setTimeout(() => loader?.classList.remove('hidden'), SPINNER_DELAY);
    };

    // Zoom controls
    const zoomControls =
        document.getElementById('media-modal-zoom-controls') ||
        zoomInBtnFallback();
    const zoomInBtn = document.getElementById('media-modal-zoom-in');
    const zoomOutBtn = document.getElementById('media-modal-zoom-out');
    const zoomResetBtn = document.getElementById('media-modal-zoom-reset');
    const zoomLevel = document.getElementById('media-modal-zoom-level');
    const downloadBtn = document.getElementById('media-modal-download');
    let downloadsEnabled = document.body.dataset.mediaDownloads === '1';

    window.addEventListener('preference:updated', (event) => {
        if (event.detail?.key !== 'media_downloads') return;

        downloadsEnabled = document.body.dataset.mediaDownloads === '1'
            || event.detail.value === true
            || event.detail.value === '1';

        const item = mediaItems[currentIndex];

        if (downloadBtn && item) {
            downloadBtn.classList.toggle('hidden', !downloadsEnabled);

            if (downloadsEnabled) {
                downloadBtn.href = item.original || item.src;
                downloadBtn.download = item.alt || fileNameFromUrl(item.original || item.src);
            }
        }
    });

    function fileNameFromUrl(url) {
        const path = String(url || '').split('?')[0].split('/').filter(Boolean).pop();

        if (!path) return 'media';

        try {
            return decodeURIComponent(path);
        } catch {
            return path;
        }
    }

    function zoomInBtnFallback() {
        return document.getElementById('media-modal-zoom-in')?.closest('div') ?? null;
    }

    let mediaItems = [];
    let currentIndex = 0;
    let lastFocusedElement = null;
    let navigationToken = 0;

    let imageSettleHandler = null;
    let imageErrorHandler = null;

    const clearImageListeners = () => {
        if (imageSettleHandler) image.removeEventListener('load', imageSettleHandler);
        if (imageErrorHandler) image.removeEventListener('error', imageErrorHandler);
        imageSettleHandler = null;
        imageErrorHandler = null;
    };

    const reduceMotion = () =>
        window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false;

    /**
     * Remove any stale load-error card left inside the viewer by an earlier
     * media. The viewer owns its own media sources, so recovery overlays should
     * never survive a close/open cycle.
     */
    const clearMediaErrors = () => {
        container.querySelectorAll('[data-media-error]').forEach((overlay) => overlay.remove());
        image.removeAttribute('data-media-error-shown');
        video.removeAttribute('data-media-error-shown');
    };

    // ── Slide indicator ──────────────────────────────────────────────────────
    function buildIndicators() {
        if (!dots) return;

        dots.innerHTML = '';
        dots.classList.toggle('hidden', mediaItems.length <= 1);

        mediaItems.forEach((item, index) => {
            const dot = document.createElement('span');
            dot.className = 'media-modal-dot';
            dot.dataset.mediaDot = String(index);
            dots.appendChild(dot);
        });
    }

    function updateIndicators(index) {
        if (!dots) return;

        dots.querySelectorAll('[data-media-dot]').forEach((dot, dotIndex) => {
            dot.classList.toggle('is-active', dotIndex === index);
        });
    }

    // ── Slide transition ─────────────────────────────────────────────────────
    // The image already owns `transform` for zooming, so the slide is applied
    // to the container instead. Animating the container keeps the zoom state
    // intact and only lasts long enough to signal direction.
    let slideAnimation = null;

    function animateOut(direction) {
        if (reduceMotion() || typeof container.animate !== 'function') {
            return Promise.resolve(null);
        }

        // Rapid navigation should retarget, not stack fills: canceling any
        // in-flight animation first keeps opacity at its current value.
        slideAnimation?.cancel();

        const animation = container.animate(
            [
                { transform: 'translate3d(0, 0, 0)', opacity: 1 },
                { transform: `translate3d(${-direction * 30}px, 0, 0)`, opacity: 0 },
            ],
            { duration: 110, easing: 'ease-in', fill: 'forwards' },
        );

        slideAnimation = animation;

        return animation.finished.catch(() => {}).then(() => animation);
    }

    function animateIn(direction, outgoing = null) {
        if (reduceMotion() || typeof container.animate !== 'function') {
            outgoing?.cancel();
            slideAnimation = null;

            return;
        }

        const previous = slideAnimation ?? outgoing;

        const incoming = container.animate(
            [
                { transform: `translate3d(${direction * 30}px, 0, 0)`, opacity: 0 },
                { transform: 'translate3d(0, 0, 0)', opacity: 1 },
            ],
            { duration: 170, easing: 'ease-out' },
        );

        slideAnimation = incoming;

        // Both animations target the same properties and the later one wins,
        // so the outgoing fill can stay in place until the incoming frame has
        // finished. Canceling it earlier briefly exposes the base container.
        incoming.finished
            .catch(() => {})
            .then(() => {
                previous?.cancel();

                if (slideAnimation === incoming) {
                    slideAnimation = null;
                }
            });
    }

    // ── Image loading ────────────────────────────────────────────────────────
    // Prefetching keeps URLs warm in the browser cache without retaining decoded
    // bitmaps. Holding Image objects here made closing the viewer expensive and
    // kept tens of megabytes alive between posts.
    const prefetched = new Set();

    function prefetchImage(src) {
        if (!src || prefetched.has(src)) return;

        prefetched.add(src);

        const img = new Image();
        img.decoding = 'async';
        img.onerror = () => prefetched.delete(src);
        img.src = src;
    }

    const idle = (fn) =>
        'requestIdleCallback' in window
            ? window.requestIdleCallback(fn, { timeout: 800 })
            : window.setTimeout(fn, 200);

    function prefetchNeighbours(index) {
        [index + 1, index - 1].forEach((n) => {
            const item = mediaItems[n];

            if (item?.type === 'image') prefetchImage(item.src);
        });
    }

    // The modal has ~8 static icons. Render them once, scoped to the modal, so
    // they never wait behind the page-wide chunked queue or trigger a
    // full-document render.
    let iconsReady = false;

    function ensureIcons() {
        if (iconsReady || !window.lucide) return;

        window.lucide.createIcons({ root: modal });

        iconsReady = true;
    }

    /**
     * Reveal the viewer as soon as it is called. The visible media element is
     * hidden until its own `load` fires, so opening never paints a stale frame
     * from the previous item.
     */
    function openModal() {
        if (modal.classList.contains('flex')) return;

        // Feed videos keep decoding behind the overlay otherwise, which is the
        // main cause of stutter while zooming in the viewer.
        pauseBackgroundMedia();

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        closeBtn.focus({ preventScroll: true });
        measureViewerCenter();

        ensureIcons();
    }

    /**
     * Pause anything playing outside the viewer and remember it, so closing the
     * viewer restores playback instead of silently stopping the feed.
     */
    let backgroundMedia = [];

    function pauseBackgroundMedia() {
        backgroundMedia = [];

        document.querySelectorAll('video, audio').forEach((element) => {
            if (element.paused || modal.contains(element)) return;

            // `policyPause` tells the media scripts this was not a viewer
            // choice, so they do not remember it as a deliberate pause.
            element.dataset.policyPause = '1';

            try {
                element.pause();
                backgroundMedia.push(element);
            } catch {
                delete element.dataset.policyPause;
            }
        });
    }

    function resumeBackgroundMedia() {
        backgroundMedia.forEach((element) => {
            if (!element.isConnected || !element.paused) return;

            element.play().catch(() => {});
        });

        backgroundMedia = [];
    }

    // ── Transform state ──────────────────────────────────────────────────────
    const MIN_ZOOM = 0.5;
    const MAX_ZOOM = 8;
    const DOUBLE_TAP_ZOOM = 2.5;
    const ANIMATION = 'transform 0.22s cubic-bezier(0.2, 0.7, 0.3, 1)';

    let scale = 1;
    let translateX = 0;
    let translateY = 0;
    let baseWidth = 0;
    let baseHeight = 0;
    let transitionTimer = 0;
    let frame = 0;
    let gesture = null;

    const pointers = new Map();
    let lastTap = { at: 0, x: 0, y: 0 };

    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

    // The container never moves while the viewer is open, so its centre is
    // measured once instead of on every pointermove.
    let viewerCenter = { x: 0, y: 0 };

    function measureViewerCenter() {
        const rect = container.getBoundingClientRect();

        viewerCenter = { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
    }

    function measureImage() {
        baseWidth = image.offsetWidth || image.naturalWidth || 0;
        baseHeight = image.offsetHeight || image.naturalHeight || 0;
    }

    function clampTranslation() {
        if (scale <= 1) {
            translateX = 0;
            translateY = 0;

            return;
        }

        const maxX = Math.max(0, (baseWidth * scale - window.innerWidth) / 2);
        const maxY = Math.max(0, (baseHeight * scale - window.innerHeight) / 2);

        translateX = clamp(translateX, -maxX, maxX);
        translateY = clamp(translateY, -maxY, maxY);
    }

    function render(animate = false) {
        window.clearTimeout(transitionTimer);

        // Panning runs render() once per frame, so only touch the DOM when a
        // value actually changes. Re-writing identical styles/text forces a
        // style recalc on every pointermove, which is what made dragging feel
        // heavy on a long feed.
        const transition = animate ? ANIMATION : 'none';

        if (image.style.transition !== transition) {
            image.style.transition = transition;
        }

        image.style.transform = `translate3d(${translateX}px, ${translateY}px, 0) scale(${scale})`;

        // Keep the affordance in sync with the gesture. Panning/pinching uses
        // the closed "grabbing" hand, otherwise the image shows the open hand.
        setMediaCursor(gesture ? 'grabbing' : 'grab');

        if (zoomLevel) {
            const label = `${Math.round(scale * 100)}%`;

            if (zoomLevel.textContent !== label) {
                zoomLevel.textContent = label;
            }
        }

        if (animate) {
            transitionTimer = window.setTimeout(() => {
                image.style.transition = 'none';
            }, 240);
        }
    }

    /**
     * Apply the cursor to every layer involved in a drag. Pointer capture moves
     * events to the container, so setting the image alone was not enough while
     * the viewer dragged the image around.
     */
    let mediaCursor = '';

    function setMediaCursor(cursor) {
        if (mediaCursor === cursor) return;

        mediaCursor = cursor;
        image.style.cursor = cursor;
        container.style.cursor = cursor;
        container.classList.toggle('is-panning', cursor === 'grabbing');
    }

    function scheduleRender() {
        if (frame) return;

        frame = window.requestAnimationFrame(() => {
            frame = 0;
            render(false);
        });
    }

    /**
     * Scale around a screen point so the content under the finger or cursor
     * stays put. This is what makes pinch and wheel zoom feel anchored.
     */
    function zoomAround(nextScale, pointX, pointY, animate = false) {
        const targetScale = clamp(nextScale, MIN_ZOOM, MAX_ZOOM);

        if (Math.abs(targetScale - scale) < 0.0001) {
            return;
        }

        const center = viewerCenter;
        const offsetX = pointX - center.x - translateX;
        const offsetY = pointY - center.y - translateY;
        const ratio = targetScale / scale;

        translateX = pointX - center.x - offsetX * ratio;
        translateY = pointY - center.y - offsetY * ratio;
        scale = targetScale;

        clampTranslation();
        render(animate);
    }

    function setZoom(nextScale) {
        zoomAround(nextScale, viewerCenter.x, viewerCenter.y, true);
    }

    function resetZoom(animate = false) {
        scale = 1;
        translateX = 0;
        translateY = 0;
        gesture = null;
        pointers.clear();
        setMediaCursor('grab');
        render(animate);
    }

    // ── Gestures ─────────────────────────────────────────────────────────────
    function pointerList() {
        return Array.from(pointers.values());
    }

    function distance(a, b) {
        return Math.hypot(b.x - a.x, b.y - a.y);
    }

    function beginPinch() {
        const [first, second] = pointerList();

        if (!first || !second) return;

        const midX = (first.x + second.x) / 2;
        const midY = (first.y + second.y) / 2;
        const center = viewerCenter;

        gesture = {
            type: 'pinch',
            startDistance: Math.max(1, distance(first, second)),
            startScale: scale,
            localX: (midX - center.x - translateX) / scale,
            localY: (midY - center.y - translateY) / scale,
            moved: true,
        };

        image.style.transition = 'none';
        setMediaCursor('grabbing');
    }

    function beginPan(pointer) {
        setMediaCursor('grabbing');

        gesture = {
            type: 'pan',
            startX: pointer.x,
            startY: pointer.y,
            startTranslateX: translateX,
            startTranslateY: translateY,
            moved: false,
            startedAt: performance.now(),
        };
    }

    function onPointerDown(event) {
        if (image.classList.contains('hidden')) return;
        if (event.pointerType === 'mouse' && event.button !== 0) return;

        // One layout read per gesture instead of one per pointermove.
        measureViewerCenter();

        event.preventDefault();
        image.style.transition = 'none';

        try {
            container.setPointerCapture(event.pointerId);
        } catch {
            /* pointer capture is best-effort */
        }

        pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

        if (pointers.size === 1) {
            beginPan({ x: event.clientX, y: event.clientY });
        } else if (pointers.size === 2) {
            beginPinch();
        }
    }

    function onPointerMove(event) {
        if (!pointers.has(event.pointerId) || !gesture) return;

        pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

        if (gesture.type === 'pinch' && pointers.size >= 2) {
            const [first, second] = pointerList();
            const midX = (first.x + second.x) / 2;
            const midY = (first.y + second.y) / 2;
            const ratio = distance(first, second) / gesture.startDistance;
            const center = viewerCenter;

            scale = clamp(gesture.startScale * ratio, MIN_ZOOM, MAX_ZOOM);
            translateX = midX - center.x - gesture.localX * scale;
            translateY = midY - center.y - gesture.localY * scale;
            clampTranslation();
            scheduleRender();

            return;
        }

        if (gesture.type === 'pan' && pointers.size === 1) {
            const deltaX = event.clientX - gesture.startX;
            const deltaY = event.clientY - gesture.startY;

            if (Math.abs(deltaX) + Math.abs(deltaY) > 4) {
                gesture.moved = true;
            }

            if (scale > 1) {
                translateX = gesture.startTranslateX + deltaX;
                translateY = gesture.startTranslateY + deltaY;
                clampTranslation();
                scheduleRender();
            }
        }
    }

    function registerTap(event) {
        const now = performance.now();
        const isDoubleTap =
            now - lastTap.at < 320 &&
            Math.hypot(event.clientX - lastTap.x, event.clientY - lastTap.y) < 30;

        if (isDoubleTap) {
            lastTap.at = 0;

            if (scale > 1.05) {
                resetZoom(true);
            } else {
                zoomAround(DOUBLE_TAP_ZOOM, event.clientX, event.clientY, true);
            }

            return;
        }

        lastTap = { at: now, x: event.clientX, y: event.clientY };
    }

    function onPointerEnd(event) {
        if (!pointers.has(event.pointerId)) return;

        const wasSinglePointer = pointers.size === 1;
        const finishedGesture = gesture;
        pointers.delete(event.pointerId);

        try {
            container.releasePointerCapture(event.pointerId);
        } catch {
            /* no-op */
        }

        if (wasSinglePointer && finishedGesture?.type === 'pan' && !finishedGesture.moved) {
            registerTap(event);
        }

        if (pointers.size === 1) {
            beginPan(pointerList()[0]);
            gesture.moved = true;

            return;
        }

        if (pointers.size === 0) {
            gesture = null;
            setMediaCursor('grab');

            if (scale < 1.02) {
                resetZoom(true);
            } else {
                clampTranslation();
                render(true);
            }
        }
    }

    container.addEventListener('pointerdown', onPointerDown, { passive: false });
    container.addEventListener('pointermove', onPointerMove, { passive: false });
    container.addEventListener('pointerup', onPointerEnd);
    container.addEventListener('pointercancel', onPointerEnd);
    image.addEventListener('dragstart', (event) => event.preventDefault());

    // Wheel zoom, anchored at the cursor.
    modal.addEventListener(
        'wheel',
        (event) => {
            if (image.classList.contains('hidden')) return;

            event.preventDefault();
            zoomAround(scale * Math.exp(-event.deltaY * 0.0015), event.clientX, event.clientY);
        },
        { passive: false }
    );

    window.addEventListener('resize', () => {
        if (image.classList.contains('hidden')) return;

        measureViewerCenter();
        measureImage();
        clampTranslation();
        render(false);
    });

    // ── Open / close / navigation ────────────────────────────────────────────
    document.addEventListener('click', (event) => {
        // Don't hijack clicks on inline players or other interactive controls.
        if (event.target.closest('video, audio, button, a, input, textarea, select, [data-video-controls]')) return;

        // The floating mini player is its own surface, not a gallery thumbnail.
        if (event.target.closest('.bleep-mini-player')) return;

        const mediaItem = event.target.closest('[data-media-index]');
        if (!mediaItem) return;

        const bleepMedia = mediaItem.closest('[data-bleep-media]');
        if (!bleepMedia) return;

        mediaItems = Array.from(bleepMedia.querySelectorAll('[data-media-index]')).map((element) => ({
            type: element.dataset.mediaType,
            // `data-media-view` points at the resized viewer image; galleries
            // that do not have one (avatars, admin reports) fall back to the
            // inline source.
            src: element.dataset.mediaView || element.dataset.mediaSrc,
            original: element.dataset.mediaOriginal || element.dataset.mediaSrc,
            alt: element.dataset.mediaAlt || '',
            mime: element.dataset.mediaMime || '',
        }));

        currentIndex = parseInt(mediaItem.dataset.mediaIndex, 10) || 0;

        clearMediaErrors();

        lastFocusedElement = document.activeElement;
        buildIndicators();
        // showMedia() reveals the viewer itself once the media is ready.
        showMedia(currentIndex);
    }, true);

    const closeModal = () => {
        const closeToken = ++navigationToken;
        setLoading(false);
        clearImageListeners();
        slideAnimation?.cancel();
        slideAnimation = null;

        // Release any pointer capture so a drag that ended on close cannot keep
        // swallowing clicks on the feed behind the (now hidden) viewer.
        pointers.forEach((_, pointerId) => {
            try {
                container.releasePointerCapture(pointerId);
            } catch {
                /* capture is best-effort */
            }
        });

        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';

        // Stop audio immediately; everything else can wait until after the
        // close frame so the interaction stays instant.
        if (!video.paused) {
            try {
                video.pause();
            } catch {
                /* no-op */
            }
        }

        resetZoom();
        image.classList.add('hidden');

        const focusTarget = lastFocusedElement;
        lastFocusedElement = null;

        // Deferred teardown: freeing big bitmaps and resetting media elements is
        // the expensive part of closing, so keep it off the close interaction.
        window.requestAnimationFrame(() => {
            window.setTimeout(() => {
                if (closeToken !== navigationToken) return; // reopened already

                clearMediaErrors();

                if (video.hasAttribute('src')) {
                    video.removeAttribute('src');
                    video.load();
                }
                video.classList.add('hidden');

                image.removeAttribute('src');
                image.alt = '';
                image.style.removeProperty('transition');
                image.style.removeProperty('transform');

                mediaItems = [];
                currentIndex = 0;

                if (counter) counter.textContent = '';

                if (dots) {
                    dots.innerHTML = '';
                    dots.classList.add('hidden');
                }

                if (downloadBtn) {
                    downloadBtn.classList.add('hidden');
                    downloadBtn.removeAttribute('href');
                }

                container.style.removeProperty('transform');
                container.style.removeProperty('opacity');

                if (focusTarget && document.contains(focusTarget)) {
                    focusTarget.focus({ preventScroll: true });
                }

                // Resume the feed only after the close frame, so starting a
                // video again cannot make the dismissal feel sluggish.
                resumeBackgroundMedia();
            }, 0);
        });
    };

    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });

    prevBtn.addEventListener('click', () => {
        currentIndex = (currentIndex - 1 + mediaItems.length) % mediaItems.length;
        showMedia(currentIndex, -1);
    });

    nextBtn.addEventListener('click', () => {
        currentIndex = (currentIndex + 1) % mediaItems.length;
        showMedia(currentIndex, 1);
    });

    document.addEventListener('keydown', (event) => {
        if (!modal.classList.contains('flex')) return;

        if (event.key === 'Escape') closeModal();
        if (event.key === 'ArrowLeft' && currentIndex > 0) prevBtn.click();
        if (event.key === 'ArrowRight' && currentIndex < mediaItems.length - 1) nextBtn.click();
        if (event.key === '+' || event.key === '=') {
            event.preventDefault();
            setZoom(scale + 0.5);
        }
        if (event.key === '-') {
            event.preventDefault();
            setZoom(scale - 0.5);
        }
    });

    zoomInBtn?.addEventListener('click', () => setZoom(scale + 0.25));
    zoomOutBtn?.addEventListener('click', () => setZoom(scale - 0.25));
    zoomResetBtn?.addEventListener('click', () => resetZoom(true));

    async function showMedia(index, direction = 0) {
        const item = mediaItems[index];
        if (!item) return;

        clearMediaErrors();

        const token = ++navigationToken;
        const animate = direction !== 0 && modal.classList.contains('flex') && mediaItems.length > 1;

        // Start the next image downloading during the fade-out instead of after
        // it. The slide then reveals from cache rather than waiting 110ms for
        // the animation plus a fresh request.
        if (item.type === 'image') prefetchImage(item.src);

        let outgoing = null;

        if (animate) {
            outgoing = await animateOut(direction);

            if (token !== navigationToken) {
                outgoing?.cancel();

                return;
            }
        }

        resetZoom();
        image.classList.add('hidden');
        video.classList.add('hidden');
        video.pause();
        setLoading(false);

        // Chrome updates first so it never lags behind a slow load.
        counter.textContent = `${index + 1} / ${mediaItems.length}`;
        updateIndicators(index);

        if (downloadBtn) {
            downloadBtn.classList.toggle('hidden', !downloadsEnabled);

            if (downloadsEnabled) {
                downloadBtn.href = item.original || item.src;
                downloadBtn.download = item.alt || fileNameFromUrl(item.original || item.src);
            }
        }

        if (mediaItems.length <= 1) {
            prevBtn.classList.add('hidden');
            nextBtn.classList.add('hidden');
        } else {
            prevBtn.classList.toggle('hidden', index === 0);
            nextBtn.classList.toggle('hidden', index === mediaItems.length - 1);
        }

        if (item.type === 'image') {
            zoomControls?.classList.remove('hidden');
            setMediaCursor('grab');
            openModal(); // open on the click frame

            const tokenAtLoad = token;
            clearImageListeners();

            const reveal = () => {
                if (tokenAtLoad !== navigationToken) return;

                clearImageListeners();
                setLoading(false);
                image.classList.remove('hidden');
                measureViewerCenter();
                measureImage();
                render(false);
            };

            imageSettleHandler = reveal;
            imageErrorHandler = () => {
                if (tokenAtLoad !== navigationToken) return;

                clearImageListeners();
                setLoading(false);
                image.classList.remove('hidden');
            };

            image.addEventListener('load', imageSettleHandler);
            image.addEventListener('error', imageErrorHandler);

            // Drop the previous bitmap before swapping so a slow new frame can
            // never paint the image from the last open.
            image.classList.add('hidden');
            image.removeAttribute('src');
            image.alt = item.alt;
            image.style.transition = 'none';
            image.style.transform = 'translate3d(0, 0, 0) scale(1)';

            image.src = item.src;

            if (image.complete && image.naturalWidth > 0) {
                window.requestAnimationFrame(reveal);
            } else {
                setLoading(true); // spinner only shows after SPINNER_DELAY
            }
        } else {
            zoomControls?.classList.add('hidden');
            setMediaCursor('default');
            delete video.dataset.positionRestored;
            video.src = item.src;
            video.classList.remove('hidden');
            openModal();
        }

        if (animate) {
            animateIn(direction, outgoing);
        }

        // Warm the neighbours only after the current media is on screen, so they
        // never compete with it for bandwidth.
        idle(() => {
            if (token === navigationToken) prefetchNeighbours(index);
        });
    }

    // The viewer's handful of icons never change. Render them while the page is
    // idle so opening the viewer does not pay for eight icon swaps on the click
    // frame. `ensureIcons` retries on open if Lucide was not ready yet.
    if (typeof window.whenIdle === 'function') {
        window.whenIdle(ensureIcons);
    } else {
        ensureIcons();
    }
});
