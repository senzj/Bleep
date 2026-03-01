// Handle follow request actions (accept/reject)
document.addEventListener('DOMContentLoaded', () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    function showToast(message, type = 'success') {
        if (!message) return;

        const toast = document.createElement('div');
        toast.className = 'toast toast-top toast-center z-100';

        const alertClass = type === 'error' ? 'alert-error' : 'alert-success';
        const icon = type === 'error' ? 'circle-alert' : 'check-circle';

        toast.innerHTML = `
            <div class="alert ${alertClass}">
                <i data-lucide="${icon}" class="h-6 w-6 shrink-0 stroke-current"></i>
                <span>${message}</span>
            </div>
        `;

        document.body.appendChild(toast);

        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }

        setTimeout(() => toast.remove(), 2500);
    }

    function renderFollowBackButton(card) {
        const requesterId = card.dataset.requesterId;
        const isFollowingBack = card.dataset.isFollowingBack === '1';

        return `
            <button type="button"
                class="btn btn-sm ${isFollowingBack ? 'btn-primary' : 'btn-outline'} gap-2 follow-btn"
                data-user-id="${requesterId}"
                data-following="${isFollowingBack ? '1' : '0'}"
                title="${isFollowingBack ? 'Following this user' : 'Follow this user back'}">
                <i data-lucide="${isFollowingBack ? 'user-check' : 'user-plus'}" class="w-4 h-4 follow-icon"></i>
                <span class="follow-text">${isFollowingBack ? 'Following' : 'Follow back'}</span>
                <span class="unfollow-text hidden">Unfollow</span>
            </button>
        `;
    }

    function renderRejectedButton() {
        return `
            <button type="button"
                class="btn btn-sm btn-disabled cursor-not-allowed opacity-50"
                disabled
                title="Cannot follow for 24 hours after rejection">
                <i data-lucide="user-x" class="w-4 h-4"></i>
                Rejected
            </button>
        `;
    }

    function updateEmptyState() {
        const remainingPendingCards = document.querySelectorAll('[data-request-id][data-status="pending"]');
        if (remainingPendingCards.length > 0) return;

        // Check if there are any non-pending requests
        const nonPendingCards = document.querySelectorAll('[data-request-id]:not([data-status="pending"])');
        if (nonPendingCards.length === 0) {
            const groupsContainer = document.getElementById('follow-requests-groups');
            if (!groupsContainer) return;

            groupsContainer.outerHTML = `
                <div class="text-center py-12">
                    <i data-lucide="inbox" class="w-16 h-16 mx-auto text-base-content/30 mb-4"></i>
                    <p class="text-base-content/60 mb-2">No pending follow requests</p>
                    <p class="text-sm text-base-content/40">When someone requests to follow you, it will appear here.</p>
                </div>
            `;

            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
        }
    }

    // Accept follow request
    document.body.addEventListener('click', async (e) => {
        const btn = e.target.closest('.accept-request-btn');
        if (!btn) return;

        const requestId = btn.dataset.requestId;
        if (!requestId) return;

        btn.disabled = true;
        btn.classList.add('loading');

        try {
            const res = await fetch(`/api/follow-requests/${requestId}/accept`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {})
                },
                body: JSON.stringify({})
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                showToast(data.message || 'Failed to accept follow request', 'error');
                btn.disabled = false;
                btn.classList.remove('loading');
                return;
            }

            const card = btn.closest('[data-request-id]');
            const actions = card?.querySelector('.request-actions');
            const message = card?.querySelector('.request-message');

            if (actions) {
                actions.innerHTML = renderFollowBackButton(card);
            }

            if (message) {
                message.innerHTML = `
                    <div class="flex items-center gap-2">
                        <span class="badge badge-success badge-sm">Accepted</span>
                        <p class="text-sm text-base-content/70">Request accepted</p>
                    </div>
                `;
            }

            // Update card status attribute
            if (card) {
                card.dataset.status = 'accepted';
                card.classList.remove('hover:bg-base-200');
            }

            btn.classList.remove('loading');
            showToast(data.message || 'Follow request accepted', 'success');

            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
        } catch (err) {
            console.error('Accept request error:', err);
            showToast('An error occurred. Please try again.', 'error');
            btn.disabled = false;
            btn.classList.remove('loading');
        }
    });

    // Reject follow request
    document.body.addEventListener('click', async (e) => {
        const btn = e.target.closest('.reject-request-btn');
        if (!btn) return;

        const requestId = btn.dataset.requestId;
        if (!requestId) return;

        btn.disabled = true;
        btn.classList.add('loading');

        try {
            const res = await fetch(`/api/follow-requests/${requestId}/reject`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {})
                },
                body: JSON.stringify({})
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                showToast(data.message || 'Failed to reject follow request', 'error');
                btn.disabled = false;
                btn.classList.remove('loading');
                return;
            }

            // Update the card to show rejected state instead of removing
            const card = btn.closest('[data-request-id]');
            const actions = card?.querySelector('.request-actions');
            const message = card?.querySelector('.request-message');

            if (actions) {
                actions.innerHTML = renderRejectedButton();
            }

            if (message) {
                message.innerHTML = `
                    <div class="flex items-center gap-2">
                        <span class="badge badge-error badge-sm">Rejected</span>
                        <p class="text-sm text-base-content/70">Request rejected</p>
                    </div>
                `;
            }

            // Update card styling and status
            if (card) {
                card.dataset.status = 'rejected';
                card.classList.remove('hover:bg-base-200');
                card.classList.add('opacity-60');
            }

            updateEmptyState();
            showToast(data.message || 'Follow request rejected', 'success');

            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
        } catch (err) {
            console.error('Reject request error:', err);
            showToast('An error occurred. Please try again.', 'error');
            btn.disabled = false;
            btn.classList.remove('loading');
        }
    });
});
