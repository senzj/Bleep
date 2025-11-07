document.addEventListener('click', (e) => {
    const btn = e.target.closest('.repost-btn');
    if (!btn) return;

    const bleepId = btn.dataset.bleepId;
    if (!bleepId) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const isReposted = btn.dataset.reposted === '1';
    const method = isReposted ? 'DELETE' : 'POST';

    btn.disabled = true;

    fetch(`/bleeps/${bleepId}/repost`, {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        body: method === 'POST' ? JSON.stringify({}) : null,
    })
    .then(async (res) => {
        if (res.status === 401) {
            window.location.href = '/login';
            return;
        }
        if (!res.ok && res.status !== 204) {
            const txt = await res.text();
            throw new Error(txt || 'Request failed');
        }
        return res.status === 204 ? { reposts_count: null } : res.json();
    })
    .then((data) => {
        // flip state
        const newState = !isReposted;
        updateRepostButtonState(bleepId, newState, data);
    })
    .catch((err) => {
        console.error('Repost error', err);
    })
    .finally(() => {
        btn.disabled = false;
    });
});

// Touch/long-press support for mobile
document.addEventListener('touchstart', (e) => {
    const btn = e.target.closest('.repost-btn');
    if (!btn) return;

    let touchTimer = setTimeout(() => {
        const isReposted = btn.dataset.reposted === '1';
        btn.title = isReposted ? 'Click to remove repost' : 'Click to repost';
    }, 500);

    const clearTimer = () => clearTimeout(touchTimer);
    btn.addEventListener('touchend', clearTimer, { once: true });
    btn.addEventListener('touchcancel', clearTimer, { once: true });
}, true);

// Helper function to update button state
function updateRepostButtonState(bleepId, newState, data) {
    document.querySelectorAll(`.repost-btn[data-bleep-id="${bleepId}"]`).forEach((btn) => {
        btn.dataset.reposted = newState ? '1' : '0';

        // Update classes
        btn.classList.toggle('bg-green-100', newState);
        btn.classList.toggle('text-green-700', newState);
        btn.classList.toggle('shadow-sm', newState);
        btn.classList.toggle('hover:bg-green-50', !newState);
        btn.classList.toggle('hover:text-green-600', !newState);
        btn.classList.toggle('text-gray-500', !newState);

        // Update title for hover/long-press
        btn.title = newState ? 'You reposted — click to remove' : 'Repost';

        // Get repost count from response or default to 0
        const repostCount = (data && data.reposts_count !== undefined) ? data.reposts_count : 0;

        // Update desktop text
        const repostText = btn.querySelector('.repost-text');
        if (repostText) {
            const label = repostText.querySelector('.repost-label');
            if (label) {
                if (newState) {
                    label.innerHTML = `
                        <span class="repost-count mr-0.5">${repostCount}</span>
                        ${repostCount === 1 ? 'Repost' : 'Reposts'}
                    `;
                } else {
                    label.innerHTML = `
                        <span class="repost-count mr-0.5">${repostCount}</span>
                        ${repostCount === 1 ? 'Repost' : 'Reposts'}
                    `;
                }
            }
        }

        // Update mobile meta count
        const mobileMeta = btn.querySelector('.repost-meta-mobile');
        if (mobileMeta) {
            mobileMeta.textContent = repostCount;
        }
    });
}
