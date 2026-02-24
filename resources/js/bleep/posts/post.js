// Toggle UI helpers (defined once, used below)

/**
 * Apply on/off classes + aria state to an icon button.
 * onClasses / offClasses are arrays of Tailwind class names.
 */
function setIconState(btn, checked, onClasses = ['bg-primary', 'text-white', 'shadow'], offClasses = ['bg-transparent']) {
    if (!btn) return;
    if (checked) {
        btn.classList.add(...onClasses);
        offClasses.forEach(c => btn.classList.remove(c));
        btn.setAttribute('aria-pressed', 'true');
        btn.setAttribute('aria-checked', 'true');
    } else {
        onClasses.forEach(c => btn.classList.remove(c));
        btn.classList.add(...offClasses);
        btn.setAttribute('aria-pressed', 'false');
        btn.setAttribute('aria-checked', 'false');
    }
}

// Anonymous toggle (legacy indicator + icon button)
const postToggle    = document.getElementById('post-anonymous-toggle');
const postIndicator = document.getElementById('post-toggle-indicator');

if (postToggle && postIndicator) {
    const updatePostToggleUI = () => {
        if (postToggle.checked) {
            postIndicator.style.backgroundImage = 'none';
            postIndicator.style.backgroundColor = '#1f2937';
            postIndicator.innerHTML = `<i data-lucide="hat-glasses" class="w-4 h-4 text-white"></i>`;
            const anonIcon = document.getElementById('post-anonymous-icon');
            if (anonIcon) anonIcon.style.backgroundColor = '#1f2937';
            if (window.createLucideIcons) window.createLucideIcons();
        } else {
            postIndicator.innerHTML = '';
            postIndicator.style.backgroundColor = 'transparent';
            postIndicator.style.backgroundImage = 'none';
            const anonIcon = document.getElementById('post-anonymous-icon');
            if (anonIcon) anonIcon.style.backgroundColor = 'transparent';
        }
    };
    postToggle.addEventListener('change', updatePostToggleUI);
    updatePostToggleUI();
}

// NSFW toggle (legacy indicator + icon button)
const postNsfwToggle    = document.getElementById('post-nsfw-toggle');
const postNsfwIndicator = document.getElementById('post-nsfw-toggle-indicator');
const postNsfwIcon      = document.getElementById('post-nsfw-icon');

if (postNsfwToggle && postNsfwIndicator) {
    const updateNsfwToggleUI = () => {
        if (postNsfwToggle.checked) {
            postNsfwIndicator.style.backgroundImage = 'none';
            postNsfwIndicator.style.backgroundColor = '#7c3aed';
            postNsfwIndicator.innerHTML = `<i data-lucide="eye-off" class="w-4 h-4 text-white"></i>`;
            if (postNsfwIcon) postNsfwIcon.style.backgroundColor = '#7c3aed';
            if (window.createLucideIcons) window.createLucideIcons();
        } else {
            postNsfwIndicator.innerHTML = '';
            postNsfwIndicator.style.backgroundColor = 'transparent';
            postNsfwIndicator.style.backgroundImage = 'none';
            if (postNsfwIcon) postNsfwIcon.style.backgroundColor = 'transparent';
        }
    };
    postNsfwToggle.addEventListener('change', updateNsfwToggleUI);
    updateNsfwToggleUI();
}

