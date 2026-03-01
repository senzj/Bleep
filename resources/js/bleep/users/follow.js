// Requires a <meta name="csrf-token" content="{{ csrf_token() }}"> in your main layout.
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

        setTimeout(() => {
            toast.remove();
        }, 2500);
    }

    function setFollowingUI(userId, following) {
        document.querySelectorAll(`.follow-btn[data-user-id="${userId}"]`).forEach(btn => {
            btn.dataset.following = following ? '1' : '0';

            const followText = btn.querySelector('.follow-text');
            const unfollowText = btn.querySelector('.unfollow-text');
            const icon = btn.querySelector('.follow-icon');

            if (following) {
                // Following state
                btn.classList.add('bg-blue-100', 'text-blue-700', 'shadow-sm');
                btn.classList.remove('bg-gray-200', 'text-gray-700', 'hover:bg-blue-50', 'hover:text-blue-600');
                btn.classList.add('hover:bg-red-100', 'hover:text-red-600');

                if (followText) followText.textContent = 'Following';
                if (icon) {
                    icon.setAttribute('data-lucide', 'user-round-check');
                    lucide.createIcons(); // Re-render lucide icons
                }
            } else {
                // Not following state
                btn.classList.remove('bg-blue-100', 'text-blue-700', 'hover:bg-red-100', 'hover:text-red-600');
                btn.classList.add('bg-gray-200', 'text-gray-700', 'shadow-sm', 'hover:bg-blue-50', 'hover:text-blue-600');

                if (followText) followText.textContent = 'Follow';
                if (icon) {
                    icon.setAttribute('data-lucide', 'user-round-plus');
                    lucide.createIcons(); // Re-render lucide icons
                }
            }
        });
    }

    // Handle hover state for following buttons
    document.body.addEventListener('mouseenter', (e) => {
        const btn = e.target.closest('.follow-btn');
        if (!btn) return;

        const isFollowing = btn.dataset.following === '1';
        if (!isFollowing) return;

        const followText = btn.querySelector('.follow-text');
        const unfollowText = btn.querySelector('.unfollow-text');
        const icon = btn.querySelector('.follow-icon');

        if (followText) followText.classList.add('hidden');
        if (unfollowText) unfollowText.classList.remove('hidden');
        if (icon) {
            icon.setAttribute('data-lucide', 'user-round-x');
            lucide.createIcons();
        }
    }, true);

    document.body.addEventListener('mouseleave', (e) => {
        const btn = e.target.closest('.follow-btn');
        if (!btn) return;

        const isFollowing = btn.dataset.following === '1';
        if (!isFollowing) return;

        const followText = btn.querySelector('.follow-text');
        const unfollowText = btn.querySelector('.unfollow-text');
        const icon = btn.querySelector('.follow-icon');

        if (followText) followText.classList.remove('hidden');
        if (unfollowText) unfollowText.classList.add('hidden');
        if (icon) {
            icon.setAttribute('data-lucide', 'user-round-check');
            lucide.createIcons();
        }
    }, true);

    // Handle hover state for requested buttons (show cancel intent)
    document.body.addEventListener('mouseenter', (e) => {
        const btn = e.target.closest('.cancel-request-btn');
        if (!btn) return;

        if (btn.classList.contains('reject-request-btn') || btn.classList.contains('accept-request-btn')) {
            return;
        }

        btn.classList.add('btn-error');
        btn.classList.remove('btn-outline');
        btn.innerHTML = '<i data-lucide="x" class="w-4 h-4"></i>Cancel request';

        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }, true);

    document.body.addEventListener('mouseleave', (e) => {
        const btn = e.target.closest('.cancel-request-btn');
        if (!btn) return;

        if (btn.classList.contains('reject-request-btn') || btn.classList.contains('accept-request-btn')) {
            return;
        }

        btn.classList.remove('btn-error');
        btn.classList.add('btn-outline');
        btn.innerHTML = '<i data-lucide="clock" class="w-4 h-4"></i>Requested';

        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }, true);

    document.body.addEventListener('click', async (e) => {
        const btn = e.target.closest('.follow-btn');
        if (!btn) return;

        const userId = btn.dataset.userId;
        if (!userId) return;

        try {
            const res = await fetch(`/bleeper/${userId}/follow`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {})
                },
                body: JSON.stringify({})
            });

            if (!res.ok) {
                return;
            }

            const data = await res.json();
            setFollowingUI(userId, !!data.following);
        } catch (err) {
            console.error('Follow error:', err);
        }
    });

    // Handle "Request" button for private profiles
    document.body.addEventListener('click', async (e) => {
        const btn = e.target.closest('.request-follow-btn');
        if (!btn) return;

        const userId = btn.dataset.userId;
        if (!userId) return;

        btn.disabled = true;
        const icon = btn.querySelector('i');
        if (icon) icon.classList.add('loading', 'loading-spinner');

        try {
            const res = await fetch('/api/follow-requests', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {})
                },
                body: JSON.stringify({ target_id: userId })
            });

            const data = await res.json();

            if (!res.ok) {
                showToast(data.message || 'Failed to send follow request', 'error');
                btn.disabled = false;
                if (icon) icon.classList.remove('loading', 'loading-spinner');
                return;
            }

            // Update button state to show "Requested"
            btn.classList.remove('request-follow-btn');
            btn.classList.add('cancel-request-btn', 'btn-outline');
            btn.textContent = '';
            btn.innerHTML = '<i data-lucide="clock" class="w-4 h-4"></i>' + 'Requested';
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
            showToast(data.message || 'Follow request sent', 'success');
        } catch (err) {
            console.error('Request follow error:', err);
            showToast('An error occurred. Please try again.', 'error');
            btn.disabled = false;
            if (icon) icon.classList.remove('loading', 'loading-spinner');
        }
    });

    // Handle "Cancel" button for sent follow requests
    document.body.addEventListener('click', async (e) => {
        const btn = e.target.closest('.cancel-request-btn');
        if (!btn) return;

        // Make sure it's for follow request, not the one on the requests page
        if (btn.classList.contains('reject-request-btn') || btn.classList.contains('accept-request-btn')) {
            return;
        }

        const userId = btn.dataset.userId;
        if (!userId) return;

        btn.disabled = true;

        try {
            const res = await fetch(`/api/follow-requests/user/${userId}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {})
                }
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                btn.disabled = false;
                showToast(data.message || 'Failed to cancel follow request', 'error');
                return;
            }

            // Update button state back to "Request"
            btn.classList.add('request-follow-btn');
            btn.classList.remove('cancel-request-btn');
            btn.textContent = '';
            btn.innerHTML = '<i data-lucide="user-plus" class="w-4 h-4"></i>' + 'Request';
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
            showToast(data.message || 'Follow request cancelled', 'success');
        } catch (err) {
            console.error('Cancel request error:', err);
            showToast('An error occurred. Please try again.', 'error');
            btn.disabled = false;
        }
    });
});
