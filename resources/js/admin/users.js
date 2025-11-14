document.addEventListener('DOMContentLoaded', () => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const modal = document.getElementById('edit_user_modal');
    const form = document.getElementById('edit-user-form');

    const euId = document.getElementById('eu_user_id');
    const euUsername = document.getElementById('eu_username');
    const euEmail = document.getElementById('eu_email');
    const euIsBanned = document.getElementById('eu_is_banned');
    const banFields = document.getElementById('ban_fields');
    const euBanReason = document.getElementById('eu_ban_reason');
    const euBanReasonCounter = document.getElementById('eu_ban_reason_counter');
    const euBannedUntil = document.getElementById('eu_banned_until');

    function toast(type, text) {
        const old = document.querySelector('.toast[data-users="1"]');
        if (old) old.remove();
        const wrap = document.createElement('div');
        wrap.className = 'toast toast-top toast-center';
        wrap.dataset.users = '1';
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `<span>${text}</span>`;
        wrap.appendChild(alert);
        document.body.appendChild(wrap);
    }

    function fmtLocal(dt) {
        const p = v => String(v).padStart(2, '0');
        return `${dt.getFullYear()}-${p(dt.getMonth()+1)}-${p(dt.getDate())}T${p(dt.getHours())}:${p(dt.getMinutes())}`;
    }

    function setMinNow() {
        if (!euBannedUntil) return;
        const now = new Date();
        euBannedUntil.min = fmtLocal(now);
    }

    function openModalWithUser(user) {
        euId.value = user.id;
        euUsername.value = user.username ?? `User #${user.id}`;
        euEmail.value = user.email ?? '';

        euIsBanned.checked = !!user.is_banned;
        toggleBanFields();

        euBanReason.value = user.ban_reason ?? '';
        updateReasonCounter();

        setMinNow();
        if (user.banned_until) {
            // Convert server ISO (UTC) to local datetime-local value
            const dt = new Date(user.banned_until);
            euBannedUntil.value = fmtLocal(dt);
        } else {
            euBannedUntil.value = '';
        }

        modal.checked = true;
    }

    function toggleBanFields() {
        banFields.classList.toggle('hidden', !euIsBanned.checked);
    }

    function updateReasonCounter() {
        const len = euBanReason.value.length;
        euBanReasonCounter.textContent = `${len} / 500`;
    }

    euBanReason?.addEventListener('input', updateReasonCounter);
    euIsBanned?.addEventListener('change', toggleBanFields);

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.edit-user-btn');
        if (btn) {
            e.preventDefault();
            let user;
            if (btn.dataset.user) {
                // Backward compatibility (if JSON attribute exists)
                user = JSON.parse(btn.dataset.user);
            } else {
                // Build from individual data-* attributes (safe)
                user = {
                    id: parseInt(btn.dataset.userId, 10),
                    username: btn.dataset.username || `User #${btn.dataset.userId}`,
                    email: btn.dataset.email || '',
                    is_banned: btn.dataset.isBanned === '1',
                    ban_reason: btn.dataset.banReason || '',
                    banned_until: btn.dataset.bannedUntil || null,
                };
            }
            openModalWithUser(user);
        }

        const preset = e.target.closest('.preset');
        if (preset && euBannedUntil) {
            const hrs = parseInt(preset.dataset.hours, 10);
            const t = new Date(Date.now() + hrs * 3600 * 1000);
            euBannedUntil.value = fmtLocal(t);
        }
    });

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = euId.value;

        // Prepare payload
        const isBanned = euIsBanned.checked;
        let bannedUntil = null;
        if (isBanned && euBannedUntil.value) {
            // Convert local to UTC for API
            const local = new Date(euBannedUntil.value);
            bannedUntil = local.toISOString();
        }

        const payload = {
            is_banned: isBanned,
            ban_reason: euBanReason.value || null,
            banned_until: bannedUntil,
        };

        try {
            const res = await fetch(`/admin/users/${id}/update`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                },
                body: JSON.stringify(payload),
            });
            const json = await res.json().catch(() => ({}));
            if (res.ok) {
                toast('success', json.message || 'Saved.');
                modal.checked = false;
                // Reload to refresh the card values
                setTimeout(() => location.reload(), 300);
            } else {
                toast('error', json.message || 'Update failed.');
            }
        } catch (err) {
            toast('error', 'Network error.');
        }
    });

    // Enhance any “Until:” labels on page with local timezone
    document.querySelectorAll('[data-unban][data-utc]').forEach(el => {
        const iso = el.getAttribute('data-utc');
        if (!iso) return;
        const dt = new Date(iso);
        el.textContent = dt.toLocaleString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit', second: '2-digit',
            timeZoneName: 'short'
        });
    });
});
