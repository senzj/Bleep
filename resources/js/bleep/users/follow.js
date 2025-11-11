// Requires a <meta name="csrf-token" content="{{ csrf_token() }}"> in your main layout.
document.addEventListener('DOMContentLoaded', () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

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
});
