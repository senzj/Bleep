/**
 * Media viewing policy.
 *
 * Decides what happens to audio and video that is no longer being viewed, i.e.
 * scrolled out of the viewport or left in a hidden tab. The user preference
 * (data-media-offscreen on <body>) is the default; every player can cycle its
 * own mode from its controls.
 *
 * Modes:
 *  - pause      stop playback until the viewer comes back
 *  - background keep playing with no visible player
 *  - pip        keep playing in a small floating player
 */

(function () {
    'use strict';

    const MODES = ['pause', 'background', 'pip'];

    let mediaObserver = null;
    let miniAnchorObserver = null;
    let promoted = null;
    let initialized = false;

    function globalMode() {
        const value = document.body?.dataset?.mediaOffscreen;

        return MODES.includes(value) ? value : 'pause';
    }

    /**
     * The explicit per-player choice wins, otherwise the account preference.
     */
    function modeFor(media) {
        if (!media) {
            return globalMode();
        }

        const explicit = media.dataset?.mediaOffscreenMode;

        return MODES.includes(explicit) ? explicit : globalMode();
    }

    function pauseMedia(media) {
        if (!media || media.paused) {
            return;
        }

        // Lets the media scripts tell policy pauses apart from a viewer pause.
        media.dataset.policyPause = '1';

        try {
            media.pause();
        } catch (error) {
            delete media.dataset.policyPause;
            console.error('Error pausing media:', error);
        }
    }

    function pauseAllAudioInContainer(container) {
        container?.querySelectorAll('audio.audio-element').forEach(pauseMedia);
    }

    function pauseAllVideoInContainer(container) {
        container?.querySelectorAll('video').forEach(pauseMedia);
    }

    function pauseAllMediaInContainer(container) {
        pauseAllAudioInContainer(container);
        pauseAllVideoInContainer(container);
    }

    /**
     * The element that should be moved into the mini player: the audio card or
     * the wrapper around a single gallery video.
     */
    function playerWrapper(media) {
        if (!media) {
            return null;
        }

        if (media.matches?.('[data-audio-player]')) {
            return media;
        }

        if (media.tagName === 'AUDIO') {
            return media.closest('[data-audio-player]') || media.parentElement;
        }

        return media.closest('[data-media-index]') || media.parentElement;
    }

    function isPromoted(media) {
        return Boolean(promoted && promoted.media === media);
    }

    function miniMetaSource(wrapper) {
        return wrapper?.closest?.('[data-bleep-media]') || wrapper;
    }

    /**
     * Title, subtitle and author line for the floating player. Built once per
     * promotion from data attributes; players without metadata (comment audio,
     * chat audio) simply render no header.
     */
    function buildMiniMeta(wrapper) {
        const data = miniMetaSource(wrapper)?.dataset || {};
        const title = data.mediaTitle?.trim() || '';
        const subtitle = data.mediaSubtitle?.trim() || '';
        const poster = data.mediaPoster?.trim() || '';
        const handle = data.mediaPosterHandle?.trim() || '';
        const avatarUrl = data.mediaPosterAvatar?.trim() || '';

        if (!title && !subtitle && !poster) {
            return null;
        }

        const meta = document.createElement('div');
        meta.className = 'bleep-mini-meta';
        meta.dataset.miniMeta = '1';

        // The mini player sits inside a clickable media item; keep taps on the
        // header from opening the full viewer.
        meta.addEventListener('click', (event) => event.stopPropagation());

        if (title) {
            const headline = document.createElement('div');
            headline.className = 'bleep-mini-meta-headline';

            const op = document.createElement('span');
            op.className = 'bleep-mini-meta-op';
            op.textContent = 'OP';

            const titleEl = document.createElement('p');
            titleEl.className = 'bleep-mini-meta-title';
            titleEl.textContent = title;

            headline.append(op, titleEl);
            meta.appendChild(headline);
        }

        if (subtitle) {
            const subtitleEl = document.createElement('p');
            subtitleEl.className = 'bleep-mini-meta-subtitle';
            subtitleEl.textContent = subtitle;
            meta.appendChild(subtitleEl);
        }

        if (poster || handle) {
            const row = document.createElement('div');
            row.className = 'bleep-mini-meta-poster';

            const avatar = document.createElement('span');
            avatar.className = 'bleep-mini-meta-avatar';
            avatar.setAttribute('aria-hidden', 'true');

            if (avatarUrl) {
                const image = document.createElement('img');
                image.src = avatarUrl;
                image.alt = '';
                image.decoding = 'async';
                image.loading = 'lazy';
                avatar.appendChild(image);
            } else {
                avatar.textContent = (poster || '?').charAt(0).toUpperCase();
            }

            row.appendChild(avatar);

            if (poster) {
                const nameEl = document.createElement('span');
                nameEl.className = 'bleep-mini-meta-name';
                nameEl.textContent = poster;
                row.appendChild(nameEl);
            }

            if (handle) {
                const handleEl = document.createElement('span');
                handleEl.className = 'bleep-mini-meta-handle';
                handleEl.textContent = handle;
                row.appendChild(handleEl);
            }

            meta.appendChild(row);
        }

        return meta;
    }

    function promote(media) {
        if (!media || isPromoted(media)) {
            return;
        }

        const wrapper = playerWrapper(media);

        if (!wrapper || !wrapper.parentElement) {
            return;
        }

        restoreMini();

        const rect = wrapper.getBoundingClientRect();

        // Hidden copies of a player (templates, closed modals) have no box to
        // preserve or float, so leave them alone.
        if (rect.width < 1 || rect.height < 1) {
            return;
        }

        const placeholder = document.createElement('div');
        placeholder.className = 'bleep-mini-placeholder';
        placeholder.dataset.miniPlaceholder = '1';
        placeholder.style.width = `${Math.max(1, Math.round(rect.width))}px`;
        placeholder.style.height = `${Math.max(1, Math.round(rect.height))}px`;

        const returnButton = document.createElement('button');
        returnButton.type = 'button';
        returnButton.className = 'bleep-mini-return';
        returnButton.innerHTML = '<i data-lucide="maximize-2" class="w-4 h-4"></i><span>Return player to post</span>';
        returnButton.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            restoreMini({ scrollIntoView: true });
        });
        placeholder.appendChild(returnButton);

        wrapper.parentElement.insertBefore(placeholder, wrapper);
        wrapper.classList.add('bleep-mini-player');
        wrapper.dataset.miniPromoted = '1';

        wrapper.querySelector(':scope > [data-mini-meta]')?.remove();

        const meta = buildMiniMeta(wrapper);

        if (meta) {
            wrapper.insertBefore(meta, wrapper.firstChild);
            // One layout read per promotion tells the controls how far down the
            // header ends, so they never sit on top of it.
            wrapper.style.setProperty('--bleep-mini-meta-height', `${meta.offsetHeight}px`);
        }

        const restoreButton = document.createElement('button');
        restoreButton.type = 'button';
        restoreButton.className = 'bleep-mini-restore btn btn-circle btn-xs';
        restoreButton.setAttribute('aria-label', 'Return player to the page');
        restoreButton.title = 'Return player to the page';
        restoreButton.innerHTML = '<i data-lucide="maximize-2" class="w-3.5 h-3.5"></i>';
        restoreButton.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            restoreMini({ scrollIntoView: true });
        });
        wrapper.appendChild(restoreButton);

        promoted = { media, wrapper, placeholder, wasOutOfView: false };

        miniAnchorObserver?.observe(placeholder);
        renderIcons(restoreButton);
        renderIcons(placeholder);
        updateControls(media);
    }

    function restoreMini({ scrollIntoView = false } = {}) {
        if (!promoted) {
            return;
        }

        const { media, wrapper, placeholder } = promoted;

        miniAnchorObserver?.unobserve(placeholder);
        placeholder.remove();
        wrapper.classList.remove('bleep-mini-player');
        delete wrapper.dataset.miniPromoted;
        wrapper.querySelector(':scope > .bleep-mini-restore')?.remove();
        wrapper.querySelector(':scope > [data-mini-meta]')?.remove();
        wrapper.style.removeProperty('--bleep-mini-meta-height');

        promoted = null;
        updateControls(media);

        if (scrollIntoView && document.contains(wrapper)) {
            wrapper.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }

    function toggleMini(media) {
        if (isPromoted(media)) {
            restoreMini({ scrollIntoView: true });

            return;
        }

        promote(media);
    }

    function setMode(media, mode) {
        if (!media || !MODES.includes(mode)) {
            return;
        }

        media.dataset.mediaOffscreenMode = mode;

        // Leaving mini-player mode puts the player back where it belongs.
        if (isPromoted(media) && mode !== 'pip') {
            restoreMini();
        }

        updateControls(media);
    }

    /**
     * The background control is a single on/off toggle. The mini player has its
     * own dedicated button, so there is no third state to cycle through here.
     */
    function toggleBackground(media) {
        const current = modeFor(media);

        setMode(media, current === 'background' ? 'pause' : 'background');
    }

    /**
     * Kept for backwards compatibility with callers that still cycle.
     */
    function cycleMode(media) {
        const current = modeFor(media);
        const next = MODES[(MODES.indexOf(current) + 1) % MODES.length];

        setMode(media, next);
    }

    function renderIcons(root = document) {
        if (!window.lucide?.createIcons) {
            return;
        }

        try {
            window.lucide.createIcons({ root });
        } catch (error) {
            console.error('Error rendering media control icons:', error);
        }
    }

    function updateControls(media) {
        const wrapper = playerWrapper(media);

        if (!wrapper) {
            return;
        }

        const backgroundOn = modeFor(media) === 'background';
        const modeButton = wrapper.querySelector('[data-media-mode]');

        if (modeButton) {
            const label = backgroundOn
                ? 'Stop playing in the background'
                : 'Keep playing in the background';

            modeButton.title = label;
            modeButton.setAttribute('aria-label', label);
            modeButton.setAttribute('aria-pressed', backgroundOn ? 'true' : 'false');
            modeButton.classList.toggle('is-active', backgroundOn);

            const iconName = backgroundOn ? 'headphones' : 'headphone-off';
            const icon = modeButton.querySelector('[data-lucide]');
            if (icon && icon.getAttribute('data-lucide') !== iconName) {
                icon.setAttribute('data-lucide', iconName);
                renderIcons(modeButton);
            }
        }

        wrapper.querySelectorAll('[data-media-mini]').forEach((button) => {
            const active = isPromoted(media);
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            button.title = active ? 'Return player to the page' : 'Open mini player';
            button.setAttribute('aria-label', button.title);
        });
    }

    function buildControls(media) {
        const group = document.createElement('div');
        group.className = 'bleep-media-controls';
        group.dataset.mediaControls = '1';

        const modeButton = document.createElement('button');
        modeButton.type = 'button';
        modeButton.className = 'bleep-media-control btn btn-ghost btn-xs btn-circle';
        modeButton.dataset.mediaMode = '1';
        modeButton.innerHTML = '<i data-lucide="headphone-off" class="w-4 h-4"></i>';
        modeButton.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            toggleBackground(media);
        });

        const miniButton = document.createElement('button');
        miniButton.type = 'button';
        miniButton.className = 'bleep-media-control btn btn-ghost btn-xs btn-circle';
        miniButton.dataset.mediaMini = '1';
        miniButton.innerHTML = '<i data-lucide="picture-in-picture" class="w-4 h-4"></i>';
        miniButton.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            toggleMini(media);
        });

        group.append(modeButton, miniButton);

        return group;
    }

    /**
     * Add the mode + mini-player buttons to a player. Audio passes its control
     * row, video gets a small overlay in the corner of its wrapper.
     */
    function attachPlayerControls(media, { host = null } = {}) {
        if (!media || media.dataset.mediaControlsAttached === '1') {
            return;
        }

        if (media.tagName === 'VIDEO' && media.hasAttribute('data-no-autoplay')) {
            return;
        }

        // The media viewer has its own controls and owns the full screen.
        if (media.closest('#media-modal')) {
            return;
        }

        const wrapper = playerWrapper(media);

        if (!wrapper) {
            return;
        }

        media.dataset.mediaControlsAttached = '1';

        const controls = buildControls(media);

        if (host) {
            controls.classList.add('bleep-media-controls--inline');
            host.appendChild(controls);
        } else {
            controls.classList.add('bleep-media-controls--overlay');
            wrapper.appendChild(controls);
        }

        renderIcons(controls);
        updateControls(media);
    }

    function handleElementLeftView(media) {
        if (!media || media.closest('#media-modal') || media.paused) {
            return;
        }

        const mode = modeFor(media);

        if (mode === 'pause') {
            pauseMedia(media);
        } else if (mode === 'pip' && (!promoted || isPromoted(media))) {
            promote(media);
        }
    }

    function handleContainerLeftView(container) {
        container?.querySelectorAll('audio, video').forEach(handleElementLeftView);
    }

    function observeContainers() {
        if (!mediaObserver) {
            return;
        }

        document.querySelectorAll('[data-bleep-media], [data-comment-media-wrapper]').forEach((container) => {
            mediaObserver.observe(container);
        });
    }

    function initObservers() {
        mediaObserver?.disconnect();
        miniAnchorObserver?.disconnect();

        mediaObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    handleContainerLeftView(entry.target);
                }
            });
        }, { threshold: 0.1 });

        miniAnchorObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!promoted || promoted.placeholder !== entry.target) {
                    return;
                }

                if (!entry.isIntersecting) {
                    promoted.wasOutOfView = true;

                    return;
                }

                // Only return the player once its original spot actually came
                // back on screen. The fixed player is always "intersecting",
                // so the placeholder is the source of truth.
                if (promoted.wasOutOfView) {
                    restoreMini();
                }
            });
        }, { threshold: 0.4 });

        observeContainers();
    }

    function refreshMediaObserver() {
        observeContainers();
    }

    function pauseMediaOnCommentsClose() {
        // Closing the comments sheet always stops its media, regardless of the
        // off-screen preference; a floating mini player would otherwise hover
        // over the page with no sheet to return to.
        window.addEventListener('close-comments', () => {
            const commentsSheet = document.querySelector('[x-data="commentSheet"]');

            if (!commentsSheet) {
                return;
            }

            if (promoted && commentsSheet.contains(promoted.wrapper)) {
                restoreMini();
            }

            pauseAllMediaInContainer(commentsSheet);
        });
    }

    function pauseMediaOnPageHidden() {
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                return;
            }

            document.querySelectorAll('audio, video').forEach((media) => {
                if (!media.paused && modeFor(media) === 'pause') {
                    pauseMedia(media);
                }
            });
        });
    }

    function pauseMediaOnNavigation() {
        window.addEventListener('beforeunload', () => {
            document.querySelectorAll('audio, video').forEach(pauseMedia);
        });
    }

    /**
     * Preferences are updated with fetch; apply them without a reload.
     */
    function listenForPreferenceChanges() {
        window.addEventListener('preference:updated', (event) => {
            if (event.detail?.key !== 'media_offscreen') {
                return;
            }

            const value = event.detail.value;
            if (MODES.includes(value)) {
                document.body.dataset.mediaOffscreen = value;
            }

            document.querySelectorAll('[data-media-mode]').forEach((button) => {
                const media = button.closest('[data-audio-player], [data-media-index]')
                    ?.querySelector('audio, video');

                if (media && !media.dataset.mediaOffscreenMode) {
                    updateControls(media);
                }
            });
        });
    }

    function init() {
        if (initialized) {
            return;
        }

        initialized = true;
        initObservers();
        pauseMediaOnCommentsClose();
        pauseMediaOnNavigation();
        pauseMediaOnPageHidden();
        listenForPreferenceChanges();
    }

    window.bleepMediaPolicy = {
        attachPlayerControls,
        cycleMode,
        toggleBackground,
        globalMode,
        handleContainerLeftView,
        handleElementLeftView,
        isPromoted,
        modeFor,
        promote,
        restoreMini,
        setMode,
        toggleMini,
    };

    // Backwards-compatible globals used elsewhere in the app.
    window.pauseMedia = pauseMedia;
    window.pauseAllMediaInContainer = pauseAllMediaInContainer;
    window.refreshMediaObserver = refreshMediaObserver;

    const start = () => {
        if (window.whenIdle) {
            window.whenIdle(init, 1500);
        } else {
            window.setTimeout(init, 1);
        }
    };

    document.addEventListener('DOMContentLoaded', start);
    document.addEventListener('bleeps:media:hydrated', refreshMediaObserver);

    if (document.readyState !== 'loading') {
        start();
    }
})();
