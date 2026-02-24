/**
 * edit.js
 * Handles the edit-bleep modal:
 *  - Populates form with current bleep data (message, toggles, existing media)
 *  - Lets user remove existing media items
 *  - Lets user add new media (images/videos up to 4, or 1 audio, not mixed)
 *  - Submits via XHR FormData (supports file upload + progress)
 *  - Updates the bleep card in-place without page reload
 */

// ── Icon/toggle state helper ──────────────────────────────────────────────────
function setIconState(btn, checked,
    onClasses  = ['bg-primary', 'text-white', 'shadow'],
    offClasses = ['bg-transparent']
) {
    if (!btn) return;
    if (checked) {
        btn.classList.add(...onClasses);
        offClasses.forEach(c => btn.classList.remove(c));
        btn.setAttribute('aria-pressed', 'true');
    } else {
        onClasses.forEach(c => btn.classList.remove(c));
        btn.classList.add(...offClasses);
        btn.setAttribute('aria-pressed', 'false');
    }
}

// ── Modal state ───────────────────────────────────────────────────────────────
let newSelectedFiles  = [];    // File objects for new uploads
let removeMediaIds    = [];    // IDs of existing media marked for removal

// ── Helpers ───────────────────────────────────────────────────────────────────
function isAudioFile(file) {
    return file.type.startsWith('audio/')
        || ['mp3','wav','ogg','m4a','aac','flac','mpeg'].includes(
               file.name.split('.').pop()?.toLowerCase()
           );
}

function closeModal() {
    const modal = document.getElementById('edit-bleep-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');

    const form = document.getElementById('edit-bleep-form');
    if (form) {
        form.setAttribute('action', '#');
        form.setAttribute('data-bleep-id', '');
        form.reset();
    }

    // Reset internal state
    newSelectedFiles = [];
    removeMediaIds   = [];

    const newGrid = document.getElementById('edit-new-media-grid');
    if (newGrid) newGrid.innerHTML = '';

    const curGrid = document.getElementById('edit-current-media');
    if (curGrid) curGrid.classList.add('hidden');

    const progWrap = document.getElementById('edit-upload-progress');
    if (progWrap) progWrap.classList.add('hidden');

    syncMediaInput();
}

// Keep the hidden file input in sync with newSelectedFiles[]
function syncMediaInput() {
    const input = document.getElementById('edit-media-input');
    if (!input) return;
    try {
        const dt = new DataTransfer();
        newSelectedFiles.forEach(f => dt.items.add(f));
        input.files = dt.files;
    } catch {
        // DataTransfer not available in very old browsers
    }
}

// ── Render existing media thumbnails ─────────────────────────────────────────
function renderCurrentMedia(mediaItems) {
    const section = document.getElementById('edit-current-media');
    const grid    = document.getElementById('edit-current-media-grid');
    if (!section || !grid) return;

    grid.innerHTML = '';

    if (!mediaItems || !mediaItems.length) {
        section.classList.add('hidden');
        return;
    }

    section.classList.remove('hidden');

    mediaItems.forEach(item => {
        // Each cell is its own positioning context so the undo overlay
        // only covers that one thumbnail, not the whole grid.
        const cell = document.createElement('div');
        cell.className       = 'relative overflow-hidden rounded-lg';
        cell.dataset.mediaId = item.id;

        if (item.type === 'image') {
            const img     = document.createElement('img');
            img.src       = item.url;
            img.alt       = 'Media';
            img.className = 'w-full h-24 object-cover border border-base-300 rounded-lg';
            cell.appendChild(img);

        } else if (item.type === 'video') {
            const vid     = document.createElement('video');
            vid.src       = item.url;
            vid.muted     = true;
            vid.className = 'w-full h-24 object-cover border border-base-300 rounded-lg';
            cell.appendChild(vid);

        } else if (item.type === 'audio') {
            const box = document.createElement('div');
            box.className = 'w-full h-24 flex flex-col items-center justify-center border border-base-300 rounded-lg bg-base-200';
            box.innerHTML = `
                <i data-lucide="music" class="w-7 h-7 text-base-content/50"></i>
                <span class="text-xs text-base-content/50 mt-1 truncate max-w-full px-2">
                    ${item.url.split('/').pop()}
                </span>`;
            cell.appendChild(box);
        }

        // Remove button — absolute inside `cell` which is relative
        const removeBtn = document.createElement('button');
        removeBtn.type      = 'button';
        removeBtn.title     = 'Remove';
        removeBtn.className = 'absolute top-1 right-1 btn btn-circle btn-xs btn-error opacity-0 hover:opacity-100 transition-opacity z-10';
        removeBtn.innerHTML = '<i data-lucide="x" class="w-3 h-3"></i>';
        removeBtn.addEventListener('mouseenter', () => removeBtn.classList.add('opacity-100'));
        removeBtn.addEventListener('mouseleave', () => {
            if (!cell.matches(':hover')) removeBtn.classList.remove('opacity-100');
        });
        cell.addEventListener('mouseenter', () => removeBtn.classList.add('opacity-100'));
        cell.addEventListener('mouseleave', () => removeBtn.classList.remove('opacity-100'));
        removeBtn.addEventListener('click', () => markMediaForRemoval(item.id, cell));
        cell.appendChild(removeBtn);

        grid.appendChild(cell);
    });

    if (window.lucide) window.lucide.createIcons();
}

