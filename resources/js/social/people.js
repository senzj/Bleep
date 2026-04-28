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

                const data = await response.json();
                if (suggestionsContainer) {
                    suggestionsContainer.innerHTML = data.html || '';
                }
                if (noResultsMessage) noResultsMessage.classList.add('hidden');
                window.lucide?.createIcons?.();
            } catch (error) {
                console.error('Search error:', error);
                if (suggestionsContainer) suggestionsContainer.innerHTML = '<div class="text-center text-error p-4">Error loading users.</div>';
            }
        }

        // Handle search input with debounce
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                const query = e.target.value.trim();

                searchTimeout = setTimeout(() => {
                    fetchUsers(query);
                }, 300);
            });
        }

    });

});
