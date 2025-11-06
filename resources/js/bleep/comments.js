document.addEventListener('DOMContentLoaded', function() {
    let currentOpenBleepId = null;
    let currentBleepElement = null;

    const floatingModal = document.getElementById('floating-comments-modal');
    const floatingContent = document.getElementById('floating-comments-scroll');
    const floatingForm = document.getElementById('floating-comment-form');
    const floatingTextarea = floatingForm?.querySelector('textarea[name="message"]');
    const anonymousToggle = floatingForm?.querySelector('#comment-anonymous-toggle');
    const anonymousHelper = floatingForm?.querySelector('#comment-anonymous-helper');
    const overlay = document.getElementById('comments-overlay');
    const closeButton = document.getElementById('close-comments-btn');
    const viewerTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';

    // Auto-grow textarea
    function autoGrow(element) {
        if (!element) return;

        const minHeight = parseInt(element.dataset.minHeight ?? 32, 10);
        element.style.height = 'auto';
        element.style.height = `${element.scrollHeight}px`;

        const computedMax = window.getComputedStyle(element).maxHeight;
        const maxHeight = computedMax && computedMax !== 'none' ? parseInt(computedMax, 10) : null;

        if (maxHeight && element.scrollHeight > maxHeight) {
            element.style.height = `${maxHeight}px`;
            element.style.overflowY = 'auto';
        } else {
            element.style.overflowY = 'hidden';
        }

        if (parseInt(element.style.height, 10) < minHeight) {
            element.style.height = `${minHeight}px`;
        }
    }

    // Event listeners
    document.querySelectorAll('.comment-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const bleepId = button.dataset.bleepId;
            const bleepElement = button.closest('article');

            if (currentOpenBleepId === bleepId) {
                closeComments();
            } else {
                openComments(bleepId, bleepElement);
            }
        });
    });

    closeButton?.addEventListener('click', closeComments);
    overlay?.addEventListener('click', closeComments);

    if (floatingTextarea) {
        autoGrow(floatingTextarea);
        floatingTextarea.addEventListener('input', () => autoGrow(floatingTextarea));
    }

    floatingForm?.addEventListener('submit', handleFloatingFormSubmit);

    // Open comments modal
    function openComments(bleepId, bleepElement) {
        if (!floatingModal || !floatingContent || !overlay) return;

        overlay.classList.remove('hidden');
        floatingModal.classList.remove('hidden');

        const isDesktop = window.matchMedia('(min-width: 1024px)').matches;

        if (isDesktop) {
            floatingModal.style.width = '28rem';
            floatingModal.style.height = '85vh';
            floatingModal.style.maxWidth = '32rem';
            floatingModal.style.right = '1.5rem';
            floatingModal.style.left = 'auto';
            floatingModal.style.top = '50%';
            floatingModal.style.transform = 'translateY(-50%)';
            floatingModal.style.bottom = 'auto';
        } else {
            floatingModal.style.width = 'calc(100vw - 1.5rem)';
            floatingModal.style.height = '85vh';
            floatingModal.style.maxWidth = '32rem';
            floatingModal.style.left = '50%';
            floatingModal.style.top = '50%';
            floatingModal.style.transform = 'translate(-50%, -50%)';
            floatingModal.style.right = 'auto';
            floatingModal.style.bottom = 'auto';
        }

        // Show loading state
        floatingContent.innerHTML = `
            <div class="flex justify-center items-center py-10">
                <span class="loading loading-spinner loading-md"></span>
            </div>
        `;

        if (floatingForm) {
            floatingForm.dataset.bleepId = bleepId;
        }

        if (floatingTextarea) {
            floatingTextarea.value = '';
            autoGrow(floatingTextarea);
        }
        if (anonymousToggle) {
            anonymousToggle.checked = false;
            anonymousToggle.dispatchEvent(new Event('change'));
        }

        currentBleepElement = bleepElement;
        document.querySelectorAll('article').forEach(article => {
            article.classList.remove('ring-2', 'ring-primary', 'ring-opacity-30');
        });
        bleepElement?.classList.add('ring-2', 'ring-primary', 'ring-opacity-30');

        currentOpenBleepId = bleepId;

        loadComments(bleepId);

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    // Position modal on desktop near the bleep
    function positionModal(bleepElement) {
        if (!bleepElement) return;

        const isDesktop = window.matchMedia('(min-width: 1024px)').matches;
        if (!isDesktop) return;

        const bleepRect = bleepElement.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        const modalHeight = floatingModal.offsetHeight;

        let top = bleepRect.top + window.scrollY;

        if (top + modalHeight > window.scrollY + viewportHeight) {
            top = window.scrollY + viewportHeight - modalHeight - 20;
        }
        if (top < window.scrollY + 20) {
            top = window.scrollY + 20;
        }

        floatingModal.style.top = `${top}px`;
        floatingModal.style.transform = 'translateY(0)';
    }

    function handleScroll() {
        if (!floatingModal || floatingModal.classList.contains('hidden')) return;

        const isDesktop = window.matchMedia('(min-width: 1024px)').matches;
        if (isDesktop && currentBleepElement) {
            positionModal(currentBleepElement);
        }
    }

    function handleResize() {
        const isDesktop = window.matchMedia('(min-width: 1024px)').matches;

        if (!isDesktop && !floatingModal.classList.contains('hidden')) {
            openComments(currentOpenBleepId, currentBleepElement);
        }
    }

    // Close comments modal
    function closeComments() {
        if (!floatingModal || !overlay) return;

        floatingModal.classList.add('hidden');
        overlay.classList.add('hidden');

        document.querySelectorAll('article').forEach(article => {
            article.classList.remove('ring-2', 'ring-primary', 'ring-opacity-30');
        });

        window.removeEventListener('scroll', handleScroll);
        window.removeEventListener('resize', handleResize);

        currentOpenBleepId = null;
        currentBleepElement = null;
    }

    // Load and render comments
    async function loadComments(bleepId) {
        if (!floatingContent) return;

        try {
            const response = await fetch(`/bleeps/${bleepId}/comments`);
            const data = await response.json();

            if (!Array.isArray(data.comments) || data.comments.length === 0) {
                floatingContent.innerHTML = renderEmptyState();
            } else {
                floatingContent.innerHTML = data.comments
                    .map(comment => renderCommentHTML(comment))
                    .join('');
            }

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        } catch (error) {
            console.error('Error loading comments:', error);
            floatingContent.innerHTML = renderErrorState();
        }
    }

    function renderEmptyState() {
        return `
            <div class="flex flex-col items-center justify-center py-10 text-base-content/60">
                <i data-lucide="message-circle-off" class="w-8 h-8 mb-3"></i>
                <p class="text-sm font-semibold">No comments yet</p>
                <p class="text-xs">Be the first to share your thoughts.</p>
            </div>
        `;
    }

    function renderErrorState() {
        return `
            <div class="flex flex-col items-center justify-center py-10 text-error">
                <i data-lucide="alert-triangle" class="w-8 h-8 mb-3"></i>
                <p class="text-sm font-semibold">Unable to load comments.</p>
                <p class="text-xs text-base-content/70">Please try again shortly.</p>
            </div>
        `;
    }

    // Render comment HTML (inline styling from Blade template)
    function renderCommentHTML(comment) {
        const user = comment.user || {};
        const isAnonymous = Boolean(comment.is_anonymous);
        const displayName = isAnonymous ? 'Anonymous' : (user.dname || 'Anonymous');
        const username = escapeHtml(user.username || '');
        const usernameLine = !isAnonymous && username
            ? `<span class="text-xs text-base-content/50 truncate">@${username}</span>`
            : '<span class="text-xs text-base-content/50 truncate">@anonymous</span>';
        const timezone = !isAnonymous && user.timezone ? user.timezone : null;
        const timestampISO = comment.created_at_iso || comment.created_at || '';
        const email = !isAnonymous && user.email ? escapeHtml(user.email) : null;

        const localTime = timestampISO ? new Date(timestampISO) : null;
        const viewerDateTime = localTime ? formatDateTimeInTimezone(localTime, viewerTimezone) : '—';
        const timezoneTooltip = isAnonymous
            ? 'Posting time hidden for anonymous users'
            : (localTime && timezone ? `${formatDateTimeTooltip(localTime, timezone)} (${formatTimezoneLabel(timezone)})` : '');
        const diffTimestamp = comment.diffTimestamp || (localTime ? timeAgo(localTime) : '');

        const avatarHtml = isAnonymous
            ? `
                <div class="size-10 rounded-full bg-base-300 flex items-center justify-center shrink-0">
                    <i data-lucide="user" class="w-5 h-5 text-base-content"></i>
                </div>
            `
            : `
                <div class="size-10 rounded-full shrink-0 overflow-hidden">
                    <img src="https://avatars.laravel.cloud/${encodeURIComponent(email ?? '')}" alt="${displayName}'s avatar" class="w-full h-full object-cover" />
                </div>
            `;

        return `
            <div class="flex gap-3 p-4 rounded-lg bg-base-100 shadow-md hover:shadow-lg transition-shadow duration-200">
                ${avatarHtml}
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex flex-col min-w-0">
                            <span class="font-semibold text-sm truncate">${displayName}</span>
                            ${usernameLine}
                        </div>
                        <div class="flex flex-col text-right shrink-0 text-xs text-base-content/50 leading-tight whitespace-nowrap">
                            <span class="font-medium text-base-content/80" title="${escapeHtml(timezoneTooltip)}">${viewerDateTime}</span>
                            <span>${diffTimestamp}</span>
                        </div>
                    </div>

                    <p class="text-sm mb-1 mt-2.5 break-words leading-snug text-base-content/90">
                        ${escapeHtml(comment.message)}
                    </p>
                </div>
            </div>
        `;
    }

    function formatDateTimeInTimezone(date, timeZone) {
        if (!date || Number.isNaN(date.getTime())) return '';
        const datePart = new Intl.DateTimeFormat('en-US', {
            timeZone,
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        }).format(date);
        const timePart = new Intl.DateTimeFormat('en-US', {
            timeZone,
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit'
        }).format(date);
        return `${datePart} | ${timePart}`;
    }

    function formatDateTimeTooltip(date, timeZone) {
        if (!date || Number.isNaN(date.getTime())) return '';
        return new Intl.DateTimeFormat('en-US', {
            timeZone,
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit'
        }).format(date);
    }

    function formatTimezoneLabel(timeZone) {
        if (!timeZone) return 'UTC';
        return timeZone.replace(/_/g, ' ');
    }

    // Utility to generate "x minutes/hours/days ago"
    function timeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        let interval = Math.floor(seconds / 31536000);
        if (interval >= 1) return interval + 'y ago';
        interval = Math.floor(seconds / 2592000);
        if (interval >= 1) return interval + 'mo ago';
        interval = Math.floor(seconds / 86400);
        if (interval >= 1) return interval + 'd ago';
        interval = Math.floor(seconds / 3600);
        if (interval >= 1) return interval + 'h ago';
        interval = Math.floor(seconds / 60);
        if (interval >= 1) return interval + 'm ago';
        return 'just now';
    }

    // Fetch partial HTML (optional, for cleaner approach)
    async function fetchPartial(partialName) {
        try {
            const response = await fetch(`/partials/${partialName}`);
            return await response.text();
        } catch (error) {
            console.error(`Error fetching partial ${partialName}:`, error);
            return '';
        }
    }

    // Handle form submission
    async function handleFloatingFormSubmit(e) {
        e.preventDefault();

        if (!floatingForm || !floatingTextarea) return;

        const bleepId = floatingForm.dataset.bleepId;
        if (!bleepId) return;

        const message = floatingTextarea.value.trim();
        const isAnonymous = anonymousToggle?.checked ?? false;
        if (!message) return;

        const submitBtn = floatingForm.querySelector('button[type="submit"]');
        submitBtn.disabled = true;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                document.querySelector('input[name="_token"]')?.value;

            const response = await fetch(`/bleeps/${bleepId}/comments`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ message, is_anonymous: isAnonymous })
            });

            if (response.ok) {
                floatingTextarea.value = '';
                autoGrow(floatingTextarea);
                if (anonymousToggle) {
                    anonymousToggle.checked = false;
                    anonymousToggle.dispatchEvent(new Event('change'));
                }
                loadComments(bleepId);
                updateCommentCount(bleepId);
            }
        } catch (error) {
            console.error('Error posting comment:', error);
        } finally {
            submitBtn.disabled = false;
        }
    }

    // Update comment count on bleep button
    async function updateCommentCount(bleepId) {
        try {
            const button = document.querySelector(`.comment-btn[data-bleep-id="${bleepId}"]`);
            const response = await fetch(`/bleeps/${bleepId}/comments/count`);
            const data = await response.json();

            if (button && data?.count !== undefined) {
                const countSpan = button.querySelector('.comment-count');
                const labelSpan = button.querySelector('.comment-label');
                if (countSpan) countSpan.textContent = data.count;
                if (labelSpan) labelSpan.textContent = data.count === 1 ? 'Comment' : 'Comments';
            }
        } catch (error) {
            console.error('Error updating comment count:', error);
        }
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    // Close modal on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && currentOpenBleepId) {
            closeComments();
        }
    });

    if (anonymousToggle) {
        const toggleIndicator = document.getElementById('toggle-indicator');
        const userEmail = '{{ auth()->user()->email }}';

        const updateToggleUI = () => {
            if (anonymousToggle.checked) {
                // Show black dot with icon
                toggleIndicator.style.backgroundImage = 'none';
                toggleIndicator.style.backgroundColor = '#1f2937';
                toggleIndicator.innerHTML = `
                    <div class="w-full h-full flex items-center justify-center">
                        <i data-lucide="hat-glasses" class="w-3 h-3 text-white"></i>
                    </div>
                `;
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            } else {
                // Show user avatar
                toggleIndicator.innerHTML = '';
                toggleIndicator.style.backgroundColor = 'transparent';
                toggleIndicator.style.backgroundImage = `url('https://avatars.laravel.cloud/${encodeURIComponent(userEmail)}')`;
            }
        };

        anonymousToggle.addEventListener('change', updateToggleUI);
        updateToggleUI();
    }

    window.autoGrow = autoGrow;
});