function markMediaForRemoval(id, cellEl) {
    const intId = parseInt(id, 10);
    if (isNaN(intId) || intId <= 0) return;

    if (!removeMediaIds.includes(intId)) removeMediaIds.push(intId);

    // Dim the cell
    cellEl.classList.add('opacity-40');

    // Undo overlay — scoped entirely to cellEl (which is position:relative)
    const undo = document.createElement('div');
    undo.className = 'absolute inset-0 flex items-center justify-center bg-black/40 rounded-lg z-20 cursor-pointer';
    undo.innerHTML = '<span class="text-xs font-bold text-white bg-error px-2 py-0.5 rounded">Undo</span>';
    undo.addEventListener('click', (e) => {
        e.stopPropagation();
        removeMediaIds = removeMediaIds.filter(i => i !== intId);
        cellEl.classList.remove('opacity-40');
        undo.remove();
    });

    cellEl.appendChild(undo);
}

function renderNewMediaPreview() {
    const grid = document.getElementById('edit-new-media-grid');
    if (!grid) return;
    grid.innerHTML = '';

    newSelectedFiles.forEach((file, index) => {
        const wrap     = document.createElement('div');
        wrap.className = 'relative group';

        if (file.type.startsWith('image/')) {
            const img     = document.createElement('img');
            img.src       = URL.createObjectURL(file);
            img.className = 'w-full h-24 object-cover rounded-lg border border-base-300';
            img.onload    = () => URL.revokeObjectURL(img.src);
            wrap.appendChild(img);

        } else if (file.type.startsWith('video/')) {
            const vid     = document.createElement('video');
            vid.src       = URL.createObjectURL(file);
            vid.muted     = true;
            vid.className = 'w-full h-24 object-cover rounded-lg border border-base-300';
            vid.onloadeddata = () => URL.revokeObjectURL(vid.src);
            wrap.appendChild(vid);

            const play = document.createElement('div');
            play.className = 'absolute inset-0 flex items-center justify-center pointer-events-none';
            play.innerHTML = '<i data-lucide="play" class="w-7 h-7 text-white drop-shadow-lg"></i>';
            wrap.appendChild(play);

        } else if (isAudioFile(file)) {
            const box = document.createElement('div');
            box.className = 'w-full h-24 flex flex-col items-center justify-center rounded-lg border border-base-300 bg-base-200';
            box.innerHTML = `
                <i data-lucide="music" class="w-7 h-7 text-base-content/50"></i>
                <span class="text-xs text-base-content/50 mt-1 truncate max-w-full px-2">${file.name}</span>`;
            wrap.appendChild(box);
        }

        const removeBtn     = document.createElement('button');
        removeBtn.type      = 'button';
        removeBtn.className = 'absolute top-1 right-1 btn btn-circle btn-xs btn-error opacity-0 group-hover:opacity-100 transition-opacity';
        removeBtn.innerHTML = '<i data-lucide="x" class="w-3 h-3"></i>';
        removeBtn.addEventListener('click', () => {
            newSelectedFiles.splice(index, 1);
            syncMediaInput();
            renderNewMediaPreview();
        });
        wrap.appendChild(removeBtn);

        grid.appendChild(wrap);
    });

    if (window.lucide) window.lucide.createIcons();
}

