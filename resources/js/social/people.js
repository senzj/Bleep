document.addEventListener('DOMContentLoaded', () => {

    // Safe CSRF token read (still useful for other requests)
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const CSRF_TOKEN = csrfMeta ? csrfMeta.getAttribute('content') : '';
    if (!CSRF_TOKEN) {
        console.warn('CSRF token meta not found — follow requests may fail.');
    }

    // Initialize each people component instance on the page
    document.querySelectorAll('.people-component').forEach((root) => {
        const searchInput = root.querySelector('.people-search-input');
        const suggestionsContainer = root.querySelector('.people-user-suggestions');
        const noResultsMessage = root.querySelector('.people-no-results-message');
        let searchTimeout = null;

        // Fetch and display users based on search query
        async function fetchUsers(query = '') {
            try {
                const response = await fetch(`/api/users/search?q=${encodeURIComponent(query)}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

                const users = await response.json();
                displayUsers(users);
            } catch (error) {
                console.error('Search error:', error);
                if (noResultsMessage) noResultsMessage.classList.remove('hidden');
                if (suggestionsContainer) suggestionsContainer.innerHTML = '<div class="text-center text-error p-4">Error loading users.</div>';
            }
        }

        // Display users in the suggestions container
        // NOTE: follow button markup is intentionally compatible with resources/js/bleep/users/follow.js
        function displayUsers(users) {
            if (!suggestionsContainer) return;
            if (users.length === 0) {
                suggestionsContainer.innerHTML = '';
                if (noResultsMessage) noResultsMessage.classList.remove('hidden');
                return;
            }

            if (noResultsMessage) noResultsMessage.classList.add('hidden');

            suggestionsContainer.innerHTML = users.map(user => `
                <div class="user-item flex items-center gap-4 w-full min-w-0 flex-wrap"
                     data-user-id="${user.id}"
                     data-username="${user.username}"
                     data-display-name="${user.dname}"
                     data-is-mutual="${user.is_mutual ? '1' : '0'}">
                    <a href="/bleeper/${user.username}" class="shrink-0">
                        <img src="${user.profile_picture_url}"
                             alt="${user.dname}'s Avatar"
                             class="w-10 h-10 rounded-full hover:ring-2 hover:ring-primary transition-all">
                    </a>

                    <div class="flex-1 min-w-0">
                        <a href="/bleeper/${user.username}" class="block hover:text-primary transition-colors">
                            <p class="font-semibold truncate flex items-center gap-2">
                                <span class="truncate">${user.dname}</span>
                                ${user.is_mutual ? '<span class="ml-2 badge badge-sm badge-outline shrink-0">Mutual</span>' : ''}
                            </p>
                            <p class="text-sm text-base-content/60 truncate">@${user.username}</p>
                        </a>
                    </div>

                    <div class="shrink-0">
                        <!-- Markup matches bleep/users/follow.js expectations -->
                        <button type="button"
                                class="cursor-pointer flex items-center gap-1.5 text-xs font-medium group follow-btn rounded-full px-2.5 py-1 transition-all duration-200 ease-out mt-2
                                    ${user.is_following ? 'bg-blue-100 text-blue-700 shadow-sm hover:bg-red-100 hover:text-red-600' : 'bg-gray-200 text-gray-700 hover:bg-blue-50 hover:text-blue-600 shadow-sm'}"
                                data-user-id="${user.id}"
                                data-following="${user.is_following ? '1' : '0'}"
                        >
                            <i data-lucide="${user.is_following ? 'user-round-check' : 'user-round-plus'}" class="w-4 h-4 transition-transform duration-200 group-hover:scale-110 follow-icon"></i>
                            <span class="follow-text">${user.is_following ? 'Following' : 'Follow'}</span>
                            <span class="unfollow-text hidden">Unfollow</span>
                        </button>
                    </div>
                </div>
            `).join('');

            // Reinitialize Lucide icons
            if (window.lucide) {
                window.lucide.createIcons();
            }
        }

        // Search with debounce
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                const query = e.target.value.trim();

                searchTimeout = setTimeout(() => {
                    fetchUsers(query);
                }, 300);
            });

            // optional: run initial fetch to refresh server-side rendered suggestions
            fetchUsers('');
        }
    });

});
