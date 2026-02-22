document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('media-modal');
    if (!modal) return;
    const closeBtn = document.getElementById('media-modal-close');
    const prevBtn = document.getElementById('media-modal-prev');
    const nextBtn = document.getElementById('media-modal-next');
    const image = document.getElementById('media-modal-image');
    const video = document.getElementById('media-modal-video');
    const videoSource = document.getElementById('media-modal-video-source');
    const counter = document.getElementById('media-modal-counter');
    const container = document.getElementById('media-modal-container');

    // Zoom controls
    const zoomControls = document.querySelector('.flex.items-center.gap-1.bg-black\\/50');
    const zoomInBtn = document.getElementById('media-modal-zoom-in');
    const zoomOutBtn = document.getElementById('media-modal-zoom-out');
    const zoomResetBtn = document.getElementById('media-modal-zoom-reset');
    const zoomLevel = document.getElementById('media-modal-zoom-level');

    let mediaItems = [];
    let currentIndex = 0;
    let currentZoom = 1;
    let isDragging = false;
    let startX = 0;
    let startY = 0;
    let translateX = 0;
    let translateY = 0;
    let currentTranslateX = 0;
    let currentTranslateY = 0;

    const MIN_ZOOM = 0.5;
    const MAX_ZOOM = 8;
    const ZOOM_STEP = 0.25;

    // Open modal when clicking on media
    document.addEventListener('click', (e) => {
        const mediaItem = e.target.closest('[data-media-index]');
        if (!mediaItem) return;

        const bleepMedia = mediaItem.closest('[data-bleep-media]');
        if (!bleepMedia) return;

        mediaItems = Array.from(bleepMedia.querySelectorAll('[data-media-index]')).map(el => ({
            type: el.dataset.mediaType,
            src: el.dataset.mediaSrc,
            alt: el.dataset.mediaAlt || '',
            mime: el.dataset.mediaMime || ''
        }));

        currentIndex = parseInt(mediaItem.dataset.mediaIndex);

        // Reset all state before showing
        resetZoom();

        showMedia(currentIndex);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }, true);

    // Close modal
    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        video.pause();
        video.currentTime = 0;
        resetZoom();
    };

    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    // Navigation
    prevBtn.addEventListener('click', () => {
        currentIndex = (currentIndex - 1 + mediaItems.length) % mediaItems.length;
        resetZoom();
        showMedia(currentIndex);
    });

    nextBtn.addEventListener('click', () => {
        currentIndex = (currentIndex + 1) % mediaItems.length;
        resetZoom();
        showMedia(currentIndex);
    });

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (!modal.classList.contains('flex')) return;

        if (e.key === 'Escape') closeModal();
        if (e.key === 'ArrowLeft' && currentIndex > 0) {
            resetZoom();
            prevBtn.click();
        }
        if (e.key === 'ArrowRight' && currentIndex < mediaItems.length - 1) {
            resetZoom();
            nextBtn.click();
        }
    });

    // Zoom functionality (images only)
    function setZoom(zoom) {
        currentZoom = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, zoom));
        updateTransform();
        zoomLevel.textContent = `${Math.round(currentZoom * 100)}%`;

        // Enable/disable dragging based on zoom
        if (currentZoom > 1) {
            image.style.cursor = 'grab';
        } else {
            image.style.cursor = 'default';
            currentTranslateX = 0;
            currentTranslateY = 0;
            updateTransform();
        }
    }

    function resetZoom() {
        currentZoom = 1;
        currentTranslateX = 0;
        currentTranslateY = 0;
        isDragging = false;
        image.style.cursor = 'default';
        image.style.transition = 'transform 0.2s ease';
        updateTransform();
        zoomLevel.textContent = '100%';
    }

    function updateTransform() {
        image.style.transform = `translate(${currentTranslateX}px, ${currentTranslateY}px) scale(${currentZoom})`;
    }

    zoomInBtn.addEventListener('click', () => {
        setZoom(currentZoom + ZOOM_STEP);
    });

    zoomOutBtn.addEventListener('click', () => {
        setZoom(currentZoom - ZOOM_STEP);
    });

    zoomResetBtn.addEventListener('click', resetZoom);

    // Mouse wheel zoom (images only)
    image.addEventListener('wheel', (e) => {
        e.preventDefault();
        const delta = e.deltaY > 0 ? -ZOOM_STEP : ZOOM_STEP;
        setZoom(currentZoom + delta);
    }, { passive: false });

    // Drag functionality for zoomed images
    function startDrag(x, y) {
        if (currentZoom <= 1) return;
        isDragging = true;
        startX = x - currentTranslateX;
        startY = y - currentTranslateY;
        image.style.cursor = 'grabbing';
        image.style.transition = 'none';
    }

    function drag(x, y) {
        if (!isDragging) return;
        currentTranslateX = x - startX;
        currentTranslateY = y - startY;
        updateTransform();
    }

    function endDrag() {
        if (!isDragging) return;
        isDragging = false;
        if (currentZoom > 1) {
            image.style.cursor = 'grab';
        }
        image.style.transition = 'transform 0.2s ease';
    }

    // Mouse events
    image.addEventListener('mousedown', (e) => {
        e.preventDefault();
        startDrag(e.clientX, e.clientY);
    });

    document.addEventListener('mousemove', (e) => {
        drag(e.clientX, e.clientY);
    });

    document.addEventListener('mouseup', endDrag);

    // Touch events for mobile
    image.addEventListener('touchstart', (e) => {
        if (e.touches.length === 1) {
            e.preventDefault();
            startDrag(e.touches[0].clientX, e.touches[0].clientY);
        }
    }, { passive: false });

    image.addEventListener('touchmove', (e) => {
        if (e.touches.length === 1) {
            e.preventDefault();
            drag(e.touches[0].clientX, e.touches[0].clientY);
        }
    }, { passive: false });

    image.addEventListener('touchend', endDrag);

    // Pinch to zoom for mobile (images only)
    let initialDistance = 0;
    let initialZoom = 1;

    image.addEventListener('touchstart', (e) => {
        if (e.touches.length === 2) {
            e.preventDefault();
            const touch1 = e.touches[0];
            const touch2 = e.touches[1];
            initialDistance = Math.hypot(
                touch2.clientX - touch1.clientX,
                touch2.clientY - touch1.clientY
            );
            initialZoom = currentZoom;
        }
    }, { passive: false });

    image.addEventListener('touchmove', (e) => {
        if (e.touches.length === 2) {
            e.preventDefault();
            const touch1 = e.touches[0];
            const touch2 = e.touches[1];
            const distance = Math.hypot(
                touch2.clientX - touch1.clientX,
                touch2.clientY - touch1.clientY
            );
            const scale = distance / initialDistance;
            setZoom(initialZoom * scale);
        }
    }, { passive: false });

    // Double tap to zoom on mobile (images only)
    let lastTap = 0;
    image.addEventListener('touchend', (e) => {
        const currentTime = new Date().getTime();
        const tapLength = currentTime - lastTap;
        if (tapLength < 300 && tapLength > 0) {
            e.preventDefault();
            if (currentZoom === 1) {
                setZoom(2);
            } else {
                resetZoom();
            }
        }
        lastTap = currentTime;
    });

    // Show media
    function showMedia(index) {
        const item = mediaItems[index];

        // Reset transform before showing new media
        image.style.transform = 'translate(0, 0) scale(1)';
        image.style.transition = 'transform 0.2s ease';

        // Hide both elements first
        image.classList.add('hidden');
        video.classList.add('hidden');
        video.pause();

        // Show/hide zoom controls based on media type
        if (item.type === 'image') {
            zoomControls.classList.remove('hidden');
            // Wait for image to load before showing
            const tempImg = new Image();
            tempImg.onload = () => {
                image.src = item.src;
                image.alt = item.alt;
                image.classList.remove('hidden');
            };
            tempImg.src = item.src;
        } else {
            zoomControls.classList.add('hidden');
            videoSource.src = item.src;
            videoSource.type = item.mime;
            video.load();
            video.classList.remove('hidden');
        }

        // Update counter
        counter.textContent = `${index + 1} / ${mediaItems.length}`;

        // Show/hide navigation buttons
        if (mediaItems.length <= 1) {
            prevBtn.classList.add('hidden');
            nextBtn.classList.add('hidden');
        } else {
            if (index === 0) {
                prevBtn.classList.add('hidden');
            } else {
                prevBtn.classList.remove('hidden');
            }

            if (index === mediaItems.length - 1) {
                nextBtn.classList.add('hidden');
            } else {
                nextBtn.classList.remove('hidden');
            }
        }

        // Reinitialize Lucide icons
        if (window.lucide) window.lucide.createIcons();
    }
});