// ── Validate file selection ───────────────────────────────────────────────────
function validateNewFiles(incomingFiles, currentMediaCount) {
    const all      = [...newSelectedFiles, ...incomingFiles];
    const audio    = all.filter(isAudioFile);
    const nonAudio = all.filter(f => !isAudioFile(f));

    if (audio.length > 0 && nonAudio.length > 0) {
        return 'Audio cannot be combined with images or videos.';
    }
    if (audio.length > 1) {
        return 'Only one audio file is allowed.';
    }

    const cap = audio.length > 0 ? 1 : 4;
    if ((currentMediaCount + all.length) > cap) {
        return `You can only attach up to ${cap} ${audio.length > 0 ? 'audio file' : 'files'}.`;
    }

    return null; // valid
}

// ── Update the bleep card in-place ────────────────────────────────────────────
function updateBleepCard(b) {
    // ── Message ───────────────────────────────────────────────────────────────
    const wrapper = document.querySelector(`.bleep-nsfw-wrapper[data-bleep-id="${b.id}"]`);
    if (wrapper) {
        const normalContent = wrapper.querySelector('.normal-bleep-content');
        if (normalContent) {
            let msgDiv = normalContent.querySelector('.text-base.leading-relaxed');
            if (b.message && b.message.trim()) {
                if (!msgDiv) {
                    msgDiv = document.createElement('div');
                    msgDiv.className = 'text-base leading-relaxed text-base-content/90 mb-3';
                    const p = document.createElement('p');
                    p.className = 'whitespace-pre-line wrap-break-word';
                    msgDiv.appendChild(p);
                    const gallery = normalContent.querySelector('[data-bleep-media]');
                    normalContent.insertBefore(msgDiv, gallery || normalContent.firstChild);
                }
                const p = msgDiv.querySelector('p') || msgDiv;
                p.textContent = b.message.trim();
            } else {
                msgDiv?.remove();
            }
        }

        // Update NSFW deferred message
        const nsfwMsg = wrapper.querySelector('.nsfw-message');
        if (nsfwMsg) nsfwMsg.textContent = b.message?.trim() ?? '';
        wrapper.querySelector('.nsfw-content')?.setAttribute('data-bleep-message', b.message ?? '');

        // ── Media gallery ─────────────────────────────────────────────────────
        updateCardMedia(wrapper, b.media ?? []);

        // ── NSFW state ────────────────────────────────────────────────────────
        wrapper.setAttribute('data-is-nsfw', b.is_nsfw ? '1' : '0');
        wrapper.setAttribute('data-is-anonymous', b.is_anonymous ? '1' : '0');

        const placeholder   = wrapper.querySelector('.nsfw-placeholder');
        const nsfwContent   = wrapper.querySelector('.nsfw-content');
        const normalDiv     = wrapper.querySelector('.normal-bleep-content');

        if (b.is_nsfw) {
            placeholder?.classList.remove('hidden');
            normalDiv?.classList.add('hidden');
            nsfwContent?.classList.add('hidden');
            try { localStorage.removeItem(`nsfw_viewed_${b.id}`); } catch {}
            delete wrapper.dataset.revealed;
        } else {
            placeholder?.classList.add('hidden');
            nsfwContent?.classList.add('hidden');
            normalDiv?.classList.remove('hidden');
        }
    }

    // ── Display name / username ───────────────────────────────────────────────
    const nameEl = document.querySelector(`.bleep-display-name[data-bleep-id="${b.id}"]`);
    if (nameEl) nameEl.textContent = b.display_name;

    const unameEl = document.querySelector(`.bleep-username[data-bleep-id="${b.id}"]`);
    if (unameEl) unameEl.textContent = b.username;

    // ── Avatar ────────────────────────────────────────────────────────────────
    const avatarEl = document.querySelector(`.bleep-avatar[data-bleep-id="${b.id}"]`);
    if (avatarEl) {
        avatarEl.innerHTML = b.is_anonymous
            ? `<div class="size-12 rounded-full bg-base-300 flex items-center justify-center overflow-hidden">
                   <i data-lucide="hat-glasses" class="w-6 h-6 text-base-content/80"></i>
               </div>`
            : `<div class="size-12 rounded-full overflow-hidden">
                   <img src="${b.avatar_url || '/images/avatar/default.jpg'}"
                        alt="${b.display_name}'s avatar"
                        class="w-full h-full object-cover">
               </div>`;
        if (window.lucide) window.lucide.createIcons();
    }

    // ── Role / verified badges ────────────────────────────────────────────────
    if (nameEl) {
        const nameContainer = nameEl.closest('.font-semibold');
        if (nameContainer) {
            nameContainer.querySelectorAll('.role-badge, .verified-icon').forEach(el => el.remove());

            if (!b.is_anonymous && b.user_role && ['admin','moderator'].includes(b.user_role)) {
                const span = document.createElement('span');
                span.className = b.user_role === 'admin'
                    ? 'px-1 py-0.5 text-[10px] font-extrabold rounded bg-blue-500/20 text-blue-500 border border-blue-600/20 role-badge'
                    : 'px-1 py-0.5 text-[10px] font-extrabold rounded bg-yellow-500/20 text-yellow-500 border border-yellow-600/20 role-badge';
                span.textContent = b.user_role === 'admin' ? 'ADMIN' : 'MOD';
                nameEl.insertAdjacentElement('afterend', span);
            }

            if (!b.is_anonymous && b.user_is_verified) {
                const i = document.createElement('i');
                i.setAttribute('data-lucide', 'badge-check');
                i.className = 'verified-icon w-4 h-4 text-blue-500';
                const after = nameContainer.querySelector('.role-badge') || nameEl;
                after.insertAdjacentElement('afterend', i);
                if (window.lucide) window.lucide.createIcons();
            }
        }
    }

    // ── Profile link wrapper (anon → div, non-anon → <a>) ────────────────────
    if (nameEl) {
        const groupWrap = nameEl.closest('.group.flex.items-start.gap-3');
        if (groupWrap) {
            if (b.is_anonymous && groupWrap.tagName.toLowerCase() === 'a') {
                const div       = document.createElement('div');
                div.className   = groupWrap.className;
                div.innerHTML   = groupWrap.innerHTML;
                groupWrap.replaceWith(div);
            } else if (!b.is_anonymous && groupWrap.tagName.toLowerCase() !== 'a') {
                const a       = document.createElement('a');
                a.className   = groupWrap.className;
                a.href        = `/bleeper/${b.username.replace(/^@/, '')}`;
                a.innerHTML   = groupWrap.innerHTML;
                groupWrap.replaceWith(a);
            }
        }
    }

    // ── Sync edit-btn data attributes ─────────────────────────────────────────
    document.querySelectorAll(`.edit-bleep-btn[data-bleep-id="${b.id}"]`).forEach(btn => {
        btn.dataset.bleepMessage   = b.message ?? '';
        btn.dataset.bleepAnonymous = b.is_anonymous ? '1' : '0';
        btn.dataset.bleepNsfw      = b.is_nsfw      ? '1' : '0';
    });
}

