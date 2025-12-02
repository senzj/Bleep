/**
 * Comment Replies Module
 * Handles reply functionality using the main comment input field
 */

(function () {
    'use strict';

    const isAuth = () => document.getElementById('floating-comment-form') !== null;

    document.addEventListener('click', async (evt) => {
        const replyBtn = evt.target.closest('.comment-reply-btn');
        if (replyBtn) {
            evt.preventDefault();
            if (!isAuth()) {
                window.location.href = '/login';
                return;
            }
            window.commentReplyState.start(replyBtn.dataset.commentId);
            return;
        }

        const toggleBtn = evt.target.closest('.comment-toggle-replies');
        if (toggleBtn) {
            evt.preventDefault();
            await toggleReplies(toggleBtn);
            return;
        }

        const loadMoreBtn = evt.target.closest('.load-more-replies-btn');
        if (loadMoreBtn) {
            evt.preventDefault();
            await fetchReplies(loadMoreBtn.dataset.commentId, loadMoreBtn);
        }
    });

    async function toggleReplies(btn) {
        const container = document.querySelector(`.comment-replies-container[data-comment-id="${btn.dataset.commentId}"]`);
        if (!container) return;

        const expanded = btn.dataset.expanded === 'true';
        if (expanded) {
            container.classList.add('hidden');
            btn.dataset.expanded = 'false';
            btn.querySelector('.replies-toggle-icon')?.classList.remove('rotate-180');
            return;
        }

        container.classList.remove('hidden');
        btn.dataset.expanded = 'true';
        btn.querySelector('.replies-toggle-icon')?.classList.add('rotate-180');

        if (!container.dataset.depth) {
            const parentDepth = parseInt(document.querySelector(`.comment-card[data-comment-id="${btn.dataset.commentId}"]`)?.dataset.commentDepth ?? '0', 10);
            container.dataset.depth = (parentDepth + 1).toString();
        }

        if (!container.dataset.loaded) {
            await fetchReplies(btn.dataset.commentId, container.querySelector('.load-more-replies-btn'), true);
        }
    }

    async function fetchReplies(commentId, loadMoreBtn, reset = false) {
        const container = document.querySelector(`.comment-replies-container[data-comment-id="${commentId}"]`);
        if (!container) return;

        const list = container.querySelector('.replies-list');
        const depth = parseInt(container.dataset.depth ?? '1', 10);
        const page = reset ? 1 : parseInt(container.dataset.nextPage ?? '1', 10);

        if (reset) {
            list.innerHTML = '';
            container.dataset.nextPage = '1';
        }

        const res = await fetch(`/bleeps/comments/${commentId}/replies?page=${page}&depth=${depth}`, {
            headers: { 'Accept': 'application/json' },
        });

        if (!res.ok) return;

        const { html, has_more, next_page } = await res.json();

        if (page === 1) {
            list.innerHTML = html;
        } else {
            list.insertAdjacentHTML('beforeend', html);
        }

        container.dataset.loaded = '1';
        container.dataset.nextPage = (next_page ?? page + 1).toString();

        if (loadMoreBtn) {
            if (has_more) {
                loadMoreBtn.classList.remove('hidden');
                loadMoreBtn.textContent = 'View more replies';
            } else {
                loadMoreBtn.classList.add('hidden');
            }
        }

        window.lucide?.createIcons();
    }

    document.addEventListener('keydown', (evt) => {
        if (evt.key === 'Escape') {
            window.commentReplyState.cancel();
        }
    });

    window.commentReplyState = {
        start(commentId) {
            const textarea = document.querySelector('#floating-comment-form textarea[name="message"]');
            if (!textarea) return;

            document.querySelectorAll('.comment-card.replying').forEach((el) => {
                el.classList.remove('replying');
                el.querySelector('.comment-body')?.classList.remove(
                    'border',
                    'border-yellow-300',
                    'bg-yellow-50',
                    'shadow-sm'
                );
            });

            const targetCard = document.querySelector(`.comment-card[data-comment-id="${commentId}"]`);
            if (!targetCard) return;

            targetCard.classList.add('replying');
            targetCard.querySelector('.comment-body')?.classList.add(
                'border',
                'border-yellow-300',
                'bg-yellow-50',
                'shadow-sm'
            );

            this.replyToId = commentId;
            this.placeholder = this.placeholder ?? textarea.placeholder;
            const banner = document.querySelector('#cancel-reply-banner') ?? createBanner();

            banner.classList.remove('hidden');
            textarea.placeholder = `Replying to @${targetCard.dataset.replyToName ?? 'user'}'s comment...`;
            textarea.focus({ preventScroll: false });
        },
        cancel() {
            const textarea = document.querySelector('#floating-comment-form textarea[name="message"]');
            if (!textarea) return;

            textarea.placeholder = this.placeholder ?? 'Write a comment...';
            const banner = document.querySelector('#cancel-reply-banner');
            banner?.classList.add('hidden');

            document.querySelectorAll('.comment-card.replying').forEach((el) => {
                el.classList.remove('replying');
                el.querySelector('.comment-body')?.classList.remove(
                    'border',
                    'border-yellow-300',
                    'bg-yellow-50',
                    'shadow-sm'
                );
            });

            this.replyToId = null;
        },
        getReplyId() {
            return this.replyToId;
        },
    };

    function createBanner() {
        const formWrap = document.querySelector('#floating-comment-form')?.parentElement;
        if (!formWrap) return null;

        const banner = document.createElement('button');
        banner.id = 'cancel-reply-banner';
        banner.type = 'button';
        banner.className = 'hidden absolute right-0 -top-12 sm:-top-11 btn btn-sm btn-outline btn-primary rounded-full shadow gap-2';
        banner.innerHTML = `<i data-lucide="reply" class="w-3 h-3"></i><span>Cancel reply</span>`;
        banner.addEventListener('click', () => window.commentReplyState.cancel());

        formWrap.style.position = 'relative';
        formWrap.insertAdjacentElement('afterbegin', banner);
        window.lucide?.createIcons();
        return banner;
    }
})();
