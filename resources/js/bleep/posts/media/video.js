const VIDEO_VOLUME_STORAGE_KEY = 'bleepVideoVolume';

// Intersection observer for autoplay
let videoObserver = null;
const AUTOPLAY_ENABLED = document.body?.dataset?.autoplayVideos !== '0';

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

            if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
                // Video is at least 50% visible - try to autoplay
                if (!isVideoSourceLoaded(video)) {
                    loadVideoSource(video);
                }

                // Wait for metadata to be ready before playing
                if (video.readyState >= 1) {
                    tryAutoplay(video);
                } else {
                    video.addEventListener('loadedmetadata', () => {
                        tryAutoplay(video);
                    }, { once: true });
                }
            } else if (!entry.isIntersecting) {
                // Video is not visible - pause it
                if (!video.paused) {
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
    if (video.paused) {
        // Ensure video is muted for autoplay (browser policy)
        video.muted = true;
        pauseAllVideosExcept(video);
        if (window.pauseAllAudio) {
            window.pauseAllAudio();
        }
        video.play().catch(() => {
            // Autoplay failed - user will need to click play
        });
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

        // Observe video for autoplay when in view
        if (observer) {
            observer.observe(video);
        }

        // Handle manual play
        video.addEventListener('play', (e) => {
            // Check if source needs to be loaded
            if (!isVideoSourceLoaded(video)) {
                e.preventDefault();
                video.pause();

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

        // Save volume when user changes it
        video.addEventListener('volumechange', () => {
            if (!video.muted) {
                setStoredVideoVolume(video.volume);
            }
        });
    });
}

/**
 * Pause all videos except the specified one
 */
function pauseAllVideosExcept(currentVideo) {
    document.querySelectorAll('video').forEach((video) => {
        if (video !== currentVideo && !video.paused) {
            video.pause();
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
            video.pause();
        }
    });
}

// Make globally accessible
window.initVideoPlayers = initVideoPlayers;
window.pauseAllVideos = pauseAllVideos;

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    // console.log('Initializing video players on page load');
    initVideoPlayers();
});

// Reinitialize when new content is loaded
document.addEventListener('bleeps:media:hydrated', () => {
    // console.log('Reinitializing video players after media hydration');
    setTimeout(() => initVideoPlayers(), 100);
});

// Pause videos when page is hidden (tab switching)
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        pauseAllVideos();
    }
});
