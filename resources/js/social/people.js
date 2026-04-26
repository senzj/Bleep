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

        // Cache SSR-rendered list so we can restore it when search is cleared
        const ssrHTML = suggestionsContainer?.innerHTML ?? '';

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

        // Handle search input with debounce
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                const query = e.target.value.trim();

                if (query === '') {
                    if (noResultsMessage) noResultsMessage.classList.add('hidden');
                    suggestionsContainer.innerHTML = ssrHTML;
                    window.lucide?.createIcons?.();
                    return;
                }

                searchTimeout = setTimeout(() => {
                    fetchUsers(query);
                }, 300);
            });

            // No initial fetchUsers() call — SSR handles the initial render
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

            const degreeLabel = (type) => {
                if (type === 'two-way') return 'Friend';
                if (type === 'friend-of-friend') return 'Mutual Friend';
                if (type === 'friend-of-friend-of-friend') return 'Mutual Friend of Friend';
                return '';
            };

            const renderActionButton = (user) => {
                if (user.is_following) {
                    return `
                        <button type="button"
                                class="btn btn-sm btn-primary gap-2 follow-btn"
                                data-user-id="${user.id}"
                                data-following="1">
                            <i data-lucide="user-check" class="w-4 h-4 follow-icon"></i>
                            <span class="follow-text">Following</span>
                            <span class="unfollow-text hidden">Unfollow</span>
                        </button>
                    `;
                }

                if (user.has_pending_request) {
                    return `
                        <button type="button"
                                class="btn btn-sm btn-outline gap-2 cancel-request-btn"
                                data-user-id="${user.id}">
                            <i data-lucide="clock" class="w-4 h-4"></i>
                            Requested
                        </button>
                    `;
                }

                if (user.is_private) {
                    return `
                        <button type="button"
                                class="btn btn-sm btn-outline gap-2 request-follow-btn"
                                data-user-id="${user.id}">
                            <i data-lucide="user-plus" class="w-4 h-4"></i>
                            Request
                        </button>
                    `;
                }

                return `
                    <button type="button"
                            class="btn btn-sm btn-outline gap-2 follow-btn"
                            data-user-id="${user.id}"
                            data-following="0">
                        <i data-lucide="user-plus" class="w-4 h-4 follow-icon"></i>
                        <span class="follow-text">Follow</span>
                        <span class="unfollow-text hidden">Unfollow</span>
                    </button>
                `;
            };

            suggestionsContainer.innerHTML = users.map(user => `
                <div class="user-item flex items-center gap-4 w-full min-w-0 flex-wrap p-4 rounded-lg hover:bg-base-100 transition"
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
                                ${user.mutual_type ? `<span class="ml-2 badge badge-sm badge-outline shrink-0">${degreeLabel(user.mutual_type)}</span>` : ''}
                            </p>
                            <p class="text-sm text-base-content/60 truncate">@${user.username}</p>
                        </a>
                    </div>

                    <div class="shrink-0 flex gap-2">
                        ${renderActionButton(user)}
                        ${user.is_friend ? `
                            <a href="/messages/${user.username}"
                               class="btn btn-sm btn-outline gap-2">
                                <i data-lucide="message-square" class="w-4 h-4"></i>
                                Message
                            </a>
                        ` : ''}
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
            // fetchUsers('');
        }
    });

});
