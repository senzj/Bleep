document.addEventListener('DOMContentLoaded', () => {
  const fileInput = document.getElementById('profile_picture_input');
  const modalCheckbox = document.getElementById('cropper_modal');
  const cropperContainer = document.getElementById('cropper_container');
  const cropperImage = document.getElementById('cropper_image');
  const cropButton = document.getElementById('crop_button');
  const cancelButton = document.getElementById('cancel_button');
  const profilePreview = document.getElementById('profile_picture_preview');
  const defaultAvatar = document.getElementById('default_avatar');
  const hiddenInput = document.getElementById('profile_picture_data');

  if (!fileInput || !modalCheckbox || !cropperContainer || !cropperImage || !cropButton || !profilePreview || !defaultAvatar || !hiddenInput) {
    console.warn('Profile cropper: required DOM elements not found. Cropper disabled.');
    return;
  }

  let currentObjectUrl = null;
  let cropArea = { x: 0, y: 0, size: 0 };
  let isDragging = false;
  let isResizing = false;
  let dragStart = { x: 0, y: 0 };
  let imageRect = null;

  // Create overlay elements
  const overlay = document.createElement('div');
  overlay.className = 'crop-overlay';

  const cropBox = document.createElement('div');
  cropBox.className = 'crop-box';

  const resizeHandle = document.createElement('div');
  resizeHandle.className = 'resize-handle';
  cropBox.appendChild(resizeHandle);

  const gridLines = document.createElement('div');
  gridLines.className = 'grid-lines';
  gridLines.innerHTML = `
    <div class="grid-line grid-line-h" style="top: 33.33%"></div>
    <div class="grid-line grid-line-h" style="top: 66.66%"></div>
    <div class="grid-line grid-line-v" style="left: 33.33%"></div>
    <div class="grid-line grid-line-v" style="left: 66.66%"></div>
  `;
  cropBox.appendChild(gridLines);

  overlay.appendChild(cropBox);

  // Add styles
  const style = document.createElement('style');
  style.textContent = `
    .crop-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      cursor: move;
    }
    .crop-overlay::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      pointer-events: none;
    }
    .crop-box {
      position: absolute;
      border: 2px solid white;
      box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
      cursor: move;
      box-sizing: border-box;
      touch-action: none;
    }
    .resize-handle {
      position: absolute;
      width: 30px;
      height: 30px;
      background: white;
      border: 2px solid #333;
      right: -15px;
      bottom: -15px;
      cursor: nwse-resize;
      border-radius: 50%;
      touch-action: none;
    }
    .grid-lines {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      pointer-events: none;
    }
    .grid-line {
      position: absolute;
      background: rgba(255, 255, 255, 0.5);
    }
    .grid-line-h {
      left: 0;
      right: 0;
      height: 1px;
    }
    .grid-line-v {
      top: 0;
      bottom: 0;
      width: 1px;
    }
    #cropper_container {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f0f0f0;
      min-height: 500px;
    }
    #cropper_image {
      max-width: 100%;
      max-height: 70vh;
      display: block;
    }
  `;
  document.head.appendChild(style);

  function initCropper() {
    cropperContainer.appendChild(overlay);

    // Wait for image to load and get dimensions
    const img = cropperImage;
    imageRect = img.getBoundingClientRect();
    const containerRect = cropperContainer.getBoundingClientRect();

    // Calculate image position relative to container
    const imgLeft = img.offsetLeft;
    const imgTop = img.offsetTop;
    const imgWidth = img.offsetWidth;
    const imgHeight = img.offsetHeight;

    // Position overlay over the image
    overlay.style.left = imgLeft + 'px';
    overlay.style.top = imgTop + 'px';
    overlay.style.width = imgWidth + 'px';
    overlay.style.height = imgHeight + 'px';

    // Initialize crop box in center
    const initialSize = Math.min(imgWidth, imgHeight);
    cropArea.size = initialSize;
    cropArea.x = (imgWidth - initialSize) / 2;
    cropArea.y = (imgHeight - initialSize) / 2;

    updateCropBox();
  }

  function updateCropBox() {
    cropBox.style.left = cropArea.x + 'px';
    cropBox.style.top = cropArea.y + 'px';
    cropBox.style.width = cropArea.size + 'px';
    cropBox.style.height = cropArea.size + 'px';
  }

  function constrainCropArea() {
    const maxX = overlay.offsetWidth - cropArea.size;
    const maxY = overlay.offsetHeight - cropArea.size;

    cropArea.x = Math.max(0, Math.min(cropArea.x, maxX));
    cropArea.y = Math.max(0, Math.min(cropArea.y, maxY));

    // Max size is the smaller dimension of the image
    const maxSize = Math.min(overlay.offsetWidth, overlay.offsetHeight);
    const minSize = 50;

    cropArea.size = Math.max(minSize, Math.min(cropArea.size, maxSize));

    // Re-check position after size constraint
    const newMaxX = overlay.offsetWidth - cropArea.size;
    const newMaxY = overlay.offsetHeight - cropArea.size;
    cropArea.x = Math.max(0, Math.min(cropArea.x, newMaxX));
    cropArea.y = Math.max(0, Math.min(cropArea.y, newMaxY));
  }

  // Helper to get touch/mouse coordinates
  function getEventCoords(e) {
    if (e.touches && e.touches.length > 0) {
      return { x: e.touches[0].clientX, y: e.touches[0].clientY };
    }
    return { x: e.clientX, y: e.clientY };
  }

  // Start dragging (mouse + touch)
  function startDrag(e) {
    if (e.target === resizeHandle) return;
    isDragging = true;
    const coords = getEventCoords(e);
    dragStart.x = coords.x - cropArea.x;
    dragStart.y = coords.y - cropArea.y;
    e.preventDefault();
  }

  // Start resizing (mouse + touch)
  function startResize(e) {
    isResizing = true;
    const coords = getEventCoords(e);
    dragStart.x = coords.x;
    dragStart.y = coords.y;
    e.stopPropagation();
    e.preventDefault();
  }

  // Handle move (mouse + touch)
  function handleMove(e) {
    if (isDragging) {
      const coords = getEventCoords(e);
      cropArea.x = coords.x - dragStart.x;
      cropArea.y = coords.y - dragStart.y;
      constrainCropArea();
      updateCropBox();
      e.preventDefault();
    } else if (isResizing) {
      const coords = getEventCoords(e);
      const deltaX = coords.x - dragStart.x;
      const deltaY = coords.y - dragStart.y;
      const delta = Math.max(deltaX, deltaY);

      const maxSize = Math.min(overlay.offsetWidth, overlay.offsetHeight);
      cropArea.size = Math.max(50, Math.min(maxSize, cropArea.size + delta));
      constrainCropArea();
      updateCropBox();

      dragStart.x = coords.x;
      dragStart.y = coords.y;
      e.preventDefault();
    }
  }

  // Stop dragging/resizing
  function stopDragResize() {
    isDragging = false;
    isResizing = false;
  }

  // Mouse events
  cropBox.addEventListener('mousedown', startDrag);
  resizeHandle.addEventListener('mousedown', startResize);
  document.addEventListener('mousemove', handleMove);
  document.addEventListener('mouseup', stopDragResize);

  // Touch events
  cropBox.addEventListener('touchstart', startDrag, { passive: false });
  resizeHandle.addEventListener('touchstart', startResize, { passive: false });
  document.addEventListener('touchmove', handleMove, { passive: false });
  document.addEventListener('touchend', stopDragResize);
  document.addEventListener('touchcancel', stopDragResize);

  fileInput.addEventListener('change', (e) => {
    const file = e.target.files && e.target.files[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
      alert('File too large. Max 5MB.');
      fileInput.value = '';
      return;
    }

    if (currentObjectUrl) URL.revokeObjectURL(currentObjectUrl);
    currentObjectUrl = URL.createObjectURL(file);

    cropperImage.src = currentObjectUrl;
    modalCheckbox.checked = true;

    cropperImage.onload = () => {
      initCropper();
    };
  });

  cropButton.addEventListener('click', () => {
    const img = cropperImage;
    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 512;
    const ctx = canvas.getContext('2d');

    // Calculate scale factor
    const scaleX = img.naturalWidth / img.offsetWidth;
    const scaleY = img.naturalHeight / img.offsetHeight;

    // Draw cropped image
    ctx.drawImage(
      img,
      cropArea.x * scaleX,
      cropArea.y * scaleY,
      cropArea.size * scaleX,
      cropArea.size * scaleY,
      0, 0, 512, 512
    );

    const dataUrl = canvas.toDataURL('image/jpeg', 0.92);

    // Update preview
    profilePreview.src = dataUrl;
    profilePreview.classList.remove('hidden');
    defaultAvatar.classList.add('hidden');

    // Set hidden input
    hiddenInput.value = dataUrl;

    // Cleanup
    cleanup();
  });

  if (cancelButton) {
    cancelButton.addEventListener('click', cleanup);
  }

  modalCheckbox.addEventListener('change', () => {
    if (!modalCheckbox.checked) {
      cleanup();
    }
  });

  function cleanup() {
    modalCheckbox.checked = false;
    if (overlay.parentNode) {
      overlay.parentNode.removeChild(overlay);
    }
    if (currentObjectUrl) {
      URL.revokeObjectURL(currentObjectUrl);
      currentObjectUrl = null;
    }
    fileInput.value = '';
  }
});
