/**
 * Media Visibility Controller
 * Pauses audio and video when they scroll out of view or when modals are closed
 */

// Track all media elements
let mediaObserver = null;
let activeMediaElements = new Set();

/**
 * Pause a media element (audio or video)
 */
function pauseMedia(mediaElement) {
    if (!mediaElement) return;

    try {
        if (mediaElement.tagName === 'AUDIO' || mediaElement.tagName === 'VIDEO') {
            if (!mediaElement.paused) {
                mediaElement.pause();
                console.log('Media paused:', mediaElement.id || 'unnamed');
            }
        }
    } catch (error) {
        console.error('Error pausing media:', error);
    }
}

/**
 * Pause all audio players in a container
 */
function pauseAllAudioInContainer(container) {
    if (!container) return;

    const audioElements = container.querySelectorAll('audio.audio-element');
    audioElements.forEach(audio => {
        pauseMedia(audio);
    });
}

/**
 * Pause all video players in a container
 */
function pauseAllVideoInContainer(container) {
    if (!container) return;

    const videoElements = container.querySelectorAll('video');
    videoElements.forEach(video => {
        pauseMedia(video);
    });
}

/**
 * Pause all media in a container (audio and video)
 */
function pauseAllMediaInContainer(container) {
    pauseAllAudioInContainer(container);
    pauseAllVideoInContainer(container);
}

/**
 * Check if element is visible in viewport
 */
function isElementInViewport(element, threshold = 0.1) {
    const rect = element.getBoundingClientRect();
    const windowHeight = window.innerHeight || document.documentElement.clientHeight;
    const windowWidth = window.innerWidth || document.documentElement.clientWidth;

    // Element is visible if at least `threshold` of it is in viewport
    const vertInView = (rect.top <= windowHeight) && ((rect.top + rect.height * threshold) >= 0);
    const horInView = (rect.left <= windowWidth) && ((rect.left + rect.width * threshold) >= 0);

    return vertInView && horInView;
}

/**
 * Initialize Intersection Observer for media elements
 */
function initMediaVisibilityObserver() {
    // Cleanup existing observer
    if (mediaObserver) {
        mediaObserver.disconnect();
    }

    // Create new observer with 10% threshold
    mediaObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const mediaContainer = entry.target;

            // If media container is not intersecting (out of view), pause all media
            if (!entry.isIntersecting) {
                pauseAllMediaInContainer(mediaContainer);
            }
        });
    }, {
        threshold: 0.1, // Trigger when 10% of element is visible/invisible
        rootMargin: '0px'
    });

    // Observe all media containers (bleeps and comments)
    observeMediaContainers();
}

/**
 * Observe all media containers on the page
 */
function observeMediaContainers() {
    if (!mediaObserver) return;

    // Observe bleep media containers
    document.querySelectorAll('[data-bleep-media]').forEach(container => {
        mediaObserver.observe(container);
        activeMediaElements.add(container);
    });

    // Observe comment media containers
    document.querySelectorAll('[data-comment-media-wrapper]').forEach(container => {
        mediaObserver.observe(container);
        activeMediaElements.add(container);
    });
}

/**
 * Pause all media when comments modal is closed
 */
function pauseMediaOnCommentsClose() {
    const commentsModal = document.getElementById('floating-comments-modal');
    const closeBtn = document.getElementById('close-comments-btn');
    const overlay = document.getElementById('comments-overlay');

    if (!commentsModal) return;

    // Helper to pause all media in comments
    const pauseCommentsMedia = () => {
        pauseAllMediaInContainer(commentsModal);
    };

    // Close button
    if (closeBtn) {
        closeBtn.addEventListener('click', pauseCommentsMedia);
    }

    // Overlay click
    if (overlay) {
        overlay.addEventListener('click', pauseCommentsMedia);
    }

    // ESC key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !commentsModal.classList.contains('hidden')) {
            pauseCommentsMedia();
        }
    });

    // Listen for custom close events
    document.addEventListener('comments:closed', pauseCommentsMedia);
}

/**
 * Pause all media on page navigation
 */
function pauseMediaOnNavigation() {
    window.addEventListener('beforeunload', () => {
        // Pause all audio elements
        document.querySelectorAll('audio').forEach(audio => pauseMedia(audio));

        // Pause all video elements
        document.querySelectorAll('video').forEach(video => pauseMedia(video));
    });
}

/**
 * Pause media when page becomes hidden (tab switching)
 */
function pauseMediaOnPageHidden() {
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            // Page is hidden, pause all media
            document.querySelectorAll('audio').forEach(audio => pauseMedia(audio));
            document.querySelectorAll('video').forEach(video => pauseMedia(video));
        }
    });
}

/**
 * Initialize all media visibility controls
 */
function initMediaVisibilityControls() {
    // Initialize intersection observer for scroll-based pausing
    initMediaVisibilityObserver();

    // Pause media when comments are closed
    pauseMediaOnCommentsClose();

    // Pause media on page navigation
    pauseMediaOnNavigation();

    // Pause media when page is hidden (tab switching)
    pauseMediaOnPageHidden();

    console.log('Media visibility controls initialized');
}

/**
 * Re-observe media containers (call after dynamically loading content)
 */
function refreshMediaObserver() {
    if (mediaObserver) {
        observeMediaContainers();
    }
}

// Export functions for global use
window.pauseMedia = pauseMedia;
window.pauseAllMediaInContainer = pauseAllMediaInContainer;
window.refreshMediaObserver = refreshMediaObserver;

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', initMediaVisibilityControls);

// Re-observe when new content is loaded
document.addEventListener('bleeps:media:hydrated', refreshMediaObserver);
