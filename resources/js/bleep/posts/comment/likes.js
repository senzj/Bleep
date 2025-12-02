document.addEventListener('click', async (evt) => {
    const btn = evt.target.closest('.comment-like-btn');
    if (!btn) return;

    evt.preventDefault();
    const isAuth = document.getElementById('floating-comment-form') !== null;
    if (!isAuth) {
        window.location.href = '/login';
        return;
    }

    const commentId = btn.dataset.commentId;
    const liked = btn.dataset.liked === '1';
    const res = await fetch(`/bleeps/comments/${commentId}/likes`, {
        method: liked ? 'DELETE' : 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!res.ok) return;
    const data = await res.json();

    btn.dataset.liked = data.liked ? '1' : '0';

    const icon = btn.querySelector('i[data-lucide="heart"]');
    icon?.classList.toggle('fill-error', data.liked);
    icon?.classList.toggle('text-error', data.liked);

    btn.classList.toggle('text-error', data.liked);

    const countEl = btn.querySelector('.comment-like-count');
    countEl?.classList.toggle('text-error', data.liked);
    countEl.textContent = data.likes_count;

    window.lucide?.createIcons();
});
