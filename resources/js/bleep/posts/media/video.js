const VIDEO_VOLUME_STORAGE_KEY = 'bleepVideoVolume';
const POSITION_STORAGE_PREFIX = 'bleepMediaPosition:';

const rememberPositions = () =>
    document.body?.dataset?.rememberMediaPosition === '1';

const videoSrc = (video) => {
    const source = video.querySelector('source');

    return video.currentSrc || source?.src || source?.dataset?.src || '';
};

function getStoredVideoPosition(src) {
    if (!src) return null;

    try {
        const value = parseFloat(localStorage.getItem(POSITION_STORAGE_PREFIX + src));

        return Number.isFinite(value) && value > 0 ? value : null;
    } catch {
        return null;
    }
}

function setStoredVideoPosition(src, seconds) {
    if (!src) return;

    try {
        if (!Number.isFinite(seconds) || seconds <= 0) {
            localStorage.removeItem(POSITION_STORAGE_PREFIX + src);
            return;
        }

        localStorage.setItem(POSITION_STORAGE_PREFIX + src, String(seconds));
    } catch { /* no-op */ }
}

// Intersection observer for autoplay
let videoObserver = null;
const AUTOPLAY_ENABLED = document.body?.dataset?.autoplayVideos !== '0';
const RESUME_MEDIA_ON_VIEW = document.body?.dataset?.resumeMediaOnView === '1';

/**
 * Get stored video volume from localStorage
 */
function getStoredVideoVolume() {
    try {
        const raw = localStorage.getItem(VIDEO_VOLUME_STORAGE_KEY);
        const vol = parseFloat(raw);
        return Number.isFinite(vol) ? Math.min(Math.max(vol, 0), 1) : 1;
    } catch {
        return 1;
    }
}

/**
 * Save video volume to localStorage
 */
function setStoredVideoVolume(vol) {
    try {
        localStorage.setItem(VIDEO_VOLUME_STORAGE_KEY, String(vol));
    } catch {
        /* no-op */
    }
}

/**
 * Load video source if needed
 */
function loadVideoSource(video) {
    const source = video.querySelector('source[data-src], source[data-media-src]');
    if (source) {
        const src = source.dataset.src || source.dataset.mediaSrc;
        if (src && !source.src) {
            source.src = src;
            source.removeAttribute('data-src');
            source.removeAttribute('data-media-src');
            video.load();
            return true; // Source was loaded
        }
    }
    return false; // Source already loaded or no source
}

/**
 * Check if video source is loaded
 */
function isVideoSourceLoaded(video) {
    const source = video.querySelector('source');
    return source && source.src && source.src !== '' && !source.src.startsWith('about:');
}

/**
 * Create intersection observer for video autoplay
 */
function createVideoObserver() {
    if (!AUTOPLAY_ENABLED) return null;
    if (videoObserver) return videoObserver;

    videoObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const video = entry.target;

            // Skip comment videos entirely
            if (video.hasAttribute('data-no-autoplay')) {
                return;
            }

            // A promoted mini player is fixed on screen; its visibility is no
            // longer a signal for the feed's autoplay behaviour.
            if (video.closest('[data-mini-promoted]')) {
                return;
            }

            if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
                if (!isVideoSourceLoaded(video)) {
                    loadVideoSource(video);
                }

                if (video.readyState >= 1) {
                    tryAutoplay(video);
                } else {
                    video.addEventListener(
                        'loadedmetadata',
                        () => tryAutoplay(video),
                        { once: true }
                    );
                }
            } else if (!entry.isIntersecting) {
                if (window.bleepMediaPolicy) {
                    window.bleepMediaPolicy.handleElementLeftView(video);
                } else if (!video.paused) {
                    video.pause();
                }
            }
        });
    }, {
        threshold: [0, 0.5, 1.0],
        rootMargin: '0px'
    });

    return videoObserver;
}

