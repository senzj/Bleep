/**
 * Likes Functionality with Optimistic UI
 */

// Handle hover effect for liked buttons
document.addEventListener('mouseover', (e) => {
    const button = e.target.closest('.like-btn');
    if (!button) return;

    const isLiked = button.classList.contains('text-red-600');
    const heartIcon = button.querySelector('.heart-icon');

    // Only show heart-crack on hover if already liked
    if (isLiked && heartIcon) {
        heartIcon.setAttribute('data-lucide', 'heart-crack');
        if (window.createLucideIcons) {
            window.createLucideIcons(button);
        }
    }
});

// Handle mouse leave to restore heart icon
document.addEventListener('mouseout', (e) => {
    const button = e.target.closest('.like-btn');
    if (!button) return;

    const isLiked = button.classList.contains('text-red-600');
    const heartIcon = button.querySelector('.heart-icon');

    // Restore heart icon when mouse leaves if still liked
    if (isLiked && heartIcon) {
        heartIcon.setAttribute('data-lucide', 'heart');
        if (window.createLucideIcons) {
            window.createLucideIcons(button);
        }
    }
});

// Use event delegation so forms added later also work
document.addEventListener('submit', async (e) => {
    const form = e.target.closest('.like-form');
    if (!form) return;

    e.preventDefault();

    const button = form.querySelector('.like-btn');
    const bleepId = button?.dataset.bleepId;
    const csrfToken = form.querySelector('input[name="_token"]')?.value
        || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (!bleepId || !csrfToken) return;

    // Prevent double-clicks
    if (button.disabled) return;
    button.disabled = true;

    // Get current state
    const isLiked = button.classList.contains('text-red-600');
    const mobileElem = form.querySelector('.like-count');
    const desktopElem = form.querySelector('.like-text');
    const heartIcon = button.querySelector('.heart-icon');

    // Get current count
    let currentCount = parseInt(mobileElem?.textContent || '0', 10);

    // Optimistic update - toggle immediately
    const newIsLiked = !isLiked;
    const newCount = newIsLiked ? currentCount + 1 : Math.max(0, currentCount - 1);

    // Update UI immediately
    button.classList.toggle('text-red-600', newIsLiked);

    // Always show heart icon on click and add animation
    heartIcon?.setAttribute('data-lucide', 'heart');

    if (window.createLucideIcons) {
        window.createLucideIcons(button);
    }

    button.classList.add('like-animate');
    setTimeout(() => button.classList.remove('like-animate'), 600);

    // Update counts
    if (mobileElem) {
        mobileElem.textContent = String(newCount);
    }
    if (desktopElem) {
        const suffix = newCount === 1
            ? (newIsLiked ? 'Liked' : 'Like')
            : 'Likes';
        desktopElem.textContent = `${newCount} ${suffix}`;
    }

    try {
        const response = await fetch(`/bleeps/${bleepId}/like`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({})
        });

        if (response.status === 401) {
            window.location.href = '/login';
            return;
        }

        if (!response.ok) {
            // Revert on error
            button.classList.toggle('text-red-600', isLiked);
            heartIcon?.setAttribute('data-lucide', 'heart');
            if (window.createLucideIcons) {
                window.createLucideIcons(button);
            }
            if (mobileElem) mobileElem.textContent = String(currentCount);
            if (desktopElem) {
                const suffix = currentCount === 1
                    ? (isLiked ? 'Liked' : 'Like')
                    : 'Likes';
                desktopElem.textContent = `${currentCount} ${suffix}`;
            }
            return;
        }

        const data = await response.json();

        // Fetch actual count to ensure consistency
        const countResponse = await fetch(`/bleeps/${bleepId}/likes-count`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        const countData = await countResponse.json();
        const serverCount = parseInt(countData.count ?? 0, 10);

        // Update with server count if different
        if (serverCount !== newCount) {
            if (mobileElem) mobileElem.textContent = String(serverCount);
            if (desktopElem) {
                const suffix = serverCount === 1
                    ? (newIsLiked ? 'Liked' : 'Like')
                    : 'Likes';
                desktopElem.textContent = `${serverCount} ${suffix}`;
            }
        }
    } catch (error) {
        console.error('Error liking bleep:', error);
        // Revert on error
        button.classList.toggle('text-red-600', isLiked);
        heartIcon?.setAttribute('data-lucide', 'heart');
        if (window.createLucideIcons) {
            window.createLucideIcons(button);
        }
        if (mobileElem) mobileElem.textContent = String(currentCount);
        if (desktopElem) {
            const suffix = currentCount === 1
                ? (isLiked ? 'Liked' : 'Like')
                : 'Likes';
            desktopElem.textContent = `${currentCount} ${suffix}`;
        }
    } finally {
        button.disabled = false;
    }
});
