document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('report_modal');
    const form = document.getElementById('report-form');
    const bleepIdInput = document.getElementById('report_bleep_id');
    const reason = document.getElementById('reason');
    const counter = document.getElementById('reason-counter');

    // Toast helper (mirrors server-rendered markup)
    function showToast(type, text) {
        const existing = document.querySelector('.toast[data-dynamic="1"]');
        if (existing) existing.remove();
        const wrap = document.createElement('div');
        wrap.className = 'toast toast-top toast-center';
        wrap.dataset.dynamic = '1';
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} animate-fade-out`;
        alert.innerHTML = `<i data-lucide="${type === 'success' ? 'check-circle' : 'alert-triangle'}" class="h-6 w-6 shrink-0 stroke-current"></i><span>${text}</span>`;
        wrap.appendChild(alert);
        document.body.appendChild(wrap);
        // Re-initialize lucide icons if available
        if (window.lucide?.createIcons) window.lucide.createIcons();
    }

    // Open modal
    document.addEventListener('click', e => {
        const btn = e.target.closest('.report-bleep-btn');
        if (btn) {
            bleepIdInput.value = btn.dataset.bleepId;
            modal.checked = true;
            reason.focus();
        }
    });

    // Live counter
    function updateCounter() {
        const len = reason.value.length;
        counter.textContent = `${len} / 500`;
        if (len > 500) {
            counter.classList.add('text-red-500');
        } else if (len > 450) {
            counter.classList.remove('text-red-500');
            counter.classList.add('text-orange-500');
        } else {
            counter.classList.remove('text-red-500', 'text-orange-500');
        }
    }
    reason?.addEventListener('input', updateCounter);
    updateCounter();

    // Submit
    form?.addEventListener('submit', async e => {
        e.preventDefault();
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        try {
            const res = await fetch('/reports', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(data),
            });

            const json = await res.json().catch(() => ({}));

            if (res.ok) {
                showToast('success', json.message || 'Report submitted.');
                modal.checked = false;
                form.reset();
                updateCounter();
            } else {
                // Self-report case: close modal, show error toast
                if (json.self_report) {
                    modal.checked = false;
                    form.reset();
                    updateCounter();
                    showToast('error', json.message || 'Cannot report own bleep.');
                    return;
                }
                // Duplicate report
                if (json.duplicate) {
                    showToast('error', json.message || 'Already reported.');
                    return;
                }
                showToast('error', json.message || 'Failed to submit report.');
            }
        } catch {
            showToast('error', 'Network error. Please try again.');
        }
    });
});