/**
 * Try to autoplay video (muted to comply with browser policies)
 */
function tryAutoplay(video) {
    if (!AUTOPLAY_ENABLED) return;

    // Double safety
    if (video.hasAttribute('data-no-autoplay')) return;

    // A deliberate pause survives scrolling away and back unless the viewer
    // opted into resuming videos automatically.
    if (video.dataset.userPaused === '1' && !RESUME_MEDIA_ON_VIEW) return;

    if (video.paused) {
        video.muted = true;
        pauseAllVideosExcept(video);
        if (window.pauseAllAudio) {
            window.pauseAllAudio();
        }

        video.play().catch(() => {
            // Autoplay blocked — expected
        });
    }
}

/**
 * Pausing for layout/autoplay reasons is not the same as the viewer choosing
 * to pause; only the latter is remembered.
 */
function pauseProgrammatically(media) {
    if (!media || media.paused) return;

    media.dataset.policyPause = '1';
    media.pause();
}

function formatVideoTime(seconds) {
    if (!Number.isFinite(seconds) || seconds < 0) return '0:00';

    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60).toString().padStart(2, '0');

    return `${mins}:${secs}`;
}

function setControlIcon(slot, name) {
    if (!slot) return;

    slot.innerHTML = `<i data-lucide="${name}" class="h-4 w-4"></i>`;

    if (typeof window.createLucideIcons === 'function') {
        window.createLucideIcons(slot);
    } else {
        window.lucide?.createIcons?.({ nodes: slot.querySelectorAll('[data-lucide]') });
    }
}

function restoreStoredVideoPosition(video) {
    if (!rememberPositions()) return;

    const saved = getStoredVideoPosition(videoSrc(video));

    if (
        saved &&
        Number.isFinite(video.duration) &&
        saved < video.duration - 2 &&
        video.dataset.positionRestored !== '1'
    ) {
        video.dataset.positionRestored = '1';
        video.currentTime = saved;
    }
}

/**
 * Replace the browser's native video controls with a compact bar that follows
 * the app's Tailwind/DaisyUI theme. Comment videos keep native controls because
 * they are mounted by Vue and manage their own lifecycle.
 */
