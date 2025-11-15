document.addEventListener('click', (e) => {
    const btn = e.target.closest('.edit-bleep-btn');
    if (!btn) return;

    const bleepId = btn.dataset.bleepId;
    const message = btn.dataset.bleepMessage ?? '';
    const isAnonymous = btn.dataset.bleepAnonymous === '1';
    const isNsfw = btn.dataset.bleepNsfw === '1';

    const modal = document.getElementById('edit-bleep-modal');
    const overlay = document.getElementById('edit-bleep-modal-overlay');
    const form = document.getElementById('edit-bleep-form');

    if (!modal || !form) return;

    // inject values
    form.setAttribute('action', `/bleeps/${bleepId}/update`);
    form.setAttribute('data-bleep-id', bleepId);
    form.querySelector('textarea[name="message"]').value = message;

    // update checkboxes (use new IDs)
    const anonCheckbox = form.querySelector('#edit-is-anonymous');
    const nsfwCheckbox = form.querySelector('#edit-is-nsfw');
    if (anonCheckbox) anonCheckbox.checked = isAnonymous;
    if (nsfwCheckbox) nsfwCheckbox.checked = isNsfw;

    // update icon highlights to match checkbox state
    updateEditIconState();

    // open modal
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    // cancel
    const cancelBtn = document.getElementById('cancel-edit-bleep');
    cancelBtn?.addEventListener('click', () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        form.setAttribute('action', '#');
        form.setAttribute('data-bleep-id', '');
    }, { once: true });

    // submit via AJAX (attach once)
    const submitHandler = async (evt) => {
        evt.preventDefault();
        const submitBtn = document.getElementById('submit-edit-bleep');
        submitBtn.disabled = true;

        const payload = {
            message: form.querySelector('textarea[name="message"]').value,
            is_anonymous: form.querySelector('#edit-is-anonymous').checked ? 1 : 0,
            is_nsfw: form.querySelector('#edit-is-nsfw')?.checked ? 1 : 0,
        };

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ||
                     form.querySelector('input[name="_token"]')?.value;

        try {
            const res = await fetch(form.getAttribute('action'), {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            if (!res.ok) throw res;

            const data = await res.json();
            const b = data.bleep;

            // safe DOM updates (use textContent)
            const msgEl = document.querySelector(`.bleep-message[data-bleep-id="${b.id}"]`);
            if (msgEl) msgEl.textContent = b.message;

            const nameEl = document.querySelector(`.bleep-display-name[data-bleep-id="${b.id}"]`);
            if (nameEl) nameEl.textContent = b.display_name;

            const unameEl = document.querySelector(`.bleep-username[data-bleep-id="${b.id}"]`);
            if (unameEl) unameEl.textContent = b.username;

            const avatarEl = document.querySelector(`.bleep-avatar[data-bleep-id="${b.id}"]`);
            if (avatarEl) {
                if (b.is_anonymous) {
                    avatarEl.innerHTML = `
                        <div class="size-12 rounded-full bg-base-300 flex items-center justify-center overflow-hidden">
                            <i data-lucide="hat-glasses" class="w-6 h-6 text-base-content/80"></i>
                        </div>
                    `;
                } else {
                    const avatarSrc = b.avatar_url || '/images/avatar/default.jpg';
                    avatarEl.innerHTML = `
                        <div class="size-12 rounded-full overflow-hidden">
                            <img src="${avatarSrc}" alt="${b.display_name}'s avatar" class="w-full h-full object-cover">
                        </div>
                    `;
                }
                if (window.createLucideIcons) window.createLucideIcons();
            }

            // Toggle header link wrapper to match anonymity
            updateBleepIdentityWrapper(b.id, !!b.is_anonymous, b.username);

            const likesEl = document.querySelector(`.like-count[data-bleep-id="${b.id}"]`);
            if (likesEl) likesEl.textContent = b.likes_count;

            const commentsBtn = document.querySelector(`.comment-btn[data-bleep-id="${b.id}"]`);
            if (commentsBtn) {
              const spans = commentsBtn.querySelectorAll('span');
              let countSpan = null;
              for (const s of spans) {
                if (s.classList.contains('inline') || s.classList.contains('text-xs')) {
                  countSpan = s;
                  break;
                }
              }
              countSpan = countSpan || spans[0] || null;
              if (countSpan) countSpan.textContent = b.comments_count;
            }

            // close modal
            document.getElementById('edit-bleep-modal')?.classList.add('hidden');

            // update every edit button for this bleep (dropdowns / clones)
            document.querySelectorAll(`.edit-bleep-btn[data-bleep-id="${bleepId}"]`)
                .forEach(btn => {
                    btn.dataset.bleepMessage = b.message;
                    btn.dataset.bleepAnonymous = b.is_anonymous ? '1' : '0';
                    btn.dataset.bleepNsfw = b.is_nsfw ? '1' : '0';
                });

            // --- Immediate UI feedback for NSFW changes ---
            (function syncNsfwUI() {
                const wrapper = document.querySelector(`.bleep-nsfw-wrapper[data-bleep-id="${b.id}"]`);
                if (!wrapper) return;

                // Update wrapper flags
                wrapper.setAttribute('data-is-nsfw', b.is_nsfw ? '1' : '0');
                wrapper.setAttribute('data-is-anonymous', b.is_anonymous ? '1' : '0');

                const placeholder = wrapper.querySelector('.nsfw-placeholder');
                const content = wrapper.querySelector('.nsfw-content');
                const normalContent = wrapper.querySelector('.normal-bleep-content');
                const plainMsg = normalContent?.querySelector('.bleep-message');
                const gallery = normalContent?.querySelector('.bleep-media-gallery, [data-bleep-media]');

                const isNsfw = Boolean(b.is_nsfw);

                if (isNsfw) {
                    // Show NSFW placeholder, hide normal content
                    if (placeholder) placeholder.classList.remove('hidden');
                    if (normalContent) normalContent.classList.add('hidden');
                    if (content) content.classList.add('hidden'); // Keep deferred content hidden until clicked

                    // Clear localStorage reveal state
                    try { localStorage.removeItem(`nsfw_viewed_${b.id}`); } catch(e) {}
                    delete wrapper.dataset.revealed;

                    // Clear deferred content srcs
                    if (content) {
                        content.querySelectorAll('[data-media-src], .nsfw-media').forEach(el => {
                            const tag = el.tagName.toLowerCase();
                            if (tag === 'img') el.removeAttribute('src');
                            if (tag === 'source') el.removeAttribute('src');
                            if (tag === 'video') {
                                try { el.pause(); } catch(e){}
                                el.removeAttribute('src');
                                const source = el.querySelector('source');
                                if (source) source.removeAttribute('src');
                            }
                        });
                    }
                } else {
                    // Not NSFW - show normal content, hide placeholder and deferred
                    if (placeholder) placeholder.classList.add('hidden');
                    if (content) {
                        const msgNode = content.querySelector('.nsfw-message');
                        if (msgNode) msgNode.textContent = '';
                        content.classList.add('hidden');
                        // Clear deferred media
                        content.querySelectorAll('[data-media-src], .nsfw-media').forEach(el => {
                            const tag = el.tagName.toLowerCase();
                            if (tag === 'img') el.removeAttribute('src');
                            if (tag === 'source') el.removeAttribute('src');
                            if (tag === 'video') {
                                try { el.pause(); } catch(e){}
                                el.removeAttribute('src');
                            }
                        });
                    }

                    // Show normal content
                    if (normalContent) normalContent.classList.remove('hidden');
                    if (plainMsg) plainMsg.textContent = b.message;

                    // Restore gallery images
                    if (gallery) {
                        gallery.querySelectorAll('[data-media-src]').forEach(el => {
                            const tag = el.tagName.toLowerCase();
                            const src = el.getAttribute('data-media-src');
                            if (!src) return;
                            if (tag === 'img' && !el.getAttribute('src')) el.setAttribute('src', src);
                            if (tag === 'source' && !el.getAttribute('src')) {
                                el.setAttribute('src', src);
                                const parentVideo = el.closest('video');
                                try { parentVideo?.load(); } catch(e){}
                            }
                            if (tag === 'video' && !el.getAttribute('src')) {
                                el.setAttribute('src', src);
                                try { el.load(); } catch(e){}
                            }
                        });
                    }
                }
            })();

            // --- end UI sync ---
        } catch (err) {
          console.error('Update failed', err);
        } finally {
          submitBtn.disabled = false;
        }
    };

    form.addEventListener('submit', submitHandler, { once: true });

    // render icons inside modal if present
    if (window.createLucideIcons) window.createLucideIcons();
});

// icon + toggle highlight helpers (modal)
function setIconState(btn, checked, onClasses = ['bg-primary','text-white','shadow'], offClasses = ['bg-transparent']) {
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

function updateEditIconState() {
    const anonIcon = document.getElementById('edit-anon-icon');
    const anonCheckbox = document.getElementById('edit-is-anonymous');
    const nsfwIcon = document.getElementById('edit-nsfw-icon');
    const nsfwCheckbox = document.getElementById('edit-is-nsfw');

    if (anonIcon && anonCheckbox) {
        setIconState(anonIcon, anonCheckbox.checked, ['bg-primary','text-white','shadow'], ['bg-transparent']);
        anonCheckbox.addEventListener('change', () => setIconState(anonIcon, anonCheckbox.checked, ['bg-primary','text-white','shadow'], ['bg-transparent']));
    }

    if (nsfwIcon && nsfwCheckbox) {
        setIconState(nsfwIcon, nsfwCheckbox.checked, ['bg-secondary','text-white','shadow'], ['bg-transparent']);
        nsfwCheckbox.addEventListener('change', () => setIconState(nsfwIcon, nsfwCheckbox.checked, ['bg-secondary','text-white','shadow'], ['bg-transparent']));
    }
}

// close modal when clicking overlay
document.addEventListener('click', (e) => {
    const overlay = document.getElementById('edit-bleep-modal-overlay');
    const modal = document.getElementById('edit-bleep-modal');
    if (e.target === overlay && modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        const form = document.getElementById('edit-bleep-form');
        if (form) {
            form.setAttribute('action', '#');
            form.setAttribute('data-bleep-id', '');
        }
    }
});

// Helper: toggle the bleep header wrapper (<a> vs <div>) based on anonymity
function updateBleepIdentityWrapper(bleepId, isAnonymous, usernameText) {
    const nameEl = document.querySelector(`.bleep-display-name[data-bleep-id="${bleepId}"]`);
    if (!nameEl) return;

    // The wrapper that contains avatar + names is either <a.group.flex.items-start.gap-3> or <div.group.flex.items-start.gap-3>
    const wrapper = nameEl.closest('.group.flex.items-start.gap-3');
    if (!wrapper) return;

    if (isAnonymous) {
        if (wrapper.tagName.toLowerCase() === 'a') {
            const div = document.createElement('div');
            div.className = wrapper.className;
            div.innerHTML = wrapper.innerHTML;
            wrapper.replaceWith(div);
        }
    } else {
        if (wrapper.tagName.toLowerCase() !== 'a') {
            const a = document.createElement('a');
            a.className = wrapper.className;
            const uname = (usernameText || '').replace(/^@/, '');
            a.href = `/bleeper/${uname}`;
            a.innerHTML = wrapper.innerHTML;
            wrapper.replaceWith(a);
        }
    }
}