// ── Replace the media gallery inside a bleep card ─────────────────────────────
// Mirrors bleepsmedia.blade.php grid layouts exactly.
function updateCardMedia(wrapper, mediaItems) {
    // The gallery may live inside .normal-bleep-content OR .nsfw-content
    // (server renders it differently for NSFW bleeps), so search the whole wrapper.
    // Also handle the server-rendered class 'nsfw-media-container' in addition to
    // 'bleep-media-gallery' so the old element is always found and replaced.
    wrapper.querySelectorAll('[data-bleep-media], .bleep-media-gallery, .nsfw-media-container')
           .forEach(el => el.remove());

    // Insert point: prefer .normal-bleep-content, fall back to wrapper itself
    const normalContent = wrapper.querySelector('.normal-bleep-content') ?? wrapper;

    if (!mediaItems || !mediaItems.length) return;

    // Separate audio from visual media — audio sits below the grid
    const visualItems = mediaItems.filter(m => m.type !== 'audio');
    const audioItems  = mediaItems.filter(m => m.type === 'audio');

    const outer = document.createElement('div');
    outer.className = 'mt-2 overflow-hidden rounded-xl border border-base-300 bleep-media-gallery';
    outer.setAttribute('data-bleep-media', '');

    // ── Visual grid ───────────────────────────────────────────────────────────
    if (visualItems.length > 0) {
        const count = visualItems.length;

        // Build the same grid class + inner structure as the Blade component
        let gridEl;

        if (count === 1) {
            // Single item — flex centered, max-h-64
            gridEl = document.createElement('div');
            gridEl.className = 'flex items-center justify-center object-cover bg-base-100 max-h-64';

            const clickWrap = makeClickWrap(visualItems[0], 0);
            const media     = makeMediaEl(visualItems[0], 'h-64 w-full object-contain');
            clickWrap.appendChild(media);
            gridEl.appendChild(clickWrap);

        } else if (count === 2) {
            gridEl = document.createElement('div');
            gridEl.className = 'grid grid-cols-2 gap-1 bg-base-200 max-h-64';

            visualItems.forEach((item, idx) => {
                const cell      = document.createElement('div');
                cell.className  = 'flex items-center justify-center overflow-hidden';
                const clickWrap = makeClickWrap(item, idx);
                clickWrap.classList.add('w-full');
                const media     = makeMediaEl(item, 'h-64 w-full object-cover');
                clickWrap.appendChild(media);
                cell.appendChild(clickWrap);
                gridEl.appendChild(cell);
            });

        } else if (count === 3) {
            gridEl = document.createElement('div');
            gridEl.className = 'grid grid-cols-2 grid-rows-2 gap-1 bg-base-200 max-h-64';

            visualItems.forEach((item, idx) => {
                const cell     = document.createElement('div');
                cell.className = idx === 0
                    ? 'col-span-1 row-span-2'
                    : 'col-span-1 row-span-1';

                const clickWrap = makeClickWrap(item, idx);
                clickWrap.classList.add('h-full', 'w-full');
                const media     = makeMediaEl(item, 'h-full w-full object-cover');
                clickWrap.appendChild(media);
                cell.appendChild(clickWrap);
                gridEl.appendChild(cell);
            });

        } else {
            // 4+ items — 2-col grid, h-64
            gridEl = document.createElement('div');
            gridEl.className = 'grid grid-cols-2 gap-1 bg-base-200 h-64';

            visualItems.slice(0, 4).forEach((item, idx) => {
                const cell      = document.createElement('div');
                cell.className  = 'relative overflow-hidden';
                const clickWrap = makeClickWrap(item, idx);
                const media     = makeMediaEl(item, 'h-full w-full object-cover');
                clickWrap.appendChild(media);
                cell.appendChild(clickWrap);
                gridEl.appendChild(cell);
            });
        }

        outer.appendChild(gridEl);
    }

    // ── Audio (below grid, full-width player) ─────────────────────────────────
    audioItems.forEach(item => {
        const audioWrap = document.createElement('div');
        audioWrap.className = 'flex items-center gap-3 px-3 py-2 bg-base-200 border-t border-base-300';

        const icon = document.createElement('i');
        icon.setAttribute('data-lucide', 'music');
        icon.className = 'w-5 h-5 text-base-content/50 shrink-0';
        audioWrap.appendChild(icon);

        const aud = document.createElement('audio');
        aud.controls  = true;
        aud.className = 'w-full';
        const src = document.createElement('source');
        src.src  = item.url;
        src.type = item.mime || 'audio/mpeg';
        aud.appendChild(src);
        audioWrap.appendChild(aud);

        outer.appendChild(audioWrap);
    });

    normalContent.appendChild(outer);
    if (window.lucide) window.lucide.createIcons();
}