// Main DOMContentLoaded block
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('bleep-form');
    if (!form) return;

    const submitBtn     = document.getElementById('post-submit-btn');
    const openMediaBtn      = document.getElementById('open-media-picker');
    const progressWrap  = document.getElementById('upload-progress');
    const progressBar   = document.getElementById('upload-progress-bar');
    const progressPercent = document.getElementById('upload-progress-percent');
    const statusText    = document.getElementById('upload-status');

    // Form submit
    form.addEventListener('submit', (e) => {
        const fileInputs = Array.from(form.querySelectorAll('input[type="file"]'));
        const hasFiles   = fileInputs.some(input => input.files && input.files.length > 0);

        if (!hasFiles) {
            // No media — let the browser do a normal form submit.
            // Play sound immediately; page will reload after server redirect.
            window.playSendSound?.();
            return;
        }

        // Media present — XHR with progress UI.
        e.preventDefault();
        if (form.dataset.uploading === '1') return;

        const formData = new FormData(form);
        const xhr      = new XMLHttpRequest();
        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        const tokenInput = form.querySelector('input[name="_token"]');
        if (tokenInput) xhr.setRequestHeader('X-CSRF-TOKEN', tokenInput.value);

        startUploadUI();

        xhr.upload.addEventListener('progress', (ev) => {
            if (!ev.lengthComputable) return;
            const pct = Math.min(99, Math.round((ev.loaded / ev.total) * 100));
            updateProgress(pct, 'Uploading media...');
        });

        xhr.onreadystatechange = () => {
            if (xhr.readyState !== 4) return;

            if (xhr.status >= 200 && xhr.status < 400) {
                updateProgress(100, 'Processing...');

                // ✅ Play send sound on successful XHR upload
                window.playSendSound?.();

                // Also fire the event so send-sound.js listener picks it up
                document.dispatchEvent(new Event('bleep:posted'));

                setTimeout(() => window.location.reload(), 200);
            } else {
                failUploadUI();
            }
        };

        xhr.onerror = failUploadUI;
        xhr.send(formData);
    });

    // Upload UI helpers
    function startUploadUI() {
        form.dataset.uploading = '1';
        progressWrap.classList.remove('hidden');
        updateProgress(0, 'Starting upload...');
        submitBtn?.setAttribute('disabled', 'true');
        openMediaBtn?.setAttribute('disabled', 'true');
        form.querySelectorAll('input, textarea, select, button').forEach((el) => {
            if (el !== submitBtn && el !== openMediaBtn) el.setAttribute('disabled', 'true');
        });
    }

    function updateProgress(val, text) {
        progressBar.value = val;
        progressPercent.textContent = `${val}%`;
        if (text) statusText.textContent = text;
    }

    function failUploadUI() {
        statusText.textContent  = 'Upload failed. Please try again.';
        form.dataset.uploading  = '0';
        submitBtn?.removeAttribute('disabled');
        openMediaBtn?.removeAttribute('disabled');
        form.querySelectorAll('input, textarea, select, button').forEach((el) => {
            el.removeAttribute('disabled');
        });
    }

    // Icon button toggle state (anonymous + NSFW)
    const anonIconBtn  = document.getElementById('post-anonymous-icon');
    const anonCheckbox = document.getElementById('post-anonymous-toggle');
    const nsfwIconBtn  = document.getElementById('post-nsfw-icon');
    const nsfwCheckbox = document.getElementById('post-nsfw-toggle');

    if (anonIconBtn && anonCheckbox) {
        setIconState(anonIconBtn, anonCheckbox.checked, ['bg-primary', 'text-white', 'shadow'], ['bg-transparent']);
        anonCheckbox.addEventListener('change', () => {
            setIconState(anonIconBtn, anonCheckbox.checked, ['bg-primary', 'text-white', 'shadow'], ['bg-transparent']);
        });
    }

    if (nsfwIconBtn && nsfwCheckbox) {
        setIconState(nsfwIconBtn, nsfwCheckbox.checked, ['bg-secondary', 'text-white', 'shadow'], ['bg-transparent']);
        nsfwCheckbox.addEventListener('change', () => {
            setIconState(nsfwIconBtn, nsfwCheckbox.checked, ['bg-secondary', 'text-white', 'shadow'], ['bg-transparent']);
        });
    }

    // Media picker
    const mediaPicker      = document.getElementById('bleep-media-input');
    const mediaPreviewGrid = document.getElementById('bleep-media-preview');
    const messageTextarea  = document.querySelector('#bleep-form textarea[name="message"]');

    // Track selected files in JS so we can remove individual items
    let selectedFiles = [];

    function updateMediaCount(count, isAudio = false) {
        const badge = document.getElementById('bleep-media-count');
        if (!badge) return;
        const cap = isAudio ? 1 : 4;
        badge.textContent = `${Math.min(count, cap)}/${cap}`;
        badge.classList.toggle('hidden', count === 0);
    }

    function isAudioFile(file) {
        const isAudioMime = file.type.startsWith('audio/');
        const ext = file.name.split('.').pop()?.toLowerCase();
        const isAudioExt = ['mp3', 'wav', 'mpeg', 'ogg', 'flac', 'm4a', 'aac'].includes(ext);
        return isAudioMime || isAudioExt;
    }

    /**
     * Sync the hidden file input with our selectedFiles array.
     * We build a new DataTransfer each time so the browser <input> stays in sync.
     */
    function syncFileInput() {
        if (!mediaPicker) return;
        try {
            const dt = new DataTransfer();
            selectedFiles.forEach(f => dt.items.add(f));
            mediaPicker.files = dt.files;
        } catch {
            // DataTransfer not supported (very old browsers) — skip sync
        }
    }

    /**
     * Remove a single file by index and re-render.
     */
    function removeFileAtIndex(index) {
        selectedFiles.splice(index, 1);
        syncFileInput();
        renderPreview(selectedFiles);
        const isAudio = selectedFiles.length === 1 && isAudioFile(selectedFiles[0]);
        updateMediaCount(selectedFiles.length, isAudio);

        if (selectedFiles.length === 0) {
            mediaPreviewGrid.dataset.audioSelected = 'false';
        }
    }

    function renderPreview(files) {
        mediaPreviewGrid.innerHTML = '';

        files.forEach((file, index) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'relative group';

            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.className = 'w-full h-24 object-cover rounded-lg border border-base-300';
                img.onload = () => URL.revokeObjectURL(img.src);
                wrapper.appendChild(img);

            } else if (file.type.startsWith('video/')) {
                const video = document.createElement('video');
                video.src = URL.createObjectURL(file);
                video.className = 'w-full h-24 object-cover rounded-lg border border-base-300';
                video.muted = true;
                video.onloadeddata = () => URL.revokeObjectURL(video.src);
                wrapper.appendChild(video);

                const playIcon = document.createElement('div');
                playIcon.className = 'absolute inset-0 flex items-center justify-center pointer-events-none';
                playIcon.innerHTML = '<i data-lucide="play" class="w-8 h-8 text-white drop-shadow-lg"></i>';
                wrapper.appendChild(playIcon);

            } else if (isAudioFile(file)) {
                const audioWrapper = document.createElement('div');
                audioWrapper.className = 'w-full h-24 flex flex-col items-center justify-center rounded-lg border border-base-300 bg-base-200';
                audioWrapper.innerHTML = `
                    <i data-lucide="music" class="w-8 h-8 text-base-content/60"></i>
                    <span class="text-xs text-base-content/60 mt-1 truncate max-w-full px-2">${file.name}</span>
                `;
                wrapper.appendChild(audioWrapper);
            }

            // Remove button
            const removeBtn = document.createElement('button');
            removeBtn.type      = 'button';
            removeBtn.className = 'absolute top-1 right-1 btn btn-circle btn-xs btn-error opacity-0 group-hover:opacity-100 transition-opacity';
            removeBtn.innerHTML = '<i data-lucide="x" class="w-3 h-3"></i>';
            removeBtn.addEventListener('click', () => removeFileAtIndex(index));
            wrapper.appendChild(removeBtn);

            mediaPreviewGrid.appendChild(wrapper);
        });

        if (window.createLucideIcons) window.createLucideIcons();
    }

    mediaPicker?.addEventListener('change', () => {
        if (!mediaPicker.files) return;

        const files      = Array.from(mediaPicker.files);
        const audioFiles = files.filter(f => isAudioFile(f));
        const otherFiles = files.filter(f => !isAudioFile(f));

        if (audioFiles.length > 1 || (audioFiles.length === 1 && otherFiles.length)) {
            showToast('Only one audio file is allowed and it cannot be combined.', 'warning');
            mediaPicker.value = '';
            mediaPreviewGrid.innerHTML = '';
            mediaPreviewGrid.dataset.audioSelected = 'false';
            selectedFiles = [];
            updateMediaCount(0, false);
            return;
        }

        const isAudio    = audioFiles.length === 1;
        const maxAllowed = isAudio ? 1 : 4;

        if (files.length > maxAllowed) {
            showToast(`You can only upload up to ${maxAllowed} ${isAudio ? 'audio file' : 'files'}.`, 'warning');
            mediaPicker.value = '';
            mediaPreviewGrid.innerHTML = '';
            mediaPreviewGrid.dataset.audioSelected = 'false';
            selectedFiles = [];
            updateMediaCount(0, false);
            return;
        }

        selectedFiles = files;
        mediaPreviewGrid.dataset.audioSelected = isAudio ? 'true' : 'false';

        // Auto-fill message textarea with audio filename if empty
        if (isAudio && messageTextarea && !messageTextarea.value.trim()) {
            const fileName = audioFiles[0].name.replace(/\.[^/.]+$/, '');
            messageTextarea.value = fileName;
        }

        renderPreview(selectedFiles);

        setTimeout(() => updateMediaCount(selectedFiles.length, isAudio), 50);
    });

    // openMediaBtn?.addEventListener('click', () => {
    //     if (mediaPicker?._opening) return;
    //     mediaPicker._opening = true;
    //     mediaPicker?.click();
    //     setTimeout(() => { if (mediaPicker) mediaPicker._opening = false; }, 500);
    // });
});
