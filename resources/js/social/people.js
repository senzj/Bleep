document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('user-search-input');
    const suggestionsContainer = document.getElementById('user-suggestions');
    const noResultsMessage = document.getElementById('no-results-message');
    const loadingSpinner = document.createElement('div'); // Create spinner element
    loadingSpinner.className = 'text-center py-4';
    loadingSpinner.innerHTML = '<span class="loading loading-spinner loading-md"></span> Loading...';

    let debounceTimer;
    let isInitialLoad = true; // Flag to avoid replacing server-rendered content initially

    // Function to load suggestions
    async function loadSuggestions(query = '') {
        // Show spinner
        suggestionsContainer.innerHTML = '';
        suggestionsContainer.appendChild(loadingSpinner);

        try {
            const response = await fetch(`/api/users/search?q=${encodeURIComponent(query)}`, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
            });

            if (response.ok) {
                const users = await response.json();
                updateSuggestions(users, query);
                isInitialLoad = false; // After first load, allow replacements
            } else {
                console.error('Load suggestions failed');
                suggestionsContainer.innerHTML = '<p class="text-center text-base-content/60">Error loading suggestions.</p>';
            }
        } catch (error) {
            console.error('Load suggestions error:', error);
            suggestionsContainer.innerHTML = '<p class="text-center text-base-content/60">Error loading suggestions.</p>';
        }
    }

    // Only load on search; keep server-rendered initial
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const query = searchInput.value.trim();
            loadSuggestions(query);
        }, 150); // Reduced to 150ms for less lag
    });

    // Update suggestions list with DocumentFragment for speed
    function updateSuggestions(users, query) {
        const fragment = document.createDocumentFragment();

        if (users.length === 0) {
            if (query) {
                noResultsMessage.classList.remove('hidden');
            } else {
                // keep a friendly message when no server suggestions are available
                const p = document.createElement('p');
                p.className = 'text-center text-base-content/60';
                p.textContent = "Looks like you're on your own buddy..";
                fragment.appendChild(p);
                noResultsMessage.classList.add('hidden');
            }
        } else {
            noResultsMessage.classList.add('hidden');
            users.forEach(user => {
                const item = document.createElement('div');
                item.className = 'user-item flex items-center space-x-4 w-full min-w-0';
                if (query) item.classList.add('searched');

                // include mutual badge if flagged from server
                const mutualBadge = user.is_mutual ? `<span class="ml-2 badge badge-sm badge-outline shrink-0">Mutual</span>` : '';

                item.innerHTML = `
                    <img src="${user.profile_picture_url}" alt="${escapeHtml(user.dname)}'s Avatar" class="w-12 h-12 rounded-full shrink-0">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold truncate flex items-center">
                            <span class="truncate">${escapeHtml(user.dname)}</span>
                            ${mutualBadge}
                        </p>
                        <p class="text-sm text-base-content/60 truncate">@${escapeHtml(user.username)}</p>
                    </div>
                    <button class="follow-btn btn btn-sm btn-primary shrink-0" data-user-id="${user.id}" data-following="false" aria-pressed="false">
                        <i data-lucide="user-plus" class="w-4 h-4 mr-1"></i>
                        <span class="btn-label">Follow</span>
                    </button>
                `;
                fragment.appendChild(item);
            });
        }

        suggestionsContainer.innerHTML = '';
        suggestionsContainer.appendChild(fragment);

        // Re-render lucide icons after dynamic insertions (support different lucide API names)
        if (window.lucide) {
            if (typeof window.lucide.replace === 'function') {
                try { window.lucide.replace(); } catch (e) { /* ignore */ }
            } else if (typeof window.lucide.createIcons === 'function') {
                try { window.lucide.createIcons(); } catch (e) { /* ignore */ }
            }
        }

        // mark that we replaced server-rendered content
        isInitialLoad = false;
    }

    // Utility: escape text to avoid injection when injecting via innerHTML
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // Follow/unfollow functionality (unchanged)
    suggestionsContainer.addEventListener('click', async (e) => {
        const btn = e.target.closest('.follow-btn');
        if (!btn) return;

        const userId = btn.dataset.userId;
        const isFollowing = btn.dataset.following === 'true';

        try {
            const response = await fetch(`/bleeper/${userId}/follow`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify({}),
            });

            if (response.ok) {
                const result = await response.json();
                if (result.following) {
                    btn.innerHTML = '<i data-lucide="user-minus" class="w-4 h-4 mr-1"></i><span class="btn-label">Unfollow</span>';
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-secondary');
                    btn.dataset.following = 'true';
                    btn.setAttribute('aria-pressed', 'true');
                } else {
                    btn.innerHTML = '<i data-lucide="user-plus" class="w-4 h-4 mr-1"></i><span class="btn-label">Follow</span>';
                    btn.classList.remove('btn-secondary');
                    btn.classList.add('btn-primary');
                    btn.dataset.following = 'false';
                    btn.setAttribute('aria-pressed', 'false');
                }

                // Re-render lucide icons within the updated button
                if (window.lucide) {
                    if (typeof window.lucide.replace === 'function') {
                        try { window.lucide.replace(); } catch (e) { /* ignore */ }
                    } else if (typeof window.lucide.createIcons === 'function') {
                        try { window.lucide.createIcons(); } catch (e) { /* ignore */ }
                    }
                }
            } else {
                alert('Failed to toggle follow. Please try again.');
            }
        } catch (error) {
            console.error('Follow error:', error);
            alert('An error occurred. Please try again.');
        }
    });
});