/**
 * Build the clickable wrapper div that carries data-media-* attributes.
 * Mirrors the <div data-media-index="..." ...> in bleepsmedia.blade.php
 */
function makeClickWrap(item, index) {
    const div = document.createElement('div');
    div.className = 'relative cursor-pointer group';
    div.dataset.mediaIndex = index;
    div.dataset.mediaType  = item.type;
    div.dataset.mediaSrc   = item.url;
    div.dataset.mediaAlt   = item.alt || 'Media';
    div.dataset.mediaMime  = item.mime || (item.type === 'video' ? 'video/mp4' : 'image/jpeg');
    return div;
}

/**
 * Build the inner <img> or <video> element.
 * For video, wraps in a div + muted preview (controls swallow clicks).
 */
function makeMediaEl(item, sizeClass) {
    if (item.type === 'image') {
        const img     = document.createElement('img');
        img.src       = item.url;
        img.alt       = item.alt || 'Media';
        img.className = sizeClass;
        img.loading   = 'lazy';
        img.setAttribute('data-media-src', item.url); // for NSFW restore compat
        return img;

    } else {
        // Video — muted preview thumbnail, click handled by parent clickWrap
        const wrap     = document.createElement('div');
        wrap.className = `relative w-full bg-base-300 overflow-hidden flex items-center justify-center`;

        const vid = document.createElement('video');
        vid.className = sizeClass + ' pointer-events-none';
        vid.muted     = true;
        vid.preload   = 'metadata';
        vid.playsInline = true;

        const src = document.createElement('source');
        src.src  = item.url;
        src.type = item.mime || 'video/mp4';
        src.setAttribute('data-media-src', item.url);
        vid.appendChild(src);
        wrap.appendChild(vid);

        return wrap;
    }
}

