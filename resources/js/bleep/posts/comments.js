document.addEventListener('DOMContentLoaded', function() {
    let currentOpenBleepId = null;
    let currentBleepElement = null;

    const floatingModal = document.getElementById('floating-comments-modal');
    const floatingContent = document.getElementById('floating-comments-scroll');
    const floatingForm = document.getElementById('floating-comment-form');
    const floatingTextarea = floatingForm?.querySelector('textarea[name="message"]');
    // find toggle inside floating form OR on the page (single post layout)
    const anonymousToggle = (floatingForm && floatingForm.querySelector('#comment-anonymous-toggle')) || document.querySelector('#comment-anonymous-toggle');
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
            // GET the comments list (route: /bleeps/comments/{bleep}/comments)
            const response = await fetch(`/bleeps/comments/${bleepId}/comments`, {
                headers: { 'Accept': 'application/json' }
            });

            if (response.status === 401) {
                // guest: not authorized to fetch comments (if your routes remained protected)
                floatingContent.innerHTML = `
                    <div class="p-4 text-center text-sm text-base-content/70">
                        <p>Please <a href="/login" class="link link-primary">login</a> to view comments.</p>
                    </div>
                `;
                return;
            }

            if (!response.ok) {
                throw new Error('Failed to load comments');
            }

            const data = await response.json();

            if (!Array.isArray(data.comments) || data.comments.length === 0) {
                floatingContent.innerHTML = renderEmptyState();
            } else {
                floatingContent.innerHTML = data.comments
                    .map(comment => renderCommentHTML(comment))
                    .join('');
            }

            if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
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
        const displayName = comment.display_name || (isAnonymous ? 'Anonymous' : (user.dname || 'Anonymous'));
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
                    <i data-lucide="hat-glasses" class="w-5 h-5 text-base-content"></i>
                </div>
            `
            : `
                <div class="size-10 rounded-full shrink-0 overflow-hidden">
                    <img src="https://avatars.laravel.cloud/${encodeURIComponent(email ?? '')}" alt="${displayName}'s avatar" class="w-full h-full object-cover" />
                </div>
            `;

        // Determine if current user owns this comment (you'll need to pass this from backend)
        // For now, we'll add data attributes and let the click handlers check permissions
        const actionsHtml = `
            <div class="dropdown dropdown-end">
                <button tabindex="0" class="btn btn-ghost btn-xs btn-circle hover:bg-base-300" title="More options">
                    <i data-lucide="more-vertical" class="w-4 h-4"></i>
                </button>
                <ul tabindex="0" class="dropdown-content z-10 shadow-lg bg-base-100 rounded-xl w-48 border border-base-200 p-2 space-y-1">
                    <li>
                        <button type="button"
                            class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 rounded-md hover:bg-base-200 transition edit-comment-btn"
                            data-comment-id="${comment.id}"
                            data-comment-message="${escapeHtml(comment.message)}"
                            data-is-anonymous="${comment.is_anonymous ? '1' : '0'}"
                            title="Edit this comment">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                            <span>Edit</span>
                        </button>
                    </li>

                    <li>
                        <button type="button"
                            class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-red-600 rounded-md hover:bg-red-50 transition delete-comment-btn"
                            data-comment-id="${comment.id}"
                            title="Delete this comment">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                            <span>Delete</span>
                        </button>
                    </li>

                    <li>
                        <button type="button"
                            class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-orange-500 rounded-md hover:bg-orange-50 transition report-comment-btn"
                            data-comment-id="${comment.id}"
                            title="Report this comment">
                            <i data-lucide="flag" class="w-4 h-4"></i>
                            <span>Report</span>
                        </button>
                    </li>
                </ul>
            </div>
        `;

        return `
            <div class="flex gap-3 p-4 rounded-lg bg-base-100 shadow-md hover:shadow-lg transition-shadow duration-200" data-comment-id="${comment.id}">
                ${avatarHtml}
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex flex-col min-w-0">
                            <span class="font-semibold text-sm truncate">${displayName}</span>
                            ${usernameLine}
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <div class="flex flex-col text-right shrink-0 text-xs text-base-content/50 leading-tight whitespace-nowrap">
                                <span title="${escapeHtml(timezoneTooltip)}">${viewerDateTime}</span>
                                <span>${diffTimestamp}</span>
                            </div>
                            ${actionsHtml}
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

            // Post comment (use route: /bleeps/comments/{bleep}/post)
            const response = await fetch(`/bleeps/comments/${bleepId}/post`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ message, is_anonymous: isAnonymous })
            });

            if (response.status === 401) {
                // If guest somehow tries to post: prompt login
                window.location.href = '/login';
                return;
            }

            if (response.ok) {
                floatingTextarea.value = '';
                autoGrow(floatingTextarea);
                if (anonymousToggle) {
                    anonymousToggle.checked = false;
                    anonymousToggle.dispatchEvent(new Event('change'));
                }
                loadComments(bleepId);
                updateCommentCount(bleepId);
            } else {
                console.error('Error posting comment:', await response.text());
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
            // Update count (use route: /bleeps/comments/{bleep}/count)
            const response = await fetch(`/bleeps/comments/${bleepId}/count`);
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
        const userEmail = toggleIndicator?.dataset.userEmail ?? '';

        const updateToggleUI = () => {
            if (!toggleIndicator) return;

            if (anonymousToggle.checked) {
                // Show anonymous icon
                toggleIndicator.style.backgroundImage = 'none';
                toggleIndicator.style.backgroundColor = '#1f2937';
                toggleIndicator.innerHTML = `
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;">
                        <i data-lucide="hat-glasses" class="w-4 h-4 text-white"></i>
                    </div>
                `;
                if (window.lucide) lucide.createIcons();
            } else {
                // Show user avatar
                toggleIndicator.innerHTML = '';
                toggleIndicator.style.backgroundColor = 'transparent';
                if (userEmail) {
                    toggleIndicator.style.backgroundImage =
                        `url('https://avatars.laravel.cloud/${encodeURIComponent(userEmail)}')`;
                    toggleIndicator.style.backgroundSize = 'cover';
                    toggleIndicator.style.backgroundPosition = 'center';
                } else {
                    toggleIndicator.style.backgroundImage = 'none';
                }
            }
        };

        anonymousToggle.addEventListener('change', updateToggleUI);
        updateToggleUI();
    }

    window.autoGrow = autoGrow;

    // Edit and Report modal handlers
    const editModal = document.getElementById('edit-comment-modal');
    const reportModal = document.getElementById('report-comment-modal');

    // Report Comment Button Click
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.report-comment-btn');
        if (!btn) return;

        const commentId = btn.dataset.commentId;
        openReportCommentModal(commentId);
    });

    // Delete Comment Button Click
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.delete-comment-btn');
        if (!btn) return;

        const commentId = btn.dataset.commentId;

        if (confirm('Are you sure you want to delete this comment?')) {
            deleteComment(commentId);
        }
    });

    // Open Report Modal
    function openReportCommentModal(commentId) {
        const form = reportModal?.querySelector('form');

        if (!reportModal || !form) return;

        form.action = `/bleeps/comments/${commentId}/report`;
        form.dataset.commentId = commentId;

        // Override default form submission
        form.onsubmit = async (e) => await handleReportCommentSubmit(e, commentId);

        reportModal.classList.remove('hidden');
        reportModal.classList.add('flex');
    }

    // Delete Comment Function
    async function deleteComment(commentId) {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            const response = await fetch(`/bleeps/comments/${commentId}/delete`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            if (response.ok) {
                // Remove comment from DOM
                const commentEl = document.querySelector(`[data-comment-id="${commentId}"]`).closest('.p-4');
                if (commentEl) {
                    commentEl.style.opacity = '0';
                    setTimeout(() => commentEl.remove(), 300);
                }
                showToast('Comment deleted successfully', 'success');
            } else {
                const error = await response.json();
                showToast(error.message || 'Failed to delete comment', 'error');
            }
        } catch (error) {
            console.error('Error deleting comment:', error);
            showToast('An error occurred', 'error');
        }
    }

    // Handle Report Comment Submit
    async function handleReportCommentSubmit(e, commentId) {
        e.preventDefault();

        const form = reportModal?.querySelector('form');
        const submitBtn = form?.querySelector('button[type="submit"]');

        if (!form) return;

        const reason = form.querySelector('input[name="reason"]:checked')?.value;
        const description = form.querySelector('textarea[name="description"]')?.value || '';

        if (!reason) {
            showToast('Please select a reason', 'error');
            return;
        }

        submitBtn.disabled = true;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            const response = await fetch(`/bleeps/comments/${commentId}/report`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    reason,
                    description,
                }),
            });

            if (response.ok) {
                reportModal.classList.add('hidden');
                reportModal.classList.remove('flex');
                showToast('Comment reported successfully', 'success');
            } else {
                const error = await response.json();
                showToast(error.message || 'Failed to report comment', 'error');
            }
        } catch (error) {
            console.error('Error reporting comment:', error);
            showToast('An error occurred', 'error');
        } finally {
            submitBtn.disabled = false;
        }
    }

    // Simple toast notification
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.textContent = message;
        const bgColor = type === 'error' ? '#DC2626' : '#111827';
        Object.assign(toast.style, {
            position: 'fixed',
            right: '16px',
            bottom: '16px',
            padding: '8px 12px',
            background: bgColor,
            color: '#fff',
            borderRadius: '8px',
            zIndex: 9999,
            fontSize: '13px',
            boxShadow: '0 10px 30px rgba(17,24,39,0.20)',
            transition: 'opacity 0.25s ease',
        });
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 250);
        }, 1500);
    }

    // Close modals
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('close-edit-comment-modal')) {
            editModal?.classList.add('hidden');
            editModal?.classList.remove('flex');
        }
        if (e.target.classList.contains('close-report-comment-modal')) {
            reportModal?.classList.add('hidden');
            reportModal?.classList.remove('flex');
        }
    });

    // Escape key to close modals
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            editModal?.classList.add('hidden');
            editModal?.classList.remove('flex');
            reportModal?.classList.add('hidden');
            reportModal?.classList.remove('flex');
        }
    });

    // Inline Edit Comment Handler (for both post page and floating modal)
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.edit-comment-btn');
        if (!btn) return;

        const commentId = btn.dataset.commentId;
        const message = btn.dataset.commentMessage;
        const isAnonymous = btn.dataset.isAnonymous === '1';

        // Find the comment container - works for both inline and modal
        const commentContainer = document.querySelector(`[data-comment-id="${commentId}"]`);
        if (!commentContainer) return;

        // Check if we're already editing this comment
        if (commentContainer.querySelector('.edit-inline-edit-ui')) return;

        // Convert to edit mode
        enableInlineEdit(commentContainer, commentId, message, isAnonymous);
    });

    function enableInlineEdit(commentContainer, commentId, originalMessage, isAnonymous) {
        // Find the message paragraph
        const messagePara = commentContainer.querySelector('p[class*="text-sm"]');
        if (!messagePara) return;

        // Get user email from the page (stored in hidden meta tag or data attribute)
        const userEmail = document.querySelector('meta[name="user-email"]')?.content ||
                          document.querySelector('[data-user-email]')?.dataset.userEmail || '';

        // Create edit UI
        const editUI = document.createElement('div');
        editUI.className = 'edit-inline-edit-ui space-y-3 py-2';
        editUI.innerHTML = `
            <textarea
                class="textarea textarea-bordered w-full resize-none text-sm"
                maxlength="255"
                rows="3"
                placeholder="Edit your comment...">${escapeHtml(originalMessage)}</textarea>

            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <label class="relative inline-flex cursor-pointer">
                        <input type="checkbox" class="edit-inline-anon-toggle peer sr-only" ${isAnonymous ? 'checked' : ''}>
                        <div class="w-14 h-8 bg-base-100 peer-checked:bg-base-300 rounded-full peer-focus:ring-2 peer-focus:ring-primary transition-all border border-gray-300"></div>
                        <div class="edit-inline-anon-indicator absolute top-1 left-1 size-6 rounded-full transition-all duration-300 peer-checked:left-6 bg-cover bg-center flex items-center justify-center"
                            data-user-email="${userEmail}"
                            style="${isAnonymous ? 'background-image: none; background-color: #1f2937;' : `background-image: url('https://avatars.laravel.cloud/${encodeURIComponent(userEmail)}');`}">
                            ${isAnonymous ? '<i data-lucide="hat-glasses" class="w-3 h-3 text-white"></i>' : ''}
                        </div>
                    </label>
                    <span class="text-xs text-base-content/60">Anonymous</span>
                </div>

                <div class="flex gap-2">
                    <button type="button" class="btn btn-ghost btn-sm cancel-inline-edit">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm save-inline-edit" data-comment-id="${commentId}">Update</button>
                </div>
            </div>
        `;

        // Hide original message and show edit UI
        messagePara.classList.add('hidden');
        messagePara.insertAdjacentElement('afterend', editUI);

        // Auto-grow textarea
        const textarea = editUI.querySelector('textarea');
        if (textarea && window.autoGrow) {
            window.autoGrow(textarea);
            textarea.addEventListener('input', () => window.autoGrow(textarea));
        }

        // Handle toggle UI
        const toggle = editUI.querySelector('.edit-inline-anon-toggle');
        const indicator = editUI.querySelector('.edit-inline-anon-indicator');

        if (toggle) {
            toggle.addEventListener('change', () => {
                if (toggle.checked) {
                    // Show anonymous icon
                    indicator.style.backgroundImage = 'none';
                    indicator.style.backgroundColor = '#1f2937';
                    indicator.innerHTML = '<i data-lucide="hat-glasses" class="w-3 h-3 text-white"></i>';
                    if (window.lucide) window.lucide.createIcons();
                } else {
                    // Show user avatar
                    indicator.innerHTML = '';
                    indicator.style.backgroundColor = 'transparent';
                    if (userEmail) {
                        indicator.style.backgroundImage = `url('https://avatars.laravel.cloud/${encodeURIComponent(userEmail)}')`;
                        indicator.style.backgroundSize = 'cover';
                        indicator.style.backgroundPosition = 'center';
                    } else {
                        indicator.style.backgroundImage = 'none';
                    }
                }
            });
        }

        // Cancel button
        const cancelBtn = editUI.querySelector('.cancel-inline-edit');
        cancelBtn?.addEventListener('click', () => {
            editUI.remove();
            messagePara.classList.remove('hidden');
        });

        // Save button
        const saveBtn = editUI.querySelector('.save-inline-edit');
        saveBtn?.addEventListener('click', async () => {
            const newMessage = textarea.value.trim();
            const newIsAnonymous = toggle?.checked ?? false;

            if (!newMessage) {
                showToast('Message cannot be empty', 'error');
                return;
            }

            saveBtn.disabled = true;

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

                const response = await fetch(`/bleeps/comments/${commentId}/update`, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        message: newMessage,
                        is_anonymous: newIsAnonymous,
                    }),
                });

                if (response.ok) {
                    // Update the message paragraph
                    messagePara.textContent = newMessage;
                    messagePara.classList.remove('hidden');

                    // Update avatar based on anonymity status
                    const avatarContainer = commentContainer.querySelector('.avatar');
                    if (avatarContainer) {
                        if (newIsAnonymous) {
                            // Show anonymous avatar
                            avatarContainer.innerHTML = `
                                <div class="size-10 rounded-full bg-base-300 flex items-center justify-center">
                                    <i data-lucide="hat-glasses" class="w-4 h-4 text-base-content/80"></i>
                                </div>
                            `;
                        } else {
                            // Show user avatar
                            const userEmail = document.querySelector('meta[name="user-email"]')?.content || '';
                            avatarContainer.innerHTML = `
                                <div class="size-10 rounded-full overflow-hidden">
                                    <img src="https://avatars.laravel.cloud/${encodeURIComponent(userEmail)}" alt="User avatar">
                                </div>
                            `;
                        }
                        // Reinitialize lucide icons if avatar changed to anonymous
                        if (window.lucide) window.lucide.createIcons();
                    }

                    // Update the edit button data attributes for next edit
                    const editBtn = commentContainer.querySelector('.edit-comment-btn');
                    if (editBtn) {
                        editBtn.dataset.commentMessage = newMessage;
                        editBtn.dataset.isAnonymous = newIsAnonymous ? '1' : '0';
                    }

                    // Update display name if anonymity changed
                    const displayNameEl = commentContainer.querySelector('span.font-semibold');
                    const usernameEl = commentContainer.querySelector('span.text-gray-500');
                    if (displayNameEl && usernameEl) {
                        // You may need to get the bleep data to recalculate anonymous name
                        if (newIsAnonymous) {
                            displayNameEl.textContent = 'Anonymous User'; // Or calculate properly
                            usernameEl.textContent = '@anonymous';
                        } else {
                            // Fetch from data attribute or recalculate
                            const userName = editBtn?.dataset.userName || 'Unknown';
                            const userUsername = editBtn?.dataset.userUsername || 'unknown';
                            displayNameEl.textContent = userName;
                            usernameEl.textContent = '@' + userUsername;
                        }
                    }

                    // Remove edit UI
                    editUI.remove();
                    showToast('Comment updated successfully', 'success');

                    // Reload comments in floating modal if open
                    if (currentOpenBleepId) {
                        loadComments(currentOpenBleepId);
                    }
                } else {
                    const error = await response.json();
                    showToast(error.message || 'Failed to update comment', 'error');
                }
            } catch (error) {
                console.error('Error updating comment:', error);
                showToast('An error occurred', 'error');
            } finally {
                saveBtn.disabled = false;
            }
        });

        // Focus textarea
        textarea.focus();

        // Reinitialize lucide icons
        if (window.lucide) window.lucide.createIcons();
    }
});