function attachCustomVideoControls(video) {
    if (video.dataset.customControls === '1') return;
    if (video.hasAttribute('data-no-autoplay')) return;

    const host = video.parentElement;
    if (!host) return;

    video.dataset.customControls = '1';
    video.removeAttribute('controls');

    if (getComputedStyle(host).position === 'static') {
        host.classList.add('relative');
    }
    host.classList.add('group');

    const bar = document.createElement('div');
    bar.className = 'bleep-video-controls';
    bar.dataset.videoControls = '1';
    bar.innerHTML = `
        <button type="button" data-video-play class="btn btn-ghost btn-xs btn-circle shrink-0" aria-label="Play video">
            <span data-video-play-icon><i data-lucide="play" class="h-4 w-4"></i></span>
        </button>
        <span data-video-current class="w-9 shrink-0 text-right font-mono text-[11px] tabular-nums">0:00</span>
        <input type="range" data-video-seek min="0" max="1000" value="0" step="1"
               class="range range-xs range-primary h-1 min-w-0 flex-1 cursor-pointer"
               aria-label="Seek video">
        <span data-video-duration class="w-9 shrink-0 font-mono text-[11px] tabular-nums text-white/70">0:00</span>
        <div class="hidden shrink-0 items-center gap-1 sm:flex">
            <button type="button" data-video-mute class="btn btn-ghost btn-xs btn-circle" aria-label="Mute video">
                <span data-video-mute-icon><i data-lucide="volume-2" class="h-4 w-4"></i></span>
            </button>
            <input type="range" data-video-volume min="0" max="100" value="100" step="1"
                   class="range range-xs range-primary h-1 w-16 cursor-pointer"
                   aria-label="Video volume">
        </div>
        <button type="button" data-video-settings class="btn btn-ghost btn-xs btn-circle shrink-0"
                aria-label="Video settings" aria-haspopup="menu" aria-expanded="false">
            <span><i data-lucide="settings" class="h-4 w-4"></i></span>
        </button>
        <button type="button" data-video-fullscreen class="btn btn-ghost btn-xs btn-circle shrink-0" aria-label="Toggle fullscreen">
            <span><i data-lucide="maximize" class="h-4 w-4"></i></span>
        </button>
        <div data-video-menu class="bleep-video-menu hidden" role="menu">
            <p class="bleep-video-menu__label">Playback speed</p>
            <div data-video-speeds class="bleep-video-menu__speeds">
                <button type="button" data-video-speed="0.5" class="bleep-video-menu__speed" role="menuitemradio">0.5&times;</button>
                <button type="button" data-video-speed="0.75" class="bleep-video-menu__speed" role="menuitemradio">0.75&times;</button>
                <button type="button" data-video-speed="1" class="bleep-video-menu__speed" role="menuitemradio">1&times;</button>
                <button type="button" data-video-speed="1.25" class="bleep-video-menu__speed" role="menuitemradio">1.25&times;</button>
                <button type="button" data-video-speed="1.5" class="bleep-video-menu__speed" role="menuitemradio">1.5&times;</button>
                <button type="button" data-video-speed="2" class="bleep-video-menu__speed" role="menuitemradio">2&times;</button>
            </div>
            <div class="bleep-video-menu__divider"></div>
            <button type="button" data-video-loop class="bleep-video-menu__item" role="menuitemcheckbox" aria-checked="false">
                <i data-lucide="repeat" class="h-4 w-4"></i>
                <span>Loop video</span>
                <i data-lucide="check" class="bleep-video-menu__check hidden h-4 w-4"></i>
            </button>
            <a data-video-download class="bleep-video-menu__item hidden" role="menuitem" download>
                <i data-lucide="download" class="h-4 w-4"></i>
                <span>Download</span>
            </a>
            <button type="button" data-video-pip class="bleep-video-menu__item" role="menuitem">
                <i data-lucide="picture-in-picture" class="h-4 w-4"></i>
                <span>Picture in picture</span>
            </button>
            <button type="button" data-video-fullscreen-menu class="bleep-video-menu__item" role="menuitem">
                <i data-lucide="maximize" class="h-4 w-4"></i>
                <span>Fullscreen</span>
            </button>
        </div>
    `;

    // Keep control interaction from bubbling into the gallery click handler.
    ['click', 'pointerdown', 'dblclick'].forEach((type) => {
        bar.addEventListener(type, (event) => event.stopPropagation());
    });

    host.appendChild(bar);

    const playBtn = bar.querySelector('[data-video-play]');
    const playIcon = bar.querySelector('[data-video-play-icon]');
    const currentEl = bar.querySelector('[data-video-current]');
    const durationEl = bar.querySelector('[data-video-duration]');
    const seekEl = bar.querySelector('[data-video-seek]');
    const muteBtn = bar.querySelector('[data-video-mute]');
    const muteIcon = bar.querySelector('[data-video-mute-icon]');
    const volumeEl = bar.querySelector('[data-video-volume]');
    const fullscreenBtn = bar.querySelector('[data-video-fullscreen]');
    const settingsBtn = bar.querySelector('[data-video-settings]');
    const menu = bar.querySelector('[data-video-menu]');
    const speedButtons = menu?.querySelectorAll('[data-video-speed]') ?? [];
    const loopBtn = menu?.querySelector('[data-video-loop]');
    const loopCheck = loopBtn?.querySelector('.bleep-video-menu__check');
    const downloadLink = menu?.querySelector('[data-video-download]');
    const pipBtn = menu?.querySelector('[data-video-pip]');
    const fullscreenMenuBtn = menu?.querySelector('[data-video-fullscreen-menu]');

    const toggleSettings = (open = menu?.classList.contains('hidden')) => {
        if (!menu) return;

        menu.classList.toggle('hidden', !open);
        settingsBtn?.setAttribute('aria-expanded', open ? 'true' : 'false');
        bar.classList.toggle('is-menu-open', open);

        if (open) {
            syncSettingsMenu();
        }
    };

    const requestFullscreen = () => {
        const target = host.closest('[data-media-index]') || host;

        if (document.fullscreenElement) {
            document.exitFullscreen?.()?.catch?.(() => {});
        } else {
            target.requestFullscreen?.()?.catch?.(() => {});
        }
    };

    const syncSpeed = () => {
        const rate = Number(video.playbackRate) || 1;

        speedButtons.forEach((button) => {
            const active = Math.abs(Number(button.dataset.videoSpeed) - rate) < 0.01;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-checked', active ? 'true' : 'false');
        });
    };

    const syncLoop = () => {
        loopBtn?.setAttribute('aria-checked', video.loop ? 'true' : 'false');
        loopBtn?.classList.toggle('is-active', video.loop);
        loopCheck?.classList.toggle('hidden', !video.loop);
    };

    const syncDownload = () => {
        if (!downloadLink) return;

        const allowed = document.body.dataset.mediaDownloads === '1';
        const source = video.querySelector('source');
        const url = video.currentSrc || source?.src || source?.dataset?.src || '';
        const wrapper = host.closest('[data-media-alt]');
        const alt = wrapper?.getAttribute('data-media-alt') || video.getAttribute('data-media-alt') || '';
        const name = alt || decodeURIComponent(url.split('/').pop() || 'video');

        downloadLink.classList.toggle('hidden', !allowed || !url);
        if (allowed && url) {
            downloadLink.href = url;
            downloadLink.setAttribute('download', name);
        }
    };

    const syncPip = () => {
        if (!pipBtn) return;

        const active = document.pictureInPictureElement === video;
        pipBtn.classList.toggle('is-active', active);
        pipBtn.querySelector('span')?.replaceChildren(
            document.createTextNode(active ? 'Exit picture in picture' : 'Picture in picture')
        );
    };

    const syncSettingsMenu = () => {
        syncSpeed();
        syncLoop();
        syncDownload();
        syncPip();
    };

    settingsBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        toggleSettings();
    });

    speedButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const rate = Number(button.dataset.videoSpeed) || 1;
            video.playbackRate = rate;
            syncSpeed();
        });
    });

    loopBtn?.addEventListener('click', () => {
        video.loop = !video.loop;
        syncLoop();
    });

    pipBtn?.addEventListener('click', () => {
        if (document.pictureInPictureElement === video) {
            document.exitPictureInPicture?.()?.catch?.(() => {});
            return;
        }

        video.requestPictureInPicture?.()?.catch?.(() => {});
    });

    fullscreenMenuBtn?.addEventListener('click', () => {
        toggleSettings(false);
        requestFullscreen();
    });

    video.addEventListener('enterpictureinpicture', syncPip);
    video.addEventListener('leavepictureinpicture', syncPip);

    const syncPlayIcon = () => {
        setControlIcon(playIcon, video.paused ? 'play' : 'pause');
        playBtn?.setAttribute('aria-label', video.paused ? 'Play video' : 'Pause video');
    };

    const syncVolumeIcon = () => {
        const name = video.muted || video.volume === 0
            ? 'volume-x'
            : video.volume <= 0.5 ? 'volume-1' : 'volume-2';

        setControlIcon(muteIcon, name);
        muteBtn?.setAttribute('aria-label', name === 'volume-x' ? 'Unmute video' : 'Mute video');

        if (volumeEl) {
            volumeEl.value = String(Math.round((video.muted ? 0 : video.volume) * 100));
        }
    };

    const syncTime = () => {
        const duration = Number.isFinite(video.duration) ? video.duration : 0;
        const current = Number.isFinite(video.currentTime) ? video.currentTime : 0;

        if (currentEl) currentEl.textContent = formatVideoTime(current);
        if (durationEl) durationEl.textContent = formatVideoTime(duration);
        if (seekEl) {
            seekEl.value = duration > 0 ? String(Math.round((current / duration) * 1000)) : '0';
        }
    };

    playBtn?.addEventListener('click', () => {
        if (video.paused) {
            if (video.ended) video.currentTime = 0;
            video.play().catch(() => {});
        } else {
            video.pause();
        }
    });

    seekEl?.addEventListener('input', () => {
        if (!Number.isFinite(video.duration) || video.duration <= 0) return;

        video.currentTime = (Number(seekEl.value) / 1000) * video.duration;
    });

    muteBtn?.addEventListener('click', () => {
        video.muted = !video.muted;

        if (!video.muted && video.volume === 0) {
            video.volume = 1;
        }

        syncVolumeIcon();
    });

    volumeEl?.addEventListener('input', () => {
        const value = Number(volumeEl.value) / 100;
        video.volume = value;
        video.muted = value === 0;
        syncVolumeIcon();
    });

    fullscreenBtn?.addEventListener('click', requestFullscreen);

    video.addEventListener('loadedmetadata', () => {
        restoreStoredVideoPosition(video);
        syncTime();
    });
    video.addEventListener('durationchange', syncTime);
    video.addEventListener('timeupdate', syncTime);
    video.addEventListener('play', syncPlayIcon);
    video.addEventListener('pause', syncPlayIcon);
    video.addEventListener('ended', () => {
        syncPlayIcon();
        syncTime();
    });
    video.addEventListener('volumechange', syncVolumeIcon);
    video.addEventListener('ratechange', syncSpeed);

    syncPlayIcon();
    syncVolumeIcon();
    syncTime();
    syncSettingsMenu();
    setStoredVideoVolume(video.volume);

    if (typeof window.createLucideIcons === 'function') {
        window.createLucideIcons(bar);
    } else {
        window.lucide?.createIcons?.({ nodes: bar.querySelectorAll('[data-lucide]') });
    }
}