// ── Open modal and populate ───────────────────────────────────────────────────
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.edit-bleep-btn');
    if (!btn) return;

    const bleepId    = btn.dataset.bleepId;
    const message    = btn.dataset.bleepMessage ?? '';
    const isAnon     = btn.dataset.bleepAnonymous === '1';
    const isNsfw     = btn.dataset.bleepNsfw === '1';

    const modal = document.getElementById('edit-bleep-modal');
    const form  = document.getElementById('edit-bleep-form');
    if (!modal || !form) return;

    // Reset state
    newSelectedFiles = [];
    removeMediaIds   = [];

    // Set form action
    form.setAttribute('action', `/bleeps/${bleepId}/update`);
    form.setAttribute('data-bleep-id', bleepId);

    // Populate message
    const textarea = document.getElementById('edit-bleep-message');
    if (textarea) {
        textarea.value = message;
        updateCharCount(message.length);
    }

    // Set toggles
    const anonCb = document.getElementById('edit-is-anonymous');
    const nsfwCb = document.getElementById('edit-is-nsfw');
    if (anonCb) anonCb.checked = isAnon;
    if (nsfwCb) nsfwCb.checked = isNsfw;

    updateEditIconState();

    // Clear new media grid
    const newGrid = document.getElementById('edit-new-media-grid');
    if (newGrid) newGrid.innerHTML = '';

    // Fetch from the existing /bleeps/{id}/data endpoint (BleepController::show)
    // This gives us real integer media IDs needed for removal
    fetch(`/bleeps/${bleepId}/data`, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            // editData returns { id, message, media: [{id, url, type, mime, filename}] }
            const media = (data.media ?? []).map(m => ({
                id:       parseInt(m.id, 10),
                url:      m.url,
                type:     m.type,
                mime:     m.mime || '',
                filename: m.filename || 'Media',
            }));
            renderCurrentMedia(media);
        })
        .catch(() => {
            // Fallback: hide the section so user can still add new media
            document.getElementById('edit-current-media')?.classList.add('hidden');
        });

    // Open modal
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    if (window.lucide) window.lucide.createIcons();
});

// ── File input handler ────────────────────────────────────────────────────────
document.addEventListener('change', (e) => {
    if (e.target.id !== 'edit-media-input') return;

    const incoming = Array.from(e.target.files ?? []);
    if (!incoming.length) return;

    // Count existing (non-removed) current media
    const currentCount = document.querySelectorAll(
        '#edit-current-media-grid [data-media-id]:not(.pointer-events-none)'
    ).length;

    const error = validateNewFiles(incoming, currentCount);
    if (error) {
        alert(error);
        e.target.value = '';
        return;
    }

    newSelectedFiles = [...newSelectedFiles, ...incoming];
    syncMediaInput();
    renderNewMediaPreview();

    // Clear input value so re-selecting same file works
    e.target.value = '';
});

// ── Drag and drop on drop zone ────────────────────────────────────────────────
document.addEventListener('dragover', (e) => {
    if (!e.target.closest('#edit-drop-zone')) return;
    e.preventDefault();
    e.target.closest('#edit-drop-zone')?.classList.add('border-primary', 'bg-primary/5');
});

document.addEventListener('dragleave', (e) => {
    if (!e.target.closest('#edit-drop-zone')) return;
    e.target.closest('#edit-drop-zone')?.classList.remove('border-primary', 'bg-primary/5');
});

document.addEventListener('drop', (e) => {
    const zone = e.target.closest('#edit-drop-zone');
    if (!zone) return;
    e.preventDefault();
    zone.classList.remove('border-primary', 'bg-primary/5');

    const incoming     = Array.from(e.dataTransfer.files ?? []);
    const currentCount = document.querySelectorAll(
        '#edit-current-media-grid [data-media-id]:not(.pointer-events-none)'
    ).length;

    const error = validateNewFiles(incoming, currentCount);
    if (error) { alert(error); return; }

    newSelectedFiles = [...newSelectedFiles, ...incoming];
    syncMediaInput();
    renderNewMediaPreview();
});

