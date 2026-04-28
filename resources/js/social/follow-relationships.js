document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('follow-relationships-modal');
    if (!modal) return;

    const overlay = document.getElementById('follow-relationships-modal-overlay');
    const closeBtn = modal.querySelector('[data-relationship-modal-close]');
    const clearBtn = modal.querySelector('[data-relationship-modal-clear]');
    const titleEl = modal.querySelector('[data-relationship-modal-title]');
    const searchInput = modal.querySelector('[data-relationship-modal-search]');
    const resultsEl = modal.querySelector('[data-relationship-modal-results]');
    const loadingEl = modal.querySelector('[data-relationship-modal-loading]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    let activeUsername = '';
    let activeType = 'followers';
    let searchTimer = null;
    let currentRequest = 0;

    function setOpen(isOpen) {
        modal.classList.toggle('hidden', !isOpen);
        modal.classList.toggle('flex', isOpen);
        document.body.classList.toggle('overflow-hidden', isOpen);
    }

    function setLoading(isLoading) {
        loadingEl?.classList.toggle('hidden', !isLoading);
        resultsEl?.classList.toggle('opacity-60', isLoading);
        resultsEl?.classList.toggle('pointer-events-none', isLoading);
    }

    function buildUrl(query = '') {
        const base = `/bleeper/${encodeURIComponent(activeUsername)}/relationships/${encodeURIComponent(activeType)}`;
        const params = new URLSearchParams();
        if (query) params.set('q', query);
        const queryString = params.toString();
        return queryString ? `${base}?${queryString}` : base;
    }

    async function loadUsers(query = '') {
        if (!activeUsername) return;

        const requestId = ++currentRequest;
        resultsEl.innerHTML = '';
        setLoading(true);

        try {
            const response = await fetch(buildUrl(query), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                },
            });

            const data = await response.json().catch(() => ({}));

            if (requestId !== currentRequest) return;

            if (!response.ok) {
                resultsEl.innerHTML = '<div class="text-center text-error py-8">Unable to load users.</div>';
                return;
            }

            resultsEl.innerHTML = data.html || '';
            titleEl.textContent = data.title || (activeType === 'followers' ? 'Followers' : 'Following');
            window.lucide?.createIcons?.();
        } catch (error) {
            if (requestId !== currentRequest) return;

            console.error('Relationship modal error:', error);
            resultsEl.innerHTML = '<div class="text-center text-error py-8">Unable to load users.</div>';
        } finally {
            if (requestId === currentRequest) {
                setLoading(false);
            }
        }
    }

    function openModal({ username, type, displayName }) {
        activeUsername = username;
        activeType = type;
        currentRequest += 1;
        searchInput.value = '';
        updateClearButton();
        resultsEl.innerHTML = '';
        titleEl.textContent = type === 'followers' ? 'Followers' : 'Following';
        setOpen(true);
        loadUsers('');
        searchInput.focus();
    }

    function closeModal() {
        activeUsername = '';
        activeType = 'followers';
        clearTimeout(searchTimer);
        currentRequest += 1;
        searchInput.value = '';
        updateClearButton();
        resultsEl.innerHTML = '';
        setOpen(false);
    }

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-relationship-trigger]');
        if (!trigger) return;

        const username = trigger.dataset.username;
        const type = trigger.dataset.relationshipTrigger;
        const displayName = trigger.dataset.displayName || username;

        if (!username || !type) return;

        openModal({ username, type, displayName });
    });

    overlay?.addEventListener('click', closeModal);
    closeBtn?.addEventListener('click', closeModal);

    function updateClearButton() {
        const hasValue = searchInput.value.trim().length > 0;
        clearBtn?.classList.toggle('hidden', !hasValue);
    }

    clearBtn?.addEventListener('click', () => {
        searchInput.value = '';
        updateClearButton();
        clearTimeout(searchTimer);
        loadUsers('');
    });

    searchInput?.addEventListener('input', (event) => {
        updateClearButton();
        clearTimeout(searchTimer);
        const query = event.target.value.trim();

        searchTimer = setTimeout(() => {
            loadUsers(query);
        }, 1000);
    });

    searchInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (searchInput.value.trim().length > 0) {
                searchInput.value = '';
                updateClearButton();
                clearTimeout(searchTimer);
                loadUsers('');
                event.preventDefault();
            } else if (!modal.classList.contains('hidden')) {
                closeModal();
                event.preventDefault();
            }
        }
    });
});

