/**
 * Edit Comment Modal Handler
 * Handles modal-based comment editing with media support and DOM updates
 */

document.addEventListener('DOMContentLoaded', function() {
    // Exit early if modal doesn't exist on this page
    const editModal = document.getElementById('edit-comment-modal');
    if (!editModal) return;

    const editForm = document.getElementById('edit-comment-form');
    const editTextarea = document.getElementById('edit-comment-message');
    const editAnonymousToggle = document.getElementById('edit-comment-anonymous');
    const editToggleIndicator = document.getElementById('edit-comment-toggle-indicator');
    const charCount = document.getElementById('edit-comment-char-count');

    // Media elements
    const mediaInput = document.getElementById('edit-comment-media-input');
    const mediaTrigger = document.getElementById('edit-comment-media-trigger');
    const mediaBtnText = document.getElementById('edit-comment-media-btn-text');
    const currentMediaSection = document.getElementById('edit-comment-current-media');
    const currentMediaPreview = document.getElementById('edit-comment-media-preview');
    const removeMediaBtn = document.getElementById('edit-comment-remove-media');
    const newMediaSection = document.getElementById('edit-comment-new-media');
    const newMediaPreview = document.getElementById('edit-comment-new-media-preview');
    const clearNewMediaBtn = document.getElementById('edit-comment-clear-new-media');

    let currentCommentId = null;
    let currentMediaPath = null;
    let shouldRemoveMedia = false;
    let originalMessage = '';
    let originalIsAnonymous = false;
    let userData = {};

    // Handle edit button clicks
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.edit-comment-btn');
        if (!btn) return;

        const commentId = btn.dataset.commentId;
        const message = btn.dataset.commentMessage;
        const isAnonymous = btn.dataset.isAnonymous === '1';
        const userName = btn.dataset.userName;
        const userUsername = btn.dataset.userUsername;
        const userEmail = btn.dataset.userEmail;
        const userAvatar = btn.dataset.userAvatar;
        const mediaPath = btn.dataset.commentMedia || null;
        const mediaType = btn.dataset.commentMediaType || null;

        openEditModal({
            commentId,
            message,
            isAnonymous,
            userName,
            userUsername,
            userEmail,
            userAvatar,
            mediaPath,
            mediaType
        });
    });

    // Open edit modal
    function openEditModal(data) {
        currentCommentId = data.commentId;
        currentMediaPath = data.mediaPath;
        shouldRemoveMedia = false;
        originalMessage = data.message;
        originalIsAnonymous = data.isAnonymous;
        userData = {
            name: data.userName,
            username: data.userUsername,
            email: data.userEmail,
            avatar: data.userAvatar
        };

        // Set message
        editTextarea.value = data.message;
        updateCharCount();

        // Set anonymous toggle
        if (editAnonymousToggle) {
            editAnonymousToggle.checked = data.isAnonymous;
            updateToggleIndicator();
        }

        // Handle current media
        if (data.mediaPath) {
            showCurrentMedia(data.mediaPath, data.mediaType);
        } else {
            currentMediaSection.classList.add('hidden');
        }

        // Reset new media
        newMediaSection.classList.add('hidden');
        mediaInput.value = '';
        mediaBtnText.textContent = 'Add Media';

        // Show modal
        editModal.showModal();

        // Focus textarea
        editTextarea.focus();
        editTextarea.setSelectionRange(editTextarea.value.length, editTextarea.value.length);

        // Reinitialize Lucide icons
        if (window.lucide) window.lucide.createIcons();
    }

    // Show current media
    function showCurrentMedia(mediaPath, mediaType) {
        const mediaUrl = mediaPath.startsWith('http') ? mediaPath : `/storage/${mediaPath}`;

        // For comments, only images are supported
        const html = `<img src="${mediaUrl}" class="max-w-full max-h-48 rounded-lg object-cover" alt="Comment media">`;

        currentMediaPreview.innerHTML = html;
        currentMediaSection.classList.remove('hidden');

        if (window.lucide) window.lucide.createIcons();
    }

    // Remove current media
    removeMediaBtn?.addEventListener('click', () => {
        shouldRemoveMedia = true;
        currentMediaSection.classList.add('hidden');
        mediaBtnText.textContent = 'Add Media';
    });

    // Media upload trigger
    mediaTrigger?.addEventListener('click', () => {
        mediaInput.click();
    });

    // Handle media selection
    mediaInput?.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;

        // Show image preview only
        const url = URL.createObjectURL(file);
        const html = `<img src="${url}" class="max-w-full max-h-48 rounded-lg object-cover" alt="New media">`;

        newMediaPreview.innerHTML = html;
        newMediaSection.classList.remove('hidden');
        mediaBtnText.textContent = 'Change Media';

        if (window.lucide) window.lucide.createIcons();
    });

    // Clear new media
    clearNewMediaBtn?.addEventListener('click', () => {
        mediaInput.value = '';
        newMediaSection.classList.add('hidden');
        newMediaPreview.innerHTML = '';
        mediaBtnText.textContent = currentMediaPath && !shouldRemoveMedia ? 'Change Media' : 'Add Media';

        // Revoke object URL
        const img = newMediaPreview.querySelector('img, video, audio');
        if (img?.src?.startsWith('blob:')) {
            URL.revokeObjectURL(img.src);
        }
    });

    // Character count
    editTextarea?.addEventListener('input', updateCharCount);

    function updateCharCount() {
        if (charCount && editTextarea) {
            charCount.textContent = editTextarea.value.length;
        }
    }

    // Toggle indicator
    editAnonymousToggle?.addEventListener('change', updateToggleIndicator);

    function updateToggleIndicator() {
        if (!editToggleIndicator || !editAnonymousToggle) return;

        if (editAnonymousToggle.checked) {
            // Show anonymous icon
            editToggleIndicator.style.backgroundImage = 'none';
            editToggleIndicator.style.backgroundColor = '#1f2937';
            editToggleIndicator.innerHTML = '<i data-lucide="glasses" class="w-4 h-4 text-white"></i>';
        } else {
            // Show user avatar
            editToggleIndicator.innerHTML = '';
            editToggleIndicator.style.backgroundColor = 'transparent';
            const avatarUrl = editToggleIndicator.dataset.userAvatar;
            if (avatarUrl) {
                editToggleIndicator.style.backgroundImage = `url('${avatarUrl}')`;
                editToggleIndicator.style.backgroundSize = 'cover';
                editToggleIndicator.style.backgroundPosition = 'center';
            }
        }

        if (window.lucide) window.lucide.createIcons();
    }

    // Form submission
    editForm?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const message = editTextarea.value.trim();
        const newIsAnonymous = editAnonymousToggle?.checked ?? false;
        const newFile = mediaInput?.files[0];

        if (!message && !currentMediaPath && !newFile) {
            showToast('Comment cannot be empty without media', 'error');
            return;
        }

        const submitBtn = editForm.querySelector('button[type="submit"]');
        const originalBtnHTML = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="loading loading-spinner loading-xs"></span> Updating...';

        try {
            const formData = new FormData();
            formData.append('message', message);
            formData.append('is_anonymous', newIsAnonymous ? '1' : '0');

            if (shouldRemoveMedia) {
                formData.append('remove_media', '1');
            }

            if (newFile) {
                formData.append('media', newFile);
            }

            const response = await fetch(`/bleeps/comments/${currentCommentId}/update`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: formData
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to update comment');
            }

            const data = await response.json();

            // Update DOM
            updateCommentInDOM(currentCommentId, {
                message,
                isAnonymous: newIsAnonymous,
                mediaHtml: data.media_html || null,
                mediaPath: data.media_path || null,
                mediaType: data.media_type || null,
                displayName: data.display_name || userData.name,
                updatedAt: data.updated_at
            });

            // Close modal
            editModal.close();
            showToast('Comment updated successfully', 'success');

        } catch (error) {
            console.error('Error updating comment:', error);
            showToast(error.message || 'Failed to update comment', 'error');
            submitBtn.innerHTML = originalBtnHTML;
            submitBtn.disabled = false;
            if (window.lucide) window.lucide.createIcons();
        }
    });

    // Update comment in DOM
    function updateCommentInDOM(commentId, data) {
        const commentCard = document.querySelector(`[data-comment-id="${commentId}"]`);
        if (!commentCard) return;

        // Update message
        const messagePara = commentCard.querySelector('.comment-message, p.whitespace-pre-line');
        if (messagePara) {
            messagePara.textContent = data.message;
        }

        // Update media
        const mediaWrapper = commentCard.querySelector(`[data-comment-media-wrapper="${commentId}"]`);
        if (data.mediaHtml) {
            if (mediaWrapper) {
                mediaWrapper.innerHTML = data.mediaHtml;
            } else {
                // Add media section if it didn't exist
                const messageDiv = commentCard.querySelector('#comment-message, .comment-body > div:last-child > div:first-child').parentElement;
                const newMediaDiv = document.createElement('div');
                newMediaDiv.setAttribute('data-comment-media-wrapper', commentId);
                newMediaDiv.className = 'mt-2';
                newMediaDiv.innerHTML = data.mediaHtml;
                messageDiv.appendChild(newMediaDiv);
            }
        } else if (mediaWrapper) {
            mediaWrapper.remove();
        }

        // Update avatar and identity
        updateCommentIdentity(commentCard, data.isAnonymous, data.displayName);

        // Update edit button dataset
        const editBtn = commentCard.querySelector('.edit-comment-btn');
        if (editBtn) {
            editBtn.dataset.commentMessage = data.message;
            editBtn.dataset.isAnonymous = data.isAnonymous ? '1' : '0';
            editBtn.dataset.commentMedia = data.mediaPath || '';
            editBtn.dataset.commentMediaType = data.mediaType || '';
        }

        // Add/update "Edited" tag
        addEditedTag(commentCard, data.updatedAt);

        // Reinitialize media players and icons
        if (window.lucide) window.lucide.createIcons();

        // Dispatch event for media initialization
        document.dispatchEvent(new CustomEvent('bleeps:media:hydrated'));
    }

    // Update comment identity (avatar and name)
    function updateCommentIdentity(commentCard, isAnonymous, displayName) {
        const avatarContainer = commentCard.querySelector('.avatar');
        const displayNameEl = commentCard.querySelector('.comment-display-name');
        const usernameEl = commentCard.querySelector('.comment-username');

        if (avatarContainer) {
            if (isAnonymous) {
                avatarContainer.innerHTML = `
                    <div class="size-10 rounded-full bg-base-300 flex items-center justify-center">
                        <i data-lucide="glasses" class="w-5 h-5 text-base-content"></i>
                    </div>
                `;
            } else {
                const avatarUrl = userData.avatar || '/images/avatar/default.jpg';
                avatarContainer.innerHTML = `
                    <a href="/profile/${userData.username}" class="group" title="View profile: ${userData.username}">
                        <div class="size-10 rounded-full overflow-hidden">
                            <img src="${avatarUrl}" alt="${displayName}'s avatar" class="w-full h-full object-cover">
                        </div>
                    </a>
                `;
            }
        }

        if (displayNameEl) {
            displayNameEl.textContent = displayName;
        }

        if (usernameEl) {
            usernameEl.textContent = isAnonymous ? '@anonymous' : `@${userData.username}`;
        }

        if (window.lucide) window.lucide.createIcons();
    }

    // Add edited tag
    function addEditedTag(commentCard, updatedAt) {
        const dateWrap = commentCard.querySelector('.comment-date-wrap');
        if (!dateWrap) return;

        let tag = dateWrap.querySelector('.comment-edited-tag');
        const dt = new Date(updatedAt);
        const now = new Date();
        const diffSeconds = (now - dt) / 1000;
        const within7Days = diffSeconds <= (7 * 86400);

        const timeLabel = dt.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        const humanTime = within7Days ? getRelativeTime(diffSeconds) : '';

        if (!tag) {
            tag = document.createElement('span');
            tag.className = 'comment-edited-tag text-xs text-base-content/50';
            dateWrap.appendChild(tag);
        }

        tag.textContent = `Edited · ${within7Days ? humanTime : timeLabel}`;
        tag.title = `Edited: ${dt.toLocaleString()}`;
    }

    // Helper: Get relative time
    function getRelativeTime(seconds) {
        if (seconds < 60) return 'just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)} minutes ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)} hours ago`;
        return `${Math.floor(seconds / 86400)} days ago`;
    }

    // Helper: Show toast notification
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'success' ? 'success' : 'error'} fixed bottom-4 right-4 w-auto max-w-sm shadow-lg z-50`;
        toast.innerHTML = `<span>${message}</span>`;
        document.body.appendChild(toast);

        setTimeout(() => toast.remove(), 4000);
    }

    // Initialize toggle on page load
    if (editAnonymousToggle && editToggleIndicator) {
        updateToggleIndicator();
    }
});
