/**
 * Custom notification sound manager for the preferences page.
 */
document.addEventListener('DOMContentLoaded', () => {
    const dropZone = document.getElementById('sound-drop-zone');
    if (!dropZone) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const toast = (ok, msg) => window.preferencesManager?.showToast(ok, msg);

    // Current saved values from the server, used to restore the selection after
    // injecting the uploaded <optgroup> into both selects.
    const saved = {
        recieve_notification_sound: dropZone.dataset.savedReceive || '',
        send_notification_sound: dropZone.dataset.savedSend || '',
    };

    let activeAudio = null;

    const playPath = (path) => {
        if (!path || path === 'none') return;
        if (activeAudio) {
            activeAudio.pause();
            activeAudio.currentTime = 0;
        }
        activeAudio = new Audio(path);
        activeAudio.volume = 0.7;
        activeAudio.play().catch(() => {});
    };

    document.querySelectorAll('.sound-preview-btn').forEach((button) => {
        button.addEventListener('click', () => {
            const select = document.getElementById(`select-${button.dataset.target}`);
            playPath(select?.value);
        });
    });

    const injectUploadedOptions = (sounds) => {
        ['recieve_notification_sound', 'send_notification_sound'].forEach((key) => {
            const select = document.getElementById(`select-${key}`);
            if (!select) return;

            select.querySelector('optgroup[data-uploaded]')?.remove();
            if (!sounds.length) return;

            const group = document.createElement('optgroup');
            group.label = 'Your Uploads';
            group.dataset.uploaded = '1';

            sounds.forEach((sound) => {
                const option = document.createElement('option');
                option.value = sound.path;
                option.textContent = sound.name;
                if (saved[key] === sound.path) option.selected = true;
                group.appendChild(option);
            });

            select.appendChild(group);
        });
    };

    const renderUploadedList = (sounds) => {
        const wrap = document.getElementById('uploaded-sounds-list');
        const items = document.getElementById('uploaded-sounds-items');
        if (!wrap || !items) return;

        if (!sounds.length) {
            wrap.classList.add('hidden');

            return;
        }

        wrap.classList.remove('hidden');
        items.innerHTML = '';

        sounds.forEach((sound) => {
            const row = document.createElement('div');
            row.className = 'flex items-center gap-2 text-sm p-2 rounded-lg bg-base-200';
            row.innerHTML = `
                <i data-lucide="bell" class="w-4 h-4 text-primary shrink-0"></i>
                <span class="truncate flex-1 text-base-content">${sound.name}</span>
                <button type="button" class="btn btn-ghost btn-xs btn-circle rounded-lg preview-row"
                        data-path="${sound.path}" title="Preview">
                    <i data-lucide="play" class="w-3 h-3"></i>
                </button>
            `;
            items.appendChild(row);
        });

        items.querySelectorAll('.preview-row').forEach((button) => {
            button.addEventListener('click', () => playPath(button.dataset.path));
        });

        window.createLucideIcons?.(items);
    };

    const loadUploadedSounds = async () => {
        try {
            const response = await fetch('/api/preferences', { headers: { Accept: 'application/json' } });
            const data = await response.json();
            const uploaded = data.sounds?.uploaded ?? [];
            injectUploadedOptions(uploaded);
            renderUploadedList(uploaded);
        } catch (error) {
            console.warn('[Sounds] Failed to load uploaded sounds:', error);
        }
    };

    const fileInput = document.getElementById('sound-file-input');
    const previewWrap = document.getElementById('sound-upload-preview');
    const previewName = document.getElementById('sound-upload-filename');
    const clearBtn = document.getElementById('sound-upload-clear');
    const uploadBtn = document.getElementById('sound-upload-btn');
    const progressWrap = document.getElementById('sound-upload-progress-wrap');
    const progressBar = document.getElementById('sound-upload-progress');
    const progressLabel = document.getElementById('sound-upload-progress-label');

    let selectedFile = null;

    const setFile = (file) => {
        if (!file) return;
        selectedFile = file;
        previewName.textContent = file.name;
        previewWrap.classList.remove('hidden');
        uploadBtn.disabled = false;
    };

    const clearFile = () => {
        selectedFile = null;
        fileInput.value = '';
        previewWrap.classList.add('hidden');
        progressWrap.classList.add('hidden');
        uploadBtn.disabled = true;
        progressBar.value = 0;
        progressLabel.textContent = '0%';
    };

    fileInput.addEventListener('change', () => setFile(fileInput.files?.[0]));
    clearBtn.addEventListener('click', clearFile);

    dropZone.addEventListener('dragover', (event) => {
        event.preventDefault();
        dropZone.classList.add('border-primary', 'bg-primary/5');
    });
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('border-primary', 'bg-primary/5');
    });
    dropZone.addEventListener('drop', (event) => {
        event.preventDefault();
        dropZone.classList.remove('border-primary', 'bg-primary/5');
        setFile(event.dataTransfer.files?.[0]);
    });

    uploadBtn.addEventListener('click', async () => {
        if (!selectedFile) return;

        uploadBtn.disabled = true;
        progressWrap.classList.remove('hidden');

        const formData = new FormData();
        formData.append('sound', selectedFile);

        try {
            const result = await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '/api/preferences/sounds/upload');
                xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
                xhr.setRequestHeader('Accept', 'application/json');

                xhr.upload.onprogress = (event) => {
                    if (!event.lengthComputable) return;
                    const pct = Math.round((event.loaded / event.total) * 100);
                    progressBar.value = pct;
                    progressLabel.textContent = `${pct}%`;
                };

                xhr.onload = () => {
                    if (xhr.status === 201) {
                        try {
                            resolve(JSON.parse(xhr.responseText));
                        } catch (error) {
                            reject(error);
                        }
                    } else {
                        try {
                            reject(new Error(JSON.parse(xhr.responseText)?.message || 'Upload failed'));
                        } catch {
                            reject(new Error('Upload failed'));
                        }
                    }
                };

                xhr.onerror = () => reject(new Error('Network error'));
                xhr.send(formData);
            });

            toast(true, `"${result.name}" uploaded!`);
            clearFile();
            injectUploadedOptions([result]);
            await loadUploadedSounds();
        } catch (error) {
            toast(false, error.message || 'Upload failed');
            uploadBtn.disabled = false;
        }
    });

    loadUploadedSounds();
});
