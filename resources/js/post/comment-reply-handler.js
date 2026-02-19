/**
 * Post Page Comment Reply Handler
 * Handles comment and reply submissions on the single post page
 */

document.addEventListener('DOMContentLoaded', function() {
    const postForm = document.getElementById('post-comment-form');
    if (!postForm) return;

    const textarea = document.getElementById('post-comment-textarea');
    const anonymousToggle = document.getElementById('comment-anonymous-toggle');

    // Intercept form submission to handle replies
    postForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const targetCommentId = window.commentReplyState?.getReplyId();
        const bleepId = postForm.dataset.bleepId;
        const message = textarea?.value.trim();
        const isAnonymous = anonymousToggle?.checked ?? false;
        const file = document.getElementById('comment-media-input')?.files?.[0];

        if (!message && !file) {
            showToast('Write something or attach media', 'error');
            return;
        }

        // Determine endpoint based on whether we're replying or commenting
        const endpoint = targetCommentId
            ? `/bleeps/comments/${targetCommentId}/replies`
            : `/bleeps/comments/${bleepId}/post`;

        const formData = new FormData();
        formData.append('is_anonymous', isAnonymous ? '1' : '0');
        if (message) formData.append('message', message);
        if (file) formData.append('media', file);

        try {
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
                    return;
                }
                throw new Error('Failed to submit');
            }

            const data = await response.json();

            // Clear form
            textarea.value = '';
            if (anonymousToggle) anonymousToggle.checked = false;

            // Clear media if any
            const mediaInput = document.getElementById('comment-media-input');
            if (mediaInput) mediaInput.value = '';

            if (targetCommentId) {
                // Handle reply submission
                const container = document.querySelector(`.comment-replies-container[data-comment-id="${targetCommentId}"]`);
                const commentCard = document.querySelector(`.comment-card[data-comment-id="${targetCommentId}"]`);

                if (container && commentCard) {
                    const parentDepth = parseInt(commentCard?.dataset.commentDepth ?? '0', 10);
                    container.dataset.depth = container.dataset.depth ?? (parentDepth + 1).toString();

                    // Insert the new reply HTML and show it
                    const repliesList = container.querySelector('.replies-list');
                    if (repliesList && data.html) {
                        repliesList.innerHTML = data.html;
                    }
                    container.classList.remove('hidden');

                    // Mark as partially loaded (only showing new reply, not all replies)
                    container.dataset.loaded = 'partial';

                    // Find or create the toggle button
                    let toggle = document.querySelector(`.comment-toggle-replies[data-comment-id="${targetCommentId}"]`);
                    const actionsBar = commentCard.querySelector('.comment-body > div:last-child > div:first-child');

                    if (!toggle && actionsBar) {
                        // Create toggle button if it doesn't exist (first reply)
                        toggle = document.createElement('button');
                        toggle.type = 'button';
                        toggle.className = 'comment-toggle-replies cursor-pointer inline-flex items-center gap-1.5 hover:text-primary transition-colors ml-auto';
                        toggle.dataset.commentId = targetCommentId;
                        toggle.dataset.expanded = 'true';
                        toggle.innerHTML = `
                            <span class="replies-toggle-text">View ${data.replies_count} ${data.replies_count === 1 ? 'reply' : 'replies'}</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 replies-toggle-icon transition-transform rotate-180"></i>
                        `;
                        actionsBar.appendChild(toggle);
                        if (window.lucide) window.lucide.createIcons();
                    } else if (toggle) {
                        // Update existing toggle button
                        toggle.querySelector('.replies-toggle-text').textContent = `View ${data.replies_count} ${data.replies_count === 1 ? 'reply' : 'replies'}`;
                        toggle.dataset.expanded = 'true';
                        toggle.querySelector('.replies-toggle-icon')?.classList.add('rotate-180');
                    }
                }

                // Cancel reply mode
                window.commentReplyState?.cancel();
                showToast('Reply posted successfully!', 'success');
            } else {
                // Handle regular comment submission - reload page to show new comment
                showToast('Comment posted successfully', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            }

            // Reinitialize Lucide icons for new content
            if (window.lucide) {
                window.lucide.createIcons();
            }

        } catch (error) {
            console.error('Error submitting:', error);
            showToast('Failed to submit. Please try again.', 'error');
        }
    });

    // Simple toast notification
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'success' ? 'success' : 'error'} fixed bottom-4 right-4 w-auto max-w-sm shadow-lg z-50 animate-fade-out`;
        toast.innerHTML = `
            <span>${message}</span>
        `;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 4000);
    }
});
