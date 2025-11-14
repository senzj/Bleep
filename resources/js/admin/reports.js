document.addEventListener('DOMContentLoaded', () => {
    console.log('[reports.js] loaded'); // Debug marker
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const modal = document.getElementById('ban_modal');
    const form = document.getElementById('ban-form');
    const reasonTextarea = document.getElementById('ban_reason');
    const reasonCounter = document.getElementById('ban_reason_counter');
    const durationType = document.getElementById('ban_duration_type');
    const dateWrapper = document.getElementById('ban_date_wrapper');
    const banUntil = document.getElementById('ban_until');
    const presets = document.querySelectorAll('.preset-btn');

    function toast(type, msg) {
        const old = document.querySelector('.toast[data-dyn="1"]');
        if (old) old.remove();
        const wrap = document.createElement('div');
        wrap.className = 'toast toast-top toast-center';
        wrap.dataset.dyn = '1';
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} animate-fade-out`;
        alert.innerHTML = `<i data-lucide="${type === 'success' ? 'check-circle' : 'alert-triangle'}" class="w-5 h-5"></i><span>${msg}</span>`;
        wrap.appendChild(alert);
        document.body.appendChild(wrap);
        window.lucide?.createIcons();
    }

    function updateReasonCounter() {
        const len = reasonTextarea?.value.length || 0;
        if (reasonCounter) {
            reasonCounter.textContent = `${len} / 500`;
            reasonCounter.classList.remove('text-red-500','text-orange-500');
            if (len > 500) reasonCounter.classList.add('text-red-500');
            else if (len > 450) reasonCounter.classList.add('text-orange-500');
        }
    }
    reasonTextarea?.addEventListener('input', updateReasonCounter);

    durationType?.addEventListener('change', () => {
        if (durationType.value === 'temporary') {
            dateWrapper?.classList.remove('hidden');
            if (banUntil) banUntil.required = true;
        } else {
            dateWrapper?.classList.add('hidden');
            if (banUntil) {
                banUntil.required = false;
                banUntil.value = '';
            }
        }
    });

    function fmtLocal(dt) {
        const pad = v => String(v).padStart(2,'0');
        return `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
    }

    // Initialize min & default (+7d)
    function initBanUntilDefault() {
        if (!banUntil) return;
        const now = new Date();
        banUntil.min = fmtLocal(now);
        const plus7 = new Date(now.getTime() + 7*24*60*60*1000);
        banUntil.value = fmtLocal(plus7);
    }

    presets.forEach(btn => {
        btn.addEventListener('click', () => {
            const hrs = parseInt(btn.dataset.hours,10);
            const target = new Date(Date.now() + hrs*60*60*1000);
            if (banUntil) banUntil.value = fmtLocal(target);
        });
    });

    function showBanModal() {
        if (!modal) return;
        if (durationType) durationType.value = 'temporary';
        dateWrapper?.classList.remove('hidden');
        if (banUntil) {
            banUntil.required = true;
            if (!banUntil.value) initBanUntilDefault();
        }
        updateReasonCounter();
        modal.checked = true;
    }

    // CLICK HANDLERS FOR ALL BUTTONS
    document.addEventListener('click', async e => {
        const markReviewedBtn = e.target.closest('.mark-reviewed-btn');
        const deleteBtn = e.target.closest('.delete-bleep-btn');
        const banOpBtn = e.target.closest('.ban-op-btn');
        const banReporterBtn = e.target.closest('.ban-reporter-btn');
        const dismissBtn = e.target.closest('.dismiss-report-btn');

        // Mark Reviewed
        if (markReviewedBtn) {
            e.preventDefault();
            const reportId = markReviewedBtn.dataset.reportId;
            const notes = prompt('Optional notes (why marked as reviewed):');

            try {
                const res = await fetch(`/admin/reports/${reportId}/mark-reviewed`, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json','X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ notes })
                });
                if (res.ok) {
                    toast('success', 'Report marked as reviewed.');
                    setTimeout(() => location.reload(), 600);
                } else {
                    toast('error', 'Failed to mark as reviewed.');
                }
            } catch {
                toast('error','Network error.');
            }
        }

        // Delete Bleep (no ban)
        if (deleteBtn) {
            e.preventDefault();
            const reportId = deleteBtn.dataset.reportId;
            if (!confirm('Delete this bleep?')) return;
            const notes = prompt('Optional notes:');

            try {
                const res = await fetch(`/admin/reports/${reportId}/delete-bleep`, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json','X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ ban_op: false, notes })
                });
                if (res.ok) {
                    toast('success', 'Bleep deleted.');
                    setTimeout(() => location.reload(), 600);
                } else {
                    toast('error', 'Failed to delete bleep.');
                }
            } catch {
                toast('error','Network error.');
            }
        }

        // Ban OP - Open Modal
        if (banOpBtn) {
            e.preventDefault();
            const reportId = banOpBtn.dataset.reportId;
            const userId = banOpBtn.dataset.userId;

            document.getElementById('ban_report_id').value = reportId;
            document.getElementById('ban_user_id').value = userId;
            document.getElementById('ban_action_type').value = 'op';

            if (reasonTextarea) {
                reasonTextarea.value = 'Violated community guidelines (reported content)';
            }
            showBanModal();
        }

        // Ban Reporter - Open Modal
        if (banReporterBtn) {
            e.preventDefault();
            const reportId = banReporterBtn.dataset.reportId;
            const userId = banReporterBtn.dataset.userId;

            document.getElementById('ban_report_id').value = reportId;
            document.getElementById('ban_user_id').value = userId;
            document.getElementById('ban_action_type').value = 'reporter';

            if (reasonTextarea) {
                reasonTextarea.value = 'Abuse of report system';
            }
            showBanModal();
        }

        // Dismiss
        if (dismissBtn) {
            e.preventDefault();
            const reportId = dismissBtn.dataset.reportId;
            const notes = prompt('Optional notes:');

            try {
                const res = await fetch(`/admin/reports/${reportId}/dismiss`, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json','X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ notes })
                });
                if (res.ok) {
                    toast('success', 'Report dismissed.');
                    setTimeout(() => location.reload(), 600);
                } else {
                    toast('error', 'Failed to dismiss report.');
                }
            } catch {
                toast('error','Network error.');
            }
        }
    });

    // BAN FORM SUBMISSION
    form?.addEventListener('submit', async e => {
        e.preventDefault();
        if (durationType?.value === 'temporary' && !banUntil?.value) {
            toast('error','Select a ban end date/time.');
            return;
        }
        const fd = new FormData(form);
        const data = Object.fromEntries(fd);
        const reportId = data.report_id;
        const actionType = data.action_type;

        let bannedUntilUtc = null;
        if (data.duration_type === 'temporary' && data.banned_until) {
            const localDate = new Date(data.banned_until);
            bannedUntilUtc = localDate.toISOString();
        }

        const payload = {
            reason: data.reason,
            banned_until: bannedUntilUtc,
            notes: data.notes || null
        };

        let endpoint;
        if (actionType === 'op') {
            endpoint = `/admin/reports/${reportId}/delete-bleep`;
            payload.ban_op = true;
        } else {
            endpoint = `/admin/reports/${reportId}/ban-reporter`;
        }

        try {
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type':'application/json','X-CSRF-TOKEN': csrf },
                body: JSON.stringify(payload)
            });
            const json = await res.json().catch(()=>({}));
            if (res.ok) {
                toast('success', actionType === 'op' ? 'Poster banned & bleep deleted.' : 'Reporter banned.');
                if (modal) modal.checked = false;
                form.reset();
                if (durationType) durationType.value = 'temporary';
                dateWrapper?.classList.remove('hidden');
                if (banUntil) {
                    banUntil.required = true;
                    const d = new Date(Date.now() + 7*24*60*60*1000);
                    banUntil.value = fmtLocal(d);
                }
                updateReasonCounter();
                setTimeout(() => location.reload(), 600);
            } else {
                toast('error', json.message || 'Ban failed.');
            }
        } catch {
            toast('error','Network error.');
        }
    });

    // Initialize on page load
    if (durationType && banUntil) {
        initBanUntilDefault();
    }
    updateReasonCounter();
});
