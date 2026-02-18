// Load media asynchronously after page is interactive - doesn't block page render
// This script ONLY runs on initial page load, not for infinite scroll content
(function() {
    'use strict';

    // Load a single image
    const loadImage = (img) => {
        const src = img.getAttribute('data-src');
        if (src && !img.src) {
            img.src = src;
            img.removeAttribute('data-src');
        }
    };

    // Load a single video
    const loadVideo = (video) => {
        const sources = video.querySelectorAll('source[data-src], source[data-media-src]');
        sources.forEach(source => {
            const src = source.getAttribute('data-src') || source.getAttribute('data-media-src');
            if (src && !source.src) {
                source.src = src;
                source.removeAttribute('data-src');
                source.removeAttribute('data-media-src');
            }
        });
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

    // Load media that's currently visible in viewport first
    const loadVisibleMedia = (images, videos, audios) => {
        const viewportTop = window.scrollY;
        const viewportBottom = viewportTop + window.innerHeight;

        images.forEach(img => {
            const rect = img.getBoundingClientRect();
            const top = rect.top + window.scrollY;
            const bottom = top + rect.height;

            if (bottom >= viewportTop - 300 && top <= viewportBottom + 300) {
                loadImage(img);
            }
        });

        videos.forEach(video => {
            const rect = video.getBoundingClientRect();
            const top = rect.top + window.scrollY;
            const bottom = top + rect.height;

            if (bottom >= viewportTop - 300 && top <= viewportBottom + 300) {
                loadVideo(video);
            }
        });

        audios.forEach(audio => {
            const rect = audio.getBoundingClientRect();
            const top = rect.top + window.scrollY;
            const bottom = top + rect.height;

            if (bottom >= viewportTop - 300 && top <= viewportBottom + 300) {
                loadAudio(audio);
            }
        });
    };

    // Load remaining media in background without blocking
    const loadRemainingMedia = (images, videos, audios) => {
        const remainingImages = Array.from(images).filter(img => img.getAttribute('data-src'));
        const remainingVideos = Array.from(videos).filter(video =>
            video.querySelector('source[data-src], source[data-media-src]')
        );
        const remainingAudios = Array.from(audios).filter(audio => audio.getAttribute('data-src'));

        if (remainingImages.length === 0 && remainingVideos.length === 0 && remainingAudios.length === 0) return;

        let imageIndex = 0;
        let videoIndex = 0;
        let audioIndex = 0;

        const loadNextBatch = () => {
            for (let i = 0; i < 3 && imageIndex < remainingImages.length; i++) {
                const img = remainingImages[imageIndex++];
                if (img.getAttribute('data-src')) {
                    loadImage(img);
                }
            }

            for (let i = 0; i < 2 && videoIndex < remainingVideos.length; i++) {
                const video = remainingVideos[videoIndex++];
                if (video.querySelector('source[data-src], source[data-media-src]')) {
                    loadVideo(video);
                }
            }

            for (let i = 0; i < 2 && audioIndex < remainingAudios.length; i++) {
                const audio = remainingAudios[audioIndex++];
                if (audio.getAttribute('data-src')) {
                    loadAudio(audio);
                }
            }

            if (imageIndex < remainingImages.length || videoIndex < remainingVideos.length || audioIndex < remainingAudios.length) {
                setTimeout(loadNextBatch, 50);
            }
        };

        loadNextBatch();
    };

    // Main function to load all media
    const loadMediaAsynchronously = () => {
        const images = document.querySelectorAll('img[data-src]');
        const videos = document.querySelectorAll('video');
        const audios = document.querySelectorAll('audio[data-src]');

        loadVisibleMedia(images, videos, audios);
        loadRemainingMedia(images, videos, audios);
    };

    // Export function globally for infinite scroll to use
    window.loadNewMedia = (container) => {
        const images = container.querySelectorAll('img[data-src]');
        const videos = container.querySelectorAll('video');
        const audios = container.querySelectorAll('audio[data-src]');

        // Load all new media immediately (no delay for infinite scroll content)
        images.forEach(loadImage);
        videos.forEach(loadVideo);
        audios.forEach(loadAudio);
    };

    // Run ONLY on initial page load - use requestIdleCallback for best performance
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            if ('requestIdleCallback' in window) {
                requestIdleCallback(loadMediaAsynchronously, { timeout: 1000 });
            } else {
                setTimeout(loadMediaAsynchronously, 0);
            }
        });
    } else {
        if ('requestIdleCallback' in window) {
            requestIdleCallback(loadMediaAsynchronously, { timeout: 1000 });
        } else {
            setTimeout(loadMediaAsynchronously, 0);
        }
    }
})();
