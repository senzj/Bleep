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

function showToast(message, type = 'info') {
    if (!message) return;

    const toast = document.createElement('div');
    toast.className = 'toast toast-top toast-center z-100';

    const alertClass = type === 'error'
        ? 'alert-error'
        : (type === 'warning' ? 'alert-warning' : 'alert-info');
    const icon = type === 'error' || type === 'warning' ? 'circle-alert' : 'info';

    toast.innerHTML = `
        <div class="alert ${alertClass}">
            <i data-lucide="${icon}" class="h-6 w-6 shrink-0 stroke-current"></i>
            <span></span>
        </div>
    `;
    toast.querySelector('span').textContent = message;

    document.body.appendChild(toast);

    if (window.createLucideIcons) {
        window.createLucideIcons();
    } else if (window.lucide) {
        window.lucide.createIcons();
    }

    setTimeout(() => toast.remove(), 3000);
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

    const submitBtn        = document.getElementById('post-submit-btn');
    const openMediaBtn     = document.getElementById('open-media-picker');
    const progressWrap     = document.getElementById('upload-progress');
    const progressBar      = document.getElementById('upload-progress-bar');
    const progressPercent  = document.getElementById('upload-progress-percent');
    const statusText       = document.getElementById('upload-status');
    const cancelUploadBtn  = document.getElementById('upload-cancel-btn');

    const mediaPicker      = document.getElementById('bleep-media-input');
    const mediaPreviewGrid = document.getElementById('bleep-media-preview');
    const messageTextarea  = document.querySelector('#bleep-form textarea[name="message"]');

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

    // Track selected files in JS so we can remove and reorder individual items.
    let selectedFiles = [];
    let activeXhr = null;
    let cancelled = false;
    let undoToast = null;
    let undoTimeout = null;
    let undoSnapshot = null;

    // Object URLs are reused between renders and only revoked once the files
    // are really gone, so the undo window can bring previews back.
    const objectUrls = new Map();
    const objectUrlFor = (file) => {
        if (!objectUrls.has(file)) {
            objectUrls.set(file, URL.createObjectURL(file));
        }

        return objectUrls.get(file);
    };
    const revokeObjectUrl = (file) => {
        const url = objectUrls.get(file);
        if (url) {
            URL.revokeObjectURL(url);
            objectUrls.delete(file);
        }
    };

    function isAudioFile(file) {
        const isAudioMime = file.type.startsWith('audio/');
        const ext = file.name.split('.').pop()?.toLowerCase();
        const isAudioExt = ['mp3', 'wav', 'mpeg', 'ogg', 'flac', 'm4a', 'aac'].includes(ext);

        return isAudioMime || isAudioExt;
    }

    const isChunkedFile = (file) => Boolean(window.MediaUploadManager?.isChunkedFile?.(file));
    const chunkedSelectedFiles = () => selectedFiles.filter((file) => isChunkedFile(file));

    function updateMediaCount(count, isAudio = false) {
        const badge = document.getElementById('bleep-media-count');
        if (!badge) return;
        const cap = isAudio ? 1 : 9;
        badge.textContent = `${Math.min(count, cap)}/${cap}`;
        badge.classList.toggle('hidden', count === 0);
    }

    /**
     * Sync the hidden file input with our selectedFiles array.
     * We build a new DataTransfer each time so the browser <input> stays in sync
     * (and the order matches the drag-and-drop order).
     */
    function syncFileInput() {
        if (!mediaPicker) return;
        try {
            const dt = new DataTransfer();
            // Files above the chunked threshold are uploaded separately via
            // `upload_ids`, so they must not be part of the multipart body.
            selectedFiles
                .filter((file) => !isChunkedFile(file))
                .forEach(f => dt.items.add(f));
            mediaPicker.files = dt.files;
        } catch {
            // DataTransfer not supported (very old browsers) — skip sync
        }
    }

    const pausePreviewMedia = (except = null) => {
        mediaPreviewGrid?.querySelectorAll('audio, video').forEach((media) => {
            if (media !== except && !media.paused) media.pause();
        });
    };

    function removeFileAtIndex(index) {
        const [removed] = selectedFiles.splice(index, 1);
        if (removed) {
            revokeObjectUrl(removed);

            if (isChunkedFile(removed)) {
                window.MediaUploadManager?.cancelFiles([removed], 'bleep');
            }
        }

        syncFileInput();
        renderPreview(selectedFiles);

        const isAudio = selectedFiles.length === 1 && isAudioFile(selectedFiles[0]);
        updateMediaCount(selectedFiles.length, isAudio);

        if (selectedFiles.length === 0) {
            mediaPreviewGrid.dataset.audioSelected = 'false';
        }
    }

    function moveFile(fromIndex, toIndex) {
        if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0) return;
        if (fromIndex >= selectedFiles.length || toIndex >= selectedFiles.length) return;

        const [moved] = selectedFiles.splice(fromIndex, 1);
        selectedFiles.splice(toIndex, 0, moved);
        syncFileInput();
        renderPreview(selectedFiles);
    }

    const previewControlButton = (icon, label) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'absolute inset-0 m-auto flex size-10 items-center justify-center rounded-full bg-black/55 text-white shadow-lg transition hover:bg-black/70';
        button.setAttribute('aria-label', label);
        button.innerHTML = `<i data-lucide="${icon}" class="h-5 w-5"></i>`;

        return button;
    };

    const syncPreviewIcon = (button, icon, label) => {
        button.innerHTML = `<i data-lucide="${icon}" class="h-5 w-5"></i>`;
        button.setAttribute('aria-label', label);
        if (window.createLucideIcons) window.createLucideIcons(button);
    };

    function renderPreview(files) {
        if (!mediaPreviewGrid) return;

        pausePreviewMedia();
        mediaPreviewGrid.innerHTML = '';

        files.forEach((file, index) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'relative group cursor-grab active:cursor-grabbing';
            wrapper.draggable = true;
            wrapper.dataset.index = String(index);

            const url = objectUrlFor(file);

            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = url;
                img.alt = file.name;
                img.className = 'h-24 w-full rounded-lg border border-base-300 object-cover pointer-events-none';
                wrapper.appendChild(img);

            } else if (file.type.startsWith('video/')) {
                const video = document.createElement('video');
                video.src = url;
                video.className = 'h-24 w-full rounded-lg border border-base-300 object-cover';
                video.muted = true;
                video.playsInline = true;
                video.preload = 'metadata';
                wrapper.appendChild(video);

                const playBtn = previewControlButton('play', `Play ${file.name}`);
                playBtn.dataset.previewToggle = '1';
                wrapper.appendChild(playBtn);

                const setState = (playing) => {
                    playBtn.classList.toggle('opacity-0', playing);
                    playBtn.classList.toggle('group-hover:opacity-100', playing);
                    syncPreviewIcon(playBtn, playing ? 'pause' : 'play', playing ? `Pause ${file.name}` : `Play ${file.name}`);
                };

                playBtn.addEventListener('click', async (event) => {
                    event.stopPropagation();
                    if (video.paused) {
                        pausePreviewMedia(video);
                        try {
                            await video.play();
                        } catch {
                            setState(false);
                        }
                    } else {
                        video.pause();
                    }
                });

                video.addEventListener('play', () => setState(true));
                video.addEventListener('pause', () => setState(false));
                video.addEventListener('ended', () => setState(false));

            } else if (isAudioFile(file)) {
                const audioCard = document.createElement('div');
                audioCard.className = 'flex h-24 w-full flex-col items-center justify-center gap-1 rounded-lg border border-base-300 bg-base-200 px-2';

                const audio = document.createElement('audio');
                audio.preload = 'none';
                audio.hidden = true;
                audioCard.appendChild(audio);

                const toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'flex size-10 items-center justify-center rounded-full bg-primary text-primary-content shadow';
                toggle.setAttribute('aria-label', `Play ${file.name}`);
                toggle.innerHTML = '<i data-lucide="play" class="h-5 w-5"></i>';
                audioCard.appendChild(toggle);

                const name = document.createElement('span');
                name.className = 'w-full truncate text-center text-xs text-base-content/60';
                name.textContent = file.name;
                audioCard.appendChild(name);

                wrapper.appendChild(audioCard);

                const setState = (playing) => {
                    syncPreviewIcon(toggle, playing ? 'pause' : 'play', playing ? `Pause ${file.name}` : `Play ${file.name}`);
                };

                toggle.addEventListener('click', async (event) => {
                    event.stopPropagation();

                    // The audio source is only attached on an explicit play so
                    // selecting a file never starts a download or playback.
                    if (!audio.src) audio.src = url;

                    if (audio.paused) {
                        pausePreviewMedia(audio);
                        try {
                            await audio.play();
                        } catch {
                            setState(false);
                        }
                    } else {
                        audio.pause();
                    }
                });

                audio.addEventListener('play', () => setState(true));
                audio.addEventListener('pause', () => setState(false));
                audio.addEventListener('ended', () => setState(false));
            }

            // Drag handle (visual affordance only; the whole tile is draggable)
            const handle = document.createElement('span');
            handle.className = 'absolute left-1 top-1 rounded-md bg-black/45 p-1 text-white opacity-0 transition group-hover:opacity-100';
            handle.innerHTML = '<i data-lucide="grip-vertical" class="h-3.5 w-3.5"></i>';
            handle.setAttribute('aria-hidden', 'true');
            wrapper.appendChild(handle);

            // Remove button
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'absolute right-1 top-1 btn btn-circle btn-xs btn-error opacity-0 group-hover:opacity-100 transition-opacity';
            removeBtn.setAttribute('aria-label', `Remove ${file.name}`);
            removeBtn.innerHTML = '<i data-lucide="x" class="w-3 h-3"></i>';
            removeBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                removeFileAtIndex(index);
            });
            wrapper.appendChild(removeBtn);

            // Drag-and-drop reordering.
            wrapper.addEventListener('dragstart', (event) => {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(index));
                wrapper.classList.add('opacity-50');
            });
            wrapper.addEventListener('dragend', () => {
                wrapper.classList.remove('opacity-50');
            });
            wrapper.addEventListener('dragover', (event) => {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                wrapper.classList.add('ring-2', 'ring-primary');
            });
            wrapper.addEventListener('dragleave', () => {
                wrapper.classList.remove('ring-2', 'ring-primary');
            });
            wrapper.addEventListener('drop', (event) => {
                event.preventDefault();
                wrapper.classList.remove('ring-2', 'ring-primary');

                const from = Number(event.dataTransfer.getData('text/plain'));
                if (Number.isFinite(from)) moveFile(from, index);
            });

            mediaPreviewGrid.appendChild(wrapper);
        });

        if (window.createLucideIcons) window.createLucideIcons(mediaPreviewGrid);
    }

    openMediaBtn?.addEventListener('click', () => {
        if (!mediaPicker) return;

        // Clear the native input so re-picking the same file fires `change`.
        mediaPicker.value = '';
        mediaPicker.click();
    });

    // If the native picker is dismissed, put the previously chosen files back
    // so the pending selection is not silently lost.
    window.addEventListener('focus', () => {
        if (mediaPicker && mediaPicker.value === '' && selectedFiles.length) {
            syncFileInput();
            updateMediaCount(
                selectedFiles.length,
                selectedFiles.length === 1 && isAudioFile(selectedFiles[0]),
            );
        }
    });

    mediaPicker?.addEventListener('change', () => {
        if (!mediaPicker.files) return;

        const incoming = Array.from(mediaPicker.files);

        // Merge with previously selected files so multiple picks accumulate up to the cap.
        const merged = [...selectedFiles];
        incoming.forEach((file) => {
            const isDuplicate = merged.some(
                (existing) => existing.name === file.name
                    && existing.size === file.size
                    && existing.lastModified === file.lastModified,
            );

            if (!isDuplicate) {
                merged.push(file);
            }
        });

        const audioFiles = merged.filter(f => isAudioFile(f));
        const otherFiles = merged.filter(f => !isAudioFile(f));

        if (audioFiles.length > 1 || (audioFiles.length === 1 && otherFiles.length)) {
            showToast('Only one audio file is allowed and it cannot be combined.', 'warning');
            selectedFiles = [];
            syncFileInput();
            mediaPreviewGrid.innerHTML = '';
            mediaPreviewGrid.dataset.audioSelected = 'false';
            updateMediaCount(0, false);
            return;
        }

        const isAudio    = audioFiles.length === 1;
        const maxAllowed = isAudio ? 1 : 9;

        if (merged.length > maxAllowed) {
            showToast(`You can only upload up to ${maxAllowed} ${isAudio ? 'audio file' : 'files'}.`, 'warning');
            selectedFiles = [];
            syncFileInput();
            mediaPreviewGrid.innerHTML = '';
            mediaPreviewGrid.dataset.audioSelected = 'false';
            updateMediaCount(0, false);
            return;
        }

        selectedFiles = merged;
        syncFileInput();
        mediaPreviewGrid.dataset.audioSelected = isAudio ? 'true' : 'false';

        // Auto-fill message textarea with audio filename if empty
        if (isAudio && messageTextarea && !messageTextarea.value.trim()) {
            const fileName = audioFiles[0].name.replace(/\.[^/.]+$/, '');
            messageTextarea.value = fileName;
        }

        renderPreview(selectedFiles);
        updateMediaCount(selectedFiles.length, isAudio);

        // Kick off background uploads immediately for large files. They keep
        // running (and survive a reload) even if the user never presses Post.
        window.MediaUploadManager?.trackFiles(chunkedSelectedFiles(), 'bleep');
    });

    // A page reload restores persisted uploads; put their files back into the
    // composer so the user can finish the post without reselecting anything.
    window.addEventListener('media-upload:restored', (event) => {
        const files = (event.detail?.files || []).filter(Boolean);
        if (!files.length) return;

        const merged = [...selectedFiles];

        files.forEach((file) => {
            const duplicate = merged.some(
                (existing) => existing.name === file.name
                    && existing.size === file.size
                    && existing.lastModified === file.lastModified,
            );

            if (!duplicate) merged.push(file);
        });

        const audioFiles = merged.filter((f) => isAudioFile(f));
        const otherFiles = merged.filter((f) => !isAudioFile(f));

        if (audioFiles.length > 1 || (audioFiles.length === 1 && otherFiles.length)) {
            return;
        }

        selectedFiles = merged;
        syncFileInput();
        if (mediaPreviewGrid) {
            mediaPreviewGrid.dataset.audioSelected = audioFiles.length === 1 ? 'true' : 'false';
        }
        renderPreview(selectedFiles);
        updateMediaCount(selectedFiles.length, audioFiles.length === 1);
    });

    // ── Upload / post ────────────────────────────────────────────────────────

    function startUploadUI(withProgress) {
        form.dataset.uploading = '1';
        cancelled = false;

        if (withProgress) {
            progressWrap?.classList.remove('hidden');
            updateProgress(0, 'Starting upload...');
        }

        submitBtn?.setAttribute('disabled', 'true');
        openMediaBtn?.setAttribute('disabled', 'true');
        form.querySelectorAll('input, textarea, select, button').forEach((el) => {
            if (el !== submitBtn && el !== openMediaBtn && el !== cancelUploadBtn) {
                el.setAttribute('disabled', 'true');
            }
        });
    }

    function updateProgress(val, text) {
        if (progressBar) progressBar.value = val;
        if (progressPercent) progressPercent.textContent = `${val}%`;
        if (text && statusText) statusText.textContent = text;
    }

    function resetUploadUI() {
        form.dataset.uploading = '0';
        activeXhr = null;
        submitBtn?.removeAttribute('disabled');
        openMediaBtn?.removeAttribute('disabled');
        form.querySelectorAll('input, textarea, select, button').forEach((el) => {
            el.removeAttribute('disabled');
        });
        progressWrap?.classList.add('hidden');
    }

    function failUploadUI(message) {
        if (statusText) statusText.textContent = message || 'Upload failed. Please try again.';
        resetUploadUI();
        showToast(message || 'Upload failed. Please try again.', 'error');
    }

    function clearComposer() {
        if (messageTextarea) messageTextarea.value = '';
        selectedFiles = [];
        syncFileInput();
        if (mediaPreviewGrid) {
            mediaPreviewGrid.innerHTML = '';
            mediaPreviewGrid.dataset.audioSelected = 'false';
        }
        updateMediaCount(0, false);
        if (anonCheckbox) anonCheckbox.checked = false;
        if (nsfwCheckbox) nsfwCheckbox.checked = false;
        if (anonIconBtn) setIconState(anonIconBtn, false, ['bg-primary', 'text-white', 'shadow'], ['bg-transparent']);
        if (nsfwIconBtn) setIconState(nsfwIconBtn, false, ['bg-secondary', 'text-white', 'shadow'], ['bg-transparent']);
    }

    function hydrateInsertedCard(card) {
        if (!card) return;

        if (typeof window.loadNewMedia === 'function') {
            window.loadNewMedia(card);
        } else {
            card.querySelectorAll('img[data-src]').forEach((img) => {
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
            });
            card.querySelectorAll('source[data-src], source[data-media-src]').forEach((source) => {
                source.src = source.dataset.src || source.dataset.mediaSrc;
                source.removeAttribute('data-src');
                source.removeAttribute('data-media-src');
            });
        }

        window.initAudioPlayers?.(card);
        window.initVideoPlayers?.(card);
        window.createLucideIcons?.(card);
        document.dispatchEvent(new Event('bleeps:media:hydrated'));
    }

    function insertPostedBleep(data) {
        const container = document.getElementById('bleeps-container');
        if (!container || !data?.html) return;

        const template = document.createElement('template');
        template.innerHTML = data.html.trim();

        const card = template.content.firstElementChild;
        if (!card) return;

        // Never show the same card twice (e.g. optimistic insert + infinite scroll).
        container.querySelectorAll(`[data-bleep-card="${data.bleep_id}"]`).forEach((existing) => existing.remove());

        const emptyState = container.querySelector('.hero');
        if (emptyState) emptyState.remove();

        container.prepend(card);
        hydrateInsertedCard(card);
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function showUndoToast(bleepId, undoUrl, snapshot) {
        // A second post within the undo window makes the previous one permanent.
        if (undoToast) {
            window.clearTimeout(undoTimeout);
            undoToast.remove();
            undoToast = null;
            undoSnapshot?.files.forEach((file) => revokeObjectUrl(file));
            undoSnapshot = null;
        }

        undoSnapshot = snapshot;

        undoToast = document.createElement('div');
        undoToast.className = 'fixed bottom-4 left-1/2 z-[100] flex w-[min(92vw,26rem)] -translate-x-1/2 items-center gap-3 rounded-xl border border-base-300 bg-base-100 px-4 py-3 shadow-2xl';
        undoToast.innerHTML = `
            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-success/15 text-success">
                <i data-lucide="check" class="h-4 w-4"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium">Your bleep is live</p>
                <p class="text-xs text-base-content/60">You can undo this for 10 seconds.</p>
            </div>
            <button type="button" data-undo-btn class="btn btn-sm btn-ghost gap-1.5 text-primary">
                <i data-lucide="undo-2" class="h-4 w-4"></i>
                Undo
            </button>
        `;

        document.body.appendChild(undoToast);
        window.createLucideIcons?.(undoToast);

        const undoBtn = undoToast.querySelector('[data-undo-btn]');

        const dismiss = () => {
            window.clearTimeout(undoTimeout);
            undoToast?.remove();
            undoToast = null;
            // The undo window is over; the uploaded files are now permanent.
            snapshot.files.forEach((file) => revokeObjectUrl(file));
            undoSnapshot = null;
        };

        undoTimeout = window.setTimeout(dismiss, 10000);

        undoBtn?.addEventListener('click', async () => {
            undoBtn.disabled = true;
            undoBtn.classList.add('btn-disabled');

            try {
                const response = await fetch(undoUrl, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                });

                if (!response.ok) throw new Error('Undo failed');

                document.querySelectorAll(`[data-bleep-card="${bleepId}"]`).forEach((card) => card.remove());

                // Restore the composer exactly as it was before posting.
                if (messageTextarea) messageTextarea.value = snapshot.message;
                if (anonCheckbox) {
                    anonCheckbox.checked = snapshot.anonymous;
                    if (anonIconBtn) setIconState(anonIconBtn, snapshot.anonymous, ['bg-primary', 'text-white', 'shadow'], ['bg-transparent']);
                }
                if (nsfwCheckbox) {
                    nsfwCheckbox.checked = snapshot.nsfw;
                    if (nsfwIconBtn) setIconState(nsfwIconBtn, snapshot.nsfw, ['bg-secondary', 'text-white', 'shadow'], ['bg-transparent']);
                }

                selectedFiles = [...snapshot.files];
                syncFileInput();
                renderPreview(selectedFiles);
                updateMediaCount(
                    selectedFiles.length,
                    selectedFiles.length === 1 && isAudioFile(selectedFiles[0]),
                );

                window.clearTimeout(undoTimeout);
                undoToast?.remove();
                undoToast = null;
                // Keep the object URLs alive: the previews use them again.
                undoSnapshot = null;
                showToast('Post undone. Your draft is back.');
            } catch {
                undoBtn.disabled = false;
                undoBtn.classList.remove('btn-disabled');
                showToast('Could not undo the post.', 'error');
            }
        });
    }

    function finishPost(data, snapshot) {
        clearComposer();
        resetUploadUI();
        insertPostedBleep(data);
        showUndoToast(data.bleep_id, data.undo_url, snapshot);

        document.dispatchEvent(new Event('bleep:posted'));
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (form.dataset.uploading === '1') return;

        const message = messageTextarea?.value.trim() || '';
        if (!message && selectedFiles.length === 0) {
            showToast('Write something or attach media.', 'warning');
            return;
        }

        const snapshot = {
            message: messageTextarea?.value || '',
            anonymous: Boolean(anonCheckbox?.checked),
            nsfw: Boolean(nsfwCheckbox?.checked),
            files: [...selectedFiles],
        };

        const chunkedFiles = chunkedSelectedFiles();
        const directFiles = selectedFiles.filter((file) => !isChunkedFile(file));
        const hasFiles = selectedFiles.length > 0;

        if (chunkedFiles.length && !window.MediaUploadManager) {
            showToast('Large uploads are not available yet. Please refresh and try again.', 'error');
            return;
        }

        // Build the multipart body while the fields are still enabled, and
        // only include files small enough for a direct upload.
        syncFileInput();
        const formData = new FormData(form);

        startUploadUI(hasFiles);

        let uploadIds = [];

        if (chunkedFiles.length) {
            try {
                uploadIds = await window.MediaUploadManager.prepareFiles(chunkedFiles, {
                    context: 'bleep',
                    onProgress: (pct, text) => updateProgress(pct, text),
                });
            } catch (error) {
                if (cancelled || error?.name === 'AbortError') {
                    resetUploadUI();
                    showToast('Upload cancelled.', 'info');
                } else {
                    failUploadUI(error?.message || 'Upload failed. Please try again.');
                }

                return;
            }

            uploadIds.forEach((id) => formData.append('upload_ids[]', id));

            if (directFiles.length === 0) {
                updateProgress(99, 'Sending post...');
            }
        }

        // The user may have cancelled while the chunks were finishing.
        if (cancelled || form.dataset.uploading !== '1') {
            resetUploadUI();

            return;
        }

        const xhr = new XMLHttpRequest();

        activeXhr = xhr;
        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        const tokenInput = form.querySelector('input[name="_token"]');
        if (tokenInput) xhr.setRequestHeader('X-CSRF-TOKEN', tokenInput.value);

        if (hasFiles) {
            xhr.upload.addEventListener('progress', (ev) => {
                if (!ev.lengthComputable) return;
                const pct = Math.min(99, Math.round((ev.loaded / ev.total) * 100));
                updateProgress(
                    chunkedFiles.length ? 99 : pct,
                    chunkedFiles.length ? 'Sending post..' : 'Uploading media..',
                );
            });
        }

        xhr.onreadystatechange = () => {
            if (xhr.readyState !== 4) return;

            if (cancelled) {
                resetUploadUI();
                showToast('Upload cancelled.', 'info');
                return;
            }

            if (xhr.status >= 200 && xhr.status < 400) {
                if (hasFiles) updateProgress(100, 'Processing...');

                let data = {};

                try {
                    data = JSON.parse(xhr.responseText || '{}');
                } catch {
                    failUploadUI('The server returned an unexpected response.');

                    return;
                }

                if (uploadIds.length) {
                    window.MediaUploadManager?.markClaimed(uploadIds);
                }

                finishPost(data, snapshot);
            } else {
                let messageText = 'Upload failed. Please try again.';

                try {
                    const error = JSON.parse(xhr.responseText || '{}');
                    messageText = error.message
                        || Object.values(error.errors || {}).flat()?.[0]
                        || messageText;
                } catch { /* keep default */ }

                failUploadUI(messageText);
            }
        };

        xhr.onerror = () => failUploadUI('Network error. Please try again.');
        xhr.onabort = () => {
            if (cancelled) resetUploadUI();
        };

        xhr.send(formData);
    });

    cancelUploadBtn?.addEventListener('click', () => {
        if (form.dataset.uploading !== '1') return;

        cancelled = true;
        activeXhr?.abort();
        activeXhr = null;
        window.MediaUploadManager?.cancelFiles(chunkedSelectedFiles(), 'bleep');
        resetUploadUI();
        showToast('Upload cancelled.', 'info');
    });
});
