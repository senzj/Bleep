// Post form anonymous toggle (shows icon only when checked)
const postToggle = document.getElementById('post-anonymous-toggle');
const postIndicator = document.getElementById('post-toggle-indicator');

if (postToggle && postIndicator) {
    const updatePostToggleUI = () => {
        if (postToggle.checked) {
            postIndicator.style.backgroundImage = 'none';
            postIndicator.style.backgroundColor = '#1f2937';
            postIndicator.innerHTML = `<i data-lucide="hat-glasses" class="w-4 h-4 text-white"></i>`;
            // also update small-screen label icon bg
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

// NSFW toggle UI (mobile icon + indicator color)
const postNsfwToggle = document.getElementById('post-nsfw-toggle');
const postNsfwIndicator = document.getElementById('post-nsfw-toggle-indicator');
const postNsfwIcon = document.getElementById('post-nsfw-icon');

if (postNsfwToggle && postNsfwIndicator) {
    const updateNsfwToggleUI = () => {
        if (postNsfwToggle.checked) {
            postNsfwIndicator.style.backgroundImage = 'none';
            // purple-ish to match "secondary" toggle
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

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('bleep-form');
    if (!form) return;

    const submitBtn = document.getElementById('post-submit-btn');
    const mediaBtn = document.getElementById('open-media-picker');

    const progressWrap = document.getElementById('upload-progress');
    const progressBar = document.getElementById('upload-progress-bar');
    const progressPercent = document.getElementById('upload-progress-percent');
    const statusText = document.getElementById('upload-status');

    form.addEventListener('submit', (e) => {
        // Only use AJAX + show loading indicator when there's media uploaded.
        const fileInputs = Array.from(form.querySelectorAll('input[type="file"]'));
        const hasFiles = fileInputs.some(input => input.files && input.files.length > 0);

        if (!hasFiles) {
        // No media -> let the form submit normally (no preventDefault)
        return;
        }

        // Media present -> handle via XHR and show progress UI
        e.preventDefault();
        if (form.dataset.uploading === '1') return;

        const formData = new FormData(form); // build before disabling inputs

        const xhr = new XMLHttpRequest();
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
            // Let server redirect complete by reloading
            setTimeout(() => window.location.reload(), 200);
        } else {
            failUploadUI();
        }
        };

        xhr.onerror = failUploadUI;
        xhr.send(formData);
    });

    function startUploadUI() {
        form.dataset.uploading = '1';
        progressWrap.classList.remove('hidden');
        updateProgress(0, 'Starting upload...');

        submitBtn?.setAttribute('disabled', 'true');
        mediaBtn?.setAttribute('disabled', 'true');

        // Prevent edits during upload (after FormData was built)
        form.querySelectorAll('input, textarea, select, button').forEach((el) => {
        if (el !== submitBtn && el !== mediaBtn) el.setAttribute('disabled', 'true');
        });
    }

    function updateProgress(val, text) {
        progressBar.value = val;
        progressPercent.textContent = `${val}%`;
        if (text) statusText.textContent = text;
    }

    function failUploadUI() {
        statusText.textContent = 'Upload failed. Please try again.';
        form.dataset.uploading = '0';

        submitBtn?.removeAttribute('disabled');
        mediaBtn?.removeAttribute('disabled');
        form.querySelectorAll('input, textarea, select, button').forEach((el) => {
        el.removeAttribute('disabled');
        });
    }

    // replace/insert toggle UI logic with the following:
    const anonIconBtn = document.getElementById('post-anonymous-icon');
    const anonCheckbox = document.getElementById('post-anonymous-toggle');
    const nsfwIconBtn = document.getElementById('post-nsfw-icon');
    const nsfwCheckbox = document.getElementById('post-nsfw-toggle');

    function setIconState(btn, checked, onClasses = ['bg-primary','text-white','shadow'], offClasses = ['bg-transparent']) {
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

    if (anonIconBtn && anonCheckbox) {
        // label click will toggle checkbox automatically; react to changes only
        anonCheckbox.addEventListener('change', () => {
            setIconState(anonIconBtn, anonCheckbox.checked, ['bg-primary','text-white','shadow'], ['bg-transparent']);
        });
        setIconState(anonIconBtn, anonCheckbox.checked, ['bg-primary','text-white','shadow'], ['bg-transparent']);
    }

    if (nsfwIconBtn && nsfwCheckbox) {
        // label click will toggle checkbox automatically; react to changes only
        nsfwCheckbox.addEventListener('change', () => {
            setIconState(nsfwIconBtn, nsfwCheckbox.checked, ['bg-secondary','text-white','shadow'], ['bg-transparent']);
        });
        setIconState(nsfwIconBtn, nsfwCheckbox.checked, ['bg-secondary','text-white','shadow'], ['bg-transparent']);
    }

    // Toggle icons highlight logic (icon is label/indicator, input is the control)
    const anonIcon = document.getElementById('post-anonymous-icon');
    const anonToggle = document.getElementById('post-anonymous-toggle');

    const nsfwIcon = document.getElementById('post-nsfw-icon');
    const nsfwToggle = document.getElementById('post-nsfw-toggle');

    function setIconState(iconEl, checked, onClass = 'bg-primary', offClass = 'bg-transparent') {
        if (!iconEl) return;
        iconEl.classList.remove(onClass, offClass, 'text-white', 'shadow');
        if (checked) {
            iconEl.classList.add(onClass, 'text-white', 'shadow');
            iconEl.setAttribute('aria-pressed', 'true');
        } else {
            iconEl.classList.add(offClass);
            iconEl.setAttribute('aria-pressed', 'false');
        }
    }

    if (anonToggle && anonIcon) {
        // init
        setIconState(anonIcon, anonToggle.checked, 'bg-primary');
        // update when user toggles control
        anonToggle.addEventListener('change', () => setIconState(anonIcon, anonToggle.checked, 'bg-primary'));
        // clicking icon (label) will toggle the input automatically via `for`
    }

    if (nsfwToggle && nsfwIcon) {
        setIconState(nsfwIcon, nsfwToggle.checked, 'bg-secondary');
        nsfwToggle.addEventListener('change', () => setIconState(nsfwIcon, nsfwToggle.checked, 'bg-secondary'));
    }

    const mediaPicker = document.getElementById('bleep-media-input');
    const mediaPreviewGrid = document.getElementById('bleep-media-preview');
    const messageTextarea = document.querySelector('#bleep-form textarea[name="message"]');

    function updateMediaCount(count, isAudio = false) {
        const badge = document.getElementById('bleep-media-count');
        // console.log('updateMediaCount called:', { count, isAudio, badge });
        if (!badge) return;

        const cap = isAudio ? 1 : 4;
        // console.log('Setting badge:', { cap, text: `${Math.min(count, cap)}/${cap}` });

        badge.textContent = `${Math.min(count, cap)}/${cap}`;
        badge.classList.toggle('hidden', count === 0);

        // Debug: Watch for changes to the badge
        // const observer = new MutationObserver((mutations) => {
        //     mutations.forEach((mutation) => {
        //         console.log('Badge changed!', {
        //             type: mutation.type,
        //             oldValue: mutation.oldValue,
        //             newValue: badge.textContent,
        //             stack: new Error().stack
        //         });
        //     });
        // });
        // observer.observe(badge, { characterData: true, childList: true, subtree: true });

        // Stop observing after 2 seconds to avoid memory leak
        setTimeout(() => observer.disconnect(), 2000);
    }

    function isAudioFile(file) {
        const isAudioMime = file.type.startsWith('audio/');
        const ext = file.name.split('.').pop()?.toLowerCase();
        const isAudioExt = ['mp3', 'wav', 'mpeg', 'ogg', 'flac', 'm4a', 'aac'].includes(ext);
        // console.log('isAudioFile check:', {
        //     fileName: file.name,
        //     fileType: file.type,
        //     ext,
        //     isAudioMime,
        //     isAudioExt,
        //     result: isAudioMime || isAudioExt
        // });
        return isAudioMime || isAudioExt;
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

                // Play icon overlay
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
            removeBtn.type = 'button';
            removeBtn.className = 'absolute top-1 right-1 btn btn-circle btn-xs btn-error opacity-0 group-hover:opacity-100 transition-opacity';
            removeBtn.innerHTML = '<i data-lucide="x" class="w-3 h-3"></i>';
            removeBtn.addEventListener('click', () => {
                removeFileAtIndex(index);
            });
            wrapper.appendChild(removeBtn);

            mediaPreviewGrid.appendChild(wrapper);
        });

        // Re-initialize Lucide icons for the new elements
        if (window.createLucideIcons) window.createLucideIcons();
    }

    mediaPicker?.addEventListener('change', () => {
        // console.log('mediaPicker change event fired');
        if (!mediaPicker.files) return;

        const files = Array.from(mediaPicker.files);
        // console.log('Files selected:', files.map(f => ({ name: f.name, type: f.type })));
        // Check for audio files using helper function
        const audioFiles = files.filter(file => isAudioFile(file));
        const otherFiles = files.filter(file => !isAudioFile(file));

        // console.log('Audio files:', audioFiles.length, 'Other files:', otherFiles.length);

        if (audioFiles.length > 1 || (audioFiles.length === 1 && otherFiles.length)) {
            showToast('Only one audio file is allowed and it cannot be combined.', 'warning');
            mediaPicker.value = '';
            mediaPreviewGrid.innerHTML = '';
            mediaPreviewGrid.dataset.audioSelected = 'false';
            updateMediaCount(0, false);
            return;
        }

        // Enforce max 1 for audio, max 4 for other media
        const isAudio = audioFiles.length === 1;
        const maxAllowed = isAudio ? 1 : 4;

        // console.log('isAudio:', isAudio, 'maxAllowed:', maxAllowed);

        if (files.length > maxAllowed) {
            showToast(`You can only upload up to ${maxAllowed} ${isAudio ? 'audio file' : 'files'}.`, 'warning');
            mediaPicker.value = '';
            mediaPreviewGrid.innerHTML = '';
            mediaPreviewGrid.dataset.audioSelected = 'false';
            updateMediaCount(0, false);
            return;
        }

        mediaPreviewGrid.dataset.audioSelected = isAudio ? 'true' : 'false';

        // Auto-fill message with audio filename if textarea is empty
        if (isAudio && messageTextarea && !messageTextarea.value.trim()) {
            const audioFile = audioFiles[0];
            // Remove file extension from name
            const fileName = audioFile.name.replace(/\.[^/.]+$/, '');
            messageTextarea.value = fileName;
        }

        renderPreview(files);

        // Use setTimeout to ensure updateMediaCount runs AFTER any other scripts
        const fileCount = files.length;
        const isAudioFinal = isAudio;
        setTimeout(() => {
            // console.log('Delayed updateMediaCount with:', fileCount, isAudioFinal);
            updateMediaCount(fileCount, isAudioFinal);
        }, 50);
    });
});
