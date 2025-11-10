document.addEventListener('DOMContentLoaded', () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    // Handle hover for repost button to show "Remove Repost"
    document.body.addEventListener('mouseenter', (e) => {
        const btn = e.target.closest('.repost-btn');
        if (!btn) return;

        const isReposted = btn.dataset.reposted === '1';
        if (!isReposted) return;

        const repostLabel = btn.querySelector('.repost-text-label');
        const unrepostLabel = btn.querySelector('.unrepost-text-label');

        if (repostLabel) repostLabel.classList.add('hidden');
        if (unrepostLabel) unrepostLabel.classList.remove('hidden');
    }, true);

    document.body.addEventListener('mouseleave', (e) => {
        const btn = e.target.closest('.repost-btn');
        if (!btn) return;

        const isReposted = btn.dataset.reposted === '1';
        if (!isReposted) return;

        const repostLabel = btn.querySelector('.repost-text-label');
        const unrepostLabel = btn.querySelector('.unrepost-text-label');

        if (repostLabel) repostLabel.classList.remove('hidden');
        if (unrepostLabel) unrepostLabel.classList.add('hidden');
    }, true);

    document.body.addEventListener('click', async (e) => {
        const btn = e.target.closest('.repost-btn');
        if (!btn) return;

        const bleepId = btn.dataset.bleepId;
        const isReposted = btn.dataset.reposted === '1';

        if (!bleepId) return;

        try {
            const endpoint = `/bleeps/${bleepId}/repost`;
            const method = isReposted ? 'DELETE' : 'POST';

            const res = await fetch(endpoint, {
                method,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {})
                }
            });

            if (!res.ok) return;

            const data = await res.json();

            // Update all repost buttons for this bleep
            updateRepostButtonState(bleepId, data.reposted, data.repostCount);

            // No more page reload!
            // The repost tag will show when the user refreshes naturally or navigates

        } catch (err) {
            console.error('Repost error:', err);
        }
    });
});

// Helper function to update button state
function updateRepostButtonState(bleepId, isReposted, repostCount) {
    document.querySelectorAll(`.repost-btn[data-bleep-id="${bleepId}"]`).forEach((btn) => {
        btn.dataset.reposted = isReposted ? '1' : '0';

        // Update classes
        if (isReposted) {
            btn.classList.add('bg-green-100', 'text-green-700', 'shadow-sm', 'hover:bg-red-100', 'hover:text-red-600');
            btn.classList.remove('hover:bg-green-50', 'hover:text-green-600', 'text-gray-500');
        } else {
            btn.classList.remove('bg-green-100', 'text-green-700', 'shadow-sm', 'hover:bg-red-100', 'hover:text-red-600');
            btn.classList.add('hover:bg-green-50', 'hover:text-green-600', 'text-gray-500');
        }

        // Update mobile count
        const mobileMeta = btn.querySelector('.repost-meta-mobile');
        if (mobileMeta) {
            mobileMeta.textContent = repostCount;
        }

        // Update desktop text
        const countSpan = btn.querySelector('.repost-count');
        if (countSpan) {
            countSpan.textContent = `${repostCount} `;
        }

        const textLabel = btn.querySelector('.repost-text-label');
        if (textLabel) {
            textLabel.textContent = isReposted ? 'Reposted' : (repostCount === 1 ? 'Repost' : 'Reposts');
        }
    });
}