/**
 * Initialize video players with volume persistence
 */
function initVideoPlayers(container = document) {
    // If container is an event object, use document instead
    if (container instanceof Event) {
        container = document;
    }

    // Ensure container has querySelectorAll method
    if (!container || typeof container.querySelectorAll !== 'function') {
        container = document;
    }

    const videoElements = container.querySelectorAll('video');

    if (videoElements.length === 0) return;

    const storedVolume = getStoredVideoVolume();
    const observer = createVideoObserver();

    videoElements.forEach((video) => {
        // Skip if already initialized
        if (video.dataset.volumeInitialized === 'true') return;
        video.dataset.volumeInitialized = 'true';

        // Set stored volume
        video.volume = storedVolume;

        // Swap the browser's controls for the themed custom bar.
        attachCustomVideoControls(video);

        // Observe video for autoplay when in view
        if (observer) {
            observer.observe(video);
        }

        // Off-screen mode + mini-player controls (skipped for comment videos)
        if (!video.hasAttribute('data-no-autoplay')) {
            window.bleepMediaPolicy?.attachPlayerControls(video);
        }

        // Handle manual play
        video.addEventListener('play', (e) => {
            delete video.dataset.policyPause;
            delete video.dataset.userPaused;

            // Check if source needs to be loaded
            if (!isVideoSourceLoaded(video)) {
                e.preventDefault();
                pauseProgrammatically(video);

                // Load the source
                loadVideoSource(video);

                // Play after metadata loads
                video.addEventListener('loadedmetadata', () => {
                    video.play().catch(() => {});
                }, { once: true });
                return;
            }

            pauseAllVideosExcept(video);
            // Pause all audio players
            if (window.pauseAllAudio) {
                window.pauseAllAudio();
            }
        });

        // Remember an explicit viewer pause. Policy/autoplay pauses carry the
        // policyPause marker and are not treated as user intent.
        video.addEventListener('pause', () => {
            if (video.dataset.policyPause === '1') {
                delete video.dataset.policyPause;

                return;
            }

            if (!video.ended) {
                video.dataset.userPaused = '1';
            }
        });

        // Save volume when user changes it
        video.addEventListener('volumechange', () => {
            if (!video.muted) {
                setStoredVideoVolume(video.volume);
            }
        });

        // Remember the playback position when the viewer opted in.
        const savePosition = () => {
            if (!rememberPositions()) return;

            const src = videoSrc(video);
            if (!src || video.currentTime <= 0) return;

            setStoredVideoPosition(src, video.currentTime);
        };

        let lastSavedAt = 0;

        video.addEventListener('loadedmetadata', () => {
            restoreStoredVideoPosition(video);
        });

        video.addEventListener('timeupdate', () => {
            const now = Date.now();
            if (now - lastSavedAt > 2000) {
                lastSavedAt = now;
                savePosition();
            }
        });

        video.addEventListener('pause', savePosition);
        video.addEventListener('ended', () => {
            setStoredVideoPosition(videoSrc(video), 0);
        });
        window.addEventListener('pagehide', savePosition);
    });
}

