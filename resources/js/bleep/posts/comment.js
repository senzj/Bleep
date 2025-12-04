import './comment/replies';
import './comment/likes';

document.addEventListener('DOMContentLoaded', function() {
    const baseUrl = document.querySelector('meta[name="base_url"]')?.content || '';

    let currentOpenBleepId = null;
    let currentBleepElement = null;

    const floatingModal = document.getElementById('floating-comments-modal');
    const floatingContent = document.getElementById('floating-comments-scroll');
    const floatingForm = document.getElementById('floating-comment-form');
    const floatingTextarea = floatingForm?.querySelector('textarea[name="message"]');
    const anonymousToggle = (floatingForm && floatingForm.querySelector('#comment-anonymous-toggle')) || document.querySelector('#comment-anonymous-toggle');
    const overlay = document.getElementById('comments-overlay');
    const closeButton = document.getElementById('close-comments-btn');
    const viewerTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';

    let isLoadingComments = false;
    let currentCommentsPage = 1;
    let hasMoreComments = true;

    // Scroll-to-close state
    let scrollStartX = 0;
    let scrollStartY = 0;
    let hasScrolledInModal = false;

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

    // Define handleCommentsScroll BEFORE using it
    function handleCommentsScroll() {
        if (!floatingContent || isLoadingComments || !hasMoreComments || !currentOpenBleepId) return;

        const scrollTop = floatingContent.scrollTop;
        const scrollHeight = floatingContent.scrollHeight;
        const clientHeight = floatingContent.clientHeight;

        // Load more when user scrolls to bottom (with 200px threshold)
        if (scrollTop + clientHeight >= scrollHeight - 200) {
            loadMoreComments(currentOpenBleepId);
        }
    }

    // Event listeners - Use event delegation for dynamically loaded bleeps
    document.body.addEventListener('click', (e) => {
        const button = e.target.closest('.comment-btn');
        if (!button) return;

        e.preventDefault();
        const bleepId = button.dataset.bleepId;
        const bleepElement = button.closest('article');

        if (currentOpenBleepId === bleepId) {
            closeComments();
        } else {
            openComments(bleepId, bleepElement);
        }
    });

    closeButton?.addEventListener('click', closeComments);
    overlay?.addEventListener('click', closeComments);

    if (floatingTextarea) {
        autoGrow(floatingTextarea);
        floatingTextarea.addEventListener('input', () => autoGrow(floatingTextarea));
    }

    const mediaInput = document.getElementById('comment-media-input');
    const mediaTrigger = document.getElementById('comment-media-trigger');
    const mediaPreview = document.getElementById('comment-media-preview');
    const mediaClear = document.getElementById('comment-media-clear');

    mediaTrigger?.addEventListener('click', () => mediaInput?.click());
    mediaInput?.addEventListener('change', () => {
        if (!mediaInput.files.length) {
            clearMediaState();
            return;
        }

        const file = mediaInput.files[0];

        const previewShell = mediaPreview?.querySelector('figure');
        if (!previewShell) return;

        if (previewShell.dataset.previewUrl) {
            URL.revokeObjectURL(previewShell.dataset.previewUrl);
            delete previewShell.dataset.previewUrl;
        }

        const url = URL.createObjectURL(file);
        previewShell.dataset.previewUrl = url;

        const lower = file.name.toLowerCase();
        let markup = '';

        if (/\.(mp4|mov|webm)$/.test(lower)) {
            markup = `<video controls class="w-full max-h-40 object-contain bg-black"><source src="${url}"></video>`;
        } else if (/\.(mp3|wav|m4a)$/.test(lower)) {
            markup = `<audio controls class="w-full"><source src="${url}"></audio>`;
        } else {
            markup = `<img src="${url}" alt="Attachment preview" class="w-full h-auto object-contain">`;
        }

        previewShell.innerHTML = markup;
        mediaPreview?.classList.remove('hidden');
    });

    mediaClear?.addEventListener('click', (evt) => {
        evt.preventDefault();
        clearMediaState();
    });

    function clearMediaState() {
        if (mediaInput) mediaInput.value = '';
        const previewShell = mediaPreview?.querySelector('figure');
        if (previewShell) {
            if (previewShell.dataset.previewUrl) {
                URL.revokeObjectURL(previewShell.dataset.previewUrl);
                delete previewShell.dataset.previewUrl;
            }
            previewShell.innerHTML = '';
        }
        mediaPreview?.classList.add('hidden');
    }

    floatingForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!floatingTextarea) return;

        const targetCommentId = window.commentReplyState?.getReplyId();
        const endpoint = targetCommentId
            ? `/bleeps/comments/${targetCommentId}/replies`
            : `/bleeps/comments/${floatingForm.dataset.bleepId}/post`;

        const message = floatingTextarea.value.trim();
        const file = mediaInput?.files?.[0] ?? null;
        const isAnonymous = anonymousToggle?.checked ?? false;

        if (!message && !file) {
            showToast('Write something or attach media', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('is_anonymous', isAnonymous ? '1' : '0');
        if (message) formData.append('message', message);
        if (file) formData.append('media', file);

        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: formData,
        });

        if (!response.ok) {
            if (response.status === 401) {
                window.location.href = '/login';
            }
            return;
        }

        const data = await response.json();
        floatingTextarea.value = '';
        autoGrow(floatingTextarea);
        if (anonymousToggle) anonymousToggle.checked = false;
        clearMediaState();

        if (targetCommentId) {
            const container = document.querySelector(`.comment-replies-container[data-comment-id="${targetCommentId}"]`);
            if (container) {
                const parentDepth = parseInt(document.querySelector(`.comment-card[data-comment-id="${targetCommentId}"]`)?.dataset.commentDepth ?? '0', 10);
                container.dataset.depth = container.dataset.depth ?? (parentDepth + 1).toString();
                container.dataset.loaded = '1';
                container?.classList.remove('hidden');
                container?.querySelector('.replies-list')?.insertAdjacentHTML('afterbegin', data.html);
                const toggle = document.querySelector(`.comment-toggle-replies[data-comment-id="${targetCommentId}"]`);
                if (toggle) {
                    toggle.dataset.expanded = 'true';
                    toggle.querySelector('.replies-toggle-text').textContent = `View ${data.replies_count} ${data.replies_count === 1 ? 'reply' : 'replies'}`;
                    toggle.querySelector('.replies-toggle-icon')?.classList.add('rotate-180');
                }
                window.commentReplyState?.cancel();
            }
        } else {
            await loadComments(floatingForm.dataset.bleepId);
        }

        window.lucide?.createIcons();
    });

    // Handle floating form submit
    async function handleFloatingFormSubmit(e) {
        e.preventDefault();

        if (!floatingForm || !floatingTextarea) return;

        const bleepId = floatingForm.dataset.bleepId;
        const message = floatingTextarea.value.trim();
        const isAnonymous = anonymousToggle?.checked ?? false;

        if (!message) {
            showToast('Message cannot be empty', 'error');
            return;
        }

        if (!bleepId) {
            showToast('Invalid bleep', 'error');
            return;
        }

        const submitBtn = floatingForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            const originalHTML = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="loading loading-spinner loading-xs"></span>';
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            const response = await fetch(`/bleeps/comments/${bleepId}/post`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    message,
                    is_anonymous: isAnonymous,
                }),
            });

            if (response.ok) {
                // Clear form
                floatingTextarea.value = '';
                autoGrow(floatingTextarea);

                // Reset toggle
                if (anonymousToggle) {
                    anonymousToggle.checked = false;
                    anonymousToggle.dispatchEvent(new Event('change'));
                }

                // Reload comments
                await loadComments(bleepId);

                showToast('Comment posted successfully', 'success');
            } else {
                const error = await response.json();
                showToast(error.message || 'Failed to post comment', 'error');
            }
        } catch (error) {
            console.error('Error posting comment:', error);
            showToast('An error occurred', 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i data-lucide="send" class="w-5 h-5"></i>';
                if (window.lucide) window.lucide.createIcons();
            }
        }
    }

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

        // Reset pagination state
        currentCommentsPage = 1;
        hasMoreComments = true;

        loadComments(bleepId);

        // Start monitoring bleep visibility
        startBleepVisibilityCheck();

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

        // Stop monitoring bleep visibility
        stopBleepVisibilityCheck();

        currentOpenBleepId = null;
        currentBleepElement = null;
    }

    // Add scroll listener for lazy loading comments
    if (floatingContent) {
        floatingContent.addEventListener('scroll', handleCommentsScroll);

        // Track initial touch/mouse position
        floatingContent.addEventListener('touchstart', (e) => {
            scrollStartX = e.touches[0].clientX;
            scrollStartY = e.touches[0].clientY;
            hasScrolledInModal = false;
        });

        floatingContent.addEventListener('mousedown', (e) => {
            scrollStartX = e.clientX;
            scrollStartY = e.clientY;
            hasScrolledInModal = false;
        });

        // Detect if user scrolled inside modal
        floatingContent.addEventListener('touchmove', (e) => {
            const deltaX = Math.abs(e.touches[0].clientX - scrollStartX);
            const deltaY = Math.abs(e.touches[0].clientY - scrollStartY);
            if (deltaY > 10 || deltaX > 10) {
                hasScrolledInModal = true;
            }
        });

        floatingContent.addEventListener('mousemove', (e) => {
            if (e.buttons === 1) { // Left mouse button is pressed
                const deltaX = Math.abs(e.clientX - scrollStartX);
                const deltaY = Math.abs(e.clientY - scrollStartY);
                if (deltaY > 10 || deltaX > 10) {
                    hasScrolledInModal = true;
                }
            }
        });
    }

    // Enhanced overlay click handler with scroll detection
    overlay?.addEventListener('click', (e) => {
        // Only close if user didn't scroll significantly inside modal
        if (!hasScrolledInModal) {
            closeComments();
        }
        hasScrolledInModal = false;
    });

    // Add touch/click outside modal detection
    document.addEventListener('touchend', handleOutsideInteraction);
    document.addEventListener('mouseup', handleOutsideInteraction);

    function handleOutsideInteraction(e) {
        if (!floatingModal || floatingModal.classList.contains('hidden')) return;

        // Check if click/touch is outside modal
        const modalRect = floatingModal.getBoundingClientRect();
        const isOutside =
            e.clientX < modalRect.left ||
            e.clientX > modalRect.right ||
            e.clientY < modalRect.top ||
            e.clientY > modalRect.bottom;

        if (isOutside) {
            const deltaX = Math.abs(e.clientX - scrollStartX);
            const deltaY = Math.abs(e.clientY - scrollStartY);

            // Only close if movement is small (threshold: 15px)
            if (deltaX < 15 && deltaY < 15 && !hasScrolledInModal) {
                closeComments();
            }
        }

        hasScrolledInModal = false;
    }

    // Load and render comments (first page)
    async function loadComments(bleepId) {
        if (!floatingContent) return;

        try {
            const response = await fetch(`/bleeps/comments/${bleepId}/html?page=1`, {
                headers: { 'Accept': 'application/json' }
            });

            if (response.status === 401) {
                floatingContent.innerHTML = `
                    <div class="p-4 text-center text-sm text-base-content/70">
                        <p>Please <a href="/login" class="link link-primary">login</a> to view comments.</p>
                    </div>
                `;
                hasMoreComments = false;
                return;
            }

            if (!response.ok) {
                throw new Error('Failed to load comments');
            }

            const data = await response.json();
            floatingContent.innerHTML = data.html;

            // Update pagination state
            currentCommentsPage = data.current_page || 1;
            hasMoreComments = data.has_more || false;

            // Add loading indicator if there are more comments
            if (hasMoreComments) {
                addCommentsLoadingIndicator();
            }

            // Initialize audio players for comments
            setTimeout(() => {
                if (typeof initAudioPlayers === 'function') {
                    initAudioPlayers(floatingContent);
                }

                // Refresh media observer for new content
                if (typeof refreshMediaObserver === 'function') {
                    refreshMediaObserver();
                }

                // Reinitialize Lucide icons
                if (window.lucide) {
                    window.lucide.createIcons();
                }

                // Dispatch event for other listeners
                document.dispatchEvent(new CustomEvent('bleeps:media:hydrated'));
            }, 100);
        } catch (error) {
            console.error('Error loading comments:', error);
            const retryBtn = document.createElement('button');
            retryBtn.className = 'btn btn-sm btn-primary mt-3';
            retryBtn.textContent = 'Try Again';
            retryBtn.onclick = () => loadComments(bleepId);

            floatingContent.innerHTML = `
                <div class="flex flex-col items-center justify-center py-10 text-base-content/60">
                    <i data-lucide="alert-circle" class="w-8 h-8 mb-3 text-error"></i>
                    <p class="text-sm font-semibold">Failed to load comments</p>
                </div>
            `;
            floatingContent.querySelector('.flex').appendChild(retryBtn);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    }

    // Load more comments (for pagination)
    async function loadMoreComments(bleepId) {
        if (isLoadingComments || !hasMoreComments) return;

        isLoadingComments = true;

        const loadingIndicator = floatingContent.querySelector('#comments-loading-indicator');
        if (loadingIndicator) {
            loadingIndicator.classList.remove('hidden');
        }

        try {
            const nextPage = currentCommentsPage + 1;
            const response = await fetch(`/bleeps/comments/${bleepId}/html?page=${nextPage}`, {
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                throw new Error('Failed to load more comments');
            }

            const data = await response.json();

            // Remove loading indicator
            loadingIndicator?.remove();

            if (data.html) {
                // Create temporary container
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = data.html;

                // Get existing date headers
                const existingDates = new Set();
                floatingContent.querySelectorAll('.comment-date-header').forEach(header => {
                    existingDates.add(header.dataset.date);
                });

                // Append new comments, removing duplicate date headers
                while (tempDiv.firstChild) {
                    const child = tempDiv.firstChild;

                    // Check if it's a date header
                    if (child.classList && child.classList.contains('comment-date-header')) {
                        const dateKey = child.dataset.date;

                        // Skip if we already have this date header
                        if (existingDates.has(dateKey)) {
                            tempDiv.removeChild(child);
                            continue;
                        } else {
                            existingDates.add(dateKey);
                        }
                    }

                    floatingContent.appendChild(child);
                }

                // Update pagination state
                currentCommentsPage = data.current_page || nextPage;
                hasMoreComments = data.has_more || false;

                // Add loading indicator if there are more comments
                if (hasMoreComments) {
                    addCommentsLoadingIndicator();
                }

                // Initialize audio players for new comments
                setTimeout(() => {
                    if (typeof initAudioPlayers === 'function') {
                        initAudioPlayers(floatingContent);
                    }

                    // Refresh media observer for new content
                    if (typeof refreshMediaObserver === 'function') {
                        refreshMediaObserver();
                    }

                    // Reinitialize Lucide icons
                    if (window.lucide) {
                        window.lucide.createIcons();
                    }

                    // Dispatch event
                    document.dispatchEvent(new CustomEvent('bleeps:media:hydrated'));
                }, 100);
            } else {
                hasMoreComments = false;
            }
        } catch (error) {
            console.error('Error loading more comments:', error);

            // Show error message
            if (loadingIndicator) {
                const retryBtn = document.createElement('button');
                retryBtn.className = 'btn btn-sm btn-ghost';
                retryBtn.innerHTML = '<i data-lucide="refresh-cw" class="w-4 h-4 mr-1"></i> Try Again';
                retryBtn.onclick = () => loadMoreComments(bleepId);

                loadingIndicator.innerHTML = `
                    <div class="text-center py-4">
                        <p class="text-sm text-error mb-2">Failed to load more comments</p>
                    </div>
                `;
                loadingIndicator.querySelector('div').appendChild(retryBtn);
                loadingIndicator.classList.remove('hidden');

                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
        } finally {
            isLoadingComments = false;
        }
    }

    function addCommentsLoadingIndicator() {
        // Remove existing indicator if present
        const existing = floatingContent.querySelector('#comments-loading-indicator');
        if (existing) return;

        const indicator = document.createElement('div');
        indicator.id = 'comments-loading-indicator';
        indicator.className = 'hidden flex justify-center items-center py-4';
        indicator.innerHTML = '<span class="loading loading-spinner loading-sm"></span>';
        floatingContent.appendChild(indicator);
    }

    // Make loadMoreComments globally accessible for retry button
    window.loadMoreComments = loadMoreComments;

    // Close modal on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && currentOpenBleepId) {
            closeComments();
        }
    });

    if (anonymousToggle) {
        const toggleIndicator = document.getElementById('toggle-indicator');
        // accept both data-user-avatar and legacy data-profile-url just in case
        const userAvatarAttr = toggleIndicator?.dataset.userAvatar ?? toggleIndicator?.dataset.profileUrl ?? '';
        const userEmail = toggleIndicator?.dataset.userEmail ?? '';
        const userAvatar = userAvatarAttr || (baseUrl ? `${baseUrl}/images/avatar/default.jpg` : '/images/avatar/default.jpg');

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
                // prefer server-provided avatar URL else default to local default image
                if (userAvatar) {
                    toggleIndicator.style.backgroundImage = `url('${userAvatar}')`;
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
        const userName = btn.dataset.userName;
        const userUsername = btn.dataset.userUsername;
        const userEmail = btn.dataset.userEmail;
        const userAvatarFromBtn = btn.dataset.userAvatar ?? '';
        const userAvatar = userAvatarFromBtn || (baseUrl ? `${baseUrl}/images/avatar/default.jpg` : '/images/avatar/default.jpg');

        // Find the comment container - works for both inline and modal
        const commentContainer = document.querySelector(`[data-comment-id="${commentId}"]`);
        if (!commentContainer) return;

        // Check if we're already editing this comment
        if (commentContainer.querySelector('.edit-inline-edit-ui')) return;

        // Convert to edit mode
        enableInlineEdit(commentContainer, commentId, message, isAnonymous, userName, userUsername, userEmail, userAvatar);
    });

    function enableInlineEdit(commentContainer, commentId, originalMessage, isAnonymous, userName, userUsername, userEmail, userAvatar) {
        // Find the message paragraph
        const messagePara = commentContainer.querySelector('.comment-message');
        if (!messagePara) return;

        // Create edit UI
        const editUI = document.createElement('div');
        editUI.className = 'edit-inline-edit-ui space-y-3 mt-2';
        editUI.innerHTML = `
            <textarea
                class="textarea textarea-bordered w-full resize-none text-sm"
                maxlength="255"
                rows="3"
                placeholder="Edit your comment...">${originalMessage}</textarea>

            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <label class="relative inline-flex cursor-pointer">
                        <input type="checkbox" class="edit-inline-anon-toggle peer sr-only" ${isAnonymous ? 'checked' : ''}>
                        <div class="w-14 h-8 bg-base-300 peer-checked:bg-base-300 rounded-full peer-focus:ring-2 peer-focus:ring-primary transition-all border border-gray-300"></div>
                        <div class="edit-inline-anon-indicator absolute top-1 left-1 size-6 rounded-full transition-all duration-300 peer-checked:left-6 bg-cover bg-center flex items-center justify-center"
                            data-user-email="${userEmail}" data-user-avatar="${userAvatar}">
                        </div>
                    </label>
                    <span class="text-xs text-base-content/60">Anonymous</span>
                </div>

                <div class="flex gap-2">
                    <button type="button" class="btn btn-ghost btn-sm cancel-inline-edit">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm save-inline-edit" data-comment-id="${commentId}">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        Update
                    </button>
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

        const updateToggleIndicator = () => {
            if (toggle.checked) {
                // Show anonymous icon
                indicator.style.backgroundImage = 'none';
                indicator.style.backgroundColor = '#1f2937';
                indicator.innerHTML = '<i data-lucide="hat-glasses" class="w-4 h-4 text-white"></i>';
                if (window.lucide) window.lucide.createIcons();
            } else {
                // Show user avatar
                indicator.innerHTML = '';
                indicator.style.backgroundColor = 'transparent';
                const src = editUI.querySelector('.edit-inline-anon-indicator')?.dataset.userAvatar || userAvatar;
                if (src) {
                    indicator.style.backgroundImage = `url('${src}')`;
                    indicator.style.backgroundSize = 'cover';
                    indicator.style.backgroundPosition = 'center';
                } else {
                    indicator.style.backgroundImage = 'none';
                }
            }
        };

        if (toggle) {
            toggle.addEventListener('change', updateToggleIndicator);
            updateToggleIndicator(); // Initialize
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
            const originalBtnHTML = saveBtn.innerHTML;
            saveBtn.innerHTML = '<span class="loading loading-spinner loading-xs"></span>';

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
                    const data = await response.json();

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
                                    <i data-lucide="hat-glasses" class="w-5 h-5 text-base-content"></i>
                                </div>
                            `;
                        } else {
                            // Show user avatar (prefer server-provided URL)
                            const src = userAvatar || (baseUrl ? `${baseUrl}/images/avatar/default.jpg` : '/images/avatar/default.jpg');
                            avatarContainer.innerHTML = `
                                <div class="size-10 rounded-full overflow-hidden">
                                    <img src="${src}" alt="${userName}'s avatar" class="w-full h-full object-cover">
                                </div>
                            `;
                        }
                        if (window.lucide) window.lucide.createIcons();
                    }

                    // Update display name and username
                    const displayNameEl = commentContainer.querySelector('.comment-display-name');
                    const usernameEl = commentContainer.querySelector('.comment-username');

                    if (displayNameEl && usernameEl) {
                        if (newIsAnonymous) {
                            // Use the anonymous name from server response if available
                            displayNameEl.textContent = data.comment?.display_name || 'Anonymous User';
                            usernameEl.textContent = '@anonymous';
                        } else {
                            displayNameEl.textContent = userName;
                            usernameEl.textContent = '@' + userUsername;
                        }
                    }

                    // Toggle identity link/non-link to match anonymity
                    updateCommentIdentity(commentContainer, newIsAnonymous, userUsername, newIsAnonymous ? (data.comment?.display_name || 'Anonymous User') : userName);

                    // Add/update compact "Edited" tag
                    try {
                        const dateWrap = commentContainer.querySelector('.comment-date-wrap');
                        const updatedAt = data.comment?.updated_at || new Date().toISOString();
                        if (dateWrap) {
                            let tag = dateWrap.querySelector('.comment-edited-tag');
                            if (!tag) {
                                tag = document.createElement('span');
                                tag.className = 'comment-edited-tag text-xs text-base-content/50';
                                tag.textContent = '· Edited';
                                // insert after the first span (created_at)
                                const firstSpan = dateWrap.querySelector('span');
                                firstSpan?.insertAdjacentElement('afterend', tag);
                            }
                            const dt = new Date(updatedAt);
                            const title = `Edited: ${dt.toLocaleString(viewerTimezone || 'UTC')}`;
                            tag.title = title;
                        }
                    } catch (err) {
                        // non-fatal
                        console.debug('Could not set edited tag', err);
                    }

                    // Remove edit UI
                    editUI.remove();
                    showToast('Comment updated successfully', 'success');

                    // Reload comments in floating modal if open
                    if (currentOpenBleepId) {
                        await loadComments(currentOpenBleepId);
                    }
                } else {
                    const error = await response.json();
                    showToast(error.message || 'Failed to update comment', 'error');
                    saveBtn.innerHTML = originalBtnHTML;
                    saveBtn.disabled = false;
                    if (window.lucide) window.lucide.createIcons();
                }
            } catch (error) {
                console.error('Error updating comment:', error);
                showToast('An error occurred', 'error');
                saveBtn.innerHTML = originalBtnHTML;
                saveBtn.disabled = false;
                if (window.lucide) window.lucide.createIcons();
            }
        });

        // Focus textarea
        textarea.focus();
        textarea.setSelectionRange(textarea.value.length, textarea.value.length);

        // Reinitialize lucide icons
        if (window.lucide) window.lucide.createIcons();
    }

    // Check if bleep is visible in viewport
    function isBleepVisible() {
        if (!currentBleepElement) return true;

        const rect = currentBleepElement.getBoundingClientRect();
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;

        const bleepHeight = rect.height;
        const visibleHeight = Math.min(rect.bottom, viewportHeight) - Math.max(rect.top, 0);
        const visibilityPercentage = visibleHeight > 0 ? (visibleHeight / bleepHeight) * 100 : 0;

        return visibilityPercentage > 20; // Close if less than 20% visible
    }

    // Start checking bleep visibility on scroll
    function startBleepVisibilityCheck() {
        stopBleepVisibilityCheck(); // Clear any existing listener

        // Check immediately
        if (!isBleepVisible()) {
            closeComments();
            return;
        }

        // Check on scroll with throttling
        let scrollTimeout;
        const scrollHandler = () => {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                if (!isBleepVisible()) {
                    closeComments();
                }
            }, 100); // Throttle to 100ms
        };

        window.addEventListener('scroll', scrollHandler, { passive: true });

        // Store handler for cleanup
        window._bleepScrollHandler = scrollHandler;
    }

    // Stop checking bleep visibility
    function stopBleepVisibilityCheck() {
        if (window._bleepScrollHandler) {
            window.removeEventListener('scroll', window._bleepScrollHandler);
            window._bleepScrollHandler = null;
        }
    }

    // Helper: toggle profile navigation for a comment card
    function updateCommentIdentity(commentContainer, isAnonymous, userUsername, displayName) {
        const displayNameEl = commentContainer.querySelector('.comment-display-name');
        const usernameEl = commentContainer.querySelector('.comment-username');
        if (!displayNameEl || !usernameEl) return;

        // The identity block is either an <a> (non-anon) or a <div> (anon)
        const anchorWithName = displayNameEl.closest('a');
        const identityBlock = anchorWithName || displayNameEl.closest('div');

        if (!identityBlock) return;

        if (isAnonymous) {
            // Ensure it's a non-clickable div
            if (anchorWithName) {
                const div = document.createElement('div');
                div.className = 'flex flex-col min-w-0';
                div.setAttribute('aria-label', 'Anonymous user');
                div.innerHTML = anchorWithName.innerHTML;

                // Remove hover-underline classes on children
                div.querySelectorAll('.group-hover\\:underline').forEach(el => el.classList.remove('group-hover:underline'));

                anchorWithName.replaceWith(div);
            }

            displayNameEl.textContent = displayName || 'Anonymous User';
            usernameEl.textContent = '@anonymous';
        } else {
            // Ensure it is an <a href="/bleeper/:username">
            if (!anchorWithName && identityBlock) {
                const a = document.createElement('a');
                a.className = 'group flex flex-col min-w-0';
                a.title = 'View profile';
                const uname = (userUsername || '').replace(/^@/, '');
                a.href = `/bleeper/${uname}`;
                a.innerHTML = identityBlock.innerHTML;

                // Re-apply hover underline to spans
                a.querySelectorAll('.comment-display-name, .comment-username').forEach(el => {
                    el.classList.add('group-hover:underline');
                });

                identityBlock.replaceWith(a);
            }

            displayNameEl.textContent = displayName || displayNameEl.textContent;
            usernameEl.textContent = '@' + (userUsername || usernameEl.textContent.replace(/^@/, ''));
        }
    }
});
