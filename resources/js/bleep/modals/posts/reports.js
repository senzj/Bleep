document.addEventListener('DOMContentLoaded', () => {
    const modal       = document.getElementById('report-modal');
    const titleEl     = document.getElementById('report-modal-title');
    const subtitleEl  = document.getElementById('report-modal-subtitle');
    const previewEl   = document.getElementById('report-content-preview');
    const typeInput   = document.getElementById('report-type');
    const targetInput = document.getElementById('report-target-id');
    const reasonEl    = document.getElementById('report-reason');
    const charCount   = document.getElementById('report-char-count');
    const submitBtn   = document.getElementById('report-submit-btn');
    const loadingEl   = document.getElementById('report-loading');
    const csrf        = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!modal) return;

    // ── Toast
    function showToast(success, message) {
        const existing = document.querySelector('.report-toast-dynamic');
        existing?.remove();

        const wrap = document.createElement('div');
        wrap.className = 'toast toast-top toast-center z-[200] report-toast-dynamic';
        wrap.innerHTML = `
            <div class="alert ${success ? 'alert-success' : 'alert-error'} shadow-lg max-w-sm gap-3">
                <i data-lucide="${success ? 'heart-handshake' : 'alert-triangle'}" class="w-5 h-5 shrink-0"></i>
                <span class="text-sm font-medium">${message}</span>
            </div>`;
        document.body.appendChild(wrap);
        window.lucide?.createIcons?.();
        setTimeout(() => wrap.remove(), 4500);
    }

    // ── Reset
    function resetModal() {
        modal.querySelectorAll('input[name="report-category"]').forEach(r => r.checked = false);
        if (titleEl) titleEl.textContent = 'Report';
        if (subtitleEl) subtitleEl.textContent = 'Help us keep the community safe';
        if (typeInput) typeInput.value = 'bleep';
        if (targetInput) targetInput.value = '';
        if (reasonEl) reasonEl.value = '';
        if (charCount) charCount.textContent = '0 / 500';
        if (charCount) charCount.className = 'text-xs text-base-content/40';
        if (submitBtn) submitBtn.disabled = true;
        loadingEl?.classList.add('hidden');
        if (previewEl) previewEl.textContent = '';
        previewEl?.classList.add('hidden');
    }

    // Reset no matter how dialog is closed (ESC, backdrop, Cancel button, or modal.close()).
    modal.addEventListener('close', resetModal);
    modal.addEventListener('cancel', () => {
        requestAnimationFrame(resetModal);
    });

    // ── Open
    function openModal(type, id, preview = '') {
        resetModal();
        typeInput.value   = type;
        targetInput.value = id;

        const isComment = type === 'comment';
        titleEl.textContent   = isComment ? 'Report Comment' : 'Report Bleep';
        subtitleEl.textContent = 'Help us keep the community safe';

        if (preview && previewEl) {
            previewEl.textContent = `"${preview}"`;
            previewEl.classList.remove('hidden');
        }

        modal.showModal();
        window.lucide?.createIcons?.();
    }

    // ── Trigger: bleep report button
    document.addEventListener('click', e => {
        const btn = e.target.closest('.report-bleep-btn');
        if (!btn) return;
        const id      = btn.dataset.bleepId;
        const preview = (btn.dataset.bleepMessage || '').slice(0, 120);
        openModal('bleep', id, preview);
    });

    // ── Trigger: comment report (fired from Vue Card.vue)
    window.addEventListener('open-comment-report', e => {
        const { commentId, preview } = e.detail;
        openModal('comment', commentId, preview);
    });

    // ── Category enables submit
    document.querySelectorAll('input[name="report-category"]').forEach(radio => {
        radio.addEventListener('change', () => {
            if (submitBtn) submitBtn.disabled = false;
        });
    });

    // ── Char counter
    reasonEl?.addEventListener('input', () => {
        const len = reasonEl.value.length;
        charCount.textContent = `${len} / 500`;
        charCount.className = 'text-xs ' + (
            len > 480 ? 'text-error' :
            len > 400 ? 'text-warning' :
            'text-base-content/40'
        );
    });

    // ── Submit
    submitBtn?.addEventListener('click', async () => {
        const type     = typeInput.value;
        const id       = targetInput.value;
        const category = document.querySelector('input[name="report-category"]:checked')?.value;
        const reason   = reasonEl?.value.trim() || null;

        if (!category || !id) return;

        submitBtn.disabled = true;
        loadingEl?.classList.remove('hidden');

        const body = { type, category, reason };
        if (type === 'bleep')   body.bleep_id   = id;
        if (type === 'comment') body.comment_id = id;

        try {
            const resp = await fetch('/reports', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(body),
            });

            const data = await resp.json().catch(() => ({}));
            modal.close();

            if (resp.ok) {
                showToast(true, 'Thank you for helping keep our community safe! 💙');
            } else if (resp.status === 409) {
                showToast(false, data.message || 'You have already reported this.');
            } else if (resp.status === 422) {
                showToast(false, data.message || 'You cannot report your own content.');
            } else {
                showToast(false, data.message || 'Failed to submit report. Please try again.');
            }

        } catch {
            modal.close();
            showToast(false, 'Network error. Please try again.');
        } finally {
            loadingEl?.classList.add('hidden');
            submitBtn.disabled = false;
        }
    });
});
