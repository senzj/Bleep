// Profile Picture Cropper
document.addEventListener('DOMContentLoaded', () => {
    const profileInput = document.getElementById('profile_picture_input');
    const cropperModal = document.getElementById('cropper_modal');
    const cropperImage = document.getElementById('cropper_image');
    const cropperContainer = document.getElementById('cropper_container');
    const cropButton = document.getElementById('crop_button');
    const cancelButton = document.getElementById('cancel_crop');
    const profilePreview = document.getElementById('profile_picture_preview');
    const defaultAvatar = document.getElementById('default_avatar');
    const profilePictureData = document.getElementById('profile_picture_data');
    const recropButton = document.getElementById('recrop_button');
    const zoomInButton = document.getElementById('zoom_in');
    const zoomOutButton = document.getElementById('zoom_out');

    if (!profileInput || !cropperContainer || !cropperImage || !cropperModal) {
        return;
    }

    let pendingImageDataUrl = '';
    let sourceImageDataUrl = '';
    let isSaving = false;

    let scale = 1;
    let minScale = 1;
    let maxScale = 5;
    let offsetX = 0;
    let offsetY = 0;
    let isDragging = false;
    let dragStartX = 0;
    let dragStartY = 0;
    let originOffsetX = 0;
    let originOffsetY = 0;
    let lastPinchDistance = null;
    let imageNaturalWidth = 0;
    let imageNaturalHeight = 0;

    const initialData = (profilePictureData?.value || '').trim();
    if (isValidImageData(initialData)) {
        setPreview(initialData);
        sourceImageDataUrl = initialData;
    } else {
        showDefaultAvatar();
    }

    profileInput.addEventListener('click', () => {
        profileInput.value = '';
    });

    profileInput.addEventListener('change', handleFileSelection);
    cropButton?.addEventListener('click', handleCropSave);
    cancelButton?.addEventListener('click', handleCropCancel);
    recropButton?.addEventListener('click', () => {
        if (!sourceImageDataUrl) return;
        pendingImageDataUrl = sourceImageDataUrl;
        openCropperWithData(pendingImageDataUrl);
    });

    zoomInButton?.addEventListener('click', () => {
        applyZoom(scale * 1.1, 0, 0);
    });

    zoomOutButton?.addEventListener('click', () => {
        applyZoom(scale * 0.9, 0, 0);
    });

    cropperModal.addEventListener('change', handleModalToggle);

    cropperContainer.addEventListener('mousedown', startDrag);
    document.addEventListener('mousemove', drag);
    document.addEventListener('mouseup', endDrag);

    cropperContainer.addEventListener('touchstart', onTouchStart, { passive: false });
    document.addEventListener('touchmove', onTouchMove, { passive: false });
    document.addEventListener('touchend', onTouchEnd);
    document.addEventListener('touchcancel', onTouchEnd);

    cropperContainer.addEventListener('wheel', onWheelZoom, { passive: false });

    function handleFileSelection(event) {
        const file = event.target.files?.[0];
        if (!file) return;

        if (!file.type.startsWith('image/')) {
            alert('Please choose an image file.');
            resetInput();
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            alert('Image must be less than 5MB.');
            resetInput();
            return;
        }

        const reader = new FileReader();
        reader.onload = e => {
            pendingImageDataUrl = String(e.target?.result || '');
            if (!pendingImageDataUrl) {
                alert('Failed to read the selected image.');
                resetInput();
                return;
            }
            openCropperWithData(pendingImageDataUrl);
        };
        reader.readAsDataURL(file);
    }

    function openCropperWithData(dataUrl) {
        if (!dataUrl) return;

        cropperModal.checked = true;
        cropperContainer.style.cursor = 'grab';
        cropperImage.style.opacity = '0';

        const handleLoad = () => {
            cropperImage.onload = null;
            cropperImage.onerror = null;

            imageNaturalWidth = cropperImage.naturalWidth;
            imageNaturalHeight = cropperImage.naturalHeight;

            if (!imageNaturalWidth || !imageNaturalHeight) {
                alert('Unable to load the selected image.');
                cropperModal.checked = false;
                return;
            }

            requestAnimationFrame(() => {
                resetCropper();
                updateImageTransform();
            });
        };

        const handleError = () => {
            cropperImage.onload = null;
            cropperImage.onerror = null;
            alert('Unable to load the selected image.');
            cropperModal.checked = false;
        };

        cropperImage.onload = handleLoad;
        cropperImage.onerror = handleError;
        cropperImage.src = dataUrl;
        if (cropperImage.complete && cropperImage.naturalWidth) {
            handleLoad();
        }
    }

    function handleCropSave() {
        if (!imageNaturalWidth || !imageNaturalHeight) return;

        const containerRect = cropperContainer.getBoundingClientRect();
        if (!containerRect) return;

        const containerSize = Math.min(containerRect.width, containerRect.height);
        if (!containerSize) return;

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const outputSize = 512;

        canvas.width = outputSize;
        canvas.height = outputSize;

        // Calculate the visible portion of the image
        const scaledWidth = imageNaturalWidth * scale;
        const scaledHeight = imageNaturalHeight * scale;

        // Calculate crop area in scaled coordinates
        const cropX = (scaledWidth - containerSize) / 2 - offsetX;
        const cropY = (scaledHeight - containerSize) / 2 - offsetY;

        // Convert back to natural image coordinates
        const sourceX = cropX / scale;
        const sourceY = cropY / scale;
        const sourceSize = containerSize / scale;

        // Draw the cropped area to canvas
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, outputSize, outputSize);

        ctx.drawImage(
            cropperImage,
            sourceX,
            sourceY,
            sourceSize,
            sourceSize,
            0,
            0,
            outputSize,
            outputSize
        );

        const croppedDataUrl = canvas.toDataURL('image/png');
        profilePictureData.value = croppedDataUrl;
        setPreview(croppedDataUrl);

        sourceImageDataUrl = pendingImageDataUrl || sourceImageDataUrl;
        pendingImageDataUrl = '';
        profileInput.value = '';
        isSaving = true;
        cropperModal.checked = false;
    }

    function handleCropCancel() {
        pendingImageDataUrl = '';
        resetInput();
    }

    function handleModalToggle() {
        if (this.checked) return;
        if (!isSaving) pendingImageDataUrl = '';
        isSaving = false;
        resetTransformState();
    }

    function resetCropper() {
        const containerRect = cropperContainer.getBoundingClientRect();
        if (!containerRect || !imageNaturalWidth || !imageNaturalHeight) return;

        const containerSize = Math.min(containerRect.width, containerRect.height);

        // Calculate minimum scale to completely fill the container (no gaps)
        const scaleX = containerSize / imageNaturalWidth;
        const scaleY = containerSize / imageNaturalHeight;
        const baseMinScale = Math.max(scaleX, scaleY);

        // Set initial scale with 5% extra zoom to prevent gaps
        scale = baseMinScale * 1.05;

        // Set minScale to the initial scale so users can't zoom out beyond it
        minScale = scale;

        // Maximum scale - reasonable zoom limit
        maxScale = minScale * 2.5;

        offsetX = 0;
        offsetY = 0;
        lastPinchDistance = null;
        cropperContainer.style.cursor = 'grab';

        // Set image dimensions
        cropperImage.style.width = `${imageNaturalWidth}px`;
        cropperImage.style.height = `${imageNaturalHeight}px`;
    }

    function startDrag(event) {
        if (!imageNaturalWidth) return;
        if (event.touches && event.touches.length > 1) return;

        isDragging = true;
        const point = getEventPoint(event);
        dragStartX = point.x;
        dragStartY = point.y;
        originOffsetX = offsetX;
        originOffsetY = offsetY;
        cropperContainer.style.cursor = 'grabbing';
        event.preventDefault();
    }

    function drag(event) {
        if (!isDragging) return;
        if (event.touches && event.touches.length > 1) return;

        const point = getEventPoint(event);
        offsetX = originOffsetX + (point.x - dragStartX);
        offsetY = originOffsetY + (point.y - dragStartY);
        updateImageTransform();
        event.preventDefault();
    }

    function endDrag() {
        if (!isDragging) return;
        isDragging = false;
        cropperContainer.style.cursor = 'grab';
    }

    function onWheelZoom(event) {
        if (!imageNaturalWidth) return;
        event.preventDefault();

        const zoomFactor = event.deltaY > 0 ? 0.9 : 1.1;
        const rect = cropperContainer.getBoundingClientRect();
        const centerX = event.clientX - rect.left - rect.width / 2;
        const centerY = event.clientY - rect.top - rect.height / 2;

        applyZoom(scale * zoomFactor, centerX, centerY);
    }

    function onTouchStart(event) {
        if (!imageNaturalWidth) return;

        if (event.touches.length === 2) {
            lastPinchDistance = getTouchDistance(event.touches);
        } else if (event.touches.length === 1) {
            startDrag(event);
        }
    }

    function onTouchMove(event) {
        if (!imageNaturalWidth) return;

        if (event.touches.length === 2) {
            event.preventDefault();
            const distance = getTouchDistance(event.touches);
            if (lastPinchDistance) {
                const rect = cropperContainer.getBoundingClientRect();
                const center = getTouchCenter(event.touches);
                const centerX = center.x - rect.left - rect.width / 2;
                const centerY = center.y - rect.top - rect.height / 2;
                const zoomFactor = distance / lastPinchDistance;
                applyZoom(scale * zoomFactor, centerX, centerY);
            }
            lastPinchDistance = distance;
        } else if (event.touches.length === 1 && isDragging) {
            event.preventDefault();
            drag(event);
        }
    }

    function onTouchEnd(event) {
        if (event.touches.length === 0) endDrag();
        if (event.touches.length < 2) lastPinchDistance = null;
    }

    function applyZoom(nextScale, focusX, focusY) {
        const clampedScale = clamp(nextScale, minScale, maxScale);
        if (clampedScale === scale) return;

        // Zoom towards the focus point
        const scaleDiff = clampedScale / scale;
        offsetX = focusX - (focusX - offsetX) * scaleDiff;
        offsetY = focusY - (focusY - offsetY) * scaleDiff;

        scale = clampedScale;
        updateImageTransform();
    }

    function updateImageTransform() {
        constrainOffsets();
        cropperImage.style.transform = `translate(-50%, -50%) translate(${offsetX}px, ${offsetY}px) scale(${scale})`;
        if (cropperImage.style.opacity !== '1') {
            cropperImage.style.opacity = '1';
        }
    }

    function constrainOffsets() {
        const rect = cropperContainer.getBoundingClientRect();
        if (!rect) return;

        const containerSize = Math.min(rect.width, rect.height);
        const scaledWidth = imageNaturalWidth * scale;
        const scaledHeight = imageNaturalHeight * scale;

        // Calculate maximum offset to prevent gaps
        const maxX = Math.max(0, (scaledWidth - containerSize) / 2);
        const maxY = Math.max(0, (scaledHeight - containerSize) / 2);

        offsetX = clamp(offsetX, -maxX, maxX);
        offsetY = clamp(offsetY, -maxY, maxY);
    }

    function getEventPoint(event) {
        if (event.touches && event.touches.length) {
            return {
                x: event.touches[0].clientX,
                y: event.touches[0].clientY
            };
        }
        return {
            x: event.clientX,
            y: event.clientY
        };
    }

    function getTouchDistance(touches) {
        const [a, b] = touches;
        return Math.hypot(b.clientX - a.clientX, b.clientY - a.clientY);
    }

    function getTouchCenter(touches) {
        const [a, b] = touches;
        return {
            x: (a.clientX + b.clientX) / 2,
            y: (a.clientY + b.clientY) / 2
        };
    }

    function resetTransformState() {
        isDragging = false;
        dragStartX = 0;
        dragStartY = 0;
        originOffsetX = 0;
        originOffsetY = 0;
        offsetX = 0;
        offsetY = 0;
        scale = 1;
        minScale = 1;
        maxScale = 5;
        imageNaturalWidth = 0;
        imageNaturalHeight = 0;
        lastPinchDistance = null;
        cropperContainer.style.cursor = 'grab';
        cropperImage.style.transform = 'translate(-50%, -50%)';
        cropperImage.style.opacity = '0';
        cropperImage.style.width = '';
        cropperImage.style.height = '';
    }

    function resetInput() {
        profileInput.value = '';
    }

    function setPreview(dataUrl) {
        if (!isValidImageData(dataUrl)) {
            showDefaultAvatar();
            return;
        }
        // Force exclusive visibility
        profilePreview.src = dataUrl;
        profilePreview.classList.remove('hidden');
        profilePreview.style.display = 'block';
        defaultAvatar?.classList.add('hidden');
        if (defaultAvatar) defaultAvatar.style.display = 'none';
        recropButton?.classList.remove('hidden');
    }

    function showDefaultAvatar() {
        // Ensure preview is fully hidden and does not show alt text
        profilePreview.classList.add('hidden');
        profilePreview.style.display = 'none';
        // Remove src so browser does not render failed image showing alt
        profilePreview.removeAttribute('src');

        if (defaultAvatar) {
            defaultAvatar.classList.remove('hidden');
            defaultAvatar.style.display = 'flex';
        }
        recropButton?.classList.add('hidden');

        if (window.createLucideIcons) window.createLucideIcons();
    }

    // Initial state enforcement after DOM ready
    (function enforceInitialState() {
        const val = (profilePictureData?.value || '').trim();
        if (isValidImageData(val)) {
            setPreview(val);
        } else {
            showDefaultAvatar();
        }
    })();

    function isValidImageData(val) {
        return typeof val === 'string'
            && val.startsWith('data:image/')
            && val.includes(';base64,');
    }

    function clamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }
});
