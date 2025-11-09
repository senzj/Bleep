document.addEventListener('click', (e) => {
    const btn = e.target.closest('.edit-bleep-btn');
    if (!btn) return;

    const bleepId = btn.dataset.bleepId;
    const message = btn.dataset.bleepMessage ?? '';
    const isAnonymous = btn.dataset.bleepAnonymous === '1';

    const modal = document.getElementById('edit-bleep-modal');
    const overlay = document.getElementById('edit-bleep-modal-overlay');
    const form = document.getElementById('edit-bleep-form');

    if (!modal || !form) return;

    // inject values
    form.setAttribute('action', `/bleeps/${bleepId}/update`);
    form.setAttribute('data-bleep-id', bleepId);
    form.querySelector('textarea[name="message"]').value = message;
    form.querySelector('#edit-is-anonymous').checked = isAnonymous;

    // Update toggle indicator on load
    updateEditToggleUI();

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
              if (b.avatar_url) {
                avatarEl.innerHTML = `<div class="size-12 rounded-full overflow-hidden"><img src="${b.avatar_url}" alt=""></div>`;
              } else {
                avatarEl.innerHTML = `<div class="size-12 rounded-full bg-base-300 flex items-center justify-center overflow-hidden"><i data-lucide="hat-glasses" class="w-6 h-6 text-base-content/80"></i></div>`;
                if (window.createLucideIcons) window.createLucideIcons();
              }
            }

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
                });

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

// Anonymous toggle handler for edit modal
const editToggle = document.getElementById('edit-is-anonymous');
const editIndicator = document.getElementById('edit-toggle-indicator');

if (editToggle && editIndicator) {
    const updateEditToggleUI = () => {
        if (editToggle.checked) {
            editIndicator.style.backgroundImage = 'none';
            editIndicator.style.backgroundColor = '#1f2937';
            editIndicator.innerHTML = `<i data-lucide="hat-glasses" class="w-4 h-4 text-white"></i>`;
            if (window.createLucideIcons) window.createLucideIcons();
        } else {
            editIndicator.innerHTML = '';
            editIndicator.style.backgroundColor = 'transparent';
            editIndicator.style.backgroundImage = 'none';
        }
    };

    editToggle.addEventListener('change', updateEditToggleUI);
    // Will be called when modal opens
    window.updateEditToggleUI = updateEditToggleUI;
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