/**
 * Pause all videos except the specified one
 */
function pauseAllVideosExcept(currentVideo) {
    document.querySelectorAll('video').forEach((video) => {
        if (video !== currentVideo && !video.paused) {
            // Double safety to prevent autoplay logic from affecting comment videos
            pauseProgrammatically(video);
        } else if (!video.hasAttribute('data-no-autoplay')) {
            // Observe non-comment videos for autoplay
            videoObserver?.observe(video);
        } else {
            // Unobserve comment videos to prevent autoplay logic from affecting them
            videoObserver?.unobserve(video);
        }
    });
}

/**
 * Pause all videos in a container
 */
function pauseAllVideos(container = document) {
    if (container instanceof Event) {
        container = document;
    }

    if (!container || typeof container.querySelectorAll !== 'function') {
        container = document;
    }

    container.querySelectorAll('video').forEach((video) => {
        if (!video.paused) {
            // Double safety to prevent autoplay logic from affecting comment videos
            pauseProgrammatically(video);
        } else if (!video.hasAttribute('data-no-autoplay')) {
            // Observe non-comment videos for autoplay
            videoObserver?.observe(video);
        } else {
            // Unobserve comment videos to prevent autoplay logic from affecting them
            videoObserver?.unobserve(video);
        }
    });
}

// Make globally accessible
window.initVideoPlayers = initVideoPlayers;
window.pauseAllVideos = pauseAllVideos;

const closeVideoMenus = () => {
    document.querySelectorAll('.bleep-video-menu:not(.hidden)').forEach((menu) => {
        const bar = menu.closest('[data-video-controls]');

        menu.classList.add('hidden');
        bar?.classList.remove('is-menu-open');
        bar?.querySelector('[data-video-settings]')?.setAttribute('aria-expanded', 'false');
    });
};

document.addEventListener('click', (event) => {
    if (event.target.closest?.('[data-video-controls]')) {
        return;
    }

    closeVideoMenus();
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeVideoMenus();
    }
});

// Initialize on page load. The custom player controls are enhancement work,
// so they run after the browser has painted the page and can accept input.
document.addEventListener('DOMContentLoaded', () => {
    const start = () => initVideoPlayers();

    if (window.whenIdle) {
        window.whenIdle(start, 1500);
    } else {
        window.setTimeout(start, 1);
    }
});

// Reinitialize when new content is loaded
document.addEventListener('bleeps:media:hydrated', () => {
    // console.log('Reinitializing video players after media hydration');
    setTimeout(() => initVideoPlayers(), 100);
});
