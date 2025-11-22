// Profile Picture Cropper - Fixed to use actual file uploads
document.addEventListener('DOMContentLoaded', () => {
    const profileInput = document.getElementById('profile_picture_input');
    const cropperModal = document.getElementById('cropper_modal');
    const cropperImage = document.getElementById('cropper_image');
    const cropperContainer = document.getElementById('cropper_container');
    const cropButton = document.getElementById('crop_button');
    const cancelButton = document.getElementById('cancel_crop');
    const profilePreview = document.getElementById('profile_picture_preview');
    const defaultAvatar = document.getElementById('default_avatar');
    const recropButton = document.getElementById('recrop_button');

    // For desktop view (settings page)
    const profilePreviewDesktop = document.getElementById('profile_picture_preview_desktop');
    const defaultAvatarDesktop = document.getElementById('default_avatar_desktop');
    const recropButtonDesktop = document.getElementById('recrop_button_desktop');

    if (!profileInput || !cropperContainer || !cropperImage || !cropperModal) {
        return;
    }

    let pendingImageDataUrl = '';
    let sourceImageDataUrl = '';
    let isSaving = false;
    let croppedFile = null; // Store the cropped file

    let scale = 1, minScale = 1, maxScale = 5;
    let offsetX = 0, offsetY = 0;
    let isDragging = false;
    let dragStartX = 0, dragStartY = 0;
    let originOffsetX = 0, originOffsetY = 0;
    let lastPinchDistance = null;
    let imageNaturalWidth = 0, imageNaturalHeight = 0;

    // Check if there's an existing image
    const existingSrc = profilePreview?.getAttribute('src') || profilePreviewDesktop?.getAttribute('src') || '';
    if (existingSrc && existingSrc.trim()) {
        showPreviewOnly();
        sourceImageDataUrl = existingSrc;
        if (recropButton) recropButton.classList.remove('hidden');
        if (recropButtonDesktop) recropButtonDesktop.classList.remove('hidden');
    } else {
        showDefaultOnly();
    }

    // Clear input on click to allow re-selecting same file
    profileInput.addEventListener('click', () => { profileInput.value = ''; });

    profileInput.addEventListener('change', onFileSelect);
    cropButton?.addEventListener('click', onCropSave);
    cancelButton?.addEventListener('click', onCropCancel);
    
    // Recrop button handlers
    recropButton?.addEventListener('click', handleRecrop);
    recropButtonDesktop?.addEventListener('click', handleRecrop);

    cropperModal.addEventListener('change', onModalToggle);

    // Drag and zoom event listeners
    cropperContainer.addEventListener('mousedown', startDrag);
    document.addEventListener('mousemove', drag);
    document.addEventListener('mouseup', endDrag);
    cropperContainer.addEventListener('touchstart', onTouchStart, { passive: false });
    document.addEventListener('touchmove', onTouchMove, { passive: false });
    document.addEventListener('touchend', onTouchEnd);
    document.addEventListener('touchcancel', onTouchEnd);
    cropperContainer.addEventListener('wheel', onWheel, { passive: false });

    async function handleRecrop() {
        if (!sourceImageDataUrl) return;
        let dataUrl = sourceImageDataUrl;
        
        // If it's a URL, fetch and convert to data URL
        if (!isDataUrl(dataUrl)) {
            try { 
                dataUrl = await fetchToDataUrl(dataUrl); 
            } catch { 
                alert('Failed to load image for cropping.'); 
                return; 
            }
            sourceImageDataUrl = dataUrl;
        }
        
        pendingImageDataUrl = dataUrl;
        openCropper(dataUrl);
    }

    function onFileSelect(e) {
        const file = e.target.files?.[0];
        if (!file) return;
        
        if (!file.type.startsWith('image/')) { 
            alert('Please choose an image file.'); 
            profileInput.value = ''; 
            return; 
        }
        
        if (file.size > 5 * 1024 * 1024) { 
            alert('Image must be less than 5MB.'); 
            profileInput.value = ''; 
            return; 
        }
        
        const reader = new FileReader();
        reader.onload = ev => {
            pendingImageDataUrl = String(ev.target?.result || '');
            if (!pendingImageDataUrl) { 
                alert('Failed to read image.'); 
                profileInput.value = ''; 
                return; 
            }
            openCropper(pendingImageDataUrl);
        };
        reader.readAsDataURL(file);
    }

    function openCropper(dataUrl) {
        if (!dataUrl) return;
        cropperModal.checked = true;
        cropperContainer.style.cursor = 'grab';
        cropperImage.style.opacity = '0';

        const onLoad = () => {
            cropperImage.onload = null; 
            cropperImage.onerror = null;
            imageNaturalWidth = cropperImage.naturalWidth;
            imageNaturalHeight = cropperImage.naturalHeight;
            if (!imageNaturalWidth || !imageNaturalHeight) { 
                alert('Unable to load image.'); 
                cropperModal.checked = false; 
                return; 
            }
            resetCropper();
            updateTransform();
        };
        
        const onError = () => { 
            cropperImage.onload = null; 
            cropperImage.onerror = null; 
            alert('Unable to load image.'); 
            cropperModal.checked = false; 
        };

        cropperImage.onload = onLoad;
        cropperImage.onerror = onError;
        cropperImage.src = dataUrl;
        if (cropperImage.complete && cropperImage.naturalWidth) onLoad();
    }

    async function onCropSave() {
        if (!imageNaturalWidth || !imageNaturalHeight) return;
        
        const rect = cropperContainer.getBoundingClientRect();
        const size = Math.min(rect.width, rect.height);
        if (!size) return;

        // Create canvas and crop
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const out = 512;
        canvas.width = out; 
        canvas.height = out;

        const scaledW = imageNaturalWidth * scale;
        const scaledH = imageNaturalHeight * scale;
        const cropX = (scaledW - size) / 2 - offsetX;
        const cropY = (scaledH - size) / 2 - offsetY;
        const sx = cropX / scale;
        const sy = cropY / scale;
        const sSize = size / scale;

        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, out, out);
        ctx.drawImage(cropperImage, sx, sy, sSize, sSize, 0, 0, out, out);

        // Convert canvas to Blob
        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
        
        // Create File from Blob
        const timestamp = Date.now();
        croppedFile = new File([blob], `profile_${timestamp}.png`, { type: 'image/png' });
        
        // Update the file input with the cropped file
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(croppedFile);
        profileInput.files = dataTransfer.files;

        // Create preview URL
        const previewUrl = URL.createObjectURL(blob);
        setPreview(previewUrl);

        // Store source for recropping
        sourceImageDataUrl = pendingImageDataUrl || sourceImageDataUrl;
        pendingImageDataUrl = '';
        
        isSaving = true;
        cropperModal.checked = false;
    }

    function onCropCancel() { 
        pendingImageDataUrl = ''; 
        profileInput.value = ''; 
    }

    function onModalToggle() {
        if (this.checked) return;
        if (!isSaving) {
            pendingImageDataUrl = '';
            profileInput.value = '';
        }
        isSaving = false;
        resetState();
    }

    function resetCropper() {
        const rect = cropperContainer.getBoundingClientRect();
        const size = Math.min(rect.width, rect.height);
        const sx = size / imageNaturalWidth;
        const sy = size / imageNaturalHeight;
        const base = Math.max(sx, sy);
        scale = base * 1.05;
        minScale = scale;
        maxScale = minScale * 2.5;
        offsetX = 0; 
        offsetY = 0;
        lastPinchDistance = null;
        cropperContainer.style.cursor = 'grab';
        cropperImage.style.width = `${imageNaturalWidth}px`;
        cropperImage.style.height = `${imageNaturalHeight}px`;
    }

    function resetState() {
        scale = 1;
        offsetX = 0;
        offsetY = 0;
        isDragging = false;
        lastPinchDistance = null;
    }

    function startDrag(e) {
        if (!imageNaturalWidth) return;
        if (e.touches && e.touches.length > 1) return;
        isDragging = true;
        const p = point(e);
        dragStartX = p.x; 
        dragStartY = p.y;
        originOffsetX = offsetX; 
        originOffsetY = offsetY;
        cropperContainer.style.cursor = 'grabbing';
        e.preventDefault();
    }

    function drag(e) {
        if (!isDragging) return;
        if (e.touches && e.touches.length > 1) return;
        const p = point(e);
        offsetX = originOffsetX + (p.x - dragStartX);
        offsetY = originOffsetY + (p.y - dragStartY);
        updateTransform();
        e.preventDefault();
    }

    function endDrag() { 
        if (!isDragging) return; 
        isDragging = false; 
        cropperContainer.style.cursor = 'grab'; 
    }

    function onWheel(e) {
        if (!imageNaturalWidth) return;
        e.preventDefault();
        const factor = e.deltaY > 0 ? 0.9 : 1.1;
        const r = cropperContainer.getBoundingClientRect();
        const cx = e.clientX - r.left - r.width / 2;
        const cy = e.clientY - r.top - r.height / 2;
        applyZoom(scale * factor, cx, cy);
    }

    function onTouchStart(e) {
        if (!imageNaturalWidth) return;
        if (e.touches.length === 2) lastPinchDistance = dist(e.touches);
        else if (e.touches.length === 1) startDrag(e);
    }

    function onTouchMove(e) {
        if (!imageNaturalWidth) return;
        if (e.touches.length === 2) {
            e.preventDefault();
            const cur = dist(e.touches);
            if (lastPinchDistance) {
                const r = cropperContainer.getBoundingClientRect();
                const c = center(e.touches);
                const cx = c.x - r.left - r.width / 2;
                const cy = c.y - r.top - r.height / 2;
                const factor = cur / lastPinchDistance;
                applyZoom(scale * factor, cx, cy);
            }
            lastPinchDistance = cur;
        } else if (e.touches.length === 1 && isDragging) {
            e.preventDefault();
            drag(e);
        }
    }

    function onTouchEnd(e) { 
        if (e.touches.length === 0) endDrag(); 
        if (e.touches.length < 2) lastPinchDistance = null; 
    }

    function applyZoom(next, fx, fy) {
        const clamped = Math.min(Math.max(next, minScale), maxScale);
        if (clamped === scale) return;
        const diff = clamped / scale;
        offsetX = fx - (fx - offsetX) * diff;
        offsetY = fy - (fy - offsetY) * diff;
        scale = clamped;
        updateTransform();
    }

    function updateTransform() {
        constrain();
        cropperImage.style.transform = `translate(-50%, -50%) translate(${offsetX}px, ${offsetY}px) scale(${scale})`;
        cropperImage.style.opacity = '1';
    }

    function constrain() {
        const rect = cropperContainer.getBoundingClientRect();
        const size = Math.min(rect.width, rect.height);
        const sW = imageNaturalWidth * scale;
        const sH = imageNaturalHeight * scale;
        const maxX = Math.max(0, (sW - size) / 2);
        const maxY = Math.max(0, (sH - size) / 2);
        offsetX = clamp(offsetX, -maxX, maxX);
        offsetY = clamp(offsetY, -maxY, maxY);
    }

    // Preview helpers
    function setPreview(src) {
        // Mobile preview
        if (profilePreview) {
            profilePreview.src = src;
            profilePreview.classList.remove('hidden');
            profilePreview.style.display = 'block';
        }
        if (defaultAvatar) {
            defaultAvatar.classList.add('hidden');
            defaultAvatar.style.display = 'none';
        }
        
        // Desktop preview
        if (profilePreviewDesktop) {
            profilePreviewDesktop.src = src;
            profilePreviewDesktop.classList.remove('hidden');
            profilePreviewDesktop.style.display = 'block';
        }
        if (defaultAvatarDesktop) {
            defaultAvatarDesktop.classList.add('hidden');
            defaultAvatarDesktop.style.display = 'none';
        }
        
        if (recropButton) recropButton.classList.remove('hidden');
        if (recropButtonDesktop) recropButtonDesktop.classList.remove('hidden');
    }

    function showPreviewOnly() {
        if (profilePreview) {
            profilePreview.classList.remove('hidden');
            profilePreview.style.display = 'block';
        }
        if (defaultAvatar) {
            defaultAvatar.classList.add('hidden');
            defaultAvatar.style.display = 'none';
        }
        if (profilePreviewDesktop) {
            profilePreviewDesktop.classList.remove('hidden');
            profilePreviewDesktop.style.display = 'block';
        }
        if (defaultAvatarDesktop) {
            defaultAvatarDesktop.classList.add('hidden');
            defaultAvatarDesktop.style.display = 'none';
        }
    }

    function showDefaultOnly() {
        if (profilePreview) {
            profilePreview.classList.add('hidden');
            profilePreview.style.display = 'none';
            profilePreview.removeAttribute('src');
        }
        if (defaultAvatar) {
            defaultAvatar.classList.remove('hidden');
            defaultAvatar.style.display = 'flex';
        }
        if (profilePreviewDesktop) {
            profilePreviewDesktop.classList.add('hidden');
            profilePreviewDesktop.style.display = 'none';
            profilePreviewDesktop.removeAttribute('src');
        }
        if (defaultAvatarDesktop) {
            defaultAvatarDesktop.classList.remove('hidden');
            defaultAvatarDesktop.style.display = 'flex';
        }
        if (recropButton) recropButton.classList.add('hidden');
        if (recropButtonDesktop) recropButtonDesktop.classList.add('hidden');
    }

    // Utils
    function point(e) { 
        return e.touches?.length 
            ? {x: e.touches[0].clientX, y: e.touches[0].clientY}
            : {x: e.clientX, y: e.clientY}; 
    }
    function dist(ts) { 
        const [a, b] = ts; 
        return Math.hypot(b.clientX - a.clientX, b.clientY - a.clientY); 
    }
    function center(ts) { 
        const [a, b] = ts; 
        return { x: (a.clientX + b.clientX) / 2, y: (a.clientY + b.clientY) / 2 }; 
    }
    function clamp(v, mn, mx) { 
        return Math.min(Math.max(v, mn), mx); 
    }
    function isDataUrl(v) { 
        return typeof v === 'string' && v.startsWith('data:image/') && v.includes(';base64,'); 
    }
    async function fetchToDataUrl(url) {
        return fetch(url)
            .then(r => r.blob())
            .then(b => new Promise(res => {
                const fr = new FileReader(); 
                fr.onload = () => res(fr.result); 
                fr.readAsDataURL(b);
            }));
    }
});