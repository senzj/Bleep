document.addEventListener('DOMContentLoaded', () => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function toast(type, text) {
        const old = document.querySelector('.toast[data-devices="1"]');
        if (old) old.remove();
        const wrap = document.createElement('div');
        wrap.className = 'toast toast-top toast-center';
        wrap.dataset.devices = '1';
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `<span>${text}</span>`;
        wrap.appendChild(alert);
        document.body.appendChild(wrap);
        setTimeout(() => wrap.remove(), 3000);
    }

    // Revoke session
    document.addEventListener('click', async (e) => {
        const sessionBtn = e.target.closest('.revoke-session-btn');
        if (sessionBtn) {
            e.preventDefault();
            const sessionId = sessionBtn.dataset.sessionId;
            if (!confirm('Revoke this session? User will be logged out.')) return;

            try {
                const res = await fetch(`/admin/devices/session/${sessionId}/revoke`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) },
                });
                const json = await res.json().catch(() => ({}));
                if (res.ok) {
                    toast('success', json.message || 'Session revoked.');
                    setTimeout(() => location.reload(), 500);
                } else {
                    toast('error', json.message || 'Failed.');
                }
            } catch {
                toast('error', 'Network error.');
            }
        }

        const deviceBtn = e.target.closest('.revoke-device-btn');
        if (deviceBtn) {
            e.preventDefault();
            const deviceId = deviceBtn.dataset.deviceId;
            if (!confirm('Remove this remembered device?')) return;

            try {
                const res = await fetch(`/admin/devices/device/${deviceId}/revoke`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) },
                });
                const json = await res.json().catch(() => ({}));
                if (res.ok) {
                    toast('success', json.message || 'Device removed.');
                    setTimeout(() => location.reload(), 500);
                } else {
                    toast('error', json.message || 'Failed.');
                }
            } catch {
                toast('error', 'Network error.');
            }
        }
    });

    // Format timestamps
    document.querySelectorAll('[data-timestamp]').forEach(el => {
        const iso = el.getAttribute('data-timestamp');
        if (!iso) return;
        const dt = new Date(iso);
        el.textContent = dt.toLocaleString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    });

    // Optional: debounce search input for live updates (sends GET)
    const searchInput = document.querySelector('#devices-filter-form input[name="q"]');
    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                document.getElementById('devices-filter-form').submit();
            }, 600);
        });
    }
});