// ── Char counter ──────────────────────────────────────────────────────────────
function updateCharCount(len) {
    const el = document.getElementById('edit-char-count');
    if (el) el.textContent = len;
}

document.addEventListener('input', (e) => {
    if (e.target.id === 'edit-bleep-message') updateCharCount(e.target.value.length);
});

// ── Form submit ───────────────────────────────────────────────────────────────
document.addEventListener('submit', async (e) => {
    if (e.target.id !== 'edit-bleep-form') return;
    e.preventDefault();

    const form      = e.target;
    const submitBtn = document.getElementById('submit-edit-bleep');
    const progWrap  = document.getElementById('edit-upload-progress');
    const progBar   = document.getElementById('edit-upload-bar');
    const progLbl   = document.getElementById('edit-upload-label');

    submitBtn.disabled = true;
    progWrap?.classList.remove('hidden');

    const formData = new FormData();
    formData.append('_method', 'PUT');
    formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');
    formData.append('message', document.getElementById('edit-bleep-message')?.value ?? '');
    formData.append('is_anonymous', document.getElementById('edit-is-anonymous')?.checked ? '1' : '0');
    formData.append('is_nsfw', document.getElementById('edit-is-nsfw')?.checked ? '1' : '0');

    // Attach new files
    newSelectedFiles.forEach(file => formData.append('media[]', file));

    // Attach IDs to remove — filter out any null/NaN that slipped in
    removeMediaIds
        .map(id => parseInt(id, 10))
        .filter(id => !isNaN(id))
        .forEach(id => formData.append('remove_media_ids[]', id));

    try {
        const result = await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', form.getAttribute('action'));   // POST + _method=PUT for multipart
            xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.content ?? '');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');

            xhr.upload.onprogress = ev => {
                if (!ev.lengthComputable) return;
                const pct = Math.round((ev.loaded / ev.total) * 100);
                if (progBar) progBar.value       = pct;
                if (progLbl) progLbl.textContent = pct + '%';
            };

            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try { resolve(JSON.parse(xhr.responseText)); }
                    catch (err) { reject(err); }
                } else {
                    try {
                        const err = JSON.parse(xhr.responseText);
                        reject(new Error(Object.values(err.errors ?? {}).flat()[0] || 'Update failed'));
                    } catch { reject(new Error('Update failed')); }
                }
            };

            xhr.onerror = () => reject(new Error('Network error'));
            xhr.send(formData);
        });

        // Update the card
        updateBleepCard(result.bleep);
        window.playSendSound?.();
        closeModal();

    } catch (err) {
        alert(err.message || 'Failed to update bleep. Please try again.');
    } finally {
        submitBtn.disabled = false;
        progWrap?.classList.add('hidden');
        if (progBar) progBar.value = 0;
    }
}, true); // capture phase so we intercept before any other submit handler

// ── Close buttons ─────────────────────────────────────────────────────────────
document.addEventListener('click', (e) => {
    if (e.target.closest('#cancel-edit-bleep') || e.target.closest('#cancel-edit-bleep-x')) {
        closeModal();
        return;
    }

    // Click on overlay
    const overlay = document.getElementById('edit-bleep-modal-overlay');
    if (overlay && e.target === overlay) closeModal();
});

// ── Icon toggle state ─────────────────────────────────────────────────────────
function updateEditIconState() {
    const anonIcon = document.getElementById('edit-anon-icon');
    const anonCb   = document.getElementById('edit-is-anonymous');
    const nsfwIcon = document.getElementById('edit-nsfw-icon');
    const nsfwCb   = document.getElementById('edit-is-nsfw');

    if (anonIcon && anonCb) {
        setIconState(anonIcon, anonCb.checked, ['bg-primary','text-white','shadow'], ['bg-transparent']);
        // Rebind (guard against double-bind by cloning the element to wipe old listeners)
        anonCb.addEventListener('change', () => setIconState(anonIcon, anonCb.checked, ['bg-primary','text-white','shadow'], ['bg-transparent']));
    }

    if (nsfwIcon && nsfwCb) {
        setIconState(nsfwIcon, nsfwCb.checked, ['bg-secondary','text-white','shadow'], ['bg-transparent']);
        nsfwCb.addEventListener('change', () => setIconState(nsfwIcon, nsfwCb.checked, ['bg-secondary','text-white','shadow'], ['bg-transparent']));
    }
}
