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

    const euRole = document.getElementById('eu_role');
    const euIsVerified = document.getElementById('eu_is_verified');

    const euBanTypeTemp = document.getElementById('eu_ban_type_temp');
    const euBanTypePerm = document.getElementById('eu_ban_type_perm');
    const euBanUntilWrap = document.getElementById('eu_ban_until_wrap');

    const warningsList = document.getElementById('eu_warnings');
    const warningCount = document.getElementById('eu_warning_count');

    let lastBanChecked = false;

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

    function updateBanTypeUI() {
        const temp = euBanTypeTemp?.checked;
        if (euBanUntilWrap) euBanUntilWrap.classList.toggle('hidden', !temp);
        if (!temp && euBannedUntil) euBannedUntil.value = '';
    }

    function toggleBanFields() {
        const show = euIsBanned.checked;
        banFields.classList.toggle('hidden', !show);
        if (!show) {
            euBanReason.value = '';
            euBannedUntil.value = '';
        }
        updateBanTypeUI();
    }

    function updateReasonCounter() {
        const len = euBanReason.value.length;
        euBanReasonCounter.textContent = `${len} / 500`;
    }

    euBanReason?.addEventListener('input', updateReasonCounter);
    euIsBanned?.addEventListener('change', () => {
        // Soft guard to avoid accidental bans
        if (euIsBanned.checked && !lastBanChecked) {
            const ok = confirm('Are you sure you want to ban this user?');
            if (!ok) {
                euIsBanned.checked = false;
            }
        }
        lastBanChecked = euIsBanned.checked;
        toggleBanFields();
    });

    euBanTypeTemp?.addEventListener('change', updateBanTypeUI);
    euBanTypePerm?.addEventListener('change', updateBanTypeUI);

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.edit-user-btn');
        if (btn) {
            e.preventDefault();
            const user = {
                id: parseInt(btn.dataset.userId, 10),
                username: btn.dataset.username || `User #${btn.dataset.userId}`,
                email: btn.dataset.email || '',
                role: btn.dataset.role || 'user',
                is_verified: btn.dataset.verified === '1',
                is_banned: btn.dataset.isBanned === '1',
                ban_reason: btn.dataset.banReason || '',
                banned_until: btn.dataset.bannedUntil || null,
            };
            openModalWithUser(user);
        }

        const preset = e.target.closest('.preset');
        if (preset && euBannedUntil) {
            const hrs = parseInt(preset.dataset.hours, 10);
            const t = new Date(Date.now() + hrs * 3600 * 1000);
            euBannedUntil.value = fmtLocal(t);
            if (euBanTypeTemp) euBanTypeTemp.checked = true;
            updateBanTypeUI();
        }

        const revokeBtn = e.target.closest('.revoke-warning-btn');
        if (revokeBtn) {
            e.preventDefault();
            const warningId = revokeBtn.dataset.warningId;
            if (!warningId) return;
            if (!confirm('Revoke this warning? It will stop counting as a strike.')) return;

            try {
                const res = await fetch(`/admin/warnings/${warningId}/revoke`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                    },
                    body: JSON.stringify({}),
                });
                const json = await res.json().catch(() => ({}));

                if (res.ok) {
                    toast('success', json.message || 'Warning revoked.');
                    loadWarnings(euId.value);
                } else {
                    toast('error', json.message || 'Could not revoke the warning.');
                }
            } catch {
                toast('error', 'Network error.');
            }
        }
    });

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = euId.value;

        // Build payload
        const isBanned = euIsBanned.checked;
        const durationType = (euBanTypePerm?.checked) ? 'permanent' : 'temporary';

        let bannedUntil = null;
        if (isBanned && durationType === 'temporary' && euBannedUntil.value) {
            const local = new Date(euBannedUntil.value);
            bannedUntil = local.toISOString(); // send UTC
        }

        const payload = {
            role: euRole?.value || 'user',
            is_verified: euIsVerified?.checked ? true : false,

            is_banned: isBanned,
            duration_type: isBanned ? durationType : null,
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
                setTimeout(() => location.reload(), 300);
            } else {
                toast('error', json.message || 'Update failed.');
            }
        } catch (err) {
            toast('error', 'Network error.');
        }
    });

    async function loadWarnings(userId) {
        if (!warningsList) return;

        warningsList.innerHTML = '<p class="text-base-content/50">Loading…</p>';
        if (warningCount) warningCount.textContent = '…';

        try {
            const res = await fetch(`/admin/users/${userId}/warnings`, {
                headers: { 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('Could not load warnings');

            renderWarnings(await res.json());
        } catch {
            warningsList.innerHTML = '<p class="text-error">Could not load warnings.</p>';
            if (warningCount) warningCount.textContent = '—';
        }
    }

    function renderWarnings(data) {
        if (!warningsList) return;

        const warnings = Array.isArray(data.warnings) ? data.warnings : [];

        if (warningCount) {
            warningCount.textContent = `${data.active_count || 0} active`;
        }

        if ((data.active_count || 0) > 0) {
            const modToggle = document.getElementById('eu_mod_collapse');
            if (modToggle) modToggle.checked = true;
        }

        warningsList.innerHTML = '';

        if (!warnings.length) {
            const empty = document.createElement('p');
            empty.className = 'text-base-content/50';
            empty.textContent = 'No warnings on record.';
            warningsList.appendChild(empty);
            return;
        }

        warnings.forEach(w => {
            const row = document.createElement('div');
            row.className = 'rounded-lg border border-base-300 bg-base-100 p-2.5';

            const head = document.createElement('div');
            head.className = 'flex items-center justify-between gap-2';

            const label = document.createElement('span');
            label.className = 'font-semibold capitalize';
            label.textContent = w.reason || 'warning';
            head.appendChild(label);

            const status = document.createElement('span');
            const state = w.active ? 'active' : (w.revoked_at ? 'revoked' : 'expired');
            status.className = 'badge badge-xs ' + (w.active ? 'badge-warning' : 'badge-ghost');
            status.textContent = state;
            head.appendChild(status);
            row.appendChild(head);

            const meta = document.createElement('div');
            meta.className = 'text-base-content/50 mt-1';
            meta.textContent = `by ${w.moderator || 'System'} · ${w.created_at_human || ''}`;
            row.appendChild(meta);

            const expiry = document.createElement('div');
            expiry.className = 'text-base-content/40 mt-0.5';
            if (w.revoked_at) {
                expiry.textContent = 'Revoked';
            } else if (w.expires_at) {
                expiry.textContent = 'Expires ' + new Date(w.expires_at).toLocaleDateString();
            }
            if (expiry.textContent) row.appendChild(expiry);

            if (w.notes) {
                const notes = document.createElement('div');
                notes.className = 'italic text-base-content/50 mt-1 break-words';
                notes.textContent = `"${w.notes}"`;
                row.appendChild(notes);
            }

            if (w.active) {
                const actions = document.createElement('div');
                actions.className = 'mt-2';

                const revoke = document.createElement('button');
                revoke.type = 'button';
                revoke.className = 'btn btn-xs btn-ghost text-error revoke-warning-btn';
                revoke.dataset.warningId = String(w.id);
                revoke.textContent = 'Revoke';

                actions.appendChild(revoke);
                row.appendChild(actions);
            }

            warningsList.appendChild(row);
        });
    }

    function openModalWithUser(user) {        euId.value = user.id;
        euUsername.value = user.username ?? `User #${user.id}`;
        euEmail.value = user.email ?? '';

        // Role + Verified
        if (euRole) euRole.value = user.role || 'user';
        if (euIsVerified) euIsVerified.checked = user.is_verified === true || user.is_verified === '1';

        // Ban state
        euIsBanned.checked = !!user.is_banned;
        lastBanChecked = euIsBanned.checked;
        banFields.classList.toggle('hidden', !euIsBanned.checked);

        euBanReason.value = user.ban_reason ?? '';
        updateReasonCounter();

        setMinNow();
        // Decide ban type from data
        const hasTemp = !!user.banned_until;
        if (euBanTypeTemp && euBanTypePerm) {
            euBanTypeTemp.checked = !!hasTemp;
            euBanTypePerm.checked = !hasTemp;
        }
        if (hasTemp) {
            const dt = new Date(user.banned_until);
            euBannedUntil.value = fmtLocal(dt);
        } else {
            euBannedUntil.value = '';
        }
        updateBanTypeUI();

        // Collapse moderation closed by default unless already banned
        const modToggle = document.getElementById('eu_mod_collapse');
        if (modToggle) modToggle.checked = !!user.is_banned;

        loadWarnings(user.id);

        modal.checked = true;
    }

    // Remove GMT/PST suffix by dropping timeZoneName
    document.querySelectorAll('[data-unban][data-utc]').forEach(el => {
        const iso = el.getAttribute('data-utc');
        if (!iso) return;
        const dt = new Date(iso);
        el.textContent = dt.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
            // no timeZoneName
        });
    });
});
