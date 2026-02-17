const VIDEO_VOLUME_STORAGE_KEY = 'bleepVideoVolume';

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

    videoElements.forEach((video) => {
        // Skip if already initialized
        if (video.dataset.volumeInitialized === 'true') return;
        video.dataset.volumeInitialized = 'true';

        // Set stored volume
        video.volume = storedVolume;

        // Save volume when user changes it
        video.addEventListener('volumechange', () => {
            if (!video.muted) {
                setStoredVideoVolume(video.volume);
            }
        });

        // Pause other videos when this one plays
        video.addEventListener('play', () => {
            pauseAllVideosExcept(video);
        });

        // console.log(`Video player initialized with volume: ${storedVolume}`);
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